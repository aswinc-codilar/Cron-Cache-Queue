<?php
/**
 * Core ERP price sync: fetch -> validate -> bulk price update -> reindex -> purge cache -> acknowledge.
 *
 * Used by both the cron job and the erp:price:sync CLI command. A lock guarantees runs never overlap.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Model;

use Codilar\ErpPriceSync\Api\PriceProviderInterface;
use Magento\Catalog\Api\BasePriceStorageInterface;
use Magento\Catalog\Api\Data\BasePriceInterface;
use Magento\Catalog\Api\Data\BasePriceInterfaceFactory;
use Magento\Catalog\Model\Indexer\Product\Price\Processor as PriceIndexerProcessor;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class SyncService
{
    public const LOCK_NAME = 'codilar_erp_price_sync';
    private const PRICE_EPSILON = 0.000001;

    /**
     * @param Config $config
     * @param PriceProviderInterface $provider Mock ERP table provider (see di.xml)
     * @param LockManagerInterface $lockManager
     * @param ProductResource $productResource
     * @param BasePriceStorageInterface $basePriceStorage
     * @param BasePriceInterfaceFactory $basePriceFactory
     * @param PriceIndexerProcessor $priceIndexerProcessor
     * @param CacheContextFactory $cacheContextFactory
     * @param EventManager $eventManager
     * @param CacheInterface $appCache
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly PriceProviderInterface $provider,
        private readonly LockManagerInterface $lockManager,
        private readonly ProductResource $productResource,
        private readonly BasePriceStorageInterface $basePriceStorage,
        private readonly BasePriceInterfaceFactory $basePriceFactory,
        private readonly PriceIndexerProcessor $priceIndexerProcessor,
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly EventManager $eventManager,
        private readonly CacheInterface $appCache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run one sync batch.
     *
     * "skipped" = items whose Magento price already matched (or duplicate SKUs in the batch).
     * "locked" = true when another run held the lock and nothing was done.
     *
     * @return array{fetched: int, updated: int, failed: int, skipped: int, locked: bool}
     * @throws \Exception When fetching or the bulk update fails (items are not acknowledged, so they retry).
     */
    public function execute(): array
    {
        $result = ['fetched' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0, 'locked' => false];

        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            $this->logger->info('ERP price sync skipped: another run is in progress.');
            $result['locked'] = true;

            return $result;
        }

        try {
            $items = $this->provider->fetch($this->config->getBatchSize());
            $result['fetched'] = count($items);

            if ($items === []) {
                $this->provider->acknowledge([]);

                return $result;
            }

            $items = $this->validate($items, $result);
            $productIds = $this->applyPrices($items, $result);

            if ($productIds !== []) {
                $this->priceIndexerProcessor->reindexList($productIds);
                $this->cleanCache($productIds);
            }

            $this->provider->acknowledge($items);
            $this->logger->info('ERP price sync finished.', $result);

            return $result;
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * Attach an "error" key to each item: unknown SKUs and negative prices are rejected, duplicates skipped.
     *
     * @param array $items
     * @param array $result
     * @return array
     */
    private function validate(array $items, array &$result): array
    {
        $skus = array_values(array_unique(array_column($items, 'sku')));
        $existing = $this->productResource->getProductsIdsBySkus($skus);
        $existingLower = array_change_key_case($existing, CASE_LOWER);

        // Last occurrence of a SKU wins; earlier duplicates are skipped.
        $lastIndexBySku = [];
        foreach ($items as $index => $item) {
            $lastIndexBySku[strtolower($item['sku'])] = $index;
        }

        foreach ($items as $index => &$item) {
            $item['error'] = null;
            $item['product_id'] = null;
            $key = strtolower($item['sku']);

            if ($lastIndexBySku[$key] !== $index) {
                $item['status'] = 'skipped';
                $result['skipped']++;
            } elseif (!isset($existingLower[$key])) {
                $item['error'] = sprintf('Product with SKU "%s" does not exist.', $item['sku']);
            } elseif ($item['price'] < 0) {
                $item['error'] = sprintf('Negative price %s is not allowed.', $item['price']);
            } else {
                $item['product_id'] = (int)$existingLower[$key];
                $item['status'] = 'pending';
            }

            if ($item['error'] !== null) {
                $item['status'] = 'failed';
                $result['failed']++;
                $this->logger->warning('ERP price rejected.', ['sku' => $item['sku'], 'error' => $item['error']]);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Bulk-update base prices (store 0) for pending items and return the IDs of products that changed.
     *
     * @param array $items
     * @param array $result
     * @return int[]
     */
    private function applyPrices(array &$items, array &$result): array
    {
        $pending = array_filter($items, static fn(array $item): bool => $item['status'] === 'pending');
        if ($pending === []) {
            return [];
        }

        $current = $this->getCurrentPrices(array_column($pending, 'sku'));
        $prices = [];
        foreach ($pending as $index => $item) {
            $key = strtolower($item['sku']);
            if (isset($current[$key]) && abs($current[$key] - $item['price']) < self::PRICE_EPSILON) {
                $items[$index]['status'] = 'skipped';
                $result['skipped']++;
                continue;
            }
            /** @var BasePriceInterface $price */
            $price = $this->basePriceFactory->create();
            $price->setSku($item['sku'])->setPrice($item['price'])->setStoreId(Store::DEFAULT_STORE_ID);
            $prices[$index] = $price;
        }
        if ($prices === []) {
            return [];
        }

        $failedBySku = [];
        foreach ($this->basePriceStorage->update(array_values($prices)) as $failure) {
            $params = $failure->getParameters();
            $sku = $params['SKU'] ?? (($params['fieldName'] ?? '') === 'SKU' ? ($params['fieldValue'] ?? null) : null);
            if ($sku !== null) {
                $failedBySku[strtolower((string)$sku)] = (string)__($failure->getMessage(), $params);
            }
        }

        $productIds = [];
        foreach (array_keys($prices) as $index) {
            $key = strtolower($items[$index]['sku']);
            if (isset($failedBySku[$key])) {
                $items[$index]['status'] = 'failed';
                $items[$index]['error'] = $failedBySku[$key];
                $result['failed']++;
                $this->logger->warning(
                    'ERP price update failed.',
                    ['sku' => $items[$index]['sku'], 'error' => $failedBySku[$key]]
                );
                continue;
            }
            $items[$index]['status'] = 'updated';
            $result['updated']++;
            $productIds[] = $items[$index]['product_id'];
        }

        return array_values(array_unique($productIds));
    }

    /**
     * Current default-scope base prices keyed by lower-case SKU.
     *
     * @param string[] $skus
     * @return array<string, float>
     */
    private function getCurrentPrices(array $skus): array
    {
        $current = [];
        try {
            foreach ($this->basePriceStorage->get($skus) as $price) {
                if ((int)$price->getStoreId() === Store::DEFAULT_STORE_ID) {
                    $current[strtolower((string)$price->getSku())] = (float)$price->getPrice();
                }
            }
        } catch (\Exception $e) {
            // Not fatal: without current prices we simply update everything.
            $this->logger->notice('Could not read current prices: ' . $e->getMessage());
        }

        return $current;
    }

    /**
     * Purge FPC/Varnish and block caches for the changed products.
     *
     * @param int[] $productIds
     * @return void
     */
    private function cleanCache(array $productIds): void
    {
        /** @var CacheContext $cacheContext */
        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerEntities(Product::CACHE_TAG, $productIds);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);

        $tags = $cacheContext->getIdentities();
        if ($tags !== []) {
            $this->appCache->clean($tags);
        }
    }
}

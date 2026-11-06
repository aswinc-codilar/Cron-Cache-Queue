<?php
/**
 * Loads category ID, name and URL and caches them for one hour.
 *
 * Cache key: CODILAR_CATEGORY_DETAILS_<categoryId>_<storeId>
 * Cache tag: CODILAR_CATEGORY_DETAILS_<categoryId> (lets clean() drop every store view at once)
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Model;

use Codilar\CategoryCache\Api\CategoryDetailsInterface;
use Codilar\CategoryCache\Model\Cache\Type\CategoryDetails as CacheType;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CategoryDetails implements CategoryDetailsInterface
{
    private const KEY_PREFIX = 'CODILAR_CATEGORY_DETAILS_';

    /**
     * @var bool
     */
    private bool $lastHit = false;

    /**
     * @param CacheType $cache
     * @param StateInterface $cacheState
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CacheType $cache,
        private readonly StateInterface $cacheState,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $categoryId, ?int $storeId = null): array
    {
        $storeId ??= (int)$this->storeManager->getStore()->getId();
        $cacheKey = $this->getCacheKey($categoryId, $storeId);
        $cacheEnabled = $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER);

        if ($cacheEnabled) {
            $cached = $this->cache->load($cacheKey);
            if ($cached !== false) {
                $this->lastHit = true;
                $this->logger->debug(sprintf('Category details cache HIT: %s', $cacheKey));

                return $this->serializer->unserialize($cached);
            }
        }

        $this->lastHit = false;
        $this->logger->debug(sprintf('Category details cache MISS: %s', $cacheKey));

        $category = $this->categoryRepository->get($categoryId, $storeId);
        $details = [
            'id' => (int)$category->getId(),
            'name' => (string)$category->getName(),
            'url' => (string)$category->getUrl(),
        ];

        if ($cacheEnabled) {
            $this->cache->save(
                $this->serializer->serialize($details),
                $cacheKey,
                [$this->getCategoryTag($categoryId)],
                self::CACHE_LIFETIME
            );
        }

        return $details;
    }

    /**
     * @inheritDoc
     */
    public function wasLastHit(): bool
    {
        return $this->lastHit;
    }

    /**
     * @inheritDoc
     */
    public function clean(int $categoryId): void
    {
        $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [$this->getCategoryTag($categoryId)]);
        $this->logger->debug(sprintf('Category details cache cleaned for category %d', $categoryId));
    }

    /**
     * @param int $categoryId
     * @param int $storeId
     * @return string
     */
    private function getCacheKey(int $categoryId, int $storeId): string
    {
        return self::KEY_PREFIX . $categoryId . '_' . $storeId;
    }

    /**
     * @param int $categoryId
     * @return string
     */
    private function getCategoryTag(int $categoryId): string
    {
        return self::KEY_PREFIX . $categoryId;
    }
}

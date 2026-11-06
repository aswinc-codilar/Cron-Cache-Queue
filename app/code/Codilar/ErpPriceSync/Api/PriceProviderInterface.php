<?php
/**
 * Contract for an ERP price source (implemented by the mock ERP table provider).
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Api;

interface PriceProviderInterface
{
    /**
     * Fetch up to $limit changed prices.
     *
     * Each item: ['sku' => string, 'price' => float, 'ref' => mixed]. "ref" is opaque provider data that is
     * handed back unchanged in acknowledge().
     *
     * @param int $limit
     * @return array List of ['sku' => string, 'price' => float, 'ref' => mixed]
     */
    public function fetch(int $limit): array;

    /**
     * Confirm items were processed.
     *
     * Each item is a fetched item plus 'error' => ?string (null on success).
     *
     * @param array $items Fetched items, each with an added 'error' key
     * @return void
     */
    public function acknowledge(array $items): void;
}

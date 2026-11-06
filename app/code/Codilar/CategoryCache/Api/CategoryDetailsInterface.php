<?php
/**
 * Cached lookup of basic category details.
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Api;

use Magento\Framework\Exception\NoSuchEntityException;

interface CategoryDetailsInterface
{
    /** Cache lifetime in seconds (1 hour). */
    public const CACHE_LIFETIME = 3600;

    /**
     * Get category details, served from cache when available.
     *
     * @param int $categoryId
     * @param int|null $storeId Defaults to the current store
     * @return array{id: int, name: string, url: string}
     * @throws NoSuchEntityException
     */
    public function get(int $categoryId, ?int $storeId = null): array;

    /**
     * Whether the last get() call was answered from cache.
     *
     * @return bool
     */
    public function wasLastHit(): bool;

    /**
     * Remove cached details of one category (all store views).
     *
     * @param int $categoryId
     * @return void
     */
    public function clean(int $categoryId): void;
}

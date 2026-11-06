<?php
/**
 * Drops the cached details when a category is saved or deleted, so a renamed
 * category or changed URL key does not wait out the 1-hour lifetime.
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Observer;

use Codilar\CategoryCache\Api\CategoryDetailsInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CleanCategoryDetailsCache implements ObserverInterface
{
    /**
     * @param CategoryDetailsInterface $categoryDetails
     */
    public function __construct(
        private readonly CategoryDetailsInterface $categoryDetails
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getCategory();
        if ($category && $category->getId()) {
            $this->categoryDetails->clean((int)$category->getId());
        }
    }
}

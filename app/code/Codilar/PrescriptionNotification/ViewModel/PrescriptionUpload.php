<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\ViewModel;

use Codilar\PrescriptionNotification\Model\Config;
use Codilar\PrescriptionNotification\Model\ResourceModel\Upload\Collection as UploadCollection;
use Codilar\PrescriptionNotification\Model\ResourceModel\Upload\CollectionFactory as UploadCollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface as OrderCollectionFactory;

class PrescriptionUpload implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly UploadCollectionFactory $uploadCollectionFactory,
        private readonly UrlInterface $url,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getFormAction(): string
    {
        return $this->url->getUrl('prescription/upload/save');
    }

    public function getMaxFileSizeMb(): float
    {
        return round($this->config->getMaxFileSizeBytes() / 1048576, 1);
    }

    public function getOrders(): OrderCollection
    {
        return $this->orderCollectionFactory->create($this->getCustomerId())
            ->addFieldToSelect(['entity_id', 'increment_id', 'created_at', 'grand_total', 'order_currency_code'])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(50);
    }

    public function getUploads(): UploadCollection
    {
        $collection = $this->uploadCollectionFactory->create()
            ->addFieldToFilter('main_table.customer_id', $this->getCustomerId())
            ->setOrder('main_table.created_at', 'DESC')
            ->setPageSize(20);
        $collection->getSelect()->joinLeft(
            ['so' => $collection->getTable('sales_order')],
            'so.entity_id = main_table.order_id',
            ['increment_id']
        );
        return $collection;
    }

    public function getStatusLabel(string $status): string
    {
        return (string)match ($status) {
            'sent' => __('Confirmed'),
            'failed' => __('Under review'),
            default => __('Processing'),
        };
    }

    private function getCustomerId(): int
    {
        return (int)$this->customerSession->getCustomerId();
    }
}

<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Observer;

use Codilar\PrescriptionNotification\Model\Upload;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Listens for "codilar_prescription_uploaded" and publishes the notification job to RabbitMQ.
 */
class PublishPrescriptionUploaded implements ObserverInterface
{
    public const TOPIC = 'codilar.prescription.uploaded';

    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Upload $upload */
        $upload = $observer->getEvent()->getData('upload');
        $payload = [
            'customer_id' => (int)$upload->getData('customer_id'),
            'order_id' => (int)$upload->getData('order_id'),
            'prescription_file' => (string)$upload->getData('prescription_file'),
        ];

        try {
            $this->publisher->publish(self::TOPIC, $this->json->serialize($payload));
            $this->logger->info('Prescription upload published to queue.', $payload);
        } catch (\Throwable $e) {
            // Never fail the customer's upload because the broker is unavailable; the row stays "pending"
            // and can be re-published with bin/magento codilar:prescription:publish.
            $this->logger->critical('Failed to publish prescription upload: ' . $e->getMessage(), $payload);
        }
    }
}

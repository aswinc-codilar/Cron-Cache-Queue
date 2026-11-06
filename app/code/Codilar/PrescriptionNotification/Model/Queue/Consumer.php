<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model\Queue;

use Codilar\PrescriptionNotification\Model\EmailSender;
use Codilar\PrescriptionNotification\Model\FileStorage;
use Codilar\PrescriptionNotification\Model\ResourceModel\Upload as UploadResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for topic "codilar.prescription.uploaded" (queue "codilar.prescription.notification").
 *
 * Every failure is caught, logged and recorded on the upload row (status=failed), so the message is
 * acknowledged rather than poisoning the queue. Failed rows can be re-sent with
 * `bin/magento codilar:prescription:publish`.
 */
class Consumer
{
    public function __construct(
        private readonly PayloadValidator $validator,
        private readonly FileStorage $fileStorage,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UploadResource $uploadResource,
        private readonly EmailSender $emailSender,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $message): void
    {
        // 1. Validate payload
        try {
            $payload = $this->validator->validate($message);
        } catch (ValidatorException $e) {
            $this->logger->error('Prescription message rejected: ' . $e->getMessage(), ['message' => $message]);
            return;
        }

        $customerId = $payload['customer_id'];
        $orderId = $payload['order_id'];
        $file = $payload['prescription_file'];
        $context = $payload;

        // 2. Cheap duplicate check before doing any work
        if ($this->uploadResource->isNotified($customerId, $orderId, $file)) {
            $this->logger->info('Duplicate prescription message skipped: notification already sent.', $context);
            return;
        }

        try {
            // 3. Verify file exists
            if (!$this->fileStorage->exists($customerId, $file)) {
                throw new LocalizedException(__('Prescription file "%1" not found for customer %2.', $file, $customerId));
            }

            // 4. Fetch customer and order, and make sure they belong together
            $customer = $this->customerRepository->getById($customerId);
            $order = $this->orderRepository->get($orderId);
            if ((int)$order->getCustomerId() !== $customerId) {
                throw new LocalizedException(__('Order %1 does not belong to customer %2.', $orderId, $customerId));
            }
        } catch (\Exception $e) {
            $this->logger->error('Prescription processing failed: ' . $e->getMessage(), $context);
            $this->uploadResource->markFailedByKey($customerId, $orderId, $file, $e->getMessage());
            return;
        }

        // 5. Claim the notification atomically (duplicate prevention under concurrency / redelivery)
        $uploadId = $this->uploadResource->claim($customerId, $orderId, $file);
        if ($uploadId === null) {
            $this->logger->info('Duplicate prescription message skipped: already sent or being processed.', $context);
            return;
        }
        $context['upload_id'] = $uploadId;

        // 6. Send email
        try {
            $this->emailSender->send($customer, $order, $file);
            $this->uploadResource->markSent($uploadId);
            $this->logger->info(
                sprintf('Prescription notification sent to %s for order #%s.', $customer->getEmail(), $order->getIncrementId()),
                $context
            );
        } catch (\Throwable $e) {
            $this->uploadResource->markFailed($uploadId, $e->getMessage());
            $this->logger->error('Prescription email failed: ' . $e->getMessage(), $context);
        }
    }
}

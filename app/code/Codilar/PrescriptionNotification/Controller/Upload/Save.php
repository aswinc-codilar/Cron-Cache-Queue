<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Controller\Upload;

use Codilar\PrescriptionNotification\Model\Config;
use Codilar\PrescriptionNotification\Model\FileStorage;
use Codilar\PrescriptionNotification\Model\ResourceModel\Upload as UploadResource;
use Codilar\PrescriptionNotification\Model\Upload;
use Codilar\PrescriptionNotification\Model\UploadFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Stores the file synchronously and returns immediately; the email is handled by the queue consumer.
 * Form key is validated automatically for HttpPostActionInterface actions.
 */
class Save implements HttpPostActionInterface
{
    private const FILE_FIELD = 'prescription_file';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly CustomerSession $customerSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly FileStorage $fileStorage,
        private readonly UploadFactory $uploadFactory,
        private readonly UploadResource $uploadResource,
        private readonly EventManager $eventManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $redirect = $this->redirectFactory->create()->setPath('prescription/upload');
        $customerId = (int)$this->customerSession->getCustomerId();

        try {
            if (!$this->config->isEnabled()) {
                throw new LocalizedException(__('Prescription upload is currently disabled.'));
            }

            $orderId = (int)$this->request->getParam('order_id');
            $this->assertOrderBelongsToCustomer($orderId, $customerId);
            $this->assertFileSize();

            $stored = $this->fileStorage->saveUploadedFile(self::FILE_FIELD, $customerId);

            /** @var Upload $upload */
            $upload = $this->uploadFactory->create();
            $upload->setData([
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'prescription_file' => $stored['file'],
                'original_name' => $stored['name'],
                'status' => Upload::STATUS_PENDING,
            ]);
            $this->uploadResource->save($upload);

            $this->eventManager->dispatch('codilar_prescription_uploaded', ['upload' => $upload]);

            $this->messageManager->addSuccessMessage(
                __('Your prescription has been uploaded successfully. You will receive a confirmation email shortly.')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Prescription upload failed: ' . $e->getMessage(), ['customer_id' => $customerId]);
            $this->messageManager->addErrorMessage(__('Something went wrong while uploading your prescription.'));
        }

        return $redirect;
    }

    /**
     * @throws LocalizedException
     */
    private function assertOrderBelongsToCustomer(int $orderId, int $customerId): void
    {
        if ($orderId <= 0) {
            throw new LocalizedException(__('Please select an order.'));
        }
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('The selected order does not exist.'));
        }
        if ((int)$order->getCustomerId() !== $customerId) {
            throw new LocalizedException(__('The selected order does not exist.'));
        }
    }

    /**
     * @throws LocalizedException
     */
    private function assertFileSize(): void
    {
        $file = $this->request->getFiles(self::FILE_FIELD);
        if (!$file || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new LocalizedException(__('Please choose a prescription file to upload.'));
        }
        if ((int)$file['size'] > $this->config->getMaxFileSizeBytes()) {
            throw new LocalizedException(
                __('The file is too large. Maximum size is %1 MB.', round($this->config->getMaxFileSizeBytes() / 1048576, 1))
            );
        }
    }
}

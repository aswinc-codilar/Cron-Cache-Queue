<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Controller\Upload;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * My Account > Upload Prescription.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            // Come back here after login
            $this->customerSession->setBeforeAuthUrl($this->url->getUrl('prescription/upload'));
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Upload Prescription'));
        return $page;
    }
}

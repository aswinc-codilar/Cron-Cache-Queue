<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\App\Emulation;

class EmailSender
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly Emulation $emulation,
        private readonly Config $config
    ) {
    }

    /**
     * @throws \Exception
     */
    public function send(CustomerInterface $customer, OrderInterface $order, string $file): void
    {
        $storeId = (int)$order->getStoreId();
        $customerName = trim($customer->getFirstname() . ' ' . $customer->getLastname());

        // Consumers run in the "global" area; emulate the order's storefront so the template renders correctly.
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($this->config->getEmailTemplate($storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars([
                    'customer_name' => $customerName,
                    'order_increment_id' => $order->getIncrementId(),
                    'prescription_file' => $file,
                ])
                ->setFromByScope($this->config->getEmailSender($storeId), $storeId)
                ->addTo((string)$customer->getEmail(), $customerName);

            foreach ($this->config->getCopyTo($storeId) as $bcc) {
                $this->transportBuilder->addBcc($bcc);
            }

            $this->transportBuilder->getTransport()->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}

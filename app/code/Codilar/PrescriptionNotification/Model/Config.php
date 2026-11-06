<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_ENABLED = 'codilar_prescription/general/enabled';
    private const XML_MAX_FILE_SIZE_MB = 'codilar_prescription/general/max_file_size_mb';
    private const XML_EMAIL_SENDER = 'codilar_prescription/email/sender';
    private const XML_EMAIL_TEMPLATE = 'codilar_prescription/email/template';
    private const XML_EMAIL_COPY_TO = 'codilar_prescription/email/copy_to';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMaxFileSizeBytes(): int
    {
        $mb = (float)$this->scopeConfig->getValue(self::XML_MAX_FILE_SIZE_MB) ?: 5.0;
        return (int)($mb * 1024 * 1024);
    }

    public function getEmailSender(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getEmailTemplate(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[]
     */
    public function getCopyTo(?int $storeId = null): array
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_EMAIL_COPY_TO, ScopeInterface::SCOPE_STORE, $storeId);
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}

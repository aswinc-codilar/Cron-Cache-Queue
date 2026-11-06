<?php
/**
 * Typed access to the ERP Price Sync admin configuration.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const XML_PATH_ENABLED = 'erp_price_sync/general/enabled';
    public const XML_PATH_CRON_SCHEDULE = 'erp_price_sync/general/cron_schedule';
    public const XML_PATH_BATCH_SIZE = 'erp_price_sync/general/batch_size';

    private const DEFAULT_BATCH_SIZE = 500;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether the sync is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Max items processed per run.
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        $size = (int)$this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE);

        return $size > 0 ? $size : self::DEFAULT_BATCH_SIZE;
    }
}

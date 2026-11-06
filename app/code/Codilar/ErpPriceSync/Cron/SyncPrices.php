<?php
/**
 * Cron entry point for the ERP price sync. Never throws: all errors are logged.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Cron;

use Codilar\ErpPriceSync\Model\Config;
use Codilar\ErpPriceSync\Model\SyncService;
use Psr\Log\LoggerInterface;

class SyncPrices
{
    /**
     * @param Config $config
     * @param SyncService $syncService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly SyncService $syncService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the sync if enabled.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->syncService->execute();
        } catch (\Throwable $e) {
            $this->logger->error('ERP price sync cron failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}

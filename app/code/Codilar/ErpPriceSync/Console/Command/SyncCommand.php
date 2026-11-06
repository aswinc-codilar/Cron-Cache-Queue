<?php
/**
 * bin/magento erp:price:sync - run one ERP price sync batch immediately.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Console\Command;

use Codilar\ErpPriceSync\Model\SyncService;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    /**
     * @param SyncService $syncService Injected as a Proxy (see di.xml)
     * @param State $appState
     * @param string|null $name
     */
    public function __construct(
        private readonly SyncService $syncService,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('erp:price:sync')
            ->setDescription('Sync product prices from the ERP now (ignores the "Enabled" setting).');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->appState->emulateAreaCode(
                Area::AREA_CRONTAB,
                fn (): array => $this->syncService->execute()
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>ERP price sync failed: ' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        if ($result['locked']) {
            $output->writeln('<comment>Another ERP price sync is running. Try again shortly.</comment>');

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf(
            'Fetched: %d | Updated: %d | Failed: %d | Skipped: %d',
            $result['fetched'],
            $result['updated'],
            $result['failed'],
            $result['skipped']
        ));

        return Cli::RETURN_SUCCESS;
    }
}

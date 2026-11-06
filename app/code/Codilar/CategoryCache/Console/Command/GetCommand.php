<?php
/**
 * bin/magento codilar:category:get <id> [--store=<id>] [--times=<n>]
 *
 * Fetches the category details <n> times (default 2) and reports whether each
 * call was served from the cache or from the database, with timings.
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Console\Command;

use Codilar\CategoryCache\Api\CategoryDetailsInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends Command
{
    private const ARG_ID = 'category_id';
    private const OPT_STORE = 'store';
    private const OPT_TIMES = 'times';

    /**
     * @param CategoryDetailsInterface $categoryDetails Injected as a Proxy (see di.xml)
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param State $appState
     * @param string|null $name
     */
    public function __construct(
        private readonly CategoryDetailsInterface $categoryDetails,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation,
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
        $this->setName('codilar:category:get')
            ->setDescription('Get category ID, name and URL (cached for 1 hour) and show cache hits/misses.')
            ->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Category ID')
            ->addOption(self::OPT_STORE, 's', InputOption::VALUE_REQUIRED, 'Store view ID (default store view if omitted)')
            ->addOption(self::OPT_TIMES, 't', InputOption::VALUE_REQUIRED, 'How many times to fetch', '2');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categoryId = (int)$input->getArgument(self::ARG_ID);
        $storeOption = $input->getOption(self::OPT_STORE);
        $storeId = $storeOption !== null
            ? (int)$this->storeManager->getStore($storeOption)->getId()
            : (int)$this->storeManager->getDefaultStoreView()->getId();
        $times = max(1, (int)$input->getOption(self::OPT_TIMES));

        try {
            return $this->appState->emulateAreaCode(
                Area::AREA_FRONTEND,
                fn (): int => $this->fetch($output, $categoryId, $storeId, $times)
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Fetch the details $times times inside a storefront emulation.
     *
     * @param OutputInterface $output
     * @param int $categoryId
     * @param int $storeId
     * @param int $times
     * @return int
     */
    private function fetch(OutputInterface $output, int $categoryId, int $storeId, int $times): int
    {
        // Store emulation so category URLs are built for the storefront, not the CLI/admin context.
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            for ($i = 1; $i <= $times; $i++) {
                $start = microtime(true);
                $details = $this->categoryDetails->get($categoryId, $storeId);
                $ms = (microtime(true) - $start) * 1000;

                $output->writeln(sprintf(
                    'Call #%d: %s (%.2f ms)',
                    $i,
                    $this->categoryDetails->wasLastHit()
                        ? '<info>CACHE HIT</info>'
                        : '<comment>CACHE MISS - loaded from database</comment>',
                    $ms
                ));
                $output->writeln(sprintf(
                    '  ID: %d | Name: %s | URL: %s',
                    $details['id'],
                    $details['name'],
                    $details['url']
                ));
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        return Cli::RETURN_SUCCESS;
    }
}

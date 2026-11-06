<?php
/**
 * bin/magento codilar:category:cache:clean <id> - drop cached details of one category.
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Console\Command;

use Codilar\CategoryCache\Api\CategoryDetailsInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends Command
{
    private const ARG_ID = 'category_id';

    /**
     * @param CategoryDetailsInterface $categoryDetails Injected as a Proxy (see di.xml)
     * @param string|null $name
     */
    public function __construct(
        private readonly CategoryDetailsInterface $categoryDetails,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('codilar:category:cache:clean')
            ->setDescription('Clear the cached details of one category (all store views).')
            ->addArgument(self::ARG_ID, InputArgument::REQUIRED, 'Category ID');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categoryId = (int)$input->getArgument(self::ARG_ID);
        $this->categoryDetails->clean($categoryId);
        $output->writeln(sprintf('<info>Cleared cached details for category %d.</info>', $categoryId));

        return Cli::RETURN_SUCCESS;
    }
}

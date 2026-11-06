<?php
/**
 * Seeds the mock ERP table with sample prices.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Setup\Patch\Data;

use Codilar\ErpPriceSync\Model\Provider\TableProvider;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddSampleErpPrices implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->insertOnDuplicate(
            $this->moduleDataSetup->getTable(TableProvider::TABLE),
            [
                ['sku' => 'TEST-SKU-001', 'price' => 100.00],
                ['sku' => 'TEST-SKU-002', 'price' => 250.50],
                ['sku' => 'TEST-SKU-003', 'price' => 75.25],
            ],
            ['price']
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}

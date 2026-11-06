<?php
/**
 * Mock ERP provider backed by the erp_product_price table.
 *
 * A row is pending when synced_at IS NULL OR updated_at > synced_at. On acknowledge, synced_at is set to the
 * updated_at value that was read (not "now"), and the row is only touched if it was not edited in the meantime.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Model\Provider;

use Codilar\ErpPriceSync\Api\PriceProviderInterface;
use Magento\Framework\App\ResourceConnection;

class TableProvider implements PriceProviderInterface
{
    public const TABLE = 'erp_product_price';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $limit): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['entity_id', 'sku', 'price', 'updated_at'])
            ->where('synced_at IS NULL OR updated_at > synced_at')
            ->order('updated_at ASC')
            ->order('entity_id ASC')
            ->limit(max(1, $limit));

        $items = [];
        foreach ($connection->fetchAll($select) as $row) {
            $items[] = [
                'sku' => (string)$row['sku'],
                'price' => (float)$row['price'],
                'ref' => [
                    'id' => (int)$row['entity_id'],
                    'updated_at' => (string)$row['updated_at'],
                    'price' => (string)$row['price'],
                ],
            ];
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function acknowledge(array $items): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);

        foreach ($items as $item) {
            $ref = $item['ref'] ?? null;
            if (!is_array($ref) || !isset($ref['id'], $ref['updated_at'], $ref['price'])) {
                continue;
            }
            // Assigning updated_at to itself suppresses MySQL's ON UPDATE CURRENT_TIMESTAMP bump.
            // The WHERE guard skips rows edited after we read them, so they stay pending for the next run.
            $connection->update(
                $table,
                [
                    'synced_at' => $ref['updated_at'],
                    'updated_at' => new \Zend_Db_Expr('updated_at'),
                    'last_error' => $item['error'] ?? null,
                ],
                [
                    'entity_id = ?' => $ref['id'],
                    'updated_at = ?' => $ref['updated_at'],
                    'price = ?' => $ref['price'],
                ]
            );
        }
    }
}

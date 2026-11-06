<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model\ResourceModel;

use Codilar\PrescriptionNotification\Model\Upload as UploadModel;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persistence for uploads, plus the atomic "claim" used to prevent duplicate notifications.
 */
class Upload extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('codilar_prescription_upload', 'entity_id');
    }

    public function isNotified(int $customerId, int $orderId, string $file): bool
    {
        $select = $this->getConnection()->select()
            ->from($this->getMainTable(), ['status'])
            ->where('customer_id = ?', $customerId)
            ->where('order_id = ?', $orderId)
            ->where('prescription_file = ?', $file);

        return $this->getConnection()->fetchOne($select) === UploadModel::STATUS_SENT;
    }

    /**
     * Atomically move the record to "processing".
     *
     * Only one consumer can win the conditional UPDATE, so redelivered or duplicated
     * messages for the same customer/order/file never send a second email.
     * Creates the record when the message was published without one (e.g. from CLI).
     *
     * @return int|null Record ID if this caller owns the notification, null if it was already sent / in progress.
     */
    public function claim(int $customerId, int $orderId, string $file): ?int
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $connection->insertOnDuplicate(
            $table,
            [
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'prescription_file' => $file,
                'status' => UploadModel::STATUS_PENDING,
            ],
            ['customer_id'] // no-op update: just ensures the row exists
        );

        $id = (int)$connection->fetchOne(
            $connection->select()
                ->from($table, ['entity_id'])
                ->where('customer_id = ?', $customerId)
                ->where('order_id = ?', $orderId)
                ->where('prescription_file = ?', $file)
        );

        $affected = $connection->update(
            $table,
            [
                'status' => UploadModel::STATUS_PROCESSING,
                'attempts' => new Expression('attempts + 1'),
                'error_message' => null,
            ],
            [
                'entity_id = ?' => $id,
                'status IN (?)' => [UploadModel::STATUS_PENDING, UploadModel::STATUS_FAILED],
            ]
        );

        return $affected === 1 ? $id : null;
    }

    public function markSent(int $id): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => UploadModel::STATUS_SENT, 'notified_at' => new Expression('NOW()'), 'error_message' => null],
            ['entity_id = ?' => $id]
        );
    }

    public function markFailed(int $id, string $error): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => UploadModel::STATUS_FAILED, 'error_message' => mb_substr($error, 0, 2000)],
            ['entity_id = ?' => $id]
        );
    }

    /**
     * Mark failed by natural key (used when processing fails before the record is claimed).
     */
    public function markFailedByKey(int $customerId, int $orderId, string $file, string $error): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => UploadModel::STATUS_FAILED, 'error_message' => mb_substr($error, 0, 2000)],
            [
                'customer_id = ?' => $customerId,
                'order_id = ?' => $orderId,
                'prescription_file = ?' => $file,
                'status != ?' => UploadModel::STATUS_SENT,
            ]
        );
    }
}

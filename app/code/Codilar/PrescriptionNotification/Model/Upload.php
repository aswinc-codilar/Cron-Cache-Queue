<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model;

use Magento\Framework\Model\AbstractModel;

class Upload extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $_eventPrefix = 'codilar_prescription_upload';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Upload::class);
    }
}

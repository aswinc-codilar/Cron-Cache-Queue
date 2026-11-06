<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Model\ResourceModel\Upload;

use Codilar\PrescriptionNotification\Model\ResourceModel\Upload as UploadResource;
use Codilar\PrescriptionNotification\Model\Upload;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Upload::class, UploadResource::class);
    }
}

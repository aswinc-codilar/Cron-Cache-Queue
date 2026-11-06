<?php
/**
 * Validates the "Cron Schedule" admin field before saving.
 */
declare(strict_types=1);

namespace Codilar\ErpPriceSync\Model\Config\Backend;

use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class CronExpression extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ScheduleFactory $scheduleFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ScheduleFactory $scheduleFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Reject invalid cron expressions.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        try {
            $this->scheduleFactory->create()->setCronExpr($value);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Invalid cron expression "%1".', $value), $e);
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}

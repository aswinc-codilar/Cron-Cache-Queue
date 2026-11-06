<?php
/**
 * Dedicated cache type so the entries can be toggled/flushed on their own.
 */
declare(strict_types=1);

namespace Codilar\CategoryCache\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class CategoryDetails extends TagScope
{
    public const TYPE_IDENTIFIER = 'codilar_category_details';

    /** Applied to every entry of this type; used by "flush this cache type". */
    public const CACHE_TAG = 'CODILAR_CATEGORY_DETAILS';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}

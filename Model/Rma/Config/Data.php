<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Config;

use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use MageOS\AdvancedReport\Model\Config\Data as ConfigData;

class Data extends ConfigData
{
    public const string CACHE_ID = 'advanced_report_datasets_rma';

    /**
     * @param Reader $reader
     * @param CacheInterface $cache
     * @param string $cacheId
     * @param SerializerInterface|null $serializer
     */
    public function __construct(
        Reader $reader,
        CacheInterface $cache,
        string $cacheId = self::CACHE_ID,
        ?SerializerInterface $serializer = null,
    ) {
        parent::__construct($reader, $cache, $cacheId, $serializer);
    }
}

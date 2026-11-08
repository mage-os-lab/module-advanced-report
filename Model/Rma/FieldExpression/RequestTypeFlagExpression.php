<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class RequestTypeFlagExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = '(%s = %s)';

    /**
     * @param ReturnStageSql $returnStageSql
     * @param string $type
     */
    public function __construct(
        protected readonly ReturnStageSql $returnStageSql,
        protected readonly string $type,
    ) {
    }

    /**
     * @param Dataset $dataset
     * @param AdapterInterface $connection
     * @return string
     */
    public function getExpression(Dataset $dataset, AdapterInterface $connection): string
    {
        return \sprintf(
            self::TEMPLATE,
            $this->returnStageSql->requestType(ReturnStageSql::MAIN_ALIAS),
            $connection->quote($this->type)
        );
    }
}

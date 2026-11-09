<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class RequestTypeExpression implements FieldExpressionProviderInterface
{
    /**
     * @param ReturnStageSql $returnStageSql
     */
    public function __construct(
        protected readonly ReturnStageSql $returnStageSql,
    ) {
    }

    /**
     * @param Dataset $dataset
     * @param AdapterInterface $connection
     * @return string
     */
    public function getExpression(Dataset $dataset, AdapterInterface $connection): string
    {
        return $this->returnStageSql->requestType(ReturnStageSql::MAIN_ALIAS);
    }
}

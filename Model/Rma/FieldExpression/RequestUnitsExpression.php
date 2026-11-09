<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class RequestUnitsExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = '(SELECT COALESCE(SUM(rqi.qty_requested), 0) FROM %s AS rqi'
        . ' WHERE rqi.rma_id = main.entity_id)';

    /**
     * @param ReportConnection $reportConnection
     */
    public function __construct(
        protected readonly ReportConnection $reportConnection,
    ) {
    }

    /**
     * @param Dataset $dataset
     * @param AdapterInterface $connection
     * @return string
     */
    public function getExpression(Dataset $dataset, AdapterInterface $connection): string
    {
        return \sprintf(self::TEMPLATE, $this->reportConnection->getTableName(ReturnStageSql::TABLE_RMA_ITEM));
    }
}

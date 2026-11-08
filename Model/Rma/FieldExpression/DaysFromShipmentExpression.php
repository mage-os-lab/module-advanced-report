<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class DaysFromShipmentExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = '(SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, MAX(rds.created_at), main.created_at)) / 86400'
        . ' FROM %s AS rds WHERE rds.order_id = main.order_id AND rds.created_at <= main.created_at)';

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
        return \sprintf(self::TEMPLATE, $this->reportConnection->getTableName(ReturnStageSql::TABLE_SALES_SHIPMENT));
    }
}

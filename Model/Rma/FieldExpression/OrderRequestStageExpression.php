<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class OrderRequestStageExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = 'EXISTS (SELECT 1 FROM %s AS owi INNER JOIN %s AS owr ON owr.entity_id = owi.rma_id'
        . ' WHERE owr.order_id = main.entity_id AND %s%s)';
    public const string NEGATION = 'NOT ';

    /**
     * @param ReturnStageSql $returnStageSql
     * @param ReportConnection $reportConnection
     * @param bool $shipped
     */
    public function __construct(
        protected readonly ReturnStageSql $returnStageSql,
        protected readonly ReportConnection $reportConnection,
        protected readonly bool $shipped,
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
            $this->reportConnection->getTableName(ReturnStageSql::TABLE_RMA_ITEM),
            $this->reportConnection->getTableName(ReturnStageSql::TABLE_RMA_ENTITY),
            $this->shipped ? '' : self::NEGATION,
            $this->returnStageSql->shippedBefore('owi.order_item_id', 'owr.order_id', 'owr.created_at')
        );
    }
}

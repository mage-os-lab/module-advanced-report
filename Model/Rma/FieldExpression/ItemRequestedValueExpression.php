<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class ItemRequestedValueExpression implements FieldExpressionProviderInterface
{
    public const string DYNAMIC_BUNDLE_CONDITION = "order_item.product_type = 'bundle'"
        . ' AND (CASE WHEN JSON_VALID(order_item.product_options)'
        . " THEN JSON_UNQUOTE(JSON_EXTRACT(order_item.product_options, '$.product_calculations')) END) = '0'";

    public const string CHILDREN_DISCOUNT = '(SELECT COALESCE(SUM(ABS(COALESCE(rvc.base_discount_amount, 0))'
        . ' - COALESCE(rvc.base_discount_tax_compensation_amount, 0)), 0) FROM %s AS rvc'
        . ' WHERE rvc.order_id = order_item.order_id AND rvc.parent_item_id = order_item.item_id)';

    public const string LINE_DISCOUNT = '(ABS(COALESCE(order_item.base_discount_amount, 0))'
        . ' - COALESCE(order_item.base_discount_tax_compensation_amount, 0))';

    public const string TEMPLATE = '(COALESCE(main.qty_requested, 0) * (COALESCE(order_item.base_row_total, 0)'
        . ' - (CASE WHEN %s THEN %s ELSE %s END)) / COALESCE(NULLIF(order_item.qty_ordered, 0), 1))';

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
        return \sprintf(
            self::TEMPLATE,
            self::DYNAMIC_BUNDLE_CONDITION,
            \sprintf(
                self::CHILDREN_DISCOUNT,
                $this->reportConnection->getTableName(ReturnStageSql::TABLE_SALES_ORDER_ITEM)
            ),
            self::LINE_DISCOUNT
        );
    }
}

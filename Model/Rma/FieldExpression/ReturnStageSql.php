<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Rma\RequestType;

class ReturnStageSql
{
    public const string MAIN_ALIAS = 'main';
    public const string TABLE_RMA_ENTITY = 'rma_entity';
    public const string TABLE_RMA_ITEM = 'rma_item';
    public const string TABLE_SALES_ORDER_ITEM = 'sales_order_item';
    public const string TABLE_SALES_SHIPMENT = 'sales_shipment';
    public const string TABLE_SALES_SHIPMENT_ITEM = 'sales_shipment_item';

    public const string SHIPPED_BEFORE = '(EXISTS (SELECT 1 FROM %4$s AS rssi INNER JOIN %5$s AS rss'
        . ' ON rss.entity_id = rssi.parent_id WHERE rss.order_id = %2$s AND rssi.order_item_id = %1$s'
        . ' AND rss.created_at <= %3$s)'
        . ' OR EXISTS (SELECT 1 FROM %6$s AS rsoc INNER JOIN %4$s AS rssc ON rssc.order_item_id = rsoc.item_id'
        . ' INNER JOIN %5$s AS rsc ON rsc.entity_id = rssc.parent_id WHERE rsoc.order_id = %2$s'
        . ' AND rsoc.parent_item_id = %1$s AND rsc.order_id = %2$s AND rsc.created_at <= %3$s))';

    public const string REQUEST_TYPE = "(SELECT CASE WHEN COUNT(*) = 0 THEN NULL WHEN SUM(%1\$s) = 0 THEN '%2\$s'"
        . " WHEN SUM(%1\$s) = COUNT(*) THEN '%3\$s' ELSE '%4\$s' END FROM %5\$s AS rti"
        . ' WHERE rti.rma_id = %6$s.entity_id)';

    /**
     * @param ReportConnection $reportConnection
     */
    public function __construct(
        protected readonly ReportConnection $reportConnection,
    ) {
    }

    /**
     * @param string $orderItemId
     * @param string $orderId
     * @param string $requestedAt
     * @return string
     */
    public function shippedBefore(string $orderItemId, string $orderId, string $requestedAt): string
    {
        return \sprintf(
            self::SHIPPED_BEFORE,
            $orderItemId,
            $orderId,
            $requestedAt,
            $this->reportConnection->getTableName(self::TABLE_SALES_SHIPMENT_ITEM),
            $this->reportConnection->getTableName(self::TABLE_SALES_SHIPMENT),
            $this->reportConnection->getTableName(self::TABLE_SALES_ORDER_ITEM),
        );
    }

    /**
     * @param string $rmaAlias
     * @return string
     */
    public function requestType(string $rmaAlias): string
    {
        return \sprintf(
            self::REQUEST_TYPE,
            $this->shippedBefore('rti.order_item_id', $rmaAlias . '.order_id', $rmaAlias . '.created_at'),
            RequestType::WITHDRAWAL->value,
            RequestType::RETURN->value,
            RequestType::MIXED->value,
            $this->reportConnection->getTableName(self::TABLE_RMA_ITEM),
            $rmaAlias,
        );
    }
}

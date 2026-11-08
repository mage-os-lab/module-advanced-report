<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\FieldExpression;

use MageOS\AdvancedReport\Model\Rma\FieldExpression\ReturnStageSql;
use MageOS\AdvancedReport\Test\Unit\Helper\SelectTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnStageSql::class)]
final class ReturnStageSqlTest extends TestCase
{
    use SelectTestTrait;

    /**
     * @return void
     */
    public function testItChecksTheShipmentsOfTheItemAndOfItsChildrenCreatedUpToTheRequest(): void
    {
        $sql = $this->sql()->shippedBefore('i.item', 'r.order', 'r.created');

        self::assertSame(
            '(EXISTS (SELECT 1 FROM sales_shipment_item AS rssi INNER JOIN sales_shipment AS rss'
            . ' ON rss.entity_id = rssi.parent_id WHERE rss.order_id = r.order AND rssi.order_item_id = i.item'
            . ' AND rss.created_at <= r.created)'
            . ' OR EXISTS (SELECT 1 FROM sales_order_item AS rsoc INNER JOIN sales_shipment_item AS rssc'
            . ' ON rssc.order_item_id = rsoc.item_id INNER JOIN sales_shipment AS rsc ON rsc.entity_id = rssc.parent_id'
            . ' WHERE rsoc.order_id = r.order AND rsoc.parent_item_id = i.item AND rsc.order_id = r.order'
            . ' AND rsc.created_at <= r.created))',
            $sql
        );
    }

    /**
     * @return void
     */
    public function testItRendersTheShippedBeforeSubqueriesAgainstThePrefixedTables(): void
    {
        $sql = $this->sql('pre_')->shippedBefore('i.item', 'r.order', 'r.created');

        self::assertStringContainsString('FROM pre_sales_shipment_item AS rssi INNER JOIN pre_sales_shipment AS rss', $sql);
        self::assertStringContainsString('FROM pre_sales_order_item AS rsoc INNER JOIN pre_sales_shipment_item AS rssc', $sql);
        self::assertStringContainsString('INNER JOIN pre_sales_shipment AS rsc', $sql);
    }

    /**
     * @return void
     */
    public function testItClassifiesTheRequestFromTheShipmentStateOfItsItems(): void
    {
        $sql = $this->normalizeSql($this->sql()->requestType('main'));

        self::assertStringStartsWith('(SELECT CASE WHEN COUNT(*) = 0 THEN NULL WHEN SUM((EXISTS', $sql);
        self::assertStringContainsString(") = 0 THEN 'withdrawal' WHEN SUM((EXISTS", $sql);
        self::assertStringContainsString(") = COUNT(*) THEN 'return' ELSE 'mixed' END", $sql);
        self::assertStringEndsWith('FROM rma_item AS rti WHERE rti.rma_id = main.entity_id)', $sql);
    }

    /**
     * @return void
     */
    public function testItCorrelatesTheShipmentStateOnTheRequestItemAndTheRequestOfTheGivenAlias(): void
    {
        $sql = $this->sql()->requestType('rma');

        self::assertStringContainsString(
            'rss.order_id = rma.order_id AND rssi.order_item_id = rti.order_item_id AND rss.created_at <= rma.created_at',
            $sql
        );
        self::assertStringContainsString(
            'rsoc.order_id = rma.order_id AND rsoc.parent_item_id = rti.order_item_id AND rsc.order_id = rma.order_id'
            . ' AND rsc.created_at <= rma.created_at',
            $sql
        );
        self::assertStringContainsString('WHERE rti.rma_id = rma.entity_id)', $sql);
    }

    /**
     * @return void
     */
    public function testItRendersTheRequestItemsSubqueryAgainstThePrefixedTable(): void
    {
        self::assertStringContainsString('FROM pre_rma_item AS rti', $this->sql('pre_')->requestType('main'));
    }

    /**
     * @param string $prefix
     * @return ReturnStageSql
     */
    protected function sql(string $prefix = ''): ReturnStageSql
    {
        return new ReturnStageSql($this->createReportConnection($prefix));
    }
}

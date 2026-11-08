<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\FieldExpression;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\OrderRequestStageExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ReturnStageSql;
use MageOS\AdvancedReport\Test\Unit\Helper\SelectTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderRequestStageExpression::class)]
final class OrderRequestStageExpressionTest extends TestCase
{
    use SelectTestTrait;

    /**
     * @return void
     */
    public function testItLooksForARequestedItemShippedBeforeItsRequest(): void
    {
        $sql = $this->expression(true);

        self::assertStringStartsWith(
            'EXISTS (SELECT 1 FROM rma_item AS owi INNER JOIN rma_entity AS owr ON owr.entity_id = owi.rma_id'
            . ' WHERE owr.order_id = main.entity_id AND (EXISTS',
            $sql
        );
        self::assertStringContainsString('rssi.order_item_id = owi.order_item_id AND rss.created_at <= owr.created_at', $sql);
        self::assertStringContainsString('rsoc.order_id = owr.order_id', $sql);
    }

    /**
     * @return void
     */
    public function testItLooksForARequestedItemNotShippedBeforeItsRequest(): void
    {
        self::assertStringContainsString('WHERE owr.order_id = main.entity_id AND NOT (EXISTS', $this->expression(false));
    }

    /**
     * @return void
     */
    public function testItRendersTheRequestSubqueryAgainstThePrefixedTables(): void
    {
        $sql = $this->expression(true, 'pre_');

        self::assertStringContainsString('FROM pre_rma_item AS owi INNER JOIN pre_rma_entity AS owr', $sql);
        self::assertStringContainsString('FROM pre_sales_shipment_item AS rssi', $sql);
    }

    /**
     * @param bool $shipped
     * @param string $prefix
     * @return string
     */
    protected function expression(bool $shipped, string $prefix = ''): string
    {
        $reportConnection = $this->createReportConnection($prefix);

        return (new OrderRequestStageExpression(new ReturnStageSql($reportConnection), $reportConnection, $shipped))
            ->getExpression(
                new Dataset('order_returns', 'O', 'acl', 'returns', false, 'sales_order', 'main', null, [], [], [], [], []),
                $this->createSqlAdapter()
            );
    }
}

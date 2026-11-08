<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\FieldExpression;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ItemRequestedValueExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ItemRequestTypeExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ItemShippedFlagExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ReturnStageSql;
use MageOS\AdvancedReport\Test\Unit\Helper\SelectTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemRequestTypeExpression::class)]
#[CoversClass(ItemShippedFlagExpression::class)]
#[CoversClass(ItemRequestedValueExpression::class)]
final class ItemExpressionsTest extends TestCase
{
    use SelectTestTrait;

    /**
     * @return void
     */
    public function testItClassifiesTheItemAsAReturnWhenShippedBeforeTheRequest(): void
    {
        $returnStageSql = new ReturnStageSql($this->createReportConnection());

        self::assertSame(
            '(CASE WHEN ' . $this->shippedBefore($returnStageSql) . " THEN 'return' ELSE 'withdrawal' END)",
            (new ItemRequestTypeExpression($returnStageSql))->getExpression($this->dataset(), $this->createSqlAdapter())
        );
    }

    /**
     * @return void
     */
    public function testItFlagsShippedAndNotShippedItems(): void
    {
        $returnStageSql = new ReturnStageSql($this->createReportConnection());
        $shippedBefore = $this->shippedBefore($returnStageSql);

        self::assertSame(
            '(' . $shippedBefore . ')',
            (new ItemShippedFlagExpression($returnStageSql, true))->getExpression($this->dataset(), $this->createSqlAdapter())
        );
        self::assertSame(
            '(NOT ' . $shippedBefore . ')',
            (new ItemShippedFlagExpression($returnStageSql, false))->getExpression($this->dataset(), $this->createSqlAdapter())
        );
    }

    /**
     * @return void
     */
    public function testItValuesTheRequestedUnitsAtTheNetRowPriceOfTheOrderedLine(): void
    {
        $sql = (new ItemRequestedValueExpression($this->createReportConnection('pre_')))
            ->getExpression($this->dataset(), $this->createSqlAdapter());

        self::assertStringStartsWith(
            '(COALESCE(main.qty_requested, 0) * (COALESCE(order_item.base_row_total, 0) - (CASE WHEN'
            . " order_item.product_type = 'bundle'",
            $sql
        );
        self::assertStringContainsString(
            'FROM pre_sales_order_item AS rvc WHERE rvc.order_id = order_item.order_id'
            . ' AND rvc.parent_item_id = order_item.item_id)',
            $sql
        );
        self::assertStringContainsString(
            'ELSE (ABS(COALESCE(order_item.base_discount_amount, 0))'
            . ' - COALESCE(order_item.base_discount_tax_compensation_amount, 0)) END))',
            $sql
        );
        self::assertStringEndsWith('/ COALESCE(NULLIF(order_item.qty_ordered, 0), 1))', $sql);
    }

    /**
     * @param ReturnStageSql $returnStageSql
     * @return string
     */
    protected function shippedBefore(ReturnStageSql $returnStageSql): string
    {
        return $returnStageSql->shippedBefore('main.order_item_id', 'rma.order_id', 'rma.created_at');
    }

    /**
     * @return Dataset
     */
    protected function dataset(): Dataset
    {
        return new Dataset('rma_items', 'I', 'acl', 'returns', false, 'rma_item', 'main', null, [], [], [], [], []);
    }
}

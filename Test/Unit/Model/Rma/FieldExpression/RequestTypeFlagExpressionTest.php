<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\FieldExpression;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\RequestTypeExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\RequestTypeFlagExpression;
use MageOS\AdvancedReport\Model\Rma\FieldExpression\ReturnStageSql;
use MageOS\AdvancedReport\Test\Unit\Helper\SelectTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestTypeFlagExpression::class)]
#[CoversClass(RequestTypeExpression::class)]
final class RequestTypeFlagExpressionTest extends TestCase
{
    use SelectTestTrait;

    /**
     * @return void
     */
    public function testItComparesTheRequestTypeOfTheMainRequestWithTheConfiguredType(): void
    {
        $returnStageSql = new ReturnStageSql($this->createReportConnection());

        $sql = (new RequestTypeFlagExpression($returnStageSql, 'withdrawal'))
            ->getExpression($this->dataset(), $this->createSqlAdapter());

        self::assertSame('(' . $returnStageSql->requestType('main') . " = 'withdrawal')", $sql);
    }

    /**
     * @return void
     */
    public function testItExposesTheRequestTypeOfTheMainRequest(): void
    {
        $returnStageSql = new ReturnStageSql($this->createReportConnection());

        self::assertSame(
            $returnStageSql->requestType('main'),
            (new RequestTypeExpression($returnStageSql))->getExpression($this->dataset(), $this->createSqlAdapter())
        );
    }

    /**
     * @return void
     */
    public function testItRendersTheFlagAgainstThePrefixedTables(): void
    {
        $sql = (new RequestTypeFlagExpression(new ReturnStageSql($this->createReportConnection('pre_')), 'mixed'))
            ->getExpression($this->dataset(), $this->createSqlAdapter());

        self::assertStringContainsString('FROM pre_rma_item AS rti', $sql);
        self::assertStringEndsWith(" = 'mixed')", $sql);
    }

    /**
     * @return Dataset
     */
    protected function dataset(): Dataset
    {
        return new Dataset('rma_requests', 'R', 'acl', 'returns', false, 'rma_entity', 'main', null, [], [], [], [], []);
    }
}

<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\Label;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use MageOS\AdvancedReport\Model\Config;
use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\Rma\Label\AbstractRmaLookupLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaItemConditionLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaReasonLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaResolutionTypeLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaStatusLabelResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractRmaLookupLabelResolver::class)]
#[CoversClass(RmaStatusLabelResolver::class)]
#[CoversClass(RmaReasonLabelResolver::class)]
#[CoversClass(RmaResolutionTypeLabelResolver::class)]
#[CoversClass(RmaItemConditionLabelResolver::class)]
#[AllowMockObjectsWithoutExpectations]
final class RmaLookupLabelResolverTest extends TestCase
{
    /**
     * @return array<string, array{class-string<AbstractRmaLookupLabelResolver>, string}>
     */
    public static function resolverProvider(): array
    {
        return [
            'status' => [RmaStatusLabelResolver::class, 'rma_status'],
            'reason' => [RmaReasonLabelResolver::class, 'rma_reason'],
            'resolution type' => [RmaResolutionTypeLabelResolver::class, 'rma_resolution_type'],
            'item condition' => [RmaItemConditionLabelResolver::class, 'rma_item_condition'],
        ];
    }

    /**
     * @param class-string<AbstractRmaLookupLabelResolver> $class
     * @param string $table
     * @return void
     */
    #[DataProvider('resolverProvider')]
    public function testItLoadsTheLabelsOfTheRequestedIdsInOneQueryOnThePrefixedTable(string $class, string $table): void
    {
        $select = $this->createMock(Select::class);
        $select->expects(self::once())->method('from')
            ->with('pre_' . $table, ['entity_id', 'label'])->willReturnSelf();
        $select->expects(self::once())->method('where')->with('entity_id IN (?)', [1, 2])->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('fetchPairs')->with($select)->willReturn([1 => 'Approved']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnectionByName')->willReturn($connection);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => 'pre_' . $name);
        $config = $this->createStub(Config::class);
        $config->method('getConnectionName')->willReturn('report');

        $labels = (new $class(new ReportConnection($resource, $config)))->resolve(['1', '2', '2']);

        self::assertSame(['1' => 'Approved', '2' => '2'], $labels);
    }
}

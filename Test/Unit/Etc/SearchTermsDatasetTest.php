<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Etc;

use MageOS\AdvancedReport\Model\Definition\AggregateFunction;
use MageOS\AdvancedReport\Model\Definition\FilterOperator;
use MageOS\AdvancedReport\Model\Definition\Granularity;
use MageOS\AdvancedReport\Model\Label\StoreLabelResolver;
use PHPUnit\Framework\TestCase;

final class SearchTermsDatasetTest extends TestCase
{
    use ReportDatasetsFixtureTrait;

    public function testItReadsTheSearchQueryTableInTheCatalogArea(): void
    {
        $dataset = $this->loadReportDataset('search_terms');

        self::assertSame('search_query', $dataset->baseTable);
        self::assertSame('main', $dataset->baseAlias);
        self::assertSame('catalog', $dataset->area);
        self::assertSame('MageOS_AdvancedReport::catalog', $dataset->aclResource);
        self::assertFalse($dataset->dateless);
        self::assertSame([], $dataset->implicitFilters);
    }

    public function testItDatesTermsByTheirLastSearchAndScopesThemByStore(): void
    {
        $dataset = $this->loadReportDataset('search_terms');

        self::assertSame('main.updated_at', $dataset->getField('created_at')->sourceMappings['mysql']->expression);
        self::assertSame('main.store_id', $dataset->getField('store_id')->sourceMappings['mysql']->expression);
        self::assertSame(StoreLabelResolver::class, $dataset->getDimension('store_id')->labelResolver);
        self::assertSame(
            [Granularity::DAY, Granularity::WEEK, Granularity::MONTH, Granularity::YEAR],
            $dataset->getDimension('last_searched')->getGranularities()
        );
    }

    public function testItSumsThePopularityCounterAndComputesTheNoResultsRateAfterAggregation(): void
    {
        $dataset = $this->loadReportDataset('search_terms');

        self::assertSame(AggregateFunction::SUM, $dataset->getMetric('searches')->aggregate);
        self::assertSame('popularity', $dataset->getMetric('searches')->fieldCode);
        self::assertSame('has_no_results', $dataset->getMetric('searches_no_results')->conditionFieldCode);
        self::assertSame(
            'searches_no_results / searches',
            $dataset->getMetric('no_results_rate')->formula
        );
        self::assertSame(
            '(main.num_results = 0)',
            $dataset->getField('has_no_results')->sourceMappings['mysql']->expression
        );
    }

    public function testItDeclaresTheTopSearchTermsAndNoResultsPresets(): void
    {
        $presets = $this->loadReportPresets();

        self::assertSame('search_terms', $presets['top_search_terms']->dataset);
        self::assertSame(['searches', 'avg_results'], $presets['top_search_terms']->metrics);
        self::assertSame(['search_term'], array_column($presets['top_search_terms']->dimensions, 'code'));

        $noResults = $presets['search_terms_no_results'];
        self::assertSame('search_terms', $noResults->dataset);
        self::assertSame('has_no_results', $noResults->filters[0]['field']);
        self::assertSame(FilterOperator::EQ, $noResults->filters[0]['operator']);
    }

    public function testItDeclaresTheMostRefundedProductsPresetOnTheOrderItems(): void
    {
        $preset = $this->loadReportPresets()['most_refunded_products'];
        $items = $this->loadReportDataset('sales_items');

        self::assertSame('sales_items', $preset->dataset);
        self::assertSame(['units_refunded', 'returns', 'units_sold', 'refund_rate'], $preset->metrics);
        self::assertSame('qty_refunded_qty', $preset->filters[0]['field']);
        self::assertSame(FilterOperator::GTE, $preset->filters[0]['operator']);
        self::assertSame('units_refunded', $preset->sort[0]['metric']);
        self::assertSame('sku', $preset->alternateDimension);
        self::assertSame('units_refunded / units_sold', $items->getMetric('refund_rate')->formula);
    }
}

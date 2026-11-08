<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Etc;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\Preset;
use PHPUnit\Framework\TestCase;

final class ReturnDatasetsTest extends TestCase
{
    use ReportDatasetsFixtureTrait;

    protected const array ETC_FILES = ['report_datasets.xml', 'report_datasets_rma.xml'];
    protected const array RMA_DATASETS = ['rma_requests', 'rma_items', 'order_returns', 'order_item_returns'];
    protected const array RMA_PRESET_CHARTS = [
        'returns_overview' => 'line',
        'withdrawals_vs_returns' => 'doughnut',
        'withdrawals_trend' => 'bar',
        'returns_by_reason' => 'horizontalBar',
        'returns_by_status' => 'horizontalBar',
        'returns_by_resolution' => 'doughnut',
        'open_return_requests' => 'bar',
        'top_returned_products' => 'horizontalBar',
        'returns_by_condition' => 'doughnut',
        'product_return_rate' => 'horizontalBar',
        'unit_return_rate_trend' => 'line',
        'order_return_rate' => 'line',
        'returns_by_shipping_country' => 'horizontalBar',
    ];
    protected const array CORE_DATASETS = [
        'sales_orders',
        'customer_orders',
        'customers',
        'sales_items',
        'catalog_stock',
        'catalog_salable',
        'carts',
        'cart_items',
    ];

    public function testItKeepsEveryRmaDatasetAfterValidationInTheReturnsArea(): void
    {
        $datasets = $this->loadReportDatasets(self::ETC_FILES);

        foreach (self::RMA_DATASETS as $code) {
            self::assertArrayHasKey($code, $datasets);
            $dataset = $datasets[$code];
            self::assertSame('returns', $dataset->area, $code);
            self::assertSame('MageOS_AdvancedReport::returns', $dataset->aclResource, $code);
            self::assertTrue($dataset->hasField(Dataset::FIELD_CREATED_AT), $code);
            self::assertTrue($dataset->hasField(Dataset::FIELD_STORE_ID), $code);
            self::assertFalse($dataset->dateless, $code);
        }
    }

    public function testItKeepsEveryRmaPresetAfterValidationWithItsChartType(): void
    {
        $presets = $this->loadReportPresets(self::ETC_FILES);
        $rmaPresets = array_filter($presets, static fn (Preset $preset): bool => $preset->area === 'returns');

        self::assertCount(13, $rmaPresets);
        foreach (self::RMA_PRESET_CHARTS as $code => $chartType) {
            self::assertArrayHasKey($code, $presets);
            self::assertSame('returns', $presets[$code]->area, $code);
            self::assertContains($presets[$code]->dataset, self::RMA_DATASETS, $code);
            self::assertSame($chartType, $presets[$code]->resolveChart()['type'], $code);
        }
    }

    public function testItAppliesTheCountedStatusFilterToTheOrderBasedRmaDatasets(): void
    {
        $datasets = $this->loadReportDatasets(self::ETC_FILES);

        foreach (['order_returns', 'order_item_returns'] as $code) {
            self::assertArrayHasKey('counted_status', $datasets[$code]->implicitFilters, $code);
        }
    }

    public function testItKeepsEveryCoreDatasetWhenTheRmaFileIsMergedIn(): void
    {
        $datasets = $this->loadReportDatasets(self::ETC_FILES);

        foreach (self::CORE_DATASETS as $code) {
            self::assertArrayHasKey($code, $datasets);
        }
    }
}

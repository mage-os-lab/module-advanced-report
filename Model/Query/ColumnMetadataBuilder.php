<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Query;

use MageOS\AdvancedReport\Api\Data\ReportRequestInterface;
use MageOS\AdvancedReport\Model\Definition\AggregateFunction;
use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\Dimension;
use MageOS\AdvancedReport\Model\Definition\Formula\Formula;
use MageOS\AdvancedReport\Model\Definition\Formula\FormulaException;
use MageOS\AdvancedReport\Model\Definition\Metric;

/**
 * Builds the column metadata for a report request's dimensions and metrics, in the same
 * order they were requested: dimensions first, then metrics. Labels and descriptions
 * are translated to the current locale (the result cache key includes it). Consumed by
 * {@see ReportResult::toArray()}'s `columns` entry.
 *
 * `nonAdditive` tells whether the values of the rows may not be summed into a meaningful whole: on a dimension, rows
 * can overlap (an item in several categories); on a metric, the value is not a sum (ratios, averages, distinct
 * counts, minimums). Charts use it to offer pie and doughnut only for additive data. `granularity` is set on a date
 * dimension grouped into a time series (day, week...), and null on date parts such as the hour or the weekday.
 */
class ColumnMetadataBuilder
{
    /**
     * @param Dataset $dataset
     * @param ReportRequestInterface $request
     * @return array<int,array{
     *     code: string,
     *     label: string,
     *     type: string,
     *     format: ?string,
     *     description: ?string,
     *     nonAdditive: bool,
     *     lowerIsBetter: bool,
     *     granularity?: ?string
     * }>
     */
    public function build(Dataset $dataset, ReportRequestInterface $request): array
    {
        $columns = [];
        foreach ($request->getDimensions() as $dimensionSelection) {
            $columns[] = $this->buildDimensionColumn(
                $dataset,
                $dataset->getDimension($dimensionSelection->code),
                $dimensionSelection->granularity?->value
            );
        }
        foreach ($request->getMetrics() as $metricCode) {
            $columns[] = $this->buildMetricColumn($dataset, $dataset->getMetric($metricCode));
        }

        return $columns;
    }

    /**
     * @return array{code: string, label: string, type: string, format: ?string, description: ?string, nonAdditive: bool, lowerIsBetter: bool, granularity: ?string}
     */
    private function buildDimensionColumn(Dataset $dataset, Dimension $dimension, ?string $granularity): array
    {
        $field = $dataset->fields[$dimension->fieldCode] ?? null;

        return [
            'code' => $dimension->code,
            'label' => (string) __($dimension->label),
            'type' => 'dimension',
            'format' => $field?->type->value,
            'description' => $this->translate($field?->description),
            'nonAdditive' => $dimension->nonAdditive,
            'lowerIsBetter' => false,
            'granularity' => $granularity,
        ];
    }

    /**
     * @return array{code: string, label: string, type: string, format: ?string, description: ?string, nonAdditive: bool, lowerIsBetter: bool}
     */
    private function buildMetricColumn(Dataset $dataset, Metric $metric): array
    {
        return [
            'code' => $metric->code,
            'label' => (string) __($metric->label),
            'type' => 'metric',
            'format' => $metric->format,
            'description' => $this->translate($metric->description),
            'nonAdditive' => !$this->isAdditive($dataset, $metric, []),
            'lowerIsBetter' => $metric->lowerIsBetter,
        ];
    }

    /**
     * A sum or a count is additive, and so is a formula that only adds and subtracts additive metrics.
     *
     * @param Dataset $dataset
     * @param Metric $metric
     * @param string[] $visiting Metric codes on the current formula path, to stop on cycles.
     */
    private function isAdditive(Dataset $dataset, Metric $metric, array $visiting): bool
    {
        if ($metric->isVirtual()) {
            return false;
        }
        if ($metric->isAggregate()) {
            return \in_array($metric->aggregate, [AggregateFunction::SUM, AggregateFunction::COUNT], true);
        }
        if ($metric->formula === null || \in_array($metric->code, $visiting, true)) {
            return false;
        }
        try {
            $formula = Formula::fromString($metric->formula);
        } catch (FormulaException) {
            return false;
        }
        if (!$formula->isLinear()) {
            return false;
        }
        $visiting[] = $metric->code;
        foreach ($formula->getReferences() as $reference) {
            $referenced = $dataset->metrics[$reference] ?? null;
            if ($referenced === null || !$this->isAdditive($dataset, $referenced, $visiting)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string|null $text
     * @return string|null
     */
    private function translate(?string $text): ?string
    {
        return $text !== null && $text !== '' ? (string) __($text) : $text;
    }
}

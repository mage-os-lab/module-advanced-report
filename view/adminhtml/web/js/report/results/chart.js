/**
 * Chart child of the exploration app. Draws with the shared chart configuration: the preset chart definition while
 * the report keeps its default dimensions, otherwise the first dimension and metric. The type can be changed from the
 * chart card for the current page, among the types that fit the data (chartConfig.allowedTypes): a choice that no
 * longer fits, after a change of dimension or metric, falls back to the default type.
 */
define([
    'jquery',
    'ko',
    'mage/translate',
    'chartjs/Chart.min',
    'MageOS_AdvancedReport/js/report/lib/api',
    'MageOS_AdvancedReport/js/report/lib/chart-config',
    'text!MageOS_AdvancedReport/template/report/results/chart.html'
], function ($, ko, $t, Chart, api, chartConfig, template) {
    'use strict';

    const DATE_SERIES_LIMIT = 366;

    /**
     * @param {Array} dimensions
     * @return {String}
     */
    function dimensionKeys(dimensions) {
        return (dimensions || []).map(function (dimension) {
            return dimension.code + '|' + (dimension.granularity || '');
        }).join(',');
    }

    ko.bindingHandlers.advancedreportChart = {
        /**
         * @param {HTMLElement} element
         * @param {Function} valueAccessor
         */
        update: function (element, valueAccessor) {
            const config = ko.unwrap(valueAccessor());

            if (element.chart) {
                element.chart.destroy();
                element.chart = null;
            }

            if (config) {
                element.chart = new Chart(element.getContext('2d'), config);
            }
        }
    };

    /**
     * @param {Object} app
     * @return {Object}
     */
    return function (app) {
        const preset = app.config.chart,
            allTypeOptions = [
                {value: 'line', label: $t('Line')},
                {value: 'bar', label: $t('Vertical bars')},
                {value: 'horizontalBar', label: $t('Horizontal bars')},
                {value: 'doughnut', label: $t('Doughnut')},
                {value: 'pie', label: $t('Pie')}
            ],
            selectedType = ko.observable(null),
            groupIndex = ko.observable(0),
            ownResult = ko.observable(null),
            usesPresetChart = ko.pureComputed(function () {
                return !!preset && dimensionKeys(app.state().dimensions) === dimensionKeys(preset.defaultDimensions);
            }),
            needsOwnRequest = ko.pureComputed(function () {
                return usesPresetChart() &&
                    dimensionKeys(preset.dimensions) !== dimensionKeys(preset.defaultDimensions);
            }),
            source = ko.pureComputed(function () {
                return needsOwnRequest() ? ownResult() : app.result();
            }),
            drawn = ko.pureComputed(function () {
                const result = source();
                let metrics,
                    wanted;

                if (!result) {
                    return null;
                }
                metrics = result.columns.filter(function (column) {
                    return column.type === 'metric';
                });
                wanted = usesPresetChart() ? metrics.filter(function (column) {
                    return preset.metrics.indexOf(column.code) !== -1;
                }) : [];

                if (!wanted.length) {
                    wanted = metrics.slice(0, 1);
                }

                return $.extend({}, result, {
                    columns: result.columns.filter(function (column) {
                        return column.type !== 'metric' || wanted.indexOf(column) !== -1;
                    })
                });
            }),
            groups = ko.pureComputed(function () {
                return chartConfig.groupMetrics(drawn());
            }),
            activeIndex = ko.pureComputed(function () {
                return groups()[groupIndex()] ? groupIndex() : 0;
            }),
            allowedTypes = ko.pureComputed(function () {
                const result = drawn(),
                    group = groups()[activeIndex()];

                return result && group ? chartConfig.allowedTypes(result, group) : [];
            }),
            defaultType = ko.pureComputed(function () {
                const allowed = allowedTypes();

                if (usesPresetChart() && allowed.indexOf(preset.type) !== -1) {
                    return preset.type;
                }

                return allowed[0] || null;
            }),
            type = ko.pureComputed({
                read: function () {
                    const selected = selectedType();

                    return selected && allowedTypes().indexOf(selected) !== -1 ? selected : defaultType();
                },
                write: selectedType
            }),
            typeOptions = ko.pureComputed(function () {
                const allowed = allowedTypes();

                return allTypeOptions.filter(function (option) {
                    return allowed.indexOf(option.value) !== -1;
                });
            }),
            config = ko.pureComputed(function () {
                const result = drawn(),
                    group = groups()[activeIndex()];

                return result && group && type() ? chartConfig.build({type: type()}, result, group, app.locale) : null;
            });
        let inflight = null;

        function loadOwnResult() {
            const hasDates = preset.dimensions.some(function (dimension) {
                    return !!dimension.granularity;
                }),
                request = $.extend({}, app.state(), {
                    dimensions: preset.dimensions,
                    metrics: preset.metrics,
                    sort: preset.sort,
                    limit: hasDates ? DATE_SERIES_LIMIT : app.pageSize,
                    offset: 0
                });
            let call;

            if (inflight) {
                inflight.xhr.abort();
            }
            delete request.bypassCache;
            call = api.fetch(app.config.urls.data, request);
            inflight = call;
            call.promise.done(function (result) {
                ownResult(result);
            }).fail(function (failure) {
                if (!failure.aborted) {
                    ownResult(null);
                    app.notify($t(failure.message), 'error');
                }
            }).always(function () {
                if (inflight === call) {
                    inflight = null;
                }
            });
        }

        app.result.subscribe(function () {
            if (needsOwnRequest()) {
                loadOwnResult();
            }
        });

        return {
            html: template,
            type: type,
            typeOptions: typeOptions,
            labels: {chartType: $t('Chart type')},
            groups: groups,
            groupIndex: groupIndex,
            activeIndex: activeIndex,
            config: config,
            hasChart: ko.pureComputed(function () {
                return !!config();
            })
        };
    };
});

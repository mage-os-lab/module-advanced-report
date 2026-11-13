# Architecture

Reports are not hard-coded queries. Every report is a combination of a few building blocks declared in
`etc/report_datasets.xml` (the semantic layer); the module turns the combination chosen in the UI into SQL.
Everything is computed live from the native Magento tables: there are no aggregation tables and no cron.

## Building blocks

| Concept | What it is | Example |
|---|---|---|
| **Dataset** | A reportable entity with one row per record: its base table (or a base select provider), the ACL resource that protects it, the area it belongs to (`sales`, `catalog`, `customers`, `carts`, `marketing`, `returns`) and the implicit filters always applied to it. | `sales_orders` (one row per order), `sales_items` (one row per top-level order item), `customer_orders`, `customers`, `catalog_stock`, `catalog_salable` (MSI only), `carts`, `cart_items`, `rma_requests`, `rma_items`, `order_returns`, `order_item_returns` (`MageOS_RMA` only). |
| **Field** | A named, typed SQL expression on the dataset rows (`money`, `integer`, `decimal`, `boolean`, `string`, `id`, `date`). It may need one or more joins. Every field can be used as a filter. | `gross_sales_amount` = subtotal minus canceled subtotal; `billing_country` = billing address country (needs the `billing_address` join). |
| **Join** | A table joined to the dataset, declared once and added to the query only when a selected field needs it. A join can `require` another join. | `payment`, `billing_address`, `store`. |
| **Metric** | A number to measure. Either an **aggregate** (`sum`, `count`, `count_distinct`, `min`, `max`, `avg`) of a field, optionally restricted to the rows where a boolean field is true (`condition`), or a **formula** over other metrics (`+ - * /`, parentheses, numbers). | `orders` = `COUNT(order_id)`; `net_sales` = `gross_sales - discounts - returns`; `aov` = `net_sales / orders`. |
| **Dimension** | A field to group by. Date dimensions support granularities (hour, day, week, month, year); a label resolver turns raw ids/codes into readable labels; `nonAdditive` marks dimensions whose rows do not sum up to the total. | `order_date` by day, `payment_method`, `customer_group_id`, `category_id` (non-additive). |
| **Preset** | A ready-made report: dataset, metrics, dimensions, filters, sort, default date range and comparison, default chart. A preset may delegate the whole computation to its own query class (`presetQuery`) for logic that is not a plain aggregation. | `sales_overview`, `product_performance`, `low_stock_coverage`. |

Reserved fields: every dated dataset declares `created_at` (date filtering and date buckets), `store_id` (store
scope) and, when it applies the `counted_status` implicit filter, `status` (counted order statuses). Metrics
flagged `hidden` are helper inputs of formulas and are not offered in the metric picker; metrics flagged `virtual`
have no SQL at all and are produced only by a preset query.

The return datasets live in a separate file, `etc/report_datasets_rma.xml`, read by
`Model\Rma\Config\RmaDefinitionSource` (a `DefinitionRegistry` definition source) only while `MageOS_RMA` is enabled.
With the module disabled or absent no return dataset, preset, report area or dashboard chart exists, and saved reports
on these datasets are hidden until the module is enabled again. No `MageOS\RMA` PHP class is used: the reports read the
RMA tables directly, through the report connection.

## From a click to a result

1. The exploration UI (a preset, a saved report or a free selection) sends a report request to the `report/data`
   endpoint: dataset, metrics, dimensions with granularity, filters, sort, period, comparison, stores and page.
2. The endpoint checks the ACL resource of the dataset; the request validator checks every code against the
   semantic layer (see [security](security.md)).
3. The query planner resolves the period in the website timezone (and the comparison period), and expands each
   formula into the aggregates it needs, so `aov` alone fetches `gross_sales`, `discounts`, `returns` and `orders`.
4. The result cache is checked (see [cache](cache.md)).
5. The source resolver picks a data source able to serve every requested field (MySQL today), or the preset
   query class when the preset declares one.
6. The MySQL data source builds the select: base table, only the joins needed, implicit filters, date and store
   scope, user filters (always as bound parameters), `GROUP BY` the dimensions and the aggregates. It runs the
   page query, a totals query and a row count, under the configured timeout and row cap (see
   [performance](performance.md#safeguards)).
7. The result is post-processed: missing date buckets (and missing hours/weekdays) are filled with empty rows,
   formulas are computed on every row and on the totals, the comparison period is merged with deltas, raw
   dimension values are translated by their label resolvers and warnings are collected.
8. The result is cached and returned to the UI, which renders the chart and the table.

Because formulas are computed after aggregation, ratios such as `aov` or `margin_pct` are always correct on totals
and in comparisons (they are never averages of averages).

## Charts

Each preset declares its default chart (`<chart>`, see [extending](extending.md#attribute-reference)). While a report
keeps the default dimensions of its preset, the chart follows `<chart>`; when `<chart>` declares other dimensions, the
page runs one additional report for the chart. After changing the dimensions, the chart draws the first dimension and
the first metric. The type selected on the chart card lasts for the current page: it is not stored in saved reports nor
in the URL.

## Roadmap

An OpenSearch data source (`Magento_OpenSearch`) behind the same `DataSourceInterface` is planned: datasets already
declare their sources per engine, so presets and the UI will not change.

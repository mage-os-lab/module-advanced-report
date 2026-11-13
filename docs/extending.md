# Extending the semantic layer

Any module can add fields, joins, metrics, dimensions, presets or whole new datasets without touching this module.
The reports, the pickers, the filters, the CSV export, the cache and `advanced-report:explain` pick up the new
definitions automatically.

## How the merge works

Every module may ship its own `etc/report_datasets.xml`. Magento reads them all and merges them by `code`:

- a `<dataset code="sales_orders">` in your file is the **same** dataset as the core one, so you only declare the
  elements you add (no need to repeat `label`, `acl`, `area` or `<source>`);
- an element with an existing `code` (e.g. `<metric code="aov">`) **overrides** the attributes you set on it;
- an element with a new `code` is **added**.

The per-file schema (`etc/report_datasets_file.xsd`) only requires the `code` attributes; the merged result is
validated against `etc/report_datasets.xsd` and by `DefinitionValidator`. In developer mode an invalid definition
throws an exception listing the errors; in production it is dropped and logged (an invalid field or metric
drops only that element, an invalid dataset drops the dataset and its presets), so a broken extension never takes
down the other reports.

The return datasets (`rma_requests`, `rma_items`, `order_returns`, `order_item_returns`) are not in
`report_datasets.xml`: they live in `etc/report_datasets_rma.xml`, a separate file (same format and schemas, cached
as `advanced_report_datasets_rma`) read only while `MageOS_RMA` is enabled. A module extends them, or adds presets on
them, in its own `etc/report_datasets_rma.xml`, merged by `code` in the same way. Do not reference them from
`report_datasets.xml`: there they do not exist while `MageOS_RMA` is disabled, and while it is enabled the RMA
definition of a dataset replaces the one of `report_datasets.xml` as a whole.

## Step by step

The example adds a "sales channel" to the `sales_orders` dataset, assuming your module `Acme_Channel` added a
column `sales_order.acme_channel_id` that points to a table `acme_channel (channel_id, name, fee_rate)`.

**1. Depend on this module** so your file is merged after the core one, in `Acme/Channel/etc/module.xml`:

```xml
<module name="Acme_Channel">
    <sequence>
        <module name="MageOS_AdvancedReport"/>
    </sequence>
</module>
```

**2. Declare the definitions** in `Acme/Channel/etc/report_datasets.xml` (no schema location is needed):

```xml
<?xml version="1.0"?>
<config>
    <dataset code="sales_orders">
        <!-- Join: table, alias, type (inner|left) and the ON condition. "main" is the dataset base alias.
             Added to the query only when a selected field lists it in "joins". -->
        <join code="acme_channel" table="acme_channel" alias="acme_channel" type="left"
              condition="acme_channel.channel_id = main.acme_channel_id"/>

        <!-- Fields: a typed SQL expression for the mysql source. Every field is also a filter. -->
        <field code="acme_channel_id" label="Sales channel" type="id">
            <mysql expression="main.acme_channel_id"/>
        </field>
        <field code="acme_channel_fee" label="Channel fee" type="money"
               description="Marketplace fee charged on the order.">
            <mysql expression="(COALESCE(main.base_subtotal, 0) * COALESCE(acme_channel.fee_rate, 0))"
                   joins="acme_channel"/>
        </field>
        <field code="is_marketplace" label="Is marketplace order" type="boolean">
            <mysql expression="(main.acme_channel_id IS NOT NULL)"/>
        </field>

        <!-- Metrics: an aggregate over a field (never SQL), optionally only where a boolean field is true... -->
        <metric code="channel_fees" label="Channel fees" format="money" aggregate="sum"
                field="acme_channel_fee" lowerIsBetter="true"
                description="Marketplace fees charged on the orders."/>
        <metric code="marketplace_orders" label="Marketplace orders" format="integer" aggregate="count"
                field="order_id" condition="is_marketplace"/>
        <!-- ...or a formula over other metric codes, including the core ones. -->
        <metric code="net_sales_after_fees" label="Net sales after fees" format="money"
                formula="net_sales - channel_fees"
                description="Net sales minus marketplace fees."/>
        <metric code="marketplace_share" label="Marketplace share" format="percent"
                formula="marketplace_orders / orders"/>

        <!-- Dimension: group by a field; the label resolver turns ids into names. -->
        <dimension code="acme_channel" label="Sales channel" field="acme_channel_id"
                   labelResolver="Acme\Channel\Model\Label\ChannelLabelResolver"/>
    </dataset>

    <!-- Preset: a ready-made report listed in the report page. -->
    <preset code="acme_sales_by_channel" area="sales" dataset="sales_orders" label="Sales by channel"
            description="Orders, net sales and marketplace fees for each sales channel."
            dateRange="last_30_days" compareTo="previous_period" sources="mysql">
        <metric code="orders"/>
        <metric code="net_sales"/>
        <metric code="channel_fees"/>
        <metric code="net_sales_after_fees"/>
        <dimension code="acme_channel"/>
        <filter field="is_marketplace" operator="eq" value="1"/>
        <sort by="net_sales" direction="desc"/>
        <!-- Default chart of the report, also used by MageOS_DashboardChart. -->
        <chart type="doughnut">
            <metric code="net_sales"/>
        </chart>
    </preset>
</config>
```

### Attribute reference

| Element | Attributes |
|---|---|
| `dataset` | `code`; for a new dataset also `label`, `acl` (ACL resource checked by the data endpoint), `area` (`sales`, `catalog`, `customers`, `carts`, `marketing`, `returns`), optional `dateless="true"` for snapshot datasets without `created_at`. Children: `<source name="mysql" table="..." alias="main"/>` (or `provider="..."`), `<implicitFilter code="counted_status"/>`, joins, fields, metrics, dimensions. |
| `join` | `code`, `table`, `alias`, `condition`, optional `type` (`left` default, `inner`) and `requires` (comma-separated join codes this join depends on) and `cardinality` (`one` default, `many` for one-to-many joins that can multiply rows and inflate aggregates). |
| `field` | `code`, `label`, `type` (`money`, `integer`, `decimal`, `boolean`, `string`, `id`, `date`), optional `description`. Child `<mysql expression="..." joins="a,b"/>` or `<mysql expressionProvider="name"/>`. |
| `metric` | `code`, `label`, `format` (`money`, `number`, `percent`, `integer`), `description`, `lowerIsBetter` (colours the comparison delta), `hidden` (formula helper not shown in the picker). Either `aggregate` (`sum`, `count`, `count_distinct`, `min`, `max`, `avg`) + `field` + optional `condition` (a boolean field), or `formula`, or `virtual="true"` (value supplied by a preset query). |
| `dimension` | `code`, `label`, `field`, optional `labelResolver` (class name), `granularities` (for date fields: `hour,day,week,month,year`), `nonAdditive="true"` (rows may not sum up to the total; a warning is shown). |
| `preset` | `code`, `area`, `dataset`, `label`, optional `description`, `dateRange` (`today`, `last_7_days`, `last_30_days`, `month_to_date`, `last_month`, `year_to_date`), `compareTo` (`none`, `previous_period`, `previous_year`), `sources`, `presetQuery`, `alternateDimension`. Children: `<metric code=""/>`, `<dimension code="" granularity=""/>`, `<filter field="" operator="eq\|neq\|in\|nin\|gte\|lte\|between" value=""/>`, `<sort by="" direction="asc\|desc"/>`, `<chart/>`. |
| `chart` (inside `preset`) | Optional `type` (`line`, `bar`, `horizontalBar`, `doughnut`, `pie`), `sortBy` (a metric or dimension of the chart), `sortDirection` (`asc`, `desc`). Children: `<metric code=""/>`, `<dimension code="" granularity=""/>`. Without it the chart uses the first dimension of the preset, its sort metric (or first metric), a line for a date dimension and horizontal bars otherwise. Metrics of different formats get a switcher (never two value axes); doughnut and pie draw one metric and fold the slices past the seventh into "Other". |


**3. Add a label resolver** when the dimension stores ids or codes. Extend
`MageOS\AdvancedReport\Model\Label\AbstractLabelResolver` (it labels empty values "(none)" and keeps unknown values
raw) or implement `MageOS\AdvancedReport\Api\LabelResolverInterface` directly. It receives all the values of the
result at once: load them with a single query.

```php
<?php

declare(strict_types=1);

namespace Acme\Channel\Model\Label;

use Magento\Framework\App\ResourceConnection;
use MageOS\AdvancedReport\Model\Label\AbstractLabelResolver;

class ChannelLabelResolver extends AbstractLabelResolver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
    ) {
    }

    protected function loadLabels(array $values, array $context): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('acme_channel'), ['channel_id', 'name'])
            ->where('channel_id IN (?)', $values);

        return $connection->fetchPairs($select);
    }
}
```

Register it in the resolver pool, keyed by its class name, in `Acme/Channel/etc/di.xml`:

```xml
<type name="MageOS\AdvancedReport\Model\Label\LabelResolverPool">
    <arguments>
        <argument name="resolvers" xsi:type="array">
            <item name="Acme\Channel\Model\Label\ChannelLabelResolver" xsi:type="object">Acme\Channel\Model\Label\ChannelLabelResolver</item>
        </argument>
    </arguments>
</type>
```

**4. Translate** every `label` and `description` in your module `i18n/*.csv` files: they are rendered through `__()`.

**5. Refresh and verify**:

```bash
bin/magento cache:clean config advanced_report
bin/magento advanced-report:explain --preset=acme_sales_by_channel --from=2026-01-01 --to=2026-01-31
```

The definitions are cached in the `config` cache type; the cache key of the results includes a hash of the
definitions, so cached results of the old definitions are never served. Check that the `EXPLAIN` output of your
preset uses indexes on large tables (see [`advanced-report:explain`](cli.md#advanced-reportexplain) and [indexes](indexes.md)).

## Advanced extension points

When XML is not enough, register a class in `di.xml`:

| Need | Implement | Register in |
|---|---|---|
| A field whose SQL depends on runtime state (correlated subqueries, configuration) | `MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface`, referenced by `<mysql expressionProvider="name"/>` | `FieldExpressionResolver` argument `providers`, keyed by `name` (see `FirstShipmentWaitExpression`) |
| A dataset whose base is not a single table (e.g. a union or a derived select) | `MageOS\AdvancedReport\Model\DataSource\Mysql\BaseSelectProviderInterface`, referenced by `<source name="mysql" provider="Class"/>` | `SelectBuilder` argument `baseSelectProviders`, keyed by class name (see `StockSelectProvider`) |
| A report that is not a single aggregation (anti-joins, rolling windows, two-step queries) | `MageOS\AdvancedReport\Api\PresetQueryInterface`, referenced by `<preset presetQuery="Class">`; may fill `virtual` metrics | `PresetQueryPool` argument `presetQueries`, keyed by class name (see `LowStockCoverageQuery`) |
| Definitions generated from data or configuration (e.g. one dimension per product attribute) | `MageOS\AdvancedReport\Model\Definition\DatasetExtenderInterface` or `PresetExtenderInterface` | `DefinitionRegistry` arguments `datasetExtenders` / `presetExtenders` (see `AttributeJoinProvider`) |
| Whole datasets and presets loaded only under a condition (e.g. another module enabled) | `MageOS\AdvancedReport\Model\Definition\DefinitionSourceInterface`, returning the converted `['datasets' => [...], 'presets' => [...]]` array (merged over `report_datasets.xml` by code, one level deep) | `DefinitionRegistry` argument `definitionSources` (see `RmaDefinitionSource`) |

Table names inside an expression provider or a preset query must be resolved with
`MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection::getTableName()`. The adapter's own
`getTableName()` never applies the `db/table_prefix`, and a unit test forbids it in module code.

```php
final class ShippedOrderCountExpression implements FieldExpressionProviderInterface
{
    public function __construct(private readonly ReportConnection $reportConnection)
    {
    }

    public function getExpression(Dataset $dataset, AdapterInterface $connection): string
    {
        // prefix-aware, unlike $adapter->getTableName()
        $shipments = $this->reportConnection->getTableName('sales_shipment');

        return '(SELECT COUNT(*) FROM ' . $shipments . ' AS s WHERE s.order_id = main.entity_id)';
    }
}
```

## Rules

- Metrics never contain SQL: SQL lives only in field expressions and joins, which come from XML or PHP, never from
  user input. All user values (filters, dates, stores) are bound as query parameters.
- `created_at`, `store_id` and `status` are reserved field codes: they drive date filtering and buckets, the store
  scope and the counted order statuses. A new dated dataset must declare `created_at` and `store_id`.
- Use `main.` (or the alias of your `<source>`) and the join aliases in expressions; always wrap nullable columns in
  `COALESCE` so sums and formulas do not turn into `NULL`.
- `count_distinct` and `avg` metrics cannot be re-aggregated: in a non-UTC store timezone without database timezone
  tables they come back empty when grouped by order date (see [time and currency](time-and-currency.md#fallback-without-database-timezone-tables)).
- Prefix your codes (e.g. `acme_`) to avoid clashes with future core definitions.


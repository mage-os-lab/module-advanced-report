# MageOS AdvancedReport

Advanced sales, catalog and customer reporting for Magento Open Source / Mage-OS. It adds an admin
exploration app (Reports > Advanced Report) on top of a declarative semantic layer: datasets, fields,
metrics and dimensions are declared in XML and translated to SQL by a MySQL data source.

## Features

- Predefined reports (sales overview, sales by payment/shipping method/country/customer group/hour/weekday,
  new vs returning customers, coupon performance, product performance, sales by category/attribute,
  customer value, new customer registrations, stock value, low stock with coverage, dead stock, never sold,
  recent carts and cart items, abandoned carts (trend, list, top products), products added to cart, cart conversion,
  and, only while `MageOS_RMA` is enabled, returns: overview, withdrawals before shipment vs returns after shipment,
  returns by reason/status/resolution/item condition, open requests, top returned products, product and order return
  rates, return rate by shipping country).
- Free exploration: pick metrics, group by dimensions, filter, compare to the previous period/year,
  chart and table, CSV export.
- A `marketing` area (ACL `MageOS_AdvancedReport::marketing`) that stays empty until a source module such as
  `MageOS_GoogleAdvancedReport` (Google Analytics 4 import) contributes datasets and presets to it.
- Saved reports (private or shared with other admins) stored in `mageos_advancedreport_saved`.
- Everything is computed live from the native Magento tables (no aggregation tables, no cron).

See [architecture](docs/architecture.md) for how reports are built from the semantic layer.

## Requirements

- Magento Open Source 2.4.7+ or Mage-OS, PHP 8.3+ (CI matrix and status: [compatibility](docs/compatibility.md)).
- Database: MySQL 8.4 or MariaDB 11.4 (tested in the CI integration matrix); MySQL 8.0 and MariaDB 10.6 are
  declared, not tested. The report guardrail uses `MAX_EXECUTION_TIME` on MySQL and
  `max_statement_time` on MariaDB.
- The `advanced_report` cache type must be enabled for result caching (reports still work with it disabled, uncached).
- A `db/table_prefix` in `app/etc/env.php` is supported: every table the module reads is resolved through the report connection (CI runs the integration suite once with prefix `pre_`). Custom code that adds SQL must do the same, see [extending](docs/extending.md#advanced-extension-points).
- `Magento_Reports` (hard dependency: the admin menu entry is a child of the Reports menu).
- Recommended: MySQL/MariaDB timezone tables (`mysql_tzinfo_to_sql`) when stores use a non-UTC timezone, see
  [time and currency](docs/time-and-currency.md#fallback-without-database-timezone-tables).
- Optional: Magento MSI (`Magento_InventoryApi`) for multi-source stock in the `catalog_stock` dataset; the legacy
  `cataloginventory_stock_item` table is used otherwise.

## Installation

```bash
composer require mage-os/module-advanced-report
bin/magento module:enable MageOS_AdvancedReport
bin/magento setup:upgrade
bin/magento cache:flush
```

`setup:upgrade` creates the `mageos_advancedreport_saved` table and adds the indexes the reports need on `sales_order`,
`sales_order_item` and `quote`. The index build is online but costs I/O and replica lag on large tables: read
[indexes](docs/indexes.md#installation-time-and-maintenance-window) before installing on a large store.

Then configure the module in Stores > Configuration > Advanced Report (see [configuration](docs/configuration.md)) and
open Reports > Advanced Report. Before upgrading an existing installation read [upgrading](docs/upgrading.md).

## Documentation

- [Architecture](docs/architecture.md): building blocks of the semantic layer, request flow, charts, roadmap.
- [Configuration](docs/configuration.md): every setting with its default.
- [Upgrading](docs/upgrading.md): steps after every upgrade, changes in this release, disabling the module.
- [Metric definitions](docs/metric-definitions.md): business definition of every metric (formula, cancel/refund treatment, currency, date attribution, additivity, known limits) with the integration tests that prove it, customer dimensions, return presets, and physical vs reserved vs salable quantities.
- [Metric inventory](docs/metric-inventory.md): generated list of every metric, its joins, fan-out risk and dependencies.
- [Time and currency](docs/time-and-currency.md): timezone used for filtering, grouping, comparison and display, DST, the fallback without timezone tables, the currency of every financial metric, rejection rules and rounding.
- [Extending](docs/extending.md): adding datasets, fields, metrics, dimensions and presets from another module; PHP extension points.
- [Console commands](docs/cli.md): `advanced-report:explain`, `advanced-report:benchmark`, `advanced-report:inventory`.
- [Performance](docs/performance.md): statements per report, safeguards, benchmark procedure and results table, bottlenecks, async evaluation.
- [Cache](docs/cache.md): cache key, TTLs, invalidation, label staleness, failures, stampede lock and freshness per report category.
- [Indexes](docs/indexes.md): rationale of every index, removable indexes, installation time and maintenance window.
- [Security](docs/security.md): ACL model per entry point and dataset, data classification, field level ACL, cache and logging.
- [Risk register](docs/risk-register.md): known limits and risks with severity and owner.
- [Compatibility](docs/compatibility.md): supported Magento, PHP and database versions and the CI job enforcing each.
- [Testing](docs/testing.md): local quality and integration commands, CI jobs and the database matrix.

## Contributing

Issues and pull requests are welcome on GitHub. Please follow the Magento coding standard, keep the semantic layer
rules described in [extending](docs/extending.md#rules) (no SQL in metrics, bound parameters only) and include unit
tests for new classes.

## License

This module is licensed under the [MIT License](LICENSE).

## Support

For bugs or feature requests, please open an issue in the GitHub repository.

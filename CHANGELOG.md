# Changelog

## [Unreleased]

### Added
- **Marketing area**: new `marketing` dataset area and ACL resource `MageOS_AdvancedReport::marketing`, used by add-on modules such as `MageOS_GoogleAdvancedReport` (Google Analytics 4 import)

## [1.0.0] - 2026-11-04

### Added
- **Exploration app** (Reports > Advanced Report): pick metrics, group by dimensions, filter, compare to the previous period or year, chart and table, CSV export. Everything is computed live from the native Magento tables, with no aggregation tables and no cron
- **Semantic layer**: datasets, fields, joins, metrics, dimensions and presets declared in `etc/report_datasets.xml`, merged across modules by `code`, validated against `etc/report_datasets.xsd` and by `DefinitionValidator`, and translated to SQL by a MySQL data source
- **Datasets**: `sales_orders`, `customer_orders`, `customers`, `sales_items`, `catalog_stock`, `catalog_salable` (MSI only), `carts`, `cart_items`
- **25 predefined reports**:
  - Sales: overview, by payment method, shipping method, shipping country, customer group, hour and weekday, new vs returning customers, coupon performance
  - Catalog: product performance, sales by category and attribute, stock value, low stock with coverage, dead stock, never sold
  - Customers: customer value, new customer registrations
  - Carts: recent carts and cart items, abandoned carts (trend, list, top products), products added to cart, cart conversion
- **Charts**: every preset declares its default chart with `<chart>` (`line`, `bar`, `horizontalBar`, `doughnut`, `pie`); the type can be changed from the chart card for the current page, and metrics of different formats get a switcher instead of a second value axis
- **Saved reports**: private or shared with other admins, stored in `mageos_advancedreport_saved`
- **Permissions**: a dataset needs its module ACL and the core ACL of its entity, with field level ACL on birth date and gender; separate resources for saved reports, sharing, export and configuration
- **Result cache**: `advanced_report` cache type, with separate lifetimes for open and closed periods
- **Guardrails**: timeout on every report statement, `max_rows` cap with a warning, read replica support through `performance/connection_name`, `db/table_prefix` support
- **Indexes** on `sales_order`, `sales_order_item` and `quote` for the reports
- **Console commands**: `advanced-report:explain`, `advanced-report:benchmark`, `advanced-report:inventory`
- **Configuration** under Stores > Configuration > Advanced Report: counted order statuses, dead stock days, report attributes, abandoned and recent cart windows, performance settings
- **Extension points**: field expression providers, base select providers, preset queries, dataset and preset extenders, label resolvers
- **Translations**: `en_US` and `it_IT`

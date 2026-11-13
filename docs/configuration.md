# Configuration

Stores > Configuration > Advanced Report > Configuration (default scope only).

| Path | Default | Description |
|---|---|---|
| `mageos_advancedreport/general/enabled` | `1` | Disables the admin menu and the controllers when set to No. |
| `mageos_advancedreport/sales/counted_statuses` | empty | Order statuses counted as sales. Empty means every status except `canceled`. |
| `mageos_advancedreport/catalog/dead_stock_days` | `90` | Days without sales after which in-stock products are flagged as dead stock. |
| `mageos_advancedreport/catalog/report_attributes` | empty | Extra product attributes exposed as `attr_<code>` dimensions of the `sales_items` dataset. |
| `mageos_advancedreport/carts/abandoned_after_hours` | `24` | Hours without activity after which an active cart with items and an email is abandoned. |
| `mageos_advancedreport/carts/recent_cart_hours` | `24` | Window of the recent cart presets, 1 to 120 hours (the presets use `last_7_days`, so a longer window would be cut off). |
| `mageos_advancedreport/performance/connection_name` | `default` | Database connection (from `app/etc/env.php`) used to run report queries. Point it to a read-only replica to keep report queries away from the checkout database. |
| `mageos_advancedreport/performance/max_rows` | `10000` | Hard cap on the rows a single query may return; results above the cap are truncated with a warning. |
| `mageos_advancedreport/performance/query_timeout_ms` | `30000` | Maximum execution time of a report query. |
| `mageos_advancedreport/performance/cache_ttl_open_period` | `300` | Cache lifetime (seconds) for periods that can still change. |
| `mageos_advancedreport/performance/cache_ttl_closed_period` | `86400` | Cache lifetime (seconds) for closed periods. |

Saving the configuration section cleans the `advanced_report` cache type (see [cache](cache.md)).

The report view shows a line with the order statuses currently counted (store-scoped labels when a single store is
selected) and a link to this configuration.

Core settings read by the module:

- `general/locale/timezone` and `general/locale/firstday`: timezone of the date filters and first day of the week, see
  [time and currency](time-and-currency.md).
- `checkout/cart/delete_quote_after` (`Magento_Checkout`): quote lifetime used by the cart retention warning, see
  [metric definitions](metric-definitions.md#carts-and-cart_items-carts).

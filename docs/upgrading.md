# Upgrading

## Every upgrade

- Run `bin/magento setup:upgrade`: it creates or updates the `mageos_advancedreport_saved` table and the module indexes
  on core tables. On a store with a large `sales_order` table run it outside peak traffic, see
  [indexes](indexes.md#installation-time-and-maintenance-window) (risk R42).
- Regenerate the generated code: `bin/magento setup:di:compile` in production mode, clear `generated/code` in developer
  mode. Constructors change between releases (for example `ReportConnection` gained `getTableName()` and the controller
  constructors changed), so stale generated proxies and factories fail (risk R33).
- Flush the result cache: `bin/magento cache:clean advanced_report`. The cache key covers the XML definitions, but not
  SQL that lives in PHP providers or `di.xml` (cart email fallback, dynamic bundle rollup, first order expression, credit
  memo states, timezone fallback), so stale results would be served until their TTL (see [cache](cache.md)).

## Changes in this release

- **Metric meaning**: discounts are now net of the discount tax compensation, item amounts are net of the canceled
  quantity, dynamic price bundles roll up their children, guest carts use the billing address email, and an ordered cart
  is never abandoned or recent. See [metric definitions](metric-definitions.md).
- **ACL**: roles that only have a module ACL (for example `MageOS_AdvancedReport::sales`) lose the datasets until the
  matching core ACL is granted (risk R29). Birth and gender fields of the order datasets now need
  `Magento_Sales::actions_view` too. See [security](security.md#dataset-access).
- **Mixed base currencies**: the former "mixed base currencies" warning was removed; reports that used to show it are now
  rejected with HTTP 400. See [time and currency](time-and-currency.md#rejection-of-mixed-base-currencies).
- **Cart retention warning**: cart reports warn when the date range reaches before the oldest surviving quote, see
  [metric definitions](metric-definitions.md#carts-and-cart_items-carts).

## Disabling or removing the module

Disabling the module and running `setup:upgrade` drops the `mageos_advancedreport_saved` table (saved reports are lost)
and the module indexes on core tables, because they are declared in `etc/db_schema.xml`. Back up the table first, see
[indexes](indexes.md#disabling-the-module).

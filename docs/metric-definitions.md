# Metric definitions

Single source of truth for the semantics of every business-critical metric of the semantic layer
(`etc/report_datasets.xml`). Each metric lists its formula, how cancellations and refunds are treated, additivity and
null/zero behaviour, the known limits (risk register IDs, see [risk register](risk-register.md)) and the integration
tests that prove it with hand-computed values. The generated [metric inventory](metric-inventory.md) lists the raw SQL
expressions; this document explains the business meaning.

Test references use the form `Folder/ClassTest::method` and are relative to `Test/Integration/`
(for example `Sales/RefundsTest::testItSubtractsRefundedShippingFromShipping` is
`Test/Integration/Sales/RefundsTest.php`). Every metric has at least one test asserting a hand-computed value (risks R03 and R24 are closed). Tests marked
**execution only** (if any remain) run the metric without asserting its value.

## Conventions shared by all datasets

- **Currency**: every amount is in the **base currency of the order's store** (`base_*` columns). No conversion is
  applied; monetary reports over several base currencies are rejected. See
  [time and currency](time-and-currency.md#currency).
- **Counted statuses**: the order datasets (`sales_orders`, `customer_orders`, `sales_items`, and with MageOS_RMA
  `order_returns` and `order_item_returns`) apply the implicit
  `counted_status` filter. Config `mageos_advancedreport/sales/counted_statuses` is a comma-separated list of order
  status codes; empty (default) means every status except `canceled`. Pinned per test method with `#[Config]`.
  Counting `canceled` is possible: the order still counts in `orders`, but its monetary and unit components are
  netted to 0 by the cancel-aware formulas below.
- **Date attribution**: the report date is the order `created_at`, converted to the store timezone before bucketing
  (rules in [time and currency](time-and-currency.md#timezone)). Refunds, shipments and credit memos are attributed to the **order date**, not their own
  date (R04, R05): a past period changes when an order is later refunded or shipped.
- **Grain**: one row per order (`sales_orders`, `customer_orders`, `order_returns`), per top-level order item
  (`sales_items`, `order_item_returns`), per customer account (`customers`), per source item (`catalog_stock`), per SKU
  and stock (`catalog_salable`), per return request (`rma_requests`), per return request item (`rma_items`).
- **Additivity**: `SUM` and `COUNT` metrics are additive across rows. `COUNT DISTINCT` metrics (`unique_customers`,
  `sales_items.orders`) are not: the total is not the sum of the rows. Derived metrics are always recomputed from the
  aggregated components (ratio of sums, never the average of row ratios), on rows, totals and comparisons alike.
- **Null / zero**: `SUM` aggregates are `COALESCE(SUM(x), 0)`, so an empty period yields 0 for counts and sums
  (`Sales/DerivedRatiosTest::testItReturnsZeroCountsZeroSumsAndNoRowsForAnEmptyPeriodInEveryDataset`). A derived ratio
  with a zero denominator (including `aov` with no orders) is **null**
  (`Sales/DerivedRatiosTest::testItReturnsNullAverageOrderValueForAPeriodWithoutOrders`).
- **Filters**: first and last day of the range are inclusive; store and website filters restrict the totals
  (`Sales/FiltersTest::testItIncludesOrdersCreatedOnTheFirstAndLastDayOfTheRange`,
  `Sales/FiltersTest::testItExcludesOrdersCreatedAfterTheEndOfTheRange`,
  `Sales/FiltersTest::testItRestrictsTotalsToTheSelectedStore`,
  `Sales/FiltersTest::testItGroupsOrdersByWebsiteWithOneRowPerWebsite`,
  `Sales/FiltersTest::testItReportsFilteredTotalsEqualToTheSumOfTheMatchingOrders`).
- **Test environment note**: the integration install charges flat-rate shipping of 5.00 **per ordered unit** even when
  the carrier type is pinned to per order (`O`); expected shipping values in the tests use this. Only method-level
  `#[Config]` and method-level `#[DataFixture]` attributes are honoured by this Magento version (class-level
  `#[Config]` is ignored, method-level fixtures replace class-level ones), see R25.

## `sales_orders` (Orders)

Grain: one row per order. Dimensions worth knowing: `order_date`, `status` (added in p1b so that orders can be grouped
by order status), website/store, payment, shipping method, customer group, country, hour, weekday.

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `orders` | `COUNT(order_id)` | Canceled orders are counted only when `canceled` is a counted status. | Additive; 0 on an empty period. | | `Sales/OrderStatusTest::testItCountsEveryStatusExceptCanceledWithTheDefaultCountedStatuses`, `Sales/OrderStatusTest::testItCountsOnlyListedStatusesWithACustomCountedStatusList`, `Sales/OrderStatusTest::testItGroupsOrdersByStatusDimensionWithOneRowPerCountedStatus`, `OrderScenarioTest::testItCountsOnlyOrdersWithCountedStatusesInTheScenario` |
| `gross_sales` | Order subtotal excluding tax: `base_subtotal - base_subtotal_canceled`. | Net of cancellations; refunds are NOT subtracted here (see `returns`). | Additive; 0 when empty. | | `Sales/MonetaryComponentsTest::testItReportsGrossSalesAsTheSumOfOrderSubtotals`, `Sales/OrderStatusTest::testItSumsGrossSalesOfPendingProcessingCompleteAndClosedOrdersByDefault`, `Sales/OrderStatusTest::testItReportsZeroGrossSalesShippingAndTaxesForACanceledOrderWhenCanceledIsCounted`, `OrderScenarioTest::testItSumsGrossSalesOfCountedScenarioOrders` |
| `discounts` | Absolute discount applied, net of the discount tax compensation: `ABS(base_discount_amount) - ABS(base_discount_canceled) - compensation kept`. Reported as a positive amount. The compensation (the part of the discount that only offsets tax on stores pricing catalog prices tax inclusive, and is not a real price reduction) is the item plus shipping compensation of the order, or the invoiced compensation when the order has canceled amounts. | Net of cancellations and of the tax compensation; refunded discount is handled in `returns`. | Additive; 0 when empty. | | `Sales/MonetaryComponentsTest::testItReportsDiscountsAsPositiveAmountsFromTheCouponOrderOnly` |
| `returns` | Refunded subtotal net of refunded discount: `base_subtotal_refunded - (ABS(base_discount_refunded) - base_discount_tax_compensation_refunded)`. The refunded compensation is added back so that it mirrors `discounts`. | Credit memo refunds (partial or full). | Additive; 0 when empty. | R04: attributed to the order date, not the credit memo date. | `Sales/RefundsTest::testItReportsPartialRefundAmountNetOfRefundedDiscountAsReturns`, `Sales/RefundsTest::testItReportsAFullRefundAsReturnsEqualToGrossSalesOfThatOrder`, `Sales/RefundsTest::testItAttributesRefundsToTheOrderDateRange`, `Sales/OrderStatusTest::testItReportsClosedOrderGrossSalesFullyOffsetByReturns`, `OrderScenarioTest::testItReportsThePartialRefundAmountOfOrderOneAsReturns` |
| `net_sales` | Derived: `gross_sales - discounts - returns`. | Both cancellations and refunds are netted. | Ratio of sums; recomputed on totals. | R04 | `Sales/MonetaryComponentsTest::testItComputesNetSalesAsGrossMinusDiscountsMinusReturns` |
| `shipping` | Shipping charged: `base_shipping_amount - base_shipping_canceled - base_shipping_refunded`. | Net of cancellations and refunded shipping. | Additive; 0 when empty. | Test env charges 5.00 per unit. | `Sales/MonetaryComponentsTest::testItReportsShippingAsFlatRatePerOrder`, `Sales/RefundsTest::testItSubtractsRefundedShippingFromShipping` |
| `taxes` | Tax charged: `base_tax_amount - base_tax_canceled - base_tax_refunded`. | Net of cancellations and refunded tax. | Additive; 0 when empty. | | `Sales/MonetaryComponentsTest::testItReportsTaxesNetOfNothingWhenNoRefundOrCancellationExists` |
| `adjustments` | Credit memo adjustments: `base_adjustment_positive - base_adjustment_negative` (extra refunded amount minus refund fee). | Only exists on refunded orders. | Additive; 0 when empty. | R04 | `Sales/RefundsTest::testItReportsAdjustmentsAsRefundAdjustmentMinusAdjustmentFee` |
| `total_sales` | Derived: `net_sales + shipping + taxes - adjustments`. | All the netting above. | Ratio of sums; recomputed on totals. | | `Sales/MonetaryComponentsTest::testItComputesTotalSalesAsNetPlusShippingPlusTaxesMinusAdjustments` |
| `aov` | Derived: `net_sales / orders` (total net sales over total orders). | As `net_sales`. | **Null** when there are no orders; the total is NOT the average of row values. | | `Sales/MonetaryComponentsTest::testItComputesAverageOrderValueAsTotalNetSalesDividedByOrderCountNotAsAverageOfRowValues`, `Sales/DerivedRatiosTest::testItReturnsNullAverageOrderValueForAPeriodWithoutOrders` |
| `units` | Quantity ordered on parent items, net of canceled quantity: `total_qty_ordered - SUM(qty_canceled)` (provider `units_net_of_canceled`, parent items only). Consistent with `gross_sales`, which subtracts canceled subtotal. | A partially invoiced then canceled order, or a counted canceled order, subtracts the canceled quantity (fixed in p1b). | Additive; 0 when empty or fully canceled. | Refunded units are not subtracted (see `sales_items.units_refunded`). | `Sales/UnitsCanceledTest::testItSubtractsCanceledQuantitiesFromUnitsOfAPartiallyCanceledOrder`, `Sales/UnitsCanceledTest::testItReportsZeroUnitsForAFullyCanceledOrderWhenCanceledIsCounted`, `Sales/UnitsCanceledTest::testItMatchesSalesItemsUnitsSoldForTheSameOrders`, `Sales/UnitsCanceledTest::testItKeepsUnitsUnchangedForOrdersWithoutCancellations` |
| `new_customer_orders` | `SUM(is_first_order)`: orders for which no earlier **counted** order exists for the same identity. A registered order looks for earlier counted orders of the same customer id. A guest order looks for any earlier counted order with the same email, whether that order was a guest or a registered one (case-insensitive through the column collation). | A canceled earlier order (not counted) does not make the customer returning. | Additive; 0 when empty. | R08: a registered order placed after a guest order with the same email counts as new (the registered check only looks at the customer id), so the person is new twice; a guest order after a registered one is returning; the 12 months is the **maximum report range** for new-vs-returning (`RequestValidator::FIRST_ORDER_MAX_MONTHS`), not a lookback window: all earlier counted orders are checked. | `Customers/CustomerMetricsTest::testItCountsACustomerSecondOrderAsReturningEvenWhenTheFirstIsOutsideTheRange`, `Customers/CustomerMetricsTest::testItIgnoresCanceledOrdersWhenDecidingAFirstOrder`, `Customers/CustomerMetricsTest::testItTreatsGuestEmailsDifferingOnlyByCaseAsOneCustomer`, `Customers/CustomerMetricsTest::testItCountsAGuestWhoLaterRegistersAsNewTwiceAsDocumented`, `Customers/CustomerMetricsTest::testItRejectsANewVersusReturningRequestWhoseRangeExceedsTwelveMonths`, `OrderScenarioTest::testItClassifiesBothCountedScenarioOrdersAsNewCustomers` |
| `returning_customer_orders` | Derived: `orders - new_customer_orders`. | As above. | Recomputed on totals. | R08 | `Customers/CustomerMetricsTest::testItCountsACustomerSecondOrderAsReturningEvenWhenTheFirstIsOutsideTheRange` |

## `customer_orders` (Customer orders)

Grain: one row per order, grouped by customer. Customer identity is `customer_key = LOWER(TRIM(customer_email))`:
guest and registered orders of the same email are merged, a customer who changes email is split in two (R14). The
helper metrics `gross_sales`, `discounts`, `returns`, `shipping`, `taxes`, `adjustments` are hidden and have exactly the
formulas of `sales_orders` (same tests, see above); they only feed the derived metrics below.

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `orders` | `COUNT(order_id)` | As `sales_orders.orders`. | Additive. | | `Customers/CustomerMetricsTest::testItTreatsGuestEmailsDifferingOnlyByCaseAsOneCustomer` |
| `unique_customers` | `COUNT DISTINCT(customer_key)`. | Only counted orders. | **Not additive** (a customer ordering in two groups is in both rows). | R14, R17 | `Customers/CustomerMetricsTest::testItCountsARegisteredCustomerOrderingOnTwoStoreViewsAsOneUniqueCustomer`, `Customers/CustomerMetricsTest::testItTreatsGuestEmailsDifferingOnlyByCaseAsOneCustomer` |
| `total_spent` | Derived lifetime value of the period: `gross_sales - discounts - returns + shipping + taxes - adjustments`. | Net of cancellations, refunds and adjustments. | Ratio of sums. | R04 | `Customers/CustomerMetricsTest::testItReportsTotalSpentPerCustomerAsLifetimeValueNetOfReturnsPlusShippingAndTaxes` |
| `avg_spent` | Derived: `total_spent / orders`. | As `total_spent`. | Null when no orders. | | `Customers/CustomerMetricsTest::testItReportsTotalSpentPerCustomerAsLifetimeValueNetOfReturnsPlusShippingAndTaxes` |
| `avg_shipping_cost` | Derived: `shipping / orders`. | Net shipping. | Null when no orders. | Value asserted. | `Sales/SalesDocumentsTest::testItComputesAverageShippingCostAsShippingAmountOverCountedOrders` |
| `days_since_last_order` | `MIN(days_since_order)`: days from the most recent order in the period to today (UTC). | Counted orders only. | Not additive (minimum). | R18: relative to today, closed periods cached 24h may be a day stale. | `Customers/CustomerMetricsTest::testItReportsTotalSpentPerCustomerAsLifetimeValueNetOfReturnsPlusShippingAndTaxes` (asserts `days_since_last_order`) |
| `avg_wait_days` | `AVG(wait_days)`: days between the order and its **first** shipment (provider `first_shipment_wait`); unshipped orders excluded. | n/a | Not additive (average). | R03, R17. Value asserted (R24 closed). | `Sales/SalesDocumentsTest::testItComputesAverageWaitDaysFromOrderDateToFirstShipmentDate`, `testItIgnoresLaterShipmentsWhenComputingWaitDays`, `testItExcludesOrdersWithoutShipmentFromAverageWaitDays` |

## `customers` (Customers)

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `registrations` | `COUNT(customer_id)`: customer accounts created in the range (account `created_at`). | n/a (not order based, no counted statuses). | Additive; 0 when empty. | R15: admin-created accounts (store 0) and accounts of a deleted store are excluded when specific stores are selected. | `Customers/CustomerMetricsTest::testItCountsRegistrationsCreatedInTheRange` |

## `sales_items` (Order items)

Grain: one row per top-level order item (implicit `top_level_item` filter). A configurable product is reported **once on
the parent line** (the SKU dimension shows the child SKU, `parent_sku` groups to the configurable) with the **cost taken
from the child**. A bundle is reported on the **parent line only** with cost 0 (R06). `category_id` is a fan-out
dimension (a product in several categories appears in each, R01): totals are not the sum of the rows and a warning is
shown. A **filter** on the category is different: it is rendered as a correlated `EXISTS` (`NOT EXISTS` for `neq` and `nin`) instead of a join, so each line is counted once even when its product is in several of the selected categories. The same holds for `cart_items`. The sum of `sales_items.gross_sales` over the same orders equals `sales_orders.gross_sales`.

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `units_sold` | `SUM(qty_ordered - qty_canceled)`. | Net of cancellations. | Additive. | | `OrderScenarioTest::testItSumsUnitsOrderedPerProductInSalesItemsDataset`, `Sales/UnitsCanceledTest::testItMatchesSalesItemsUnitsSoldForTheSameOrders`, `Catalog/ProductTypesTest::testItReportsAConfigurableSaleOnceOnTheParentLine` |
| `units_refunded` | `SUM(qty_refunded)`. | Credit memo quantities; independent of refunded amounts. | Additive. | R04 | `Sales/RefundsTest::testItReportsRefundedUnitsPerProductIndependentlyOfRefundedAmounts` |
| `units_shipped` | `SUM(qty_shipped)`. | n/a | Additive. | R05: order date; bundles shipped separately count 0. Value asserted (R24 closed). | `Sales/ShipmentsTest::testItCountsUnitsShippedAcrossPartialShipmentsOfOneOrder`, `testItAttributesShippedUnitsToTheOrderDateNotTheShipmentDate` |
| `units_to_ship` | `SUM(qty_ordered - qty_shipped - qty_refunded - qty_canceled)` for shippable items (virtual items and separately shipped bundles excluded). | Canceled and refunded quantities are removed from the backlog. | Additive. | R05. Value asserted (R24 closed). | `Sales/ShipmentsTest::testItReportsTheRemainingBacklogAsUnitsToShipAfterAPartialShipment`, `testItExcludesCanceledOrdersFromUnitsToShip` |
| `gross_sales` | `SUM(base_row_total x kept share)` excluding tax (item level), kept share as in `discounts`. | Net of canceled quantity. | Additive. | R06 | `Catalog/ProductTypesTest::testItReportsItemGrossSalesSummingToOrderGrossSalesForTheSameOrders`, `Catalog/ProductTypesTest::testItReportsAConfigurableSaleOnceOnTheParentLine` |
| `discounts` | `SUM((ABS(base_discount_amount) - base_discount_tax_compensation_amount) x kept share)`, where the kept share is `1 - qty_canceled / qty_ordered` (1 when nothing was ordered). Dynamic price bundles roll up the children (see below). | Net of the canceled quantity and of the discount tax compensation. | Additive. | | `Catalog/ProductTypesTest::testItReportsItemGrossSalesSummingToOrderGrossSalesForTheSameOrders` (net sales consistency) |
| `returns` | Amount refunded for the item net of refunded discount: `base_amount_refunded - (ABS(base_discount_refunded) - base_discount_tax_compensation_refunded)`. | Credit memo refunds. | Additive. | R04 | `Sales/RefundsTest::testItComputesReturnRatePerProductFromSummedReturnsAndGrossSales` |
| `net_sales` | Derived: `gross_sales - discounts - returns`. | | Ratio of sums. | | `Catalog/ProductTypesTest::testItReportsAConfigurableSaleOnceOnTheParentLine` |
| `cost` | `SUM(unit cost x units)` over rows with a known cost (`has_cost`); the child cost for configurables. Units are `qty_ordered - qty_canceled - qty_refunded`. | Net of canceled **and refunded** units (unlike `units_sold`, which only nets cancellations). | Additive; rows without cost contribute 0. | R06 (bundle parent cost 0); cost basis is the order item `base_cost`. | `Catalog/ProductTypesTest::testItUsesTheChildProductCostForConfigurableMargin` |
| `gross_margin` | Derived: `net_sales - cost`. | | Ratio of sums. | A row without cost has margin equal to net sales; read with `cost_coverage`. | `Catalog/ProductTypesTest::testItUsesTheChildProductCostForConfigurableMargin`, `Sales/DerivedRatiosTest::testItComputesMarginPercentTotalsFromSummedMarginAndSummedNetSales` |
| `margin_pct` | Derived ratio (not x100): `gross_margin / net_sales`. | | Total is margin total over net sales total, not the mean of the row percentages. Null when net sales is 0. | | `Sales/DerivedRatiosTest::testItComputesMarginPercentTotalsFromSummedMarginAndSummedNetSales` |
| `return_rate` | Derived ratio (not x100): `returns / gross_sales`. | | Ratio of sums. Null when gross sales is 0. | R04 | `Sales/DerivedRatiosTest::testItComputesReturnRateTotalsFromSummedReturnsAndSummedGrossSales`, `Sales/RefundsTest::testItComputesReturnRatePerProductFromSummedReturnsAndGrossSales` |
| `orders` | `COUNT DISTINCT(order_id)`. | | **Not additive.** | R01 (not flagged as fan-out). | `Catalog/ProductTypesTest::testItCountsDistinctOrdersPerProduct` |
| `shipments` | Number of shipments containing the product (provider `shipment_count`). | n/a | Additive over rows. | R03, R05. Value asserted (R03, R24 closed). | `Sales/ShipmentsTest::testItCountsTwoShipmentsForOneOrderLineShippedTwice` |
| `credit_memos` | Number of credit memos containing the product, with at least one item of qty > 0 (provider `credit_memo_count`). | Adjustment-only memos and memos in the canceled or open state are not counted (the excluded states are configurable in `etc/di.xml`). | Additive over rows. | R03, R04. Value asserted (R03, R24 closed). | `Sales/SalesDocumentsTest::testItCountsTwoCreditMemosForOneOrderRefundedInTwoSteps` (plus `Sales/RefundsTest`) |
| `cost_coverage` | Derived: `has_cost_rows / rows` (hidden helpers: item rows with known cost, all item rows). | | Ratio of sums; null with no rows. | | `Sales/DerivedRatiosTest::testItComputesCostCoverageFromRowsWithCostOverAllRows` |

**Dynamic price bundles.** For a bundle parent with dynamic pricing (`product_calculations = 0`) the money fields are
rolled up from the children (provider `DynamicBundleRollupExpression`, expressions in `etc/di.xml`): gross sales is the parent
row total minus the children's canceled share, discounts and returns are the sum over the children (net of compensation),
and units are the parent quantity times one minus the highest canceled fraction of the children. The parent's own
compensation is ignored for those bundles so it is never counted twice. Fixed price bundles use the parent row. `cost`
is unchanged (bundle parent cost stays 0, R06).

## `catalog_stock` (Stock), physical quantity

Grain: one row per source item (MSI, `inventory_source_item`) or per legacy stock item (without MSI). Dateless
dataset: not filtered by the order date. See [Inventory quantities](#inventory-quantities-physical-reserved-and-salable)
for how it relates to salable stock.

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `qty_on_hand` | `SUM(quantity)` of the **enabled** sources (`inventory_source.enabled = 1`). Source items of a disabled source are excluded; a source item with status 0 (out of stock at the source) still counts its physical quantity. | Physical quantity: orders and cancellations affect it only once shipped / returned to stock. | Additive. | Fixed in p1b: disabled sources were counted. | `Inventory/PhysicalStockTest::testItSumsPhysicalQuantityAcrossEnabledSourcesOnly`, `Inventory/PhysicalStockTest::testItGroupsQuantityBySourceCode` |
| `stock_value_cost` | `SUM(quantity x cost)`; cost is the global (store 0) `cost` attribute; products without cost count 0. | n/a | Additive; 0 when empty. | R10: no website/store price or cost overrides. | `Inventory/PhysicalStockTest::testItValuesStockAtCostAsQuantityTimesGlobalCostAttribute`, `Inventory/PhysicalStockTest::testItValuesStockWithoutCostAsZeroCostValueAndFullRetailValue` |
| `stock_value_retail` | `SUM(quantity x price)` with the global `price` attribute. | n/a | Additive. | R10 | `Inventory/PhysicalStockTest::testItValuesStockWithoutCostAsZeroCostValueAndFullRetailValue` |
| `skus_in_stock` | `COUNT DISTINCT(product_id)` where the quantity on hand is greater than 0. | n/a | **Not additive.** | Value asserted (R24 closed). | `Inventory/StockCountMetricsTest::testItCountsSkusWithPositiveQuantityAsInStockEvenWhenTheSourceItemStatusIsOutOfStockAndExcludesZeroQuantity`, `testItDoesNotCountASkuTwiceWhenItHasQuantityOnTwoSources`, `testItExcludesSkusOnDisabledSourcesFromSkusInStock` |
| `dead_stock` / `never_sold` presets | `dead_stock`: products with `qty > 0` and no counted sale in the last `dead_stock_days` (`days_since_last_sale` is the age of the last counted sale, null when never sold). `never_sold`: enabled products with no order item at all, **in any order status** (canceled included, counted statuses are not applied) and **with no quantity filter**, so out-of-stock products are listed too. | Dead stock uses the counted statuses; never sold does not. | Not additive. | The order item subqueries respect the selected stores. Both presets read sales data, so they require the `sales_items` dataset permission (see [security](security.md#dataset-access)). | `Preset/StockPresetScopeTest` |
| `units_sold_30d`, `avg_daily_sales`, `coverage_days`, `days_since_last_sale` | Virtual metrics produced by the stock presets (anti-joins and 30-day sales on `sales_order_item`): units sold over 30 days, divided by 30, days of coverage, days since last counted sale. Empty when there are no sales. | Counted statuses, net of cancellations. | Not additive. | Disabled sources are excluded in the presets too. Values asserted (R24 closed). | `Preset/PresetMetricsTest::testItSumsUnitsSoldInTheLast30DaysAndIgnoresOlderOrders`, `testItComputesAverageDailySalesAsUnitsSoldIn30DaysDividedBy30`, `testItComputesCoverageDaysAsQuantityOnHandDividedByAverageDailySales`, `testItReturnsZeroSalesAndNullCoverageDaysForASkuWithoutSales`, `testItComputesDaysSinceLastSaleFromTheMostRecentCountedOrder`, `testItIgnoresCanceledOrdersWhenComputingDaysSinceLastSale`; `Inventory/PhysicalStockTest::testItExcludesDisabledSourcesFromStockPresets` |

## `catalog_salable` (Salable stock), MSI only

Grain: one row per SKU and stock. Available only when the MSI modules are enabled (`Magento_InventoryApi`,
`Magento_InventoryIndexer`, `Magento_InventoryReservations`, `Magento_InventoryCatalog`); otherwise the dataset and its
presets are dropped from the registry at load time (info log per dropped dataset). Configurable, bundle and grouped
products (no source items of their own) are excluded.

| Metric | Formula (business terms / SQL field) | Cancel / refund treatment | Additivity, null / zero | Known limits | Tests |
|---|---|---|---|---|---|
| `stock_qty` | Quantity in the stock index (`inventory_stock_<id>`), before pending reservations. Follows source quantity, shipments. | A shipment lowers it; a cancel does not change it. | Additive across SKUs / stocks. | R26: non-default stocks unit-test only. | `Inventory/SalableStockTest::testItReportsPhysicalQuantityReducedAndReservationReleasedAfterShipment`, `Inventory/SalableStockTest::testItReflectsASourceQuantityAdjustmentInSalableQuantity` |
| `reserved_qty` | Quantity reserved by pending orders: `-SUM(inventory_reservation.quantity)`; positive when reserved. | Placing an order reserves; shipping or canceling releases (compensating reservation). | Additive. | R26 | `Inventory/SalableStockTest::testItReportsReservedQuantityAfterAnOrderIsPlaced`, `Inventory/SalableStockTest::testItReleasesTheReservationWhenTheOrderIsCanceled`, `Inventory/SalableStockTest::testItReportsPhysicalQuantityReducedAndReservationReleasedAfterShipment` |
| `salable_qty` | `stock_qty - reserved_qty - effective min qty` when the stock index marks the product salable, else 0. Mirrors core `GetProductSalableQty`; **no clamping**, so it can be negative. | As above. | Additive. | R26 | `Inventory/SalableStockTest::testItMatchesCoreSalableQuantityAtEveryStep`, `Inventory/SalableStockTest::testItReflectsASourceQuantityAdjustmentInSalableQuantity` |
| `skus_salable` | `COUNT DISTINCT(product_id)` where `salable_qty > 0`. | | **Not additive.** | Value asserted (R24 closed). | `Inventory/StockCountMetricsTest::testItCountsSkusWithPositiveSalableQuantityAfterReservationsAsSalable`, `testItExcludesASkuFullyReservedByPlacedOrdersFromSkusSalable` |

## `carts` and `cart_items` (Carts)

Grain: one row per quote (`carts`), per quote item (`cart_items`). Area `carts`; ACL `MageOS_AdvancedReport::carts`.
Amounts are in the base currency of the cart's store. Implicit filter `non_empty_cart`: only quotes with
`items_count > 0` are read.

Definitions shared by both datasets (`cart_items` evaluates them on the cart the item belongs to):

| Term | Definition |
|---|---|
| Date | `quote.updated_at` (last activity), converted to the store timezone. Carts are not dated by creation. |
| Email | The quote `customer_email`, trimmed; when blank, the email of the billing address of the quote (guest checkout stores it there until the order is placed). The `carts.customer_email` dimension uses the same fallback, lowercased. |
| Abandoned | `is_active = 1`, `items_count > 0`, a non-empty email (above), `updated_at` older than `mageos_advancedreport/carts/abandoned_after_hours` (default 24) and **no order** (a converted cart is never abandoned). |
| Recent | `is_active = 1`, `items_count > 0`, no order and `updated_at` within `mageos_advancedreport/carts/recent_cart_hours` (default 24, maximum 120 because the recent presets use `last_7_days`). |
| Converted | At least one order references the cart through `sales_order.quote_id`, in any status (canceled included). Converted takes precedence over abandoned and recent, even when the quote was reactivated after the order. A multi-shipping checkout creates several orders for one cart and counts as one converted cart. |
| `cart_state` | Active cart updated within the abandoned threshold: `active`; active and idle beyond it: `abandoned` (with email) or `abandoned without email`; inactive cart with an order: `converted`; any other inactive cart: `inactive`. Evaluated in this order, converted first (an active, reactivated quote with an order is `converted`). |

Thresholds are computed in UTC from `ClockInterface::now()`, not from the database clock.

Known limits:

- **Dating**: a cart is dated by its last activity. A converted cart is dated near its order time, so `conversion_rate`
  and `abandonment_rate` of a period compare carts active in that period, not carts created in it.
- **Any order status**: a canceled order still marks its cart as converted.
- **Multi-shipping**: several orders of one cart count once.
- **PII**: `customer_email` is exposed; see [security](security.md#data-classification).
- **Cache**: the result changes as time passes, so cart datasets always use the open-period TTL ([cache](cache.md#ttl)).
- **Quote retention**: Magento deletes old quotes (`checkout/cart/delete_quote_after`, days, default 30, a setting of
  `Magento_Checkout` that this module reads but does not declare), through the core `sales_clean_quotes` cron. When the
  date range reaches back before the oldest surviving quote the report shows a warning, because older carts may be gone.
  The warning assumes that cron runs. A lifetime of 0 or empty means that only today's quotes survive (every range starting
  before today warns). The smallest lifetime of the stores in scope is used.

### `carts` metrics

| Metric | Formula | Notes |
|---|---|---|
| `carts` | `COUNT(entity_id)` | Carts with items. |
| `cart_value` | `SUM(base_grand_total)` | |
| `avg_cart_value` | `cart_value / carts` | Null when there are no carts. |
| `items_in_carts` | `SUM(items_qty)` | Total quantity. |
| `abandoned_carts` | `SUM(is_abandoned)` | Lower is better. |
| `abandoned_value` | `SUM(base_grand_total)` of abandoned carts | Lower is better. |
| `recent_carts` | `SUM(is_recent)` | |
| `converted_carts` | `SUM(is_converted)` | |
| `converted_value` | `SUM(base_grand_total)` of converted carts | Cart total, not the order total. |
| `abandonment_rate` | `abandoned_carts / (abandoned_carts + converted_carts)` | Lower is better; null when both are 0. |
| `conversion_rate` | `converted_carts / carts` | Null when there are no carts. |

### `cart_items` metrics

Configurable products are counted on the parent line (the simple child is ignored); bundles are a single line. Items are
dated by the last activity of their cart. `category_id` is a fan-out dimension: totals may not equal the sum of the rows (R01).

| Metric | Formula | Notes |
|---|---|---|
| `units_in_carts` | `SUM(qty)` | |
| `cart_items_value` | `SUM(base_row_total)` | Excluding tax. |
| `carts` | `COUNT DISTINCT(quote_id)` | Not additive across rows. |
| `abandoned_units` | `SUM(qty)` in abandoned carts | Lower is better. |
| `abandoned_items_value` | `SUM(base_row_total)` in abandoned carts | Lower is better. |
| `abandoned_carts` | `COUNT DISTINCT(quote_id)` of abandoned carts | Not additive; lower is better. |
| `converted_units` | `SUM(qty)` in converted carts | |
| `item_conversion_rate` | `converted_units / units_in_carts` | Null when there are no units. |

Carts are verified by the unit tests of the cart providers and the integration tests under `Test/Integration/`; the raw
expressions are in the [metric inventory](metric-inventory.md).

## Returns (MageOS_RMA): `rma_requests`, `rma_items`, `order_returns`, `order_item_returns`

Area `returns`; ACL `MageOS_AdvancedReport::returns` (plus the core ACLs, see
[security](security.md#dataset-access)). The four datasets exist **only while the module `MageOS_RMA` is enabled**: they
are defined in `etc/report_datasets_rma.xml`, read by `Model\Rma\Config\RmaDefinitionSource` only when `MageOS_RMA` is
enabled. With the module disabled or absent they are not in the registry, and neither are their presets. The reports read
the RMA tables (`rma_entity`, `rma_item` and the lookups `rma_status`, `rma_reason`, `rma_resolution_type`,
`rma_item_condition`) directly; no `MageOS\RMA` class is used.

| Dataset | Grain | Date | Implicit filters |
|---|---|---|---|
| `rma_requests` | one row per return request (`rma_entity`) | request `created_at` | none |
| `rma_items` | one row per request item (`rma_item`) | `created_at` of the request the item belongs to | none |
| `order_returns` | one row per order (`sales_order`) | order `created_at` | `counted_status` |
| `order_item_returns` | one row per top-level order item (`sales_order_item`) | order `created_at` | `top_level_item`, `counted_status` |

Dates are converted to the store timezone before bucketing, as for every dataset. `rma_requests` and `rma_items` are
scoped by the store of the request, `order_returns` and `order_item_returns` by the store of the order. Amounts are in
the base currency of the order's store.

Definitions shared by the four datasets:

| Term | Definition |
|---|---|
| Request type | Decided by comparing the items of the request with the shipments of the order. **Withdrawal before shipment** (`withdrawal`): no requested item had been shipped when the request was created. **Return after shipment** (`return`): every requested item had been shipped. **Mixed** (`mixed`, partly shipped): some requested items had been shipped, others had not. |
| Shipped | An item counts as shipped when a shipment containing it was created at or before the request (`sales_shipment.created_at <= rma_entity.created_at`); for a bundle shipped separately, a shipment containing one of its children. Only shipments created before the request count. |
| Delivery | **Not tracked** (Magento stores no delivery date): a request made after shipment but before delivery is a return after shipment (R50). |
| Request without items | Has no type: counted in `requests`, in none of the three type metrics. |
| Item level type | On `rma_items` and in the `order_returns` type flags the type is decided per item, so it is only withdrawal or return (never mixed). |
| Quantities | Always the requested quantity (`rma_item.qty_requested`), never the approved (`qty_approved`) or received (`qty_returned`) quantity (R52). |
| Status | Requests are counted in **any status**, rejected and canceled by customer included. The status metrics (`open_requests`, `rejected_requests`, `resolved_requests`, `canceled_requests`, `rejection_rate`) and the `status_id` dimension use the **current** status of the request, not the status it had at the end of the period. |
| Open | Current status code not in `rejected`, `canceled_by_customer`, `resolved`. |

Attribution:

- `rma_requests` and `rma_items`: requests are attributed to the date they were made.
- `order_returns` and `order_item_returns`: orders and units are attributed to the **order date**; the requests on those
  orders are counted **whenever they were made**, also after the end of the period. A past period changes when a new
  request is created on one of its orders, and recent periods keep growing as their orders receive requests.
- Status changes and later requests change past results, so the four datasets always use the open-period cache TTL
  ([cache](cache.md#ttl)).

### `rma_requests` metrics

| Metric | Formula | Notes |
|---|---|---|
| `requests` | `COUNT(rma_id)` | Return requests created in the period, in any status. |
| `orders_with_requests` | `COUNT DISTINCT(order_id)` | Distinct orders with at least one request created in the period. Not additive across rows. |
| `customers_with_requests` | `COUNT DISTINCT(customer_email)` | Distinct customers by request email (`LOWER(TRIM(customer_email))`): guest and registered requests of the same email are merged. Not additive across rows. |
| `requests_per_order` | `requests / orders_with_requests` | Null when there are no requests. |
| `units_requested` | `SUM(units_requested_qty)` | Sum of `qty_requested` over the items of the request (R52). |
| `withdrawals` | `SUM(is_withdrawal)` | Requests of type withdrawal before shipment (R50). |
| `returns_after_shipment` | `SUM(is_return_after_shipment)` | Requests of type return after shipment (R50). |
| `mixed_requests` | `SUM(is_mixed_request)` | Requests of type mixed (R50). |
| `withdrawal_share` | `withdrawals / requests` | Null when there are no requests. |
| `open_requests` | `SUM(is_open)` | Current status is open (above). Lower is better. |
| `rejected_requests` | `SUM(is_rejected)` | Current status `rejected`. |
| `resolved_requests` | `SUM(is_resolved)` | Current status `resolved`. |
| `canceled_requests` | `SUM(is_canceled)` | Current status `canceled_by_customer`. |
| `rejection_rate` | `rejected_requests / requests` | Lower is better; null when there are no requests. |
| `avg_days_to_request` | `AVG(days_to_request)` | Days from the order `created_at` to the request `created_at` (never negative). |
| `avg_days_from_shipment` | `AVG(days_from_shipment)` | Days from the latest shipment of the order created at or before the request to the request; requests with nothing shipped before them (withdrawals) are excluded from the average. |

Dimensions: `request_date` (hour, day, week, month, year), `status_id`, `reason_id`, `resolution_type_id` (labels from
the RMA lookup tables, translated through `__()`), `request_type`, `store_id`, `website_id`, `customer_group_id` (of the
order), `customer_type` (guest when the request has no customer account), `shipping_country` (order shipping address),
`increment_id` (request number), `customer_email`. `customer_email` and `increment_id` are personal data, see
[security](security.md#data-classification).

### `rma_items` metrics

| Metric | Formula | Notes |
|---|---|---|
| `units_requested` | `SUM(qty_requested_qty)` | `COALESCE(qty_requested, 0)` of the item (R52). |
| `requested_value` | `SUM(requested_value_amount)` | Requested quantity times the unit value of the order item: `(base_row_total - (ABS(base_discount_amount) - base_discount_tax_compensation_amount)) / qty_ordered`. Base currency, excluding tax, net of discount; dynamic price bundles use the discount of their children. It is the value of the request, **not the refunded amount** (refunds are `returns` in `sales_orders` and `sales_items`, R53). |
| `requests` | `COUNT DISTINCT(rma_id)` | Distinct requests containing the items. Not additive across rows. |
| `withdrawn_units` | `SUM(qty_requested_qty)` where `is_withdrawal` | Units of items not shipped when the request was created (R50). |
| `returned_units_after_shipment` | `SUM(qty_requested_qty)` where `is_return_after_shipment` | Units of items shipped when the request was created (R50). |
| `units_per_request` | `units_requested / requests` | Null when there are no requests. |

Dimensions: `request_date`, `sku` (simple SKU of the order item), `parent_sku` (configurable parent), `product_id`
(product name), `status_id` and `reason_id` (of the request), `condition_id` (declared item condition), `request_type`
(per item: withdrawal or return), `store_id` (of the request). There is no category dimension.

### `order_returns` metrics

| Metric | Formula | Notes |
|---|---|---|
| `orders` | `COUNT(order_id)` | Orders placed in the period with a counted status. |
| `orders_with_requests` | `SUM(has_return_request)` | Orders of the period with at least one request, made at any time; an order with several requests counts once. |
| `return_requests` | `SUM(return_request_count)` | Requests on the orders of the period, made at any time, in any status. |
| `order_return_rate` | `orders_with_requests / orders` | Lower is better; null when there are no orders. Recent periods can still grow. |
| `requests_per_returned_order` | `return_requests / orders_with_requests` | Null when no order has requests. |
| `orders_with_withdrawals` | `SUM(has_withdrawal)` | Orders with at least one requested item not shipped when its request was created (R50). |
| `orders_with_returns_after_shipment` | `SUM(has_return_after_shipment)` | Orders with at least one requested item shipped when its request was created (R50). |
| `withdrawal_rate` | `orders_with_withdrawals / orders` | Lower is better. |
| `return_after_shipment_rate` | `orders_with_returns_after_shipment / orders` | Lower is better. |

An order with a mixed request, or with requests of both types, counts in both `orders_with_withdrawals` and
`orders_with_returns_after_shipment`. Dimensions: `order_date`, `store_id`, `website_id`, `customer_group_id`,
`shipping_country`, `shipping_method`, `payment_method`.

### `order_item_returns` metrics

| Metric | Formula | Notes |
|---|---|---|
| `units_ordered` | `SUM(qty_net)` | Quantity ordered net of cancellations (provider `bundle_qty_net`, same as `sales_items.units_sold`). |
| `units_requested` | `SUM(qty_requested_rma)` | `qty_requested` of the order item over all its requests, made at any time, in any status (R52). |
| `unit_return_rate` | `units_requested / units_ordered` | Lower is better; null when no units were ordered. Recent periods can still grow. |
| `orders` | `COUNT DISTINCT(order_id)` | Distinct orders contributing item rows. Not additive across rows. |

Dimensions: `order_date`, `store_id`, `customer_group_id`, `sku`, `parent_sku`, `product_id`, `category_id`.
`category_id` is a fan-out dimension (R01): totals may not equal the sum of the rows.

Returns are verified by the unit tests under `Test/Unit/Model/Rma/` (definition source, request type SQL, providers,
label resolvers); no integration test asserts their values. The raw expressions are in the
[metric inventory](metric-inventory.md) while `MageOS_RMA` is enabled.

## Inventory quantities: physical, reserved and salable

| Term | Meaning | Where it is reported |
|---|---|---|
| **Physical** (on hand) | Quantity physically held in the **enabled** sources (`inventory_source_item.quantity`). Unchanged by an order until it is shipped. | `catalog_stock.qty_on_hand`, per source |
| **Stock** (indexed) | Physical quantity of the sources assigned to a stock, as aggregated by the MSI stock index; after a shipment it is lower. | `catalog_salable.stock_qty`, per stock |
| **Reserved** | Quantity committed to pending orders and not yet shipped, stored as negative rows in `inventory_reservation`; shown as a positive number. A shipment or cancellation adds the compensating row, so reserved returns to 0. | `catalog_salable.reserved_qty` |
| **Salable** | What can still be sold: `stock - reserved - minimum quantity`, mirroring core `GetProductSalableQty` (0 when the stock index marks the product not salable; negative values are shown as-is). | `catalog_salable.salable_qty` |
| **Available** | In this module, "available" means **salable**. There is no separate available metric. | `catalog_salable.salable_qty` |

Differences without MSI (legacy `cataloginventory_stock_item`, MSI modules disabled): `catalog_stock` uses the legacy
provider (a single stock, no sources, no disabled-source filter, no source dimension); there is **no** `catalog_salable`
dataset and no reserved quantity (the legacy stock item quantity already is decremented at order placement, so physical
and salable are not distinguishable). Reports and presets that depend on `catalog_salable` are dropped, not shown empty.

Order events seen from the reports (default stock, covered by `Inventory/SalableStockTest`):

| Event | `stock_qty` | `reserved_qty` | `salable_qty` |
|---|---|---|---|
| Order placed | unchanged | + order qty | - order qty |
| Order shipped | - shipped qty | back to 0 for the shipped qty | unchanged by the shipment itself |
| Order canceled | unchanged | back to 0 | back to the previous value |
| Source quantity adjusted by n | + n | unchanged | + n |

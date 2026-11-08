## sales_orders (Orders)

Base table: `sales_order`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `orders` | Orders | aggregate | count | `main.entity_id` |  |  | timezone, counted_statuses |
| `gross_sales` | Gross sales | aggregate | sum | `(COALESCE(main.base_subtotal, 0) - COALESCE(main.base_subtotal_canceled, 0))` |  |  | timezone, currency, counted_statuses |
| `discounts` | Discounts | aggregate | sum | `(ABS(COALESCE(main.base_discount_amount, 0)) - ABS(COALESCE(main.base_discount_canceled, 0)) - (CASE WHEN main.base_subtotal_canceled IS NOT NULL THEN COALESCE(main.base_discount_tax_compensation_invoiced, 0) ELSE COALESCE(main.base_discount_tax_compensation_amount, 0) + COALESCE(main.base_shipping_discount_tax_compensation_amnt, 0) END))` |  |  | timezone, currency, counted_statuses |
| `returns` | Returns | aggregate | sum | `(COALESCE(main.base_subtotal_refunded, 0) - (ABS(COALESCE(main.base_discount_refunded, 0)) - COALESCE(main.base_discount_tax_compensation_refunded, 0)))` |  |  | timezone, currency, counted_statuses |
| `net_sales` | Net sales | derived |  | `gross_sales - discounts - returns` |  |  | timezone, currency, counted_statuses |
| `shipping` | Shipping | aggregate | sum | `(COALESCE(main.base_shipping_amount, 0) - COALESCE(main.base_shipping_canceled, 0) - COALESCE(main.base_shipping_refunded, 0))` |  |  | timezone, currency, counted_statuses |
| `taxes` | Taxes | aggregate | sum | `(COALESCE(main.base_tax_amount, 0) - COALESCE(main.base_tax_canceled, 0) - COALESCE(main.base_tax_refunded, 0))` |  |  | timezone, currency, counted_statuses |
| `adjustments` | Adjustments | aggregate | sum | `(COALESCE(main.base_adjustment_positive, 0) - COALESCE(main.base_adjustment_negative, 0))` |  |  | timezone, currency, counted_statuses |
| `total_sales` | Total sales | derived |  | `net_sales + shipping + taxes - adjustments` |  |  | timezone, currency, counted_statuses |
| `aov` | Average order value | derived |  | `net_sales / orders` |  |  | timezone, currency, counted_statuses |
| `units` | Units | aggregate | sum | `provider:units_net_of_canceled` |  |  | timezone, counted_statuses |
| `new_customer_orders` | New customer orders | aggregate | sum | `provider:first_order` |  |  | timezone, counted_statuses |
| `returning_customer_orders` | Returning customer orders | derived |  | `orders - new_customer_orders` |  |  | timezone, counted_statuses |

## customer_orders (Customer orders)

Base table: `sales_order`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `orders` | Orders | aggregate | count | `main.entity_id` |  |  | timezone, counted_statuses |
| `unique_customers` | Unique customers | aggregate | count_distinct | `LOWER(TRIM(main.customer_email))` |  |  | timezone, counted_statuses |
| `gross_sales` (hidden) | Gross sales | aggregate | sum | `(COALESCE(main.base_subtotal, 0) - COALESCE(main.base_subtotal_canceled, 0))` |  |  | timezone, currency, counted_statuses |
| `discounts` (hidden) | Discounts | aggregate | sum | `(ABS(COALESCE(main.base_discount_amount, 0)) - ABS(COALESCE(main.base_discount_canceled, 0)) - (CASE WHEN main.base_subtotal_canceled IS NOT NULL THEN COALESCE(main.base_discount_tax_compensation_invoiced, 0) ELSE COALESCE(main.base_discount_tax_compensation_amount, 0) + COALESCE(main.base_shipping_discount_tax_compensation_amnt, 0) END))` |  |  | timezone, currency, counted_statuses |
| `returns` (hidden) | Returns | aggregate | sum | `(COALESCE(main.base_subtotal_refunded, 0) - (ABS(COALESCE(main.base_discount_refunded, 0)) - COALESCE(main.base_discount_tax_compensation_refunded, 0)))` |  |  | timezone, currency, counted_statuses |
| `shipping` (hidden) | Shipping | aggregate | sum | `(COALESCE(main.base_shipping_amount, 0) - COALESCE(main.base_shipping_canceled, 0) - COALESCE(main.base_shipping_refunded, 0))` |  |  | timezone, currency, counted_statuses |
| `taxes` (hidden) | Taxes | aggregate | sum | `(COALESCE(main.base_tax_amount, 0) - COALESCE(main.base_tax_canceled, 0) - COALESCE(main.base_tax_refunded, 0))` |  |  | timezone, currency, counted_statuses |
| `adjustments` (hidden) | Adjustments | aggregate | sum | `(COALESCE(main.base_adjustment_positive, 0) - COALESCE(main.base_adjustment_negative, 0))` |  |  | timezone, currency, counted_statuses |
| `total_spent` | Total spent | derived |  | `gross_sales - discounts - returns + shipping + taxes - adjustments` |  |  | timezone, currency, counted_statuses |
| `avg_spent` | Average spent per order | derived |  | `(gross_sales - discounts - returns + shipping + taxes - adjustments) / orders` |  |  | timezone, currency, counted_statuses |
| `avg_shipping_cost` | Average shipping cost | derived |  | `shipping / orders` |  |  | timezone, currency, counted_statuses |
| `days_since_last_order` | Days since last order | aggregate | min | `DATEDIFF(UTC_TIMESTAMP(), main.created_at)` |  |  | timezone, counted_statuses |
| `avg_wait_days` | Average wait days | aggregate | avg | `provider:first_shipment_wait` |  |  | timezone, counted_statuses |

## customers (Customers)

Base table: `customer_entity`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `registrations` | Registrations | aggregate | count | `main.entity_id` |  |  | timezone |

## sales_items (Order items)

Base table: `sales_order_item`

Fan-out dimensions: category_id

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `units_sold` | Units sold | aggregate | sum | `provider:bundle_qty_net` |  | ⚠ by category_id | timezone, counted_statuses |
| `units_refunded` | Units refunded | aggregate | sum | `COALESCE(main.qty_refunded, 0)` |  | ⚠ by category_id | timezone, counted_statuses |
| `units_shipped` | Units shipped | aggregate | sum | `COALESCE(main.qty_shipped, 0)` |  | ⚠ by category_id | timezone, counted_statuses |
| `units_to_ship` | Units to ship | aggregate | sum | `(CASE WHEN main.is_virtual = 1 THEN 0 WHEN main.product_type = 'bundle' AND (CASE WHEN JSON_VALID(main.product_options) THEN JSON_UNQUOTE(JSON_EXTRACT(main.product_options, '$.shipment_type')) END) = '1' THEN 0 ELSE GREATEST(COALESCE(main.qty_ordered, 0) - COALESCE(main.qty_shipped, 0) - COALESCE(main.qty_refunded, 0) - COALESCE(main.qty_canceled, 0), 0) END)` |  | ⚠ by category_id | timezone, counted_statuses |
| `gross_sales` | Gross sales | aggregate | sum | `provider:bundle_gross_sales_amount` |  | ⚠ by category_id | timezone, currency, counted_statuses |
| `discounts` | Discounts | aggregate | sum | `provider:bundle_discount_amount` |  | ⚠ by category_id | timezone, currency, counted_statuses |
| `returns` | Returns | aggregate | sum | `provider:bundle_returns_amount` |  | ⚠ by category_id | timezone, currency, counted_statuses |
| `net_sales` | Net sales | derived |  | `gross_sales - discounts - returns` |  | ⚠ by category_id | timezone, currency, counted_statuses |
| `cost` | Cost | aggregate | sum | `(COALESCE(child.base_cost, main.base_cost) * (COALESCE(main.qty_ordered, 0) - COALESCE(main.qty_canceled, 0) - COALESCE(main.qty_refunded, 0)))` | child | ⚠ by category_id | timezone, currency, counted_statuses |
| `gross_margin` | Gross margin | derived |  | `net_sales - cost` | child | ⚠ by category_id | timezone, currency, counted_statuses |
| `margin_pct` | Margin % | derived |  | `gross_margin / net_sales` | child | ⚠ by category_id | timezone, currency, counted_statuses |
| `return_rate` | Return rate | derived |  | `returns / gross_sales` |  | ⚠ by category_id | timezone, currency, counted_statuses |
| `shipments` | Shipments | aggregate | sum | `provider:shipment_count` |  | ⚠ by category_id | timezone, counted_statuses |
| `credit_memos` | Credit memos | aggregate | sum | `provider:credit_memo_count` |  | ⚠ by category_id | timezone, counted_statuses |
| `orders` | Orders | aggregate | count_distinct | `main.order_id` |  |  | timezone, counted_statuses |
| `has_cost_rows` (hidden) | Rows with cost | aggregate | sum | `(COALESCE(child.base_cost, main.base_cost) IS NOT NULL)` | child | ⚠ by category_id | timezone, counted_statuses |
| `rows` (hidden) | Rows | aggregate | count | `main.item_id` |  | ⚠ by category_id | timezone, counted_statuses |
| `cost_coverage` | Cost coverage | derived |  | `has_cost_rows / rows` | child | ⚠ by category_id | timezone, counted_statuses |

## catalog_stock (Stock)

Base: provider: `MageOS\AdvancedReport\Model\Stock\StockSelectProvider`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `qty_on_hand` | Qty on hand | aggregate | sum | `main.qty` |  |  | stock_provider |
| `stock_value_cost` | Stock value (cost) | aggregate | sum | `(main.qty * COALESCE(main.cost, 0))` |  |  | currency, stock_provider |
| `stock_value_retail` | Stock value (retail) | aggregate | sum | `(main.qty * COALESCE(main.price, 0))` |  |  | currency, stock_provider |
| `skus_in_stock` | SKUs in stock | aggregate | count_distinct | `main.product_id` |  |  | stock_provider |
| `units_sold_30d` | Units sold (30 days) | virtual |  |  |  |  | stock_provider |
| `avg_daily_sales` | Avg daily sales | virtual |  |  |  |  | stock_provider |
| `coverage_days` | Coverage (days) | virtual |  |  |  |  | stock_provider |
| `days_since_last_sale` | Days since last sale | virtual |  |  |  |  | stock_provider |

## catalog_salable (Salable stock)

Base: provider: `MageOS\AdvancedReport\Model\Stock\SalableStockSelectProvider`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `stock_qty` | Stock quantity | aggregate | sum | `main.stock_qty` |  |  | stock_provider |
| `reserved_qty` | Reserved quantity | aggregate | sum | `main.reserved_qty` |  |  | stock_provider |
| `salable_qty` | Salable quantity | aggregate | sum | `main.salable_qty` |  |  | stock_provider |
| `skus_salable` | SKUs salable | aggregate | count_distinct | `main.product_id` |  |  | stock_provider |

## carts (Carts)

Base table: `quote`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `carts` | Carts | aggregate | count | `main.entity_id` |  |  | timezone |
| `cart_value` | Cart value | aggregate | sum | `COALESCE(main.base_grand_total, 0)` |  |  | timezone, currency |
| `avg_cart_value` | Average cart value | derived |  | `cart_value / carts` |  |  | timezone, currency |
| `items_in_carts` | Items in carts | aggregate | sum | `COALESCE(main.items_qty, 0)` |  |  | timezone |
| `abandoned_carts` | Abandoned carts | aggregate | sum | `provider:cart_abandoned` |  |  | timezone |
| `abandoned_value` | Abandoned value | aggregate | sum | `COALESCE(main.base_grand_total, 0)` |  |  | timezone, currency |
| `recent_carts` | Recent carts | aggregate | sum | `provider:cart_recent` |  |  | timezone |
| `converted_carts` | Converted carts | aggregate | sum | `provider:cart_converted` |  |  | timezone |
| `converted_value` | Converted value | aggregate | sum | `COALESCE(main.base_grand_total, 0)` |  |  | timezone, currency |
| `abandonment_rate` | Abandonment rate | derived |  | `abandoned_carts / (abandoned_carts + converted_carts)` |  |  | timezone |
| `conversion_rate` | Conversion rate | derived |  | `converted_carts / carts` |  |  | timezone |

## cart_items (Cart items)

Base table: `quote_item`

Fan-out dimensions: category_id

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `units_in_carts` | Units in carts | aggregate | sum | `COALESCE(main.qty, 0)` |  | ⚠ by category_id | timezone |
| `cart_items_value` | Cart items value | aggregate | sum | `COALESCE(main.base_row_total, 0)` |  | ⚠ by category_id | timezone, currency |
| `carts` | Carts | aggregate | count_distinct | `main.quote_id` |  |  | timezone |
| `abandoned_units` | Abandoned units | aggregate | sum | `COALESCE(main.qty, 0)` | quote | ⚠ by category_id | timezone |
| `abandoned_items_value` | Abandoned items value | aggregate | sum | `COALESCE(main.base_row_total, 0)` | quote | ⚠ by category_id | timezone, currency |
| `abandoned_carts` | Abandoned carts | aggregate | count_distinct | `main.quote_id` | quote |  | timezone |
| `converted_units` | Converted units | aggregate | sum | `COALESCE(main.qty, 0)` | quote | ⚠ by category_id | timezone |
| `item_conversion_rate` | Item conversion rate | derived |  | `converted_units / units_in_carts` | quote | ⚠ by category_id | timezone |

## rma_requests (Return requests)

Base table: `rma_entity`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `requests` | Return requests | aggregate | count | `main.entity_id` |  |  | timezone |
| `orders_with_requests` | Orders with return requests | aggregate | count_distinct | `main.order_id` |  |  | timezone |
| `customers_with_requests` | Customers with return requests | aggregate | count_distinct | `LOWER(TRIM(main.customer_email))` |  |  | timezone |
| `requests_per_order` | Requests per order | derived |  | `requests / orders_with_requests` |  |  | timezone |
| `units_requested` | Units requested | aggregate | sum | `provider:rma_request_units` |  |  | timezone |
| `withdrawals` | Withdrawals before shipment | aggregate | sum | `provider:rma_is_withdrawal` |  |  | timezone |
| `returns_after_shipment` | Returns after shipment | aggregate | sum | `provider:rma_is_return_after_shipment` |  |  | timezone |
| `mixed_requests` | Mixed requests | aggregate | sum | `provider:rma_is_mixed_request` |  |  | timezone |
| `withdrawal_share` | Withdrawal share | derived |  | `withdrawals / requests` |  |  | timezone |
| `open_requests` | Open requests | aggregate | sum | `(rma_status.code NOT IN ('rejected', 'canceled_by_customer', 'resolved'))` | rma_status |  | timezone |
| `rejected_requests` | Rejected requests | aggregate | sum | `(rma_status.code = 'rejected')` | rma_status |  | timezone |
| `resolved_requests` | Resolved requests | aggregate | sum | `(rma_status.code = 'resolved')` | rma_status |  | timezone |
| `canceled_requests` | Canceled requests | aggregate | sum | `(rma_status.code = 'canceled_by_customer')` | rma_status |  | timezone |
| `rejection_rate` | Rejection rate | derived |  | `rejected_requests / requests` | rma_status |  | timezone |
| `avg_days_to_request` | Avg days from order to request | aggregate | avg | `(GREATEST(0, TIMESTAMPDIFF(SECOND, order.created_at, main.created_at)) / 86400)` | order |  | timezone |
| `avg_days_from_shipment` | Avg days from shipment to request | aggregate | avg | `provider:rma_days_from_shipment` |  |  | timezone |

## rma_items (Returned items)

Base table: `rma_item`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `units_requested` | Units requested | aggregate | sum | `COALESCE(main.qty_requested, 0)` |  |  | timezone |
| `requested_value` | Requested value | aggregate | sum | `provider:rma_item_requested_value` | order_item |  | timezone, currency |
| `requests` | Return requests | aggregate | count_distinct | `main.rma_id` |  |  | timezone |
| `withdrawn_units` | Units withdrawn before shipment | aggregate | sum | `COALESCE(main.qty_requested, 0)` | rma |  | timezone |
| `returned_units_after_shipment` | Units returned after shipment | aggregate | sum | `COALESCE(main.qty_requested, 0)` | rma |  | timezone |
| `units_per_request` | Units per request | derived |  | `units_requested / requests` |  |  | timezone |

## order_returns (Orders and return requests)

Base table: `sales_order`

Fan-out dimensions: none

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `orders` | Orders | aggregate | count | `main.entity_id` |  |  | timezone, counted_statuses |
| `orders_with_requests` | Orders with return requests | aggregate | sum | `provider:rma_order_has_request` |  |  | timezone, counted_statuses |
| `return_requests` | Return requests | aggregate | sum | `provider:rma_order_request_count` |  |  | timezone, counted_statuses |
| `order_return_rate` | Order return rate | derived |  | `orders_with_requests / orders` |  |  | timezone, counted_statuses |
| `requests_per_returned_order` | Requests per returned order | derived |  | `return_requests / orders_with_requests` |  |  | timezone, counted_statuses |
| `orders_with_withdrawals` | Orders with withdrawals | aggregate | sum | `provider:rma_order_has_withdrawal` |  |  | timezone, counted_statuses |
| `orders_with_returns_after_shipment` | Orders with returns after shipment | aggregate | sum | `provider:rma_order_has_return_after_shipment` |  |  | timezone, counted_statuses |
| `withdrawal_rate` | Withdrawal rate | derived |  | `orders_with_withdrawals / orders` |  |  | timezone, counted_statuses |
| `return_after_shipment_rate` | Return after shipment rate | derived |  | `orders_with_returns_after_shipment / orders` |  |  | timezone, counted_statuses |

## order_item_returns (Ordered and returned units)

Base table: `sales_order_item`

Fan-out dimensions: category_id

| Metric | Label | Kind | Aggregate | Expression | Joins | Fan-out | Depends on |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `units_ordered` | Units ordered | aggregate | sum | `provider:bundle_qty_net` |  | ⚠ by category_id | timezone, counted_statuses |
| `units_requested` | Units requested | aggregate | sum | `provider:rma_order_item_units_requested` |  | ⚠ by category_id | timezone, counted_statuses |
| `unit_return_rate` | Unit return rate | derived |  | `units_requested / units_ordered` |  | ⚠ by category_id | timezone, counted_statuses |
| `orders` | Orders | aggregate | count_distinct | `main.order_id` |  |  | timezone, counted_statuses |

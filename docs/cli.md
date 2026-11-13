# Console commands

All commands are trusted CLI tools: shell access is the trust boundary, see [security](security.md#entry-points-and-acl).

## `advanced-report:explain`

Prints, for one preset or all presets, the generated SQL and its `EXPLAIN` output, to check index usage on real data.

```bash
bin/magento advanced-report:explain --from=2026-01-01 --to=2026-03-31
bin/magento advanced-report:explain --preset=sales_overview --from=2026-01-01 --to=2026-01-31
```

`--from` and `--to` (Y-m-d, `from <= to`) are required, `--preset` is optional (default: all presets).

Every statement of a request is explained: `page`, `row_count` (only run when the page is full), `totals`, `comparison_page`,
`comparison_totals`, `currency_probe`, the timezone `fallback` variant and the preset's own query. Further options:

- `--compare=previous_period|previous_year`: include the comparison statements (default: the preset's own comparison).
- `--analyze`: use `EXPLAIN ANALYZE`. **This executes every statement** (bounded by the configured query timeout).
- `--format=table|json`: `json` prints a list of `{preset, statement, sql, plan}` entries.

## `advanced-report:benchmark`

Read-only benchmark of the report scenarios (it only runs the report queries; it never writes data). Each scenario is run
`--repeat` times cold (cache bypassed) and `--repeat` times warm; the output lists cold median and p95, SQL statements, rows
examined, warm median and statements (0 when served from cache) and peak memory. The default matrix covers the dated presets
(`sales_overview`, `new_vs_returning`, `product_performance`, `customer_value`) over 30 days and 12 months, all stores and one
store, with and without comparison, the dateless presets (`stock_value`, `low_stock_coverage`, `dead_stock`) and a few
expensive scenarios.

```bash
bin/magento advanced-report:benchmark --profile-label=medium --repeat=3 --format=markdown
bin/magento advanced-report:benchmark --preset=sales_overview --range=last_30_days --budget-ms=3000
bin/magento advanced-report:benchmark --preset=sales_overview --range=last_30_days --store=0 --compare=none --concurrency=8
```

| Option | Meaning |
| --- | --- |
| `--preset=CODE` | Preset, repeatable (default: the full matrix) |
| `--range=last_30_days\|last_12_months\|custom` | Date range; `custom` needs `--from` and `--to` (Y-m-d) |
| `--store=ID` | Store id, repeatable; `0` = all stores (cannot be combined with other ids). Default: the matrix stores |
| `--compare=none\|previous_period\|previous_year` | Comparison override |
| `--repeat=N` | Cold and warm runs per scenario (default 3) |
| `--format=table\|json\|markdown` | Output format (default `table`) |
| `--output=FILE` | Write the output to a file relative to `var/` |
| `--budget-ms=N` | Scenarios whose cold median exceeds N ms are listed and the exit code is 1 |
| `--profile-label=NAME` | Label of the data profile (small, medium, large) shown in the header |
| `--concurrency=N` | Run exactly one scenario in N (1-32) parallel worker processes and report how many executed SQL or were served from cache. **Cleans the whole `advanced_report` cache type.** |

`--store` and `--compare` (and `--range=custom`) override the store, comparison and range part of the scenario code
(`--range=custom` sets the range part to `custom` plus `@from..to`), and scenarios that become identical run once. Without
overrides the matrix is unchanged. With overrides, an expensive scenario identical to a matrix scenario (for example
`new_vs_returning_12m`) is merged into the matrix one (the first one is kept).

`--worker` and `--start-at` are internal options of the concurrency mode. The command runs under a trusted CLI authorization
(no admin session), see [security](security.md#trusted-cli-authorization-benchmark). The seed, procedure, budgets and the
results table are in [performance](performance.md).

## `advanced-report:inventory`

Prints the metric inventory: for every metric of every dataset its kind, aggregate, expression, joins, fan-out (row multiplication) risk and context dependencies. Output is deterministic, so regenerating it for unchanged definitions gives an empty diff.

```bash
bin/magento advanced-report:inventory                                  # Markdown, all datasets
bin/magento advanced-report:inventory --format=json --dataset=sales_orders
bin/magento advanced-report:inventory --output=docs/metric-inventory.md
```

`--format` (`md` default, or `json`), `--dataset` (default: all) and `--output` (file path, relative to the current directory, instead of stdout) are optional. Regenerate `docs/metric-inventory.md` whenever definitions change with `bin/magento advanced-report:inventory --output=app/code/MageOS/AdvancedReport/docs/metric-inventory.md` from the Magento root (prefer `--output` over redirecting stdout).


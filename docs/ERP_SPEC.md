# IronCore ERP Living Specification

## Project Purpose
IronCore is a multi-company ERP for Canary Islands companies (IGIC regime), built incrementally by release.

## Release Status
- ✅ Release 1: Foundation + documents + vendor bills + expenses + audit
- ✅ Release 2: Inventory ledger + average costing
- ✅ Release 3: Sales/POS/invoice core + PrestaShop ingest (this update)
- ✅ Release 4: Repairs + tablet flow
- ✅ Release 5: Subscriptions / recurring billing
- ✅ Release 6: VeriFactu readiness layer
- ✅ Release 7: Financial visibility + control dashboards

## Release 2 Guardrails (enforced)
1. Default warehouse/location are company-specific and seeded (`MAIN` / `DEF`).
2. Vendor bill stock receiving is idempotent (existing `vendor_bill_line` receipt move is not duplicated).
3. Stock adjustments are manager/admin only and always audit logged.

## Release 2 Inventory Design
- `stock_moves` is the single source of truth.
- `product_costs` caches average cost by company+product.
- Average cost v1 formula:
  - `avg_cost = SUM(receipt_like.total_cost) / SUM(ABS(receipt_like.qty))`
  - receipt-like: `receipt`, `adjustment_in`, `transfer_in`, `return_in`
- Negative stock is allowed; alerts are generated when on-hand drops below zero.

## Release 3 Sales Schema
- `customers` (global)
- `customer_company` (per-company fiscal overrides)
- `sales_documents` (ticket / invoice / credit_note)
- `sales_document_lines`
- `payments`
- `integration_api_tokens`
- `integration_runs`
- `inventory_alerts`

## Release 3 Posting Workflow
- Draft documents are editable.
- Posting is transaction-safe and:
  - assigns sequential `number` per `(company_id, series)`
  - computes totals from lines
  - writes immutable ordered snapshot to `immutable_payload`
  - sets `posted_at`, `locked_at`
  - blocks edits after lock
- Draft cancel supported; posted docs require credit-note correction.
- Credit notes reference source document and can be posted with return stock moves.
- All posting flows call `App\Services\SalesDocumentService` (Filament post action, POS, PrestaShop auto-post, subscriptions auto-post).

## Numbering Rules
- Unique index on `(company_id, series, number)`.
- Full number format: `<SERIES>-<YEAR>-<6-digit-seq>`.

## Inventory Integration from Sales
- On posting ticket/invoice for stock products: create `sale` move (`qty` negative).
- On posting credit note for stock products: create `return_in` move (`qty` positive).
- Outflow costing uses current `avg_cost` fallback and stores `cost_unit`/`cost_total` on lines.
- Avg cost recalculation remains receipt-like only.

## Negative Stock Policy
- Negative stock is explicitly allowed.
- If posting makes on-hand < 0, create `inventory_alerts` with `alert_type=negative_stock`.
- Inventory dashboard exposes:
  - count of negative-stock products
  - negative stock value exposure

## Release 3 UI
- CustomerResource + CustomerCompany relation manager
- SalesDocumentResource (draft/create/edit/post/cancel draft/create credit note)
- POS page (fast entry, optional customer, one-click post ticket)
- Inventory Dashboard enhanced with negative-stock widgets

## PrestaShop Endpoint
- `POST /api/integrations/prestashop/order-paid`
- Token protected via `integration_api_tokens`
- Logs each run in `integration_runs`
- Ingest behavior:
  - map/create customer by email
  - map/create products by sku/barcode (placeholder as service if missing)
  - create draft sales document with `source=prestashop`, `source_ref=order_id`
  - choose ticket vs invoice by `customer_company.wants_full_invoice`
  - auto-post controlled by `PRESTASHOP_AUTO_POST`

## Out of Scope in this iteration
- Release 5 subscriptions
- ✅ Release 6 VeriFactu hash/QR/export logic

## Release 4 Addendum: Customer Signature + Pickup Confirmation Tablet Flow
- Public tablet routes (unauthenticated):
  - `GET /p/repairs/{token}`
  - `POST /p/repairs/{token}/sign`
  - `POST /p/repairs/{token}/feedback`
- Security:
  - one-time `public_tokens` with purpose scoping and expiry (default 30 minutes)
  - token tied to company + repair + purpose
  - token invalidated on successful use (`used_at`)
  - rate-limited endpoints (`throttle:30,1`)
- New persistence:
  - `repair_signatures` (intake/pickup signatures + image hash)
  - `repair_pickups` (pickup confirmation event)
  - `repair_feedback` (1-5 rating + optional comment)
  - `public_tokens` (generic public flow token table)
- Signature capture:
  - touch-friendly HTML canvas posts base64 PNG
  - server stores PNG on configured disk and writes SHA-256 hash for integrity
- Workflow integration:
  - Filament `RepairResource` actions generate intake/pickup/feedback links
  - pickup signature creates pickup record, confirms pickup, and sets repair status to `collected`
  - repairs link to `sales_documents` via `linked_sales_document_id` only (no separate invoices table)
  - configurable rule `REPAIRS_REQUIRE_INVOICE_BEFORE_PICKUP` blocks pickup signature until linked sales document exists and is `posted`
- Reporting:
  - repair signature image viewable from signatures relation
  - pickup receipt PDF generated and stored as `documents` attachment on repair

- Signature files are stored as: `{company_id}/repairs/{repair_id}/{signature_type}/{timestamp}.png` with SHA-256 hash persisted in `repair_signatures.signature_hash`.
- Pickup signature tokens can mint a new feedback token; token purposes are strict and enforced per endpoint.
- If invoice-before-pickup fails, tablet endpoint responds with HTTP 409 and message: `Invoice must be posted before pickup.`

## Release 5: Subscriptions / Recurring Billing
- Policy: this module is generic and must not include domain-specific branding references.
- Subscriptions always generate `sales_documents` through existing invoice core services.

### Entities
- `subscription_plans` (company-scoped templates)
  - `plan_type`: `subscription` or `service_contract`
  - `interval_months` supports 3/6/12 and remains extensible
  - defaults for doc type, series, auto-post, tax and price
- `subscriptions` (company-scoped recurring contracts)
  - optional plan link and per-subscription override fields
  - states: `active`, `paused`, `cancelled`
  - run fields: `starts_at`, `next_run_at`, optional `ends_at`
- `subscription_items`
  - line-level recurring items for future expansion
- `subscription_runs`
  - immutable run log for success/skipped/failed attempts

### Scheduling and execution
- Command: `php artisan subscriptions:run-due`
- Scheduler frequency: hourly
- Service: `SubscriptionBillingService`
  - computes stable next run via month interval
  - processes due subscriptions (`next_run_at <= now`)
  - skips paused/cancelled and ended subscriptions with run log entries
  - handles per-subscription failures without stopping batch

### Sales document generation
- Effective config resolution order:
  1. subscription overrides
  2. plan defaults
  3. company settings fallback (series)
- Creates draft `sales_documents` with source ref format:
  - `subscription:{subscription_id}:{run_date}`
- Line source:
  - `subscription_items` if present
  - fallback single line from plan/subscription settings
- If `auto_post` is true, document is posted and locked via existing sales posting workflow.
- Each attempt writes `subscription_runs` and audit logs.


## Release 6 VeriFactu Compliance
- Official source list tracked in `docs/VERIFACTU_SOURCES.md`.
- Registry tables:
  - `verifactu_exports`
  - `verifactu_export_items`
  - `verifactu_events`
- Sales posting hook:
  - implemented inside `App\Services\SalesDocumentService::post()` transaction
  - calculates `previous_hash` by chain scope `(company_id + series)`
  - canonicalizes immutable payload deterministically
  - computes SHA-256 hash
  - generates and stores `qr_payload`
  - persists hash fields atomically with posting/number allocation

### Chain scope decision
- Implemented chain scope: `company_id + series`.
- Isolated chains by company and by series within company.

### Canonicalization strategy
- Deterministic array payload with stable key ordering.
- Canonical source fields include issuer id, invoice identifier, date, totals, previous hash, and normalized lines.
- Numeric normalization uses fixed decimal formatting for stable hashing.

### Export process
- Command: `php artisan verifactu:export --company=ID --from=YYYY-MM-DD --to=YYYY-MM-DD`
- Export steps:
  1. select posted documents in period
  2. build deterministic record payloads
  3. write export file under `storage/app/verifactu/...`
  4. compute file SHA-256
  5. create `verifactu_exports` + `verifactu_export_items`
  6. write audit event (`verifactu.export.generated`)

### CI source of truth
- GitHub Actions workflow added to run `composer install`, migrations, and `php artisan test --testsuite=Feature` on push/PR.


## Release 7 Financial Visibility + Control

### Snapshot strategy
- Cached metrics persisted in `report_snapshots` (`daily` / `weekly`) with payload JSON.
- Optional drilldowns persisted in `report_snapshot_items`.
- Idempotent generation uses update-or-create on `(company_id, snapshot_type, snapshot_date/week_start_date)`.

### Metrics definitions included in payload
- **Sales / Margin**: `revenue_gross`, `revenue_net`, `tax_total`, `cogs_total`, `gross_profit`, `gross_margin_percent`, `negative_margin_documents`, top products by profit/revenue, `below_cost_sales_last_7_days`.
- **Repairs**: `repairs_count`, `repairs_invoiced_count`, `repairs_total_billed_net`, `repairs_total_billed_gross`, `repair_labour_net`, `repair_parts_cogs`, `unbilled_time_minutes`, `billed_time_vs_logged_ratio`.
- **Inventory**: `stock_value`, `negative_stock_count`, `negative_stock_value_exposure`, dead-stock counters and top dead stock by value.
  - `stock_value` only values **positive on-hand** (`max(0, on_hand) * avg_cost`), so oversold inventory is tracked in exposure but does not reduce valuation.
- **Subscriptions**: `active_subscriptions_count`, `mrr_estimate`, `upcoming_renewals_7d`, `upcoming_renewals_30d`, `failed_runs_7d`.
- **Cash discipline**: `unpaid_vendor_bills_count`, `bills_due_7d`, `bills_due_30d`.


### Release 7.1 Control Pack A clarifications
- **COGS sign convention**: `sales_document_lines.cost_total` is treated as an absolute positive cost amount.
  - Posted ticket/invoice COGS contribute positively.
  - Credit note COGS are netted by subtracting their absolute COGS from sales COGS.
- **Repair parts COGS source**: computed from `repair_parts.line_cost` for now (no `repair_line_items` table exists in current schema).
- **Sell-below-cost controls**:
  - POS and Sales Document posting flows show per-line margin estimate (`line_net - estimated_cost_total`).
  - If any line is below cost, only `manager`/`admin` can post, and an override reason is mandatory.
  - Override writes audit event/action `below_cost_override` with reason and affected line details.

### Commands + scheduling
- `php artisan reports:snapshot-daily --date=YYYY-MM-DD --company=ID(optional)`
- `php artisan reports:snapshot-weekly --week-start=YYYY-MM-DD --company=ID(optional)`
- Scheduler:
  - daily snapshot: `02:00`
  - weekly snapshot: Sundays `03:00`

### Filament pages/resources added
- `CompanyPerformanceDashboard`
- `RepairProfitabilityReport`
- `InventoryValueRiskReport`
- `SubscriptionsOverviewReport`

### Lightweight exports
- CSV export endpoint for:
  - sales margin report
  - dead stock list
  - negative stock list
  - repair profitability list


## Release 7.2 Control Packs B + C
- Repairs config defaults: `time_leak_threshold_minutes=15`, `require_labour_if_time_logged=true`, `manager_override_requires_reason=true`, `labour_rate_per_hour_net=60.00`, `default_tax_rate=7.0`.
- Repair leakage controls add `repair_line_items` labour quick-add and enforce manager/admin override for `ready` / `collected` transitions when logged time exceeds threshold without labour lines.
- Audit events: `repair_time_leak_override`, `repair.labour_quick_add`, and `supplier_cost_increase`.
- Supplier drift controls add `supplier_product_costs` history and line-level flags (`vendor_bill_lines.cost_increase_flag`, `cost_increase_percent`) when unit cost rises >5%.
- Dead-stock exports now include `last_moved_at` and `on_hand_qty` in addition to product/value aging fields.


## Release 8 Purchasing Intelligence + Reorder Suggestions

### Data model
- `product_reorder_settings`: per-company per-product reorder controls (`lead_time_days`, `safety_days`, cover targets, min order, pack size, preferred supplier).
- `supplier_stock_snapshots` + `supplier_stock_snapshot_items`: external supplier warehouse stock snapshots imported via CSV/UI/API.
- `reorder_suggestions` + `reorder_suggestion_items`: cached generation runs and per-product recommendations with quantity/spend/reason metadata.

### Reorder algorithm
For each enabled stock product:
1. Compute `avg_daily_sold` from posted tickets/invoices net of credit notes over selected period (30/60/90 days).
2. Compute current `on_hand` from stock ledger sums; negative stock remains allowed.
3. Compute required coverage window:
   - `required_window_days = lead_time_days + safety_days + min_days_cover`
4. Compute raw suggestion:
   - `target_qty = avg_daily_sold * required_window_days`
   - `suggested_qty = max(0, target_qty - max(0, on_hand))`
   - if `on_hand < 0`, urgency reason includes negative exposure (`abs(on_hand)`).
5. Apply rounding rules:
   - enforce `min_order_qty` if configured
   - round up to `pack_size_qty` multiple when configured
6. Estimate spend using supplier cost priority:
   - `supplier_product_costs.last_unit_cost` first
   - else latest supplier snapshot item `unit_cost`

### Supplier stock import formats
- Filament page supports CSV upload with explicit column mapping:
  - `supplier_sku`, `barcode`, `product_name`, `qty_available`, `unit_cost`, `currency`, optional `sku`
- API endpoint: `POST /api/integrations/supplier-stock/import` (integration token protected)
  - accepts supplier, warehouse, and items array payload.
- Matching priority on import:
  1) barcode
  2) sku
  3) supplier_sku fallback to internal sku match

### Filament pages and exports
- `ReorderSuggestions`: generate + filter + CSV export for latest suggestion set.
- `SupplierStockImport`: CSV import UI for supplier warehouse stock.
- `SupplierStockSnapshots`: snapshot listing with matched/unmatched visibility and placeholder creation action for unmatched rows.
- Export type added: `reorder-suggestions` via `/reports/export/reorder-suggestions`.

## Release 9 — Attribution, KPI Layer, and Purchasing Execution

### Attribution model and permissions
- Added `store_locations` table (`company_id`, `name`, optional `code`, `address`, `is_active`) with unique `(company_id, name)`.
- Added `user_store_locations` pivot for staff-to-store assignments.
- Added nullable `store_location_id` to `sales_documents`, `repairs`, and `vendor_bills`.
- Added `technician_user_id` on repairs for technician attribution.
- Added `User::isManagerOrAdmin()` and `User::assignedStoreLocationIds()` helpers.
- Resource-level store scoping:
  - non-manager users can only see records in assigned stores.
  - manager/admin users see all stores.
  - non-manager create/edit requires `store_location_id`.

### KPI definitions and payload structure
`report_snapshots.payload` now includes:
- `kpi`
  - `sales_margin_percent`
  - `repairs_throughput` (`created`, `invoiced`, `collected`)
  - `time_leakage_rate`
  - `below_cost_overrides`
  - `subscription_mrr`
- `breakdown_by_store`
  - per store: revenue net/gross/tax, avg basket value, repairs created/collected
- `breakdown_by_user`
  - per user: revenue net/gross, gross profit estimate, avg basket, minutes logged, labour billed net and ratio (best effort)

Commands:
- `reports:kpi-daily --date= --company=`
- `reports:kpi-weekly --week-start= --company=`

### Purchasing execution lifecycle
Operational purchase tracking (non-accounting):
- `purchase_plans`
  - status lifecycle: `draft` → `ordered` → `partially_received` / `received` (or `cancelled`)
  - optional `store_location_id`, optional `supplier_id`, planned/ordered/expected dates
- `purchase_plan_items`
  - carries suggested/ordered/received quantities and estimated cost
  - links back to `reorder_suggestion_items` where created from reorder

Flow:
1. Reorder suggestions page can create a purchase plan from latest suggestion items.
2. Purchase plan can be marked as ordered.
3. Receive-items action increments `received_qty` and auto-updates item/plan status.
4. Optional vendor bill linkage (`vendor_bills.purchase_plan_id`) syncs received quantities by matching posted bill lines to plan items.

### Operational boundary
- Purchase plans in Release 9 are **operational execution tracking only**.
- Inventory ledger posting remains with existing vendor bill stock receiving logic.
- No full procurement accounting workflow is introduced in Release 9.

## Release 10 — Accountant Export Pack + Vendor Bill IGIC tightening

### Vendor bill IGIC tightening
- `vendor_bill_lines.tax_rate` added as decimal(5,2).
- Backfill logic for legacy rows:
  - if `net_amount > 0`: `tax_rate = round((tax_amount / net_amount) * 100, 2)`
  - else: `tax_rate = 0.00`
- For MySQL/PostgreSQL deployments, column is tightened to NOT NULL with default `0.00` after backfill.
- Vendor bill line editor now requires `tax_rate` and auto-calculates:
  - `tax_amount = net_amount * tax_rate / 100`
  - `gross_amount = net_amount + tax_amount`

### Accountant export pack
New tables:
- `accountant_export_batches`
- `accountant_export_files`

Service:
- `AccountantExportService::generateBatch(company_id, from, to, breakdown_by_store, user_id)`

Generated files:
- Sales:
  - `sales_docs.csv`
  - `sales_lines.csv`
  - `sales_igic_summary.csv` (grouped by line-level `tax_rate`, optional store split)
- Purchases:
  - `vendor_bills.csv`
  - `vendor_bill_lines.csv` (includes `tax_rate`)
  - `purchase_igic_summary.csv`
- Net summary:
  - `igic_summary.csv` with `output_tax_total`, `input_tax_total`, `net_payable_estimate`

Rules:
- Credit notes reduce taxable base and IGIC totals (netting).
- Grouping uses line-level tax rates.
- Optional store breakdown is available in export generation.

Bundle & integrity:
- All CSVs are zipped into a single batch ZIP.
- ZIP SHA256 and per-file SHA256 hashes are stored for traceability.

### Filament UI
- Added `AccountantExportBatchResource`.
- Users can generate accountant packs using period presets (month, quarter, custom), review summary metadata and download ZIP files.

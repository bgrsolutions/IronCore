# IronCore ERP Living Specification

## Project Purpose
IronCore is a multi-company ERP for Canary Islands companies (IGIC regime), built incrementally by release.

## Release Status
- ✅ Release 1: Foundation + documents + vendor bills + expenses + audit
- ✅ Release 2: Inventory ledger + average costing
- ✅ Release 3: Sales/POS/invoice core + PrestaShop ingest (this update)
- ⛔ Release 4+ not started in this iteration

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
- Release 4 repairs
- Release 5 subscriptions
- Release 6 VeriFactu hash/QR/export logic

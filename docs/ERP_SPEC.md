# IronCore ERP Living Specification

## Project Purpose
<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
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
=======
IronCore is a multi-company ERP for Canary Islands companies operating under IGIC. Release 1 delivers a usable operational foundation with secure document vault, supplier bills, expenses, auditability, and admin UI.

## System Architecture Overview
- **Backend:** Laravel 11 + PHP 8.3
- **Admin UI:** Filament v3 resources
- **Auth/RBAC:** Spatie Laravel Permission roles (`admin`, `manager`, `staff`, `accountant_readonly`)
- **Storage:** S3/MinIO-compatible document storage
- **Core pattern:** multi-company context with global scoping and explicit company selector

## Data Model Summary (Release 1)
- `companies`: minimal company master (`name`, `tax_id`)
- `company_settings`: single source of truth for fiscal configuration and invoice series prefixes JSON
- `user_company`: user-company membership
- `suppliers`
- `documents`: file metadata records only
- `document_attachments`: many-to-many polymorphic attachment links (`document_id` <-> `attachable`)
- `tags`, `taggables`
- `vendor_bills`, `vendor_bill_lines`
- `expenses`, `expense_lines`
- `audit_logs`

## Entity Definitions
- **Company** has one **CompanySetting**, many users/suppliers/bills/expenses/documents.
- **Document** can attach to many entities through **DocumentAttachment**.
- **VendorBill/Expense** follow status workflow and lock on posting.
- **AuditLog** is append-only and records sensitive actions.

## UI Resources (Filament)
- Companies (admin)
- Users (admin)
- Suppliers
- Documents (upload + tag + supplier link)
- Vendor Bills
- Expenses
- Tags
- Audit Logs (read-only)
- Company Context Switcher page

## Workflows & State Machines
- Vendor Bill: `draft -> approved -> posted -> cancelled`
- Expense: `draft -> approved -> posted -> cancelled`
- Posting computes totals from lines, sets `posted_at` and `locked_at`.
- Locked records are immutable.
- Deletion policy:
  - posted/locked: never deletable
  - draft: admin-only deletion with audit log
- Cancellation requires reason and audit log entry.

## Compliance Design Notes (VeriFactu Readiness)
- Company fiscal setup centralized in `company_settings` for deterministic configuration.
- Invoice series prefixes stored in JSON to align with future multi-series requirements.
- Audit logging is enabled for approval/post/cancel/delete/override attempts.

## Release 1 Checklist
- [x] Corrected documents architecture for multi-attachment support (`document_attachments`).
- [x] Enforced cancellation-based lifecycle for financial records.
- [x] Added Eloquent models and relationships for Release 1 tables.
- [x] Added service-layer posting/locking logic for vendor bills and expenses.
- [x] Added Filament resources and company-context scaffolding.
- [x] Added roles + default company + admin + default company settings seeders.

## Assumptions
- Full Laravel + Filament runtime wiring (providers/routes/views) is handled in the host application bootstrap.
- S3 disk is configured as `s3` for document uploads.

## TODO Roadmap
- Harden policy classes and gate checks per resource action.
- Add end-to-end HTTP/Filament tests.
- Begin Release 2 only after UI validation pass in deployed app.


## Release 1 Runtime Verification
- Laravel bootstrap and Filament admin panel are now wired and runnable locally.
- Docker compose includes MariaDB, Redis, and MinIO for local parity.
- Filament workflows support bill/expense draft->approved->posted->cancelled with locking and audit logs.
- Document upload/download is enabled and attachments can be linked to vendor bills and expenses via relation managers.
>>>>>>> main

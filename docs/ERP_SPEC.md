# IronCore ERP Living Specification

## Project purpose
IronCore is a multi-company ERP designed for Canary Islands businesses under the IGIC regime. It is intended to replace Holded with a modular architecture that includes accounting, purchasing, inventory, sales/POS, repairs, subscriptions, and compliance capabilities.

This specification is **living** and must be updated after every release.

## System architecture overview
- **Backend**: Laravel 11 (PHP 8.3)
- **Database**: MariaDB
- **Queue / Async**: Redis + Horizon
- **Admin UI**: Filament v3
- **Permissions**: Spatie Laravel Permission
- **Object storage**: S3-compatible (MinIO in local environments)
- **Integration layer**: REST API (PrestaShop + future integrations)

### Architectural principles
1. Multi-company tenant model from day one.
2. `company_id` is mandatory on all transactional entities.
3. Shared master entities with company overrides:
   - `products` + `product_company`
   - `customers` + `customer_company`
4. Immutable posting for statutory documents.
5. Soft delete / no hard delete for regulated documents and inventory events.
6. Full sensitive-action observability via `audit_logs`.

## Data model summary

### Release 1 entities
- `companies`
- `company_settings`
- `user_company`
- `suppliers`
- `documents`
- `tags`
- `taggables` (polymorphic many-to-many)
- `vendor_bills`
- `vendor_bill_lines`
- `expenses`
- `expense_lines`
- `audit_logs`

### Release 2 entities
- `products` (global)
- `product_company` (per-company override and costing state)
- `warehouses`
- `locations`
- `stock_moves` (event-sourced inventory source of truth)

### Release 3 entities
- `customers` (global)
- `customer_company` (per-company override)
- `sales`
- `invoices`
- `invoice_lines`

### Release 4 entities (started)
- `repairs`
- `repair_status_history`
- `repair_time_entries`
- `repair_parts`

## Entity definitions

### companies
Holds legal company context and invoice series prefixes (`T/F/NC`) for future invoice core.

### company_settings
Stores runtime defaults and fiscal metadata:
- `tax_regime_label` (default: IGIC)
- `default_currency` (default: EUR)

### user_company
Pivot for multi-company user membership.

### suppliers
Per-company supplier master with contact information.

### documents
Polymorphic document vault metadata with S3 object key references and status.
Supports attachment to bills/expenses and future entities.

### tags + taggables
Tagging and indexed retrieval across documentable entities.

### vendor_bills + vendor_bill_lines
Vendor AP documents with draft/approved/posted lifecycle.
Posted bills are immutable (`locked_at`, `posted_at`).

### expenses + expense_lines
Direct spending records with receipt attachment support and posting lock.

### audit_logs
Append-only audit trail for:
- create/update
- status transitions
- approvals
- document attachment/detachment
- override attempts against locked records

### products / product_company
Release 2 inventory foundation using global product master with per-company operational/costing state.

### warehouses / locations
Physical stock topology per company.

### stock_moves
Single source of truth inventory events:
- receive (from vendor bill)
- issue
- adjustment
- transfer
Each row represents an immutable movement with signed quantity delta.

### customers / customer_company
Global customer identity with per-company defaults and commercial overrides.

### invoices / invoice_lines
Release 3 invoice core with series type support (`T`, `F`, `NC`), per-company numbering, immutable posting fields, and compliance-reserved fields.

### sales
Sales transaction shell linked to invoice and integration source (`manual`, `prestashop`).

### repairs / repair_status_history
Repair order lifecycle from intake to invoiced with explicit transition history.

### repair_time_entries
Mandatory labour timer entries constrained to products representing 15, 30, and 60 minute services.

### repair_parts
Consumed inventory parts linked to repair jobs; each part consumption results in inventory stock movement events.

## UI resources (Filament target resources)
- CompanyResource
- SupplierResource
- DocumentResource
- VendorBillResource
- ExpenseResource
- ProductResource
- WarehouseResource
- StockMoveResource (read-only)
- CustomerResource
- InvoiceResource
- SaleResource
- RepairResource
- RepairStatusHistoryResource (read-only)
- RepairTimeEntryResource
- RepairPartResource
- AuditLogResource (read-only)
- Dashboard widgets:
  - Spend This Month
  - Unpaid Bills
  - Bills Awaiting Approval
  - Dead Stock Ageing (30/60/90/180)

## Workflows & state machines

### Vendor bill
`draft -> approved -> posted`
- editable while `draft`
- approval action logs approver and timestamp
- posting sets `posted_at` + `locked_at` and blocks further mutation
- posting triggers inventory receiving event (Release 2 wiring service)

### Expense
`draft -> approved -> posted`
- receipt attachment allowed in draft/approved
- posting locks immutable totals and metadata

### Inventory movement
- Receiving from posted vendor bill creates positive `stock_moves`
- stock adjustments create signed movement events
- no direct stock quantity writes; availability is computed from movement aggregation
- average cost recalculated per product/company on each receipt

### Invoice core
`draft -> posted`
- series type required (`T/F/NC`)
- numbering assigned on posting by company+series
- payload snapshot persisted at posting
- posted invoices immutable
- corrections only via credit note (`NC`) linked through `credit_note_id`

### PrestaShop ingest (Release 3)
`order paid -> draft invoice -> post`
- emits `T` by default
- emits `F` when customer fiscal identity is available

### Repairs workflow (Release 4)
`intake -> diagnosing -> awaiting_approval -> in_progress -> waiting_parts -> ready -> collected -> invoiced`
- mandatory `Diagnostic Fee` auto-added at intake (net 45.00 + IGIC)
- manager-only override requires reason + audit log
- timer entries mandatory and restricted to 15/30/60 labour products
- part consumption creates stock movement output events

## Compliance design notes (VeriFactu readiness)
- Invoice-core fields present from Release 3:
  `hash`, `previous_hash`, `qr_payload`, `posted_at`, `locked_at`, `void_reason`, `credit_note_id`, `export_batch_id`.
- Immutable posting model introduced in Release 1 and expanded in Release 3.
- Audit logging and payload snapshot patterns are introduced as preconditions for Release 6.
- AEAT-specific hash chaining and QR generation are deferred to Release 6 and must use official AEAT specifications.

## Release checklist

### Release 1 checklist
- [x] Multi-company foundation tables and pivots
- [x] Role seed design for `admin`, `manager`, `staff`, `accountant_readonly`
- [x] Suppliers + document vault + tagging schema
- [x] Vendor bills + lines + posting lock workflow logic
- [x] Expenses + optional lines + posting lock fields
- [x] Audit log append-only schema + service helper
- [x] Dashboard metrics query contract
- [x] Tests for bill posting and document attachment logic

### Release 2 checklist
- [x] Inventory schema foundations
- [x] Average-cost movement service contract
- [x] Dead-stock ageing service (30/60/90/180)
- [x] Integration service from posted vendor bills to stock movements
- [x] Inventory KPI service contracts

### Release 3 checklist
- [x] Customers + customer_company schema
- [x] Invoice core schema with immutable and compliance fields
- [x] Series numbering + posting service contracts (`T/F/NC`)
- [x] PrestaShop paid-order ingest service contract
- [ ] POS barcode flow UI
- [ ] Return flow to credit note UI actions

### Release 4 checklist (started)
- [x] Repairs schema (`repairs`, history, timer, parts)
- [x] Diagnostic fee policy service contract (auto-add + manager override)
- [x] Repair status transition guardrails
- [x] Repair labour timer service contract (15/30/60)
- [x] Parts consumption to stock movement service contract
- [ ] Repair intake/board UI resources
- [ ] Repair to invoice orchestration

## Assumptions
1. Laravel app bootstrap and vendor dependencies exist outside this patch scope.
2. Soft deletes are used where no-hard-delete policy is required.
3. Currency handling is decimal fixed-point (`decimal(14,2)`), quantity precision `decimal(14,4)`.
4. Release 3-4 services are domain-layer contracts pending full application wiring to controllers/resources/events.

## TODO roadmap
- Release 3 complete POS interface, sales orchestration, and return-to-credit-note UI.
- Release 4 complete repair board UI and repair->invoice closeout.
- Release 5 generic subscriptions and recurrence engine.
- Release 6 VeriFactu official AEAT hash-chain + QR + export registry.
- Release 7 management reporting and accountant export pack.

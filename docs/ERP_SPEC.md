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

### Release 2 entities (initiated after Release 1)
- `products` (global)
- `product_company` (per-company override and costing state)
- `warehouses`
- `locations`
- `stock_moves` (event-sourced inventory source of truth)

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

## UI resources (Filament target resources)
- CompanyResource
- SupplierResource
- DocumentResource
- VendorBillResource
- ExpenseResource
- AuditLogResource (read-only)
- Dashboard widgets:
  - Spend This Month
  - Unpaid Bills
  - Bills Awaiting Approval

## Workflows & state machines

### Vendor bill
`draft -> approved -> posted`
- editable while `draft`
- approval action logs approver and timestamp
- posting sets `posted_at` + `locked_at` and blocks further mutation

### Expense
`draft -> approved -> posted`
- receipt attachment allowed in draft/approved
- posting locks immutable totals and metadata

### Inventory movement (Release 2)
- Receiving from posted vendor bill creates positive `stock_moves`
- stock adjustments create signed movement events
- no direct stock quantity writes; availability is computed from movement aggregation

## Compliance design notes (VeriFactu readiness)
- Invoice-core fields reserved from Release 3:
  `hash`, `previous_hash`, `qr_payload`, `posted_at`, `locked_at`, `void_reason`, `credit_note_id`, `export_batch_id`.
- Immutable posting model introduced in Release 1 for bills/expenses to align with future statutory behavior.
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

### Release 2 checklist (started automatically after Release 1)
- [x] Inventory schema foundations
- [x] Average-cost movement service contract
- [x] Dead-stock ageing service (30/60/90/180)
- [ ] Integration from posted vendor bills to stock movements (application event wiring)
- [ ] Inventory dashboard/report widgets

## Assumptions
1. Laravel app bootstrap and vendor dependencies exist outside this patch scope.
2. Soft deletes are used where no-hard-delete policy is required.
3. Currency handling is decimal fixed-point (`decimal(14,2)`), quantity precision `decimal(14,4)`.
4. Invoice-core release implementation starts in Release 3; current release prepares constraints and conventions.

## TODO roadmap
- Release 2 complete event listeners from vendor bill posting to `stock_moves`.
- Release 3 sales, customers, and POS flows with immutable invoice posting.
- Release 4 repairs workflow with mandatory diagnostic fee and time tracking.
- Release 5 generic subscriptions and recurrence engine.
- Release 6 VeriFactu official AEAT hash-chain + QR + export registry.
- Release 7 management reporting and accountant export pack.

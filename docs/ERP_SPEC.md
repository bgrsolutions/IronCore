# IronCore ERP Living Specification

## Project Purpose
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

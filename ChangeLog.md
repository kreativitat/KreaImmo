# CHANGELOG MODULE KREAIMMO FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Unreleased

## [1.1.0] - 2026-11-21

- Add authenticated, exact-company read APIs for owners, buildings, properties,
  renters, leases, receipts, payments, costs and meters, with matching KreaMCP
  contracts, fixed public projections, native permissions and bounded pagination.
- Add confirmed preview-first management for all nine resources, with native
  CRUD/status lifecycles, state/request/plan hashes, transactional receipt payment
  summaries, explicit bank posting and safe native payment deletion.
- Preserve monetary strings, native zero-to-NULL behavior, parent ownership and
  renter commercial visibility. Protect canonical company documents and ECM
  attachments during deletion/reference changes. Retain existing renter routes.
- Restore the native receipt-validation trigger call before exposing validation.
- Keep native receipt/renter validation document renames in the verified current
  company output directory, preserving other-company directories and files.

## 1.0

Initial version

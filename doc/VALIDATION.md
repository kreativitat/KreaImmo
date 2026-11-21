# KreaImmo validation

Validation date: 2026-11-21. Module version: 1.0.0, module ID 156571.

## Runtime and isolation

**96/96 standalone integration checks passed**, including a clean installation, schema replay, retained sentinel records and all 39 expected module tables.

**96/96 standalone integration checks pass**, including clean installation, repeat activation, all 39 declared module tables and preservation of an existing sentinel record.

Tests use Dolibarr 24.0.2, PHP 8.2.35 and MariaDB 11.4.13 in disposable containers. The application and database share an internal network, with a localhost proxy solely for browser/API acceptance. No real ERP database, credentials, business records, email, external service or automatic job is used. Synthetic company fixtures use entities 2 and 3; HTTP fixtures use entity 1 in the same disposable database.

## Acceptance evidence

- Native module installation, repeated schema loading and preservation of a sentinel business record.
- Standalone integration checks cover owner, property, renter, lease, receipt, cost, meter and payment CRUD; auxiliary parent-owned records and dictionaries; reference/list helpers; monetary decimal readback; transactional cleanup; company read/write/FK/clone/setter denial.
- Renter API class checks cover missing/read-only grants, CRUD, pagination/filter boundaries, protected fields and external users' third-party boundaries.
- Actual HTTP renter API checks pass: authentication denial, create, fetch, update, list, delete, deleted-record absence and entity mass-assignment denial.
- Actual session/CSRF owner page create, update and delete pass. Direct-page missing grants, read-only create/delete, another domain's access and missing CSRF are denied. A granular owner read grant is accepted without a module-wide read grant.
- Actual receipt PDF generation produces a valid PDF; foreign-company document generation is denied. Full lease PDF generation and visual PDF layout remain unverified.
- Synthetic bank posting checks cover foreign accounts/lines, duplicate retries, reconciled-line deletion denial and native bank-line/URL cleanup.
- Rendered dashboard and property create forms fit at widths 320, 390, 768 and 1280 pixels, without document horizontal scrolling. Owner creation and native page scoping are also inspected. The module layout class is absent on core pages.
- PHP syntax checks, installer archive coverage, original-name residue checks, Git diff checks and a redacted Gitleaks source scan are part of publication validation.

## Boundaries

The declared Dolibarr 19/PHP 7.4 minimums are not a separately executed compatibility matrix. Browser inspection samples the dashboard and creation forms; it does not automate every possible field combination or third-party theme. The optional external tenant portal is not exercised end to end; its inherited meter submission expects configured meter type ID 1. External services and real bank operations are not exercised. Concurrent bank requests, visual PDF layout and full lease PDF generation remain unverified. Source publication does not deploy or activate the module in any ERP company.

Module-specific paths, tables, classes, helpers, constants, object identities, document models, menus and assets use KreaImmo identifiers. Existing contributor copyright and license notices are retained. Disabling the module retains its tables and business records; rollback must preserve database and documents. Keep automatic jobs disabled in test installations.

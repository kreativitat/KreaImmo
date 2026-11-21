# Disposable integration checks

`run.php` is a standalone CLI integration runner using real Dolibarr business classes and its native `DoliDB` connection. It covers synthetic owner/property/renter/rent/receipt/cost/meter/payment and auxiliary-object CRUD, decimal values, transaction rollback, company isolation, shared-dictionary ownership, native setters, element/entity hooks, and the renter API's authorization, external-user visibility and mass-assignment boundaries. Synthetic bank posting tests account/line identity, denied foreign links, retry deduplication and deletion. Receipt PDF checks generate a real PDF and deny foreign-company generation. PHP warnings fail checks; PHP 8.2 deprecation notices are excluded for supported older Dolibarr code.

Prepare a **disposable**, isolated MariaDB server with no existing business data and an empty root password. The bundled `bootstrap.php` connects to `127.0.0.1:3306`, creates only `kreaimmo_clean`, loads native core table DDL, and performs native module activation plus schema replay around a synthetic preservation record. It never loads `conf.php`. Module activation metadata is reset through Dolibarr's native removal method before activation; its tables and records are retained. Core prerequisite indexes and core seed/business data are not imported. The JSON distinguishes a fresh module installation from replay on an existing fixture.

```sh
KREAIMMO_TEST_ALLOW_WRITES=1 \
php test/integration/run.php
```

The bootstrap discovers the parent Dolibarr source or accepts `KREAIMMO_TEST_DOL_DOCUMENT_ROOT`. A container may override `KREAIMMO_TEST_DB_HOST` and `KREAIMMO_TEST_DB_PORT` to reach its disposable database. `KREAIMMO_TEST_BOOTSTRAP` can select a different explicit fixture bootstrap; the runner still refuses any database other than `kreaimmo_clean` and any document root outside `/tmp/kreaimmo-*`.

The runner uses synthetic `.invalid` addresses and an outer transaction, rolled back in `finally`. Schema DDL runs before that transaction. Do not run this against an existing ERP database, cloned business database, or production. No production service, real financial record, external API, email or automatic job is exercised. Native bank entries are created only in the disposable synthetic database and rolled back with the fixture.

The JSON report and exit status distinguish passing and failing checks. REST checks invoke the actual API class with native Dolibarr user permission objects; they do not verify HTTP routing/session middleware. Bank retries are sequential; concurrent-request scheduling requires a separate test. Browser layout, CSRF HTTP flows and visual PDF layout require separate acceptance checks. Generated PDFs remain in the dedicated temporary document root until the disposable container or directory is removed.

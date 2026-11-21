# KreaImmo

Real-estate management for Dolibarr: owners, buildings, properties, tenants, leases, rent receipts, payments, costs, meters and rent indices.

## Requirements and identity

- Dolibarr 19 or newer; PHP 7.4 or newer.
- Module ID **156571**, within Kreativität Works' registered **156550–156599** range.
- Independent `kreaimmo_*` and `c_kreaimmo_*` tables, configuration, documents, PHP classes, object identities and menus.
- English, European Portuguese, French and German language files are included.
- GPL-3.0-or-later; existing contributor and bundled dependency notices are retained.

## Installation

Place this directory at `htdocs/custom/kreaimmo`, then enable **KreaImmo** in Dolibarr's module administration for the intended company. Activation creates its own tables and reference dictionaries. Existing data from another module is not imported automatically. Back up the database and documents before activation on an existing installation.

Setup: `admin/immoreceipt.php`. Home: `kreaimmoindex.php`. Automatic jobs are not registered. Staff pages require an authenticated internal user and native module permissions. The separately enabled tenant portal requires an authenticated, linked external contact; readings also require meter write permission. New users receive no module permissions by default. The renter REST API additionally applies native third-party visibility and company ownership.

Business records belong to the current company; child records inherit ownership through their parent. Shipped static reference dictionaries use entity 0. Company-created dictionary entries remain company-owned. Rent indices and renter types are shared reference tables without an entity column.

## Build and verification

With PHP's Zip extension available, run `php build/buildzip.php` from this directory. The installer archive is written to the system temporary directory as `module_kreaimmo-1.0.0.zip`.

See [validation evidence](doc/VALIDATION.md) and [disposable integration checks](test/integration/README.md). The standalone integration runner is not PHPUnit and must only use a newly created, disposable database. It never loads an installation's credentials or starts scheduled jobs.

The declared minimum versions are distinct from the validated runtime. Current integration validation uses Dolibarr 24.0.2, PHP 8.2.35 and MariaDB 11.4.13. Production deployment and activation are separate operations from publishing source.

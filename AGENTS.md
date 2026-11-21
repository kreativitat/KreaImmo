# KreaImmo maintainer guide

KreaImmo is a GPL-3.0-or-later external Dolibarr real-estate module. Preserve existing contributor notices and third-party licenses.

- Source root: this directory, installed as `htdocs/custom/kreaimmo`.
- Descriptor: `core/modules/modKreaImmo.class.php`; module ID **156571**, registered range **156550–156599**. KreaTasty uses **156572** and retains its existing permission IDs.
- Declared minimums: Dolibarr 19 and PHP 7.4. Validation currently uses Dolibarr 24.0.2, PHP 8.2.35 and MariaDB 11.4.13.
- Use the installed `skill-doli-devmodule` and applicable Codex Software Team rules. Root coordinates specialists, Git and environment operations.
- The user's maintained branch is **main**. Task-authorized commits and pushes go to the verified repository; preserve unrelated edits. Do not commit PHPUnit files without explicit authorization.
- Use Dolibarr native objects, forms, permissions, CSRF and database APIs. Search the installed core before adding helpers. PHP indentation uses tabs; technical text is English, `pt_PT` is European Portuguese.
- Module business tables use `kreaimmo_*`; never adopt another module's tables, configuration or documents. Use `MAIN_DB_PREFIX` in PHP.
- Company-owned records use the current company. Child records inherit ownership through their parent; references must belong to the permitted company. Shared `c_kreaimmo_*` reference values may use entity 0. Rent indices and renter-type dictionaries have no entity column. Native third parties and bank accounts follow Dolibarr sharing rules.
- `CommonObjectKreaImmo` guards ownership at object boundaries; page and API entry points enforce operation permissions. Preserve both checks.
- Payments keep bank account IDs in `fk_account` and bank line IDs in `fk_bank`; never interchange them.
- Hook registration is company-scoped. Company cost-type defaults use native `__ENTITY__` and `(entity, ref)` replay checks; never seed business dictionaries into a fixed company.
- SQL installation and repeatable upgrades live under `sql/`, executed by the native module installer. Preserve existing data. No automatic jobs are registered.
- Tests use only a disposable database and synthetic fixtures. See `test/integration/README.md` and `doc/VALIDATION.md`. Never run the fixture against an ERP database or enable scheduled jobs as a test.
- Package with `php build/buildzip.php`; output is `/tmp/module_kreaimmo-<version>.zip`. Do not publish local configuration, credentials, documents, logs or business data.
- A push is source synchronization. Activation/deployment requires its own authorized target, backup and runtime verification. Disabling the module preserves its business records.

- Module-owned variables, arguments and transient query aliases use English. Preserve stored field/request names and inherited Dolibarr identifiers when they are compatibility contracts.
- Mailing adapter filenames must match their `mailing_<filename stem>` class. Native discovery scans inactive custom modules too; retired source belongs outside the web/custom root. Recipient selection must enforce native mailing permissions and company ownership.

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
- REST evidence routes in `class/api_kreaimmo.class.php` expose nine fixed resource projections under `/kreaimmoapi/records`; see [API contracts](doc/API.md). Keep list/detail ownership, native granular permissions, internal-user restrictions, decimal strings, filter validation and bounded pagination aligned with KreaMCP. Buildings and meters inherit company ownership through properties. Preserve the existing renter CRUD contracts.
- Management uses `class/kreaimmoapimanagement.class.php` at `/kreaimmoapi/manage/{resource}`. Every apply requires a confirmed matching preview hash, including creates; native MySQL/MariaDB advisory locks and native transactions protect cooperating API writers. Keep editable contracts and native zero-to-NULL/date/numbering behavior aligned with KreaMCP. Payment mutations lock their receipt and update exact-cent summaries through native objects; bank posting is explicit, posted edits are denied, and native bank deletion protections remain active. Preserve commercial renter visibility and current-company document/ECM guards. No schema migration or automatic job is involved.

- Production API reads/previews were verified for KreaImmo 1.1.1 with KreaMCP 5.3.0 on Dolibarr 24.0.3/PHP 8.1.34. Native Restler public PHPDoc must use its supported scalar/array types; retain detailed nullable/generic types in static-analysis tags, since union parameter annotations fail before its null handling. Cover actual native Validator and Explorer paths; see [validation](doc/VALIDATION.md).

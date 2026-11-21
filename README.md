# KreaImmo for Dolibarr

KreaImmo helps property managers handle their rental portfolio inside Dolibarr. It connects owners, buildings, properties and tenants with leases, rent receipts, payments, operating costs and utility meters.

## Features

- **Property portfolio:** owners, buildings and individual rental properties.
- **Tenancy management:** tenant records, rental agreements and rent indices.
- **Rent collection:** rent receipts, recorded payments and collection statistics.
- **Costs and reporting:** property costs, charge allocation and financial result views.
- **Utilities:** meters, readings and related costs.
- **Tenant access:** an optional authenticated portal for linked tenants, with separate permissions for submitting meter readings.
- **Languages:** English, European Portuguese, French and German translations.

## Requirements

- Dolibarr 19 or later.
- PHP 7.4 or later.
- Dolibarr's Third Parties and Bank modules enabled.
- Administrator access to install the module and assign permissions.

## Installation

1. Back up the Dolibarr database and documents before installing on an existing system.
2. Place the module directory at `htdocs/custom/kreaimmo`.
3. Enable **KreaImmo** in Dolibarr's module administration for the intended company.
4. Open the module setup page to configure rent receipts and related options.
5. Grant the appropriate read, write and object-specific permissions to your users or groups.

The installation creates KreaImmo's own tables and reference dictionaries. It does not automatically import records from another real-estate module. New users receive no KreaImmo permissions by default.

## First steps

1. Create the property owner and any buildings in the portfolio.
2. Add the properties or rental units and their associated information.
3. Create tenants and link them to rental agreements.
4. Issue rent receipts, record payments and follow outstanding amounts.
5. Add costs and meter readings to complete the property's operating picture.

Open the KreaImmo home page at `/custom/kreaimmo/kreaimmoindex.php`. Business records are scoped to the active company; users need permission for each operation.

The tenant portal is optional and must be enabled separately. Each tenant signs in with a linked external Dolibarr account; the portal is not anonymous public access.

## Development

The module uses native Dolibarr pages and business objects. PHP classes are in `class/`, installation schemas in `sql/`, and document models and the module descriptor in `core/`.

To build an installer archive, run from the module directory with PHP's Zip extension available:

```bash
php build/buildzip.php
```

The archive is written to the system temporary directory. See the [integration test guide](test/integration/README.md) for checks against a disposable database.

## License

GPL-3.0-or-later. See [COPYING](COPYING). Existing contributor notices and bundled dependency licenses are retained.

Maintained by [Kreativität Works](https://kreativitat.com).

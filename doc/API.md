# KreaImmo API and MCP integration

The versioned portfolio and management contracts are available from KreaImmo
1.1.0, with KreaMCP 5.3.0 providing the matching MCP tools.

KreaImmo provides authenticated REST resources through Dolibarr's native API at
`/api/index.php/kreaimmoapi`. Enable Dolibarr's API module and KreaImmo in each
intended company, then use an API account with the corresponding KreaImmo read
permissions. Send its key using `DOLAPIKEY` and select the permitted company using
`DOLAPIENTITY`. Authentication, company selection and API-user restrictions remain
Dolibarr's responsibility; a header does not grant additional access.

## Real-estate evidence

The following read routes are available:

```text
GET /records/{resource}?limit=100&page=0
GET /records/{resource}/{id}
```

These routes require an authenticated **internal** Dolibarr user and an active
KreaImmo module in the selected company. Grant either module-wide `kreaimmo.read`
or the resource permission below. These routes do not expose the optional tenant
portal to external accounts. Existing renter routes retain their own external
user restrictions.

| Resource | Granular read permission | Optional exact filters |
| --- | --- | --- |
| `owners` | `owner.read` | `ref` |
| `buildings` | `property.read` | `property_id` |
| `properties` | `property.read` | `ref`, `property_id`, `owner_id` |
| `renters` | `renter.read` | `ref` |
| `leases` | `rent.read` | `ref`, `property_id`, `renter_id`, `owner_id` |
| `receipts` | `receipt.read` | `ref`, `property_id`, `renter_id`, `lease_id`, `owner_id` |
| `payments` | `payment.read` | `ref`, `property_id`, `renter_id`, `lease_id`, `owner_id` |
| `costs` | `cost.read` | `ref`, `property_id`, `owner_id` |
| `meters` | `immocompteur.read` | `ref`, `property_id` |

All permissions in the table are under `kreaimmo`. Filters combine with AND.
An unsupported filter returns HTTP 400. IDs are positive integers up to
2,147,483,647. `ref` is a literal, case-sensitive binary match with no wildcard
syntax; it accepts 1–128
characters without control characters. A property's `property_id` filter matches
its **parent** property (`fk_property`); use the detail route for the property's
own ID. `lease_id` matches the stored `fk_rent` relationship. A meter's property
relationship is stored as `fk_immoproperty`.

`limit` accepts 1–200 and defaults to 100. `page` accepts 0–1000 and defaults to 0.
Invalid values return HTTP 400. Lists sort by ascending record ID and read one
extra row to determine `has_more`; continue with the same filters and the next
page while it is true. Concurrent changes can affect offset-based pages. A
missing or foreign-company detail ID returns HTTP 404.

List responses use this envelope, including an empty object for unused filters:

```json
{
  "schema": "kreaimmo_records_v1",
  "entity_id": 2,
  "resource": "buildings",
  "items": [{"id": 7, "entity": 2, "label": "Example building", "fk_property": 12}],
  "limit": 100,
  "page": 0,
  "has_more": false,
  "filters": {}
}
```

Detail responses use `schema: "kreaimmo_record_v1"` and replace the list metadata
and `items` with one `record`. Each record has integer `id` and `entity`. Company
ownership is read from the actual record, or from the property parent for
buildings and meters. Both list and detail enforce exact company ownership.

## Public record fields

The following fixed fields are returned in addition to `id` and `entity`:

| Resource | Fields |
| --- | --- |
| `owners` | `ref`, `status`, `fk_soc`, `fk_owner_type`, `societe`, `firstname`, `lastname`, `address`, `zip`, `town`, `country_id`, `email`, `phone`, `phone_mobile` |
| `buildings` | `label`, `fk_property` |
| `properties` | `ref`, `status`, `label`, `property_type_id`, `fk_property`, `fk_owner`, `fk_soc`, `juridique_id`, `datebuilt`, `target`, `area`, `numroom`, `numflat`, `numdoor`, `numfloor`, `staircase`, `building`, `address`, `zip`, `town`, `country_id` |
| `renters` | `ref`, `status`, `fk_soc`, `societe`, `firstname`, `lastname`, `email`, `phone`, `phone_mobile`, `town`, `country_id` |
| `leases` | `ref`, `status`, `fk_property`, `fk_owner`, `fk_renter`, `fk_account`, `fk_soc`, `location_type_id`, `vat`, `rentamount`, `chargesamount`, `totalamount`, `deposit`, `encours`, `periode`, `preavis`, `date_start`, `date_end`, `date_next_rent`, `date_last_regul`, `date_last_regul_charge` |
| `receipts` | `ref`, `status`, `label`, `fk_rent`, `fk_property`, `fk_renter`, `fk_owner`, `fk_soc`, `date_echeance`, `date_start`, `date_end`, `rentamount`, `chargesamount`, `total_amount`, `partial_payment`, `balance`, `paye`, `vat_amount`, `vat_tx` |
| `payments` | `ref`, `status`, `fk_rent`, `fk_receipt`, `fk_owner`, `fk_soc`, `fk_property`, `fk_renter`, `date_payment`, `amount`, `fk_mode_reglement`, `fk_payment`, `fk_bank`, `fk_account`, `num_payment` |
| `costs` | `ref`, `status`, `label`, `fk_property`, `fk_owner`, `fk_soc`, `fk_cost_type`, `amount`, `amount_ht`, `amount_vat`, `date_start`, `date_end`, `dispatch` |
| `meters` | `ref`, `label`, `compteur_type_id`, `date_relever`, `qty`, `fk_immoproperty` |

Nullable database values remain JSON null. Non-null `fk_*` fields and `status`,
`property_type_id`, `juridique_id`, `datebuilt`, `target`, `country_id`, `location_type_id`, `vat`,
`preavis`, `numroom`, `paye`, `vat_tx`, `dispatch` and `compteur_type_id` are integers.
Every other non-null field is a string. Monetary values retain their exact
database strings, including scale. Dates retain native database date/datetime
strings; no guessed timezone conversion is applied. `datebuilt` retains its
native integer code (1–5); `target` is -1, 1 or 2. Birth dates,
notes, document paths, user/audit internals and arbitrary extrafields are excluded.

These are stored records, not computed accounting conclusions. In particular,
`fk_account` identifies a bank account and `fk_bank` identifies a bank line.
Read routes preserve the stored financial evidence. Renter reads also retain
native third-party/commercial-assignment visibility.

## Preview-first management

`POST /manage/{resource}` manages the same nine resources through native Dolibarr
business objects. Management requires the read permissions above plus the
resource's `write` or `delete` permission. Every request requires `confirm: true`;
`dry_run` defaults to true. Management uses MySQL/MariaDB, matching the module's
native installation schema.

Preview an operation before applying it:

```json
{
  "operation": "update",
  "record_id": 12,
  "fields": {"label": "Updated property label"},
  "confirm": true,
  "dry_run": true
}
```

Apply the identical operation, record ID and fields with `dry_run: false` and
the preview's 64-character lowercase hexadecimal `expected_hash`. Every apply,
including create, requires this hash. It binds the request, selected company and
currency, current resource collection, relevant parent evidence and proposed
after/effect projections. A stale, altered or repeated apply returns HTTP 409
`stale_preview`; inspect current records and obtain a new preview. Do not blindly
retry an uncertain financial write. KreaMCP additionally uses its durable mutation
journal and requires an idempotency key.

| Operation | Resources | Fields and restrictions |
| --- | --- | --- |
| `create` | All nine | Required create fields below; omit `record_id`. |
| `update` | All nine | Nonempty subset of editable fields. Financial parent moves and parent moves with linked history are denied. Receipts must be draft without payments; posted payments cannot be edited. |
| `delete` | All nine | Empty fields object. Linked dependents and documents block deletion. Receipts must be draft without payments. Posted payment deletion also requires native bank permissions and unreconciled, verified bank evidence; native accounting protections remain active. |
| `set_status` | Owners, properties, renters | `{"status": 0}`, `1` or `9`. Renters use native draft/validate/cancel/reopen methods; draft-to-canceled is denied. |
| `set_status` | Leases | `{"status": 0}` or `1`. |
| `set_status` | Costs | `{"status": 0}`, `1` or `-2`. |
| `validate` | Receipts | Empty fields object; draft receipt without payments/documents only. Uses native validation, numbering and triggers. |
| `post_bank` | Payments | `{"fk_account": 123}`; creates native bank evidence only when explicitly requested. Requires native bank read/modify permissions and an open account owned by this company in its base currency. |

Requests cannot assign company/record IDs, audit fields, arbitrary extrafields,
document paths, paid status, computed totals or bank-line IDs. Optional fields
can be omitted; text fields may be cleared with an empty string where the native
contract permits. Parent and dictionary IDs are positive integers. Money uses
nonnegative decimal **strings** with up to 12 integer digits and exact-cent
precision. Sub-cent input or stored financial evidence requires review. Payments
must be positive and cannot overpay receipts. Dates are real `YYYY-MM-DD`
calendar dates from 1970 through 2099. Payment dates, bank accounts and tax rates
are never inferred.

| Resource | Required create fields | Other editable fields |
| --- | --- | --- |
| Owners | `firstname`, `lastname`, `email` | `ref`, `fk_soc`, `fk_owner_type`, `societe`, `address`, `zip`, `town`, `country_id`, `phone`, `phone_mobile` |
| Buildings | `label`, `fk_property` | None |
| Properties | `label`, `property_type_id`, `fk_owner` | `fk_property`, `fk_soc`, `juridique_id`, `datebuilt`, `target`, `area`, `numroom`, `numflat`, `numdoor`, `numfloor`, `staircase`, `building`, `address`, `zip`, `town`, `country_id` |
| Renters | `lastname` | `ref`, `fk_soc`, `societe`, `firstname`, `email`, `phone`, `phone_mobile`, `town`, `country_id` |
| Leases | `fk_property`, `fk_owner`, `fk_renter`, `rentamount`, `chargesamount`, `date_start` | `fk_account`, `fk_soc`, `location_type_id`, `vat`, `deposit`, `periode`, `preavis`, `date_end`, `date_next_rent`, `date_last_regul`, `date_last_regul_charge` |
| Receipts | `fk_rent`, `label`, `rentamount`, `chargesamount`, `date_start`, `date_end`, `date_echeance` | `ref`, `vat_amount`, `vat_tx` |
| Payments | `fk_receipt`, `amount`, `date_payment`, `fk_mode_reglement` | `num_payment` |
| Costs | `label`, `fk_property`, `amount`, `amount_ht`, `amount_vat`, `date_start`, `date_end` | `ref`, `fk_owner`, `fk_soc`, `fk_cost_type`, `dispatch` |
| Meters | `label`, `compteur_type_id`, `date_relever`, `qty`, `fk_immoproperty` | None |

`datebuilt` accepts 1–5; `target` accepts -1, 1 or 2; `juridique_id` and
`location_type_id` accept 1 or 2; `preavis` accepts 1 or 2; `vat` and `dispatch`
accept 0 or 1. `vat_tx` accepts integer 0–100 without calculating tax from it.
Meter `qty` is a nonnegative decimal string with up to eight fractional digits.
Cost `amount` must equal `amount_ht + amount_vat`. Lease owner must match property
owner. Lease, property and meter references follow native numbering and are
generated rather than accepted as editable inputs. Native lease creation defaults
omitted `preavis` to 1; previews expose that behavior.

Receipts derive property/renter/owner/third-party links from a verified lease.
Payments derive those links from a verified, validated receipt and require an
active native payment method. Payment create/update/delete locks the receipt,
calculates exact linked-payment cents and updates its `partial_payment`, `balance`
and `paye` through the native receipt object in the same transaction. Zero-valued
fields retain native `notnull=-1` storage and may read back as null. Preview and
readback preserve these semantics and the native monetary scale.

Management responses contain `schema: "kreaimmo_management_v1"`, `entity_id`,
`currency`, `resource`, `operation`, `dry_run`, `status`, `record_id`,
`expected_hash`, `request`, `before`, `after` and `effects`. Status is `dry_run`,
`confirmed` or `unchanged`. `request` echoes the operation, fields and optional
record ID without safety flags. Before/after use the public read projections.
Create previews have null ID and a null reference when generated by native
creation; execution returns actual identity. Validation previews resolve native
numbering before apply. Delete has null after. Related payment effects are a
list of `{resource: "receipts", record_id, before, after}` with full receipt
projections.

Management uses a bounded company advisory lock, row locks and transactional
native lifecycles. Resource snapshots are hashed incrementally without buffering
business rows in PHP. Native UI and independent database writes do not participate
in the advisory lock; short previews and current-state verification remain
necessary. Document-bearing deletes, reference edits and validation renames
require a separate document-preserving workflow. A database commit failure is
reported as an unknown outcome rather than safe to retry.

## KreaMCP

Compatible KreaMCP versions expose `list_kreaimmo_records` and
`get_kreaimmo_record` through the same company-selected Dolibarr client. Use the
resource names and supported filters above; the MCP detail input is `record_id`.
The bridge validates response identity, ownership, pagination, filters and public
field types. Those two tools are read-only; `manage_kreaimmo_record` provides the
managed workflows above and independently verifies native readback. Financial
workflows also require KreaMCP's verified per-company currency configuration.
HTTP exposure of management requires an explicit write-allowlist decision.
Existing HTTP authentication and company allowlists remain unchanged. Install this module
API before using the corresponding bridge tools.

## Existing renter API

The existing endpoints remain available with their established response shapes:

```text
GET    /immorenters/
GET    /immorenters/{id}
POST   /immorenters/
PUT    /immorenters/{id}
DELETE /immorenter/{id}
```

The singular DELETE path is retained for compatibility. These routes require
their corresponding native renter permission and enforce company and linked
third-party visibility. The separate management endpoint grants
no additional permissions. Publishing source does not deploy it or activate it
in a company.

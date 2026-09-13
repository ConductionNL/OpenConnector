# Tasks: objecten-api-facade

## Implementation tasks

### Task 1: The objecttype mapping and its configuration
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-an-objecttype-is-a-declared-mapping-onto-a-register-and-schema-req-oaf-001`
- **files**: `lib/Settings/integriq_register.json` (the `objecttype` schema), `lib/Service/Objecten/ObjecttypeRegistry.php`
- [ ] Implement (VNG uuid, name, register, schema, allowed versions; no name-based inference)
- [ ] Test

### Task 2: The Objecttypen API v2
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-the-objecttypen-api-serves-the-schema-it-stands-for-req-oaf-002`
- **files**: `lib/Service/Objecten/ObjecttypeEndpointHandler.php`, the endpoint seeds
- [ ] Implement (list, read, versions; `jsonSchema` rendered from the OpenRegister schema)
- [ ] Test

### Task 3: The Objecten API v2 read path
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-the-objecten-api-reads-objects-in-the-standards-shape-req-oaf-003`
- **files**: `lib/Service/Objecten/ObjectEndpointHandler.php`, `lib/Service/Objecten/ObjectRecordTranslator.php`
- [ ] Implement (`type`, `data_attrs`, `date`, `registrationDate`, `ordering`, pagination, geometry search)
- [ ] Test (including the literal-leak guard per `zgw-version-translation` REQ-001)

### Task 4: The Objecten API write path
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-a-write-lands-in-openregister-and-announces-req-oaf-004`
- **files**: `lib/Service/Objecten/ObjectEndpointHandler.php`, `lib/Service/NotificatiesPublisher.php`
- [ ] Implement (create, partial update, replace, delete; announce on the `objecten` kanaal)
- [ ] Test

### Task 5: Tokens with a permission per objecttype
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-a-token-carries-a-permission-per-objecttype-req-oaf-005`
- **files**: `lib/Service/Objecten/ObjectenTokenService.php`, the token schema, the credential broker resolver
- [ ] Implement (403 on a refused objecttype, 404 on an unknown one, no Nextcloud session, credentials by reference)
- [ ] Test (a token scoped to one objecttype is refused on a second; a log assertion that no key is written)

### Task 6: Declaration, throttling, catalog entry, docs
- **spec_ref**: `openspec/changes/objecten-api-facade/specs/objecten-api-facade/spec.md#requirement-a-leaf-app-declares-the-objecttypes-it-publishes-req-oaf-006`
- **files**: the endpoint declaration reader, the throttle configuration (ADR-082), `lib/Settings/catalog.seed.json`, Dutch and English strings, docs
- [ ] Implement
- [ ] Test (`tests/e2e/objecten-api-facade.spec.ts`, Newman over the two APIs)

## Verification

- [ ] `openspec validate objecten-api-facade --strict` passes
- [ ] PHPUnit run in the container, exit code read rather than the summary line
- [ ] The geometry search query plan read against a seeded register, not assumed

## Cross-repo follow-ups

- [ ] Tell dossiq to declare its `caseObject` types as objecttypes and delete the controller answering today; the task sits in dossiq's `competitor-parity-2026-09`
- [ ] Re-point register row 12.3 at this change; the openregister lane already moved the owner

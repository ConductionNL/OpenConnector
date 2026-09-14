# Tasks: migration-source-adapters

Kind: code. Size M, as integriq's half of cluster 8, which the build plan
sizes L with openregister as owner. Candidates C-configuration-88 and
C-configuration-16, both matrix holes, plus the read half of
C-configuration-95. Numbers 2 and 13 of the twenty-five loudest.

## Implementation tasks

### Task 1: The migration source contract
- **spec_ref**: `openspec/changes/migration-source-adapters/specs/migration-sources/spec.md#requirement-a-migration-source-is-an-adapter-behind-one-contract-req-msa-001`
- **files**: `lib/Migration/MigrationSourceAdapterInterface.php`, `lib/Migration/MigrationSourceRegistry.php`, `lib/AppInfo/Application.php` (DI tag)
- [ ] Implement (`describe`, `count`, `read`, `sample`; no write to source or target; record kinds and their identifiers declared)
- [ ] Test (an unknown source id fails naming itself and reads nothing)

### Task 2: The file source and its stored mapping
- **spec_ref**: `openspec/changes/migration-source-adapters/specs/migration-sources/spec.md#requirement-a-file-is-read-through-a-stored-column-mapping-req-msa-002`
- **files**: `lib/Migration/Source/FileMigrationSource.php`, the column mapping object in `lib/Settings/`, the mapping editor surface
- [ ] Implement (stored, versioned, reusable; target field names validated at save; an unmapped required field refused before the run)
- [ ] Test (a second delivery reusing a saved mapping, and a mapping onto a missing field)

### Task 3: The first incumbent adapter
- **spec_ref**: `openspec/changes/migration-source-adapters/specs/migration-sources/spec.md#requirement-a-named-incumbent-has-an-adapter-and-a-supported-path-is-rehearsable-req-msa-003`
- **files**: `lib/Migration/Source/<incumbent>/`, a mock-mode fixture per `source-management`
- [ ] Choose the incumbent with the first migration customer, and record the choice here with its reason
- [ ] Implement (the adapter behind the contract, the rehearsal run that writes nothing)
- [ ] Test (against the mock-mode fixture, and a registered second adapter that needs no engine change)

### Task 4: The read-only pass
- **spec_ref**: `openspec/changes/migration-source-adapters/specs/migration-sources/spec.md#requirement-a-read-only-pass-reports-what-a-migration-would-bring-req-msa-004`
- **files**: `lib/Migration/MigrationPreviewReader.php`, the fetch-completeness tracking of `synchronization-engine` REQ-009
- [ ] Implement (count per record kind, a bounded sample, completeness reported as completeness and never as a smaller count)
- [ ] Test (a truncated fixture reports incomplete)

### Task 5: Foreign identity on every record
- **spec_ref**: `openspec/changes/migration-source-adapters/specs/migration-sources/spec.md#requirement-every-yielded-record-carries-its-foreign-identity-req-msa-005`
- **files**: the yielded record shape, reusing `registry-backed-field-source` REQ-RFS-003 provenance
- [ ] Implement (identifier and source on every record; a re-run matches; a record kind with no stable key declared in `describe()`)
- [ ] Test (a second run of the same source)

### Task 6: Coordination, docs and the hand-offs
- **files**: `docs/`, Dutch and English strings, the catalogue entries, this change's row in `competitor-parity-2026-09`
- [ ] Ask the openregister lane, cluster 8, for the import engine's preview and conflict policy, and agree the record shape and the match signal this change yields
- [ ] Say in the same message that C-integrations-50 and C-integrations-22 stay openregister's, and that integriq adds no export
- [ ] Tell the openregister lane that the foreign identity reuses `registry-backed-field-source` REQ-RFS-003 rather than a second shape
- [ ] Test (`tests/e2e/migration-file-source.spec.ts`, `openspec validate migration-source-adapters --type change --strict`)

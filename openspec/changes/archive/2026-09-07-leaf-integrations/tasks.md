# Tasks: leaf-integrations

## Implementation Tasks

### Task 1: Declare `configuration.linkedTypes` on `source` and `synchronization`
- **spec_ref**: `openspec/changes/leaf-integrations/specs/integration-leaves/spec.md#requirement-the-leaf-surface-is-declared-in-the-register-and-the-manifest-and-is-exactly-four-leaves-on-two-schemas-req-ocl-001`
- **files**: `lib/Settings/register.d/leaf-integrations.json`
- **note**: the declaration moved out of `lib/Settings/integriq_register.json` and into an ADR-037 fragment, which is this repo's convention for a per-change register edit and is also the only shape that forces the import to run: `InitializeRegister` folds the md5 of every fragment into the version it hands `importFromApp`, whereas `importFromApp` on the base file alone is gated on appinfo's version, which does not move between releases.
- **acceptance_criteria**:
  - GIVEN the fragment WHEN read THEN `source.configuration` carries `linkedTypes: ["files", "deck", "talk"]` and `synchronization.configuration` carries `linkedTypes: ["calendar"]`, and nothing else in either schema is touched
  - GIVEN the merged register WHEN searched for `linkedTypes` THEN exactly 2 schemas carry it and none of the other 37 does (`endpoint`, `mapping`, `job`, `rule`, `consumer`, `sync_item_dead_letter` and every `*_log` schema assert absence explicitly)
  - GIVEN each edit WHEN `python3 -m json.tool` runs on the fragment THEN it exits 0; the `register.d/99-*` lockdown overlays are untouched
  - GIVEN the register is re-imported WHEN `Schema::validateLinkedTypesValue()` runs THEN no invalid-linked-type error is raised
  - GIVEN the import has run WHEN the stored schemas are read back THEN `configuration.linkedTypes` is present on both (the assertion that matters — see the two findings below)
- [x] Implement
- [x] Test — `integration-leaves.spec.ts` REQ-OCL-001, green against a live instance

**Two silent-failure modes found while implementing this, both of which pass every static check:**

1. **A top-level `linkedTypes` is dropped without an error.** `Schema::hydrate()` folds only `x-openregister-*` keys and `x-schema-org` into `configuration`; every other top-level key is dispatched to `set<Key>()` and the resulting "method does not exist" is swallowed by hydrate's own catch. Two schemas in a neighbouring fleet app (`Cohort`, `Session` in learniq) carry exactly that shape and are therefore declaring nothing. Not fixed here; worth raising against that app.
2. **A configuration-only change does not re-apply.** `ImportHandler::schemaContentDiffers()` compares `properties`, `required`, `authorization` and `x-openregister-*` annotations only, and the importer otherwise skips a stored schema whose version is not newer. The first import of this fragment landed (`imported_config_integriq_version` carried its fragment hash) and changed nothing. The fix here is a schema version bump in the fragment; the general fix belongs in OpenRegister and is filed separately.

### Task 2: Add the three integration widgets to SourceDetail
- **spec_ref**: `openspec/changes/leaf-integrations/specs/integration-leaves/spec.md#requirement-leaves-are-pure-link-surfaces-and-never-read-or-write-source-properties-req-ocl-002`
- **files**: `src/manifest.json`, `src/icons.js`
- **acceptance_criteria**:
  - GIVEN the manifest WHEN edited THEN SourceDetail gains `src-files` (`integrationId: "files"`, title "Supplier documents"), `src-deck` (`integrationId: "deck"`, title "Incident follow-ups"), and `src-talk` (`integrationId: "talk"`, title "Incident war-room"), each with `id`, `type: "integration"`, `integrationId`, `title`, `icon`
  - GIVEN the manifest WHEN searched for `"type": "integration"` THEN the count is exactly 3, all on SourceDetail, and no custom page (`SynchronizationDetail`, `MappingDetail`, `DeadLetters`, …) gained a widget
  - GIVEN each new widget WHEN the page's `config.layout` is read THEN it has a layout entry. A widget with no layout entry is absent from the DOM and every gate still passes
  - GIVEN each widget icon WHEN `src/icons.js` is read THEN the name is registered. An unregistered icon renders no glyph
  - GIVEN the built app WHEN `npm run check:manifest` runs THEN Ajv validation passes
- [x] Implement
- [x] Test — verified in the browser: the source detail page renders headings "Supplier documents", "Incident follow-ups" and "Incident war-room" below "Environments"

### Task 3: e2e spec-coverage for the SourceDetail leaves
- **spec_ref**: `openspec/changes/leaf-integrations/specs/integration-leaves/spec.md#requirement-leaves-are-pure-link-surfaces-and-never-read-or-write-source-properties-req-ocl-002`
- **files**: `tests/e2e/spec-coverage/integration-leaves.spec.ts`
- **acceptance_criteria**:
  - GIVEN a running instance WHEN the suite runs THEN it reads the stored schemas back through OpenRegister's schema API and asserts the four declared leaves, and the absence of any other Integriq leaf
  - GIVEN a seeded source WHEN its detail page renders THEN the files leaf is asserted unconditionally and the deck and talk leaves when their app is installed (provider `isEnabled()` behaviour)
  - GIVEN a source holding credential values WHEN the page is rendered THEN none of them appears anywhere in it
  - GIVEN the page has rendered WHEN the source is read back THEN it is unchanged
- [x] Implement
- [x] Test — 2 passed against `iq-e2e-nextcloud-1`

**Scope note:** the spec's "an incident war-room is linked without touching the source" scenario was rewritten to an `@e2e exclude` rather than tagged. The test proves the render, the credential absence and the immutability; it does not drive a link, because creating a conversation is Talk's own UI and Talk is not installed on every instance the suite runs against. Tagging it would have been a tag the assertions do not earn.

### Task 4: Documentation
- **spec_ref**: `openspec/changes/leaf-integrations/specs/integration-leaves/spec.md#requirement-every-other-schema-and-leaf-type-stays-off-until-a-spec-change-argues-otherwise-req-ocl-004`
- **files**: `docs/features/integration-leaves.md`, `docs/features/README.md`, `CHANGELOG.md`
- **acceptance_criteria**:
  - GIVEN `docs/features/integration-leaves.md` WHEN read THEN it records the incident workflow, the planning-only calendar leaf and where to find it, the OFF list with reasons including the deferred dead-letter files leaf, and the two silent-failure modes an implementer has to avoid
  - GIVEN `docs/features/README.md` WHEN read THEN the feature index links it
  - GIVEN `CHANGELOG.md` WHEN read THEN it records Integriq's first leaf adoption
- [x] Implement
- [x] Test — no em-dashes in the new prose (CLAUDE.md writing rule)

## Verification
- [x] All tasks checked off
- [x] `npm run check:specs` passes (exit 0)
- [x] Manual testing against acceptance criteria: the three widgets render on a real source; the stored schemas carry the four leaves; the magic tables gained `_deck`, `_talk` (source) and `_calendar` (synchronization)
- [x] Code review against spec requirements

## Tests (company-wide ADR-009)

- [x] Browser tests (Playwright): `tests/e2e/spec-coverage/integration-leaves.spec.ts` (Task 3)
- [x] All tests pass — no PHP is added or changed, so `composer test` is unaffected
- PHPUnit: N/A — no PHP is added or changed.
- Newman/Postman: N/A — no HTTP endpoint is added; leaf data flows through OpenRegister's existing integrations API.

## Documentation (company-wide ADR-010)

- [x] Feature documentation added at `docs/features/integration-leaves.md` (Task 4)
- [ ] Screenshot of SourceDetail with the three leaf widgets committed to `docs/images/` — not done. The docs-capture project writes to `docs/assets/`, and adding a page to it is its own change; the rendered headings are recorded in Task 2 instead.

## i18n (company-wide hydra ADR-007)

- [x] Dutch and English strings for the three widget titles, in `l10n/en.json` and `l10n/nl.json` with `l10n/*.js` regenerated by `npm run l10n:build`. The JSON is the source; editing the generated `.js` is discarded by the next build.

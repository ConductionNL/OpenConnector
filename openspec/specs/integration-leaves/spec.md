# integration-leaves Specification

## Purpose

Which OpenRegister integration leaves Integriq declares, on which schemas and
surfaces, and why the rest are off.

A leaf links a Nextcloud entity that lives in another app to an Integriq object.
It is not a copy and not a sync: the file stays in Files, the card in Deck, the
conversation in Talk, the event in Calendar, and Integriq stores the link.

Integriq declares four, on two schemas. The restraint is the point: its domain
objects are the integration control plane, so most consumer-style leaves would
be decoration and every leaf on a data-bearing schema is an exposure question.

## Requirements
### Requirement: The leaf surface is declared in the register and the manifest, and is exactly four leaves on two schemas (REQ-OCL-001)

Integriq's integration-leaf surface SHALL consist solely of `configuration.linkedTypes` declarations in the register plus manifest integration widgets in `src/manifest.json`, and SHALL be exactly: `source` → `["files", "deck", "talk"]` with three widgets on SourceDetail (`src-files` "Supplier documents", `src-deck` "Incident follow-ups", `src-talk` "Incident war-room"); `synchronization` → `["calendar"]` with no manifest widget (surfaced via the shared object sidebar, because SynchronizationDetail is a `type: "custom"` page the manifest cannot reach). No other schema SHALL carry `linkedTypes`, Integriq SHALL NOT implement an `IntegrationProvider`, and every declared id MUST survive OpenRegister's import-time validation (`Schema::validateLinkedTypesValue()` rejects ids absent from the provider registry, failing the import loudly).

The declaration SHALL be written as `configuration.linkedTypes` and SHALL carry a schema `version` newer than the stored one. Both are load-bearing and both fail silently: `Schema::hydrate()` folds only `x-openregister-*` and `x-schema-org` into `configuration`, so a top-level `linkedTypes` is dispatched to a setter that does not exist and swallowed by hydrate's own catch; and `ImportHandler::schemaContentDiffers()` compares only `properties`, `required`, `authorization` and `x-openregister-*` annotations, so a configuration-only change is invisible to it and the stored schema is left untouched.

#### Scenario: the register declares files, deck and talk on source and calendar on synchronization

- GIVEN the register has been imported on a running instance
- WHEN the stored schemas are read back through OpenRegister's schema API
- THEN `source` carries exactly `["deck", "files", "talk"]` in `configuration.linkedTypes`
- AND `synchronization` carries exactly `["calendar"]`
- AND no other Integriq schema carries any
- @e2e integration-leaves::the-register-declares-files-deck-and-talk-on-source-and-calendar-on-synchronization

#### Scenario: an invalid leaf id cannot ship

- GIVEN a `linkedTypes` value naming a provider that does not exist
- WHEN the register is imported
- THEN the import fails with the list of valid ids rather than silently dropping the leaf
- @e2e exclude backend import validation — OpenRegister's own `Schema::validateLinkedTypesValue()`, covered by its suite. Integriq cannot exercise the failure path without shipping a deliberately broken fragment.

### Requirement: Leaves are pure link surfaces and never read or write source properties (REQ-OCL-002)

No leaf on `source` SHALL read or write any register property of the source object — in particular none of the plaintext credential properties (`password`, `apikey`, `secret`, `jwt`, `authenticationConfig`). The files, deck, and talk leaves link external entities (Nextcloud files, Deck cards, Talk conversations) to the object by id; linking, unlinking, or interacting with a linked entity SHALL leave the source object unchanged, and no credential value SHALL be rendered inside any leaf widget. Leaves SHALL render only after the page's normal object read has succeeded, so no user gains sight of a source through a leaf that they could not already open.

Rendering a leaf widget additionally requires OpenRegister's SHARED CLIENT
REGISTRY, installed by its `openregister-integration-global` bundle. OpenRegister
gitignores `/js/` and force-tracks three files, which do not include that bundle,
so an instance running an unbuilt OpenRegister checkout serves no registry and no
integration widget can render — for any app, not only this one. That is a
property of the instance and not of the leaf declaration, so the two are asserted
separately: the declaration unconditionally, the render only where a registry
exists.

#### Scenario: SourceDetail renders the leaf widgets

- GIVEN a seeded source, on an instance whose OpenRegister serves the shared
  integration registry
- WHEN its detail page is opened
- THEN the files leaf renders as "Supplier documents"
- AND the deck and talk leaves render when their Nextcloud app is installed
- @e2e integration-leaves::sourcedetail-renders-the-leaf-widgets

#### Scenario: a source detail page exposes no credential and is unchanged by rendering

- GIVEN a seeded source holding credential values
- WHEN its detail page is opened
- THEN no credential value appears anywhere in the rendered page
- AND the source object read back afterwards is unchanged
- AND this holds whether or not the leaf widgets rendered, because it is a
  property of the object read rather than of the leaf renderer
- @e2e integration-leaves::a-source-detail-page-exposes-no-credential-and-is-unchanged-by-rendering

#### Scenario: an incident war-room is linked without touching the source

- GIVEN a source whose sync has failed repeatedly
- WHEN an admin links a Talk conversation via the `src-talk` widget on SourceDetail
- THEN the conversation is linked to the source object and shown in the widget
- AND the source object's properties are byte-identical before and after linking
- @e2e exclude the linking half is uncovered. `integration-leaves.spec.ts` proves the widgets render, that no credential reaches the DOM and that rendering leaves the source unchanged, and stops short of driving a link: creating a conversation is Talk's own UI reached through OpenRegister's linked-entity API, and Talk is not installed on every instance the suite runs against. Uncovered rather than covered elsewhere.

### Requirement: The synchronization calendar leaf is planning-only and never a scheduler (REQ-OCL-003)

The `synchronization` schema SHALL declare `calendar` in `configuration.linkedTypes` so maintenance windows and cutover dates can be linked as CalDAV events to the data flow they affect, surfaced through the shared object sidebar. The leaf SHALL NOT read or write any synchronization property and SHALL NOT influence execution: scheduling remains exclusively the job mechanism (`job.interval`, `job.nextRun`, the job list), and linking, moving, or deleting a calendar event SHALL never trigger, delay, or suppress a synchronization run.

#### Scenario: a maintenance window is linked to a synchronization

- GIVEN a synchronization whose supplier announces a maintenance window
- WHEN an admin links a calendar event for that window to the synchronization object
- THEN the event is visible from the synchronization's object sidebar
- AND the synchronization's schedule, status, and next run are unchanged
- @e2e exclude sidebar surface on a `type: "custom"` page. The shared-sidebar render path is OpenRegister's and is covered there; Integriq's half is the schema declaration, which the REQ-OCL-001 scenario asserts against the stored schema. Revisit with a widget-level test when SynchronizationDetail becomes manifest-driven.

### Requirement: Every other schema and leaf type stays OFF until a spec change argues otherwise (REQ-OCL-004)

No schema other than `source` and `synchronization` SHALL carry `linkedTypes`, and no leaf type other than the four declared SHALL be adopted, without a change to this capability that argues against the recorded rationale: credential/authorization-bearing schemas (`endpoint`, `consumer`, `event_subscription`, `rule`, `lti_*`, `eudi_*`) because link surfaces invite secrets into linked entities; `mapping` and `job` because their collaboration artefact is versioned export, not cards or chats; log schemas because nothing should be attachable to evidence; `sync_item_dead_letter` deferred (not refused) until a per-object surface exists; and consumer-style leaf types (mail intake, contacts, forms, polls, photos, maps, and the rest) because no integrator workflow maps to them on control-plane objects.

#### Scenario: the OFF list is enforced by the declared surface

- GIVEN the imported register
- WHEN every schema's configuration is inspected
- THEN only `source` and `synchronization` carry `linkedTypes`
- AND no dead-letter, log, endpoint, mapping, job, rule, or consumer schema carries any
- @e2e exclude the absence half of REQ-OCL-001's first scenario, which asserts it against the same stored schemas in the same test. Splitting it into its own anchor would tag one assertion twice.

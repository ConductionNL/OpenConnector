# openconnector-app-manifest Specification

## Purpose
TBD - created by archiving change openconnector-app-manifest. Update Purpose after archive.
## Requirements
### Requirement: Manifest file MUST exist at canonical path

The integriq app MUST ship a file at `src/manifest.json` adjacent to `src/main.js`
and `src/App.vue`. The file MUST be valid JSON parseable without errors.

#### Scenario: Manifest file present at canonical path
- GIVEN a checkout of the integriq repo
- WHEN inspecting `integriq/src/`
- THEN `manifest.json` MUST exist as a regular file
- AND it MUST parse as valid JSON with no syntax errors

#### Scenario: Manifest file is adjacent to main.js
- GIVEN a checkout of the integriq repo
- WHEN listing `integriq/src/`
- THEN `manifest.json`, `main.js`, and `App.vue` MUST all be present in the same directory
- @e2e exclude a directory listing, not a DOM behaviour; `check:specs` parses the manifest from that path on every CI run

---

### Requirement: Manifest MUST declare a valid schema reference

The manifest file MUST contain a `$schema` field set to the published URL of the
canonical schema:
`"https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json"`

#### Scenario: $schema field is present and correct
- GIVEN the manifest file is loaded
- WHEN inspecting the `$schema` top-level key
- THEN it MUST equal `"https://raw.githubusercontent.com/ConductionNL/nextcloud-vue/main/src/schemas/app-manifest-v2.schema.json"`
- @e2e exclude a JSON value assertion. `manifest-pages.spec.ts` matches only the filename suffix, and its own header records that as deliberately NOT anchored, so claiming it here would be the annotation-without-coverage this gate exists to catch

---

### Requirement: Manifest version MUST follow semver

The manifest file MUST contain a `version` field matching the semver pattern
`^\d+\.\d+\.\d+`. The initial value for D1 MUST be `"1.0.0"`.

#### Scenario: version field is valid semver
- GIVEN the manifest file is loaded
- WHEN validating `version` against `/^\d+\.\d+\.\d+$/`
- THEN the match MUST succeed

#### Scenario: the manifest declares the schema generation it is written against

`1.0.0` pinned the D1 delivery and has been wrong since the v2 manifest landed;
the value is `2.1.0` today. The durable claim is that the version tracks the
manifest SCHEMA generation, which `$schema` also names, not the app release.

- GIVEN the manifest file is loaded
- WHEN reading `manifest.version`
- THEN it MUST be a `2.x` version, matching the `app-manifest-v2` schema the
  `$schema` key names
- @e2e exclude asserted by `check:specs` (`validate-manifest`) against the real
  Ajv schema on every CI run, which is a stronger check than a browser can make

---

### Requirement: Manifest MUST declare openregister as a runtime dependency

The manifest `dependencies` array MUST contain the string `"openregister"`. No other
apps need to be listed in D1.

#### Scenario: dependencies contains openregister
- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.dependencies`
- THEN the array MUST contain exactly the string `"openregister"`
- @e2e exclude a JSON value assertion, and the runtime consequence is covered instead by gate-66 openregister-dependency-shape

#### Scenario: dependencies is a JSON array
- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.dependencies`
- THEN it MUST be a JSON array (not null, not an object, not a string)
- @e2e exclude a JSON type assertion the Ajv schema enforces in `check:specs`

---

### Requirement: Manifest MUST declare a menu entry for every primary navigation item

The manifest `menu` array MUST contain one entry for each of the 15 navigation items
currently rendered by `src/navigation/MainMenu.vue` plus the external Documentation
link. Each entry MUST have: `id` (unique string), `label` (i18n key string), `icon`
(Nextcloud icon class string), `route` OR `href` (but not both), and `order` (integer).
Items in `NcAppNavigationSettings` MUST additionally have `"section": "settings"`.

Required menu entries (confirmed from `src/navigation/MainMenu.vue`):

| id | label | section | order |
|----|-------|---------|-------|
| Dashboard | integriq navigation.dashboard | — | 10 |
| Sources | integriq navigation.sources | — | 20 |
| Endpoints | integriq navigation.endpoints | — | 30 |
| Consumers | integriq navigation.consumers | — | 40 |
| Webhooks | integriq navigation.webhooks | — | 50 |
| Mappings | integriq navigation.mappings | — | 60 |
| Jobs | integriq navigation.jobs | — | 70 |
| CloudEvents | integriq navigation.cloudEvents | — | 80 |
| Synchronizations | integriq navigation.synchronizations | — | 90 |
| Rules | integriq navigation.rules | — | 100 |
| Import | integriq navigation.import | settings | 110 |
| Documentation | integriq navigation.documentation | settings | 120 |
| Settings | integriq navigation.settings | settings | 130 |

#### Scenario: the navigation is grouped, not a flat list

This scenario described a flat 15-entry menu. That design is gone: ADR-097 groups
the domain entries, so `manifest.menu` now holds 10 top-level entries, five of them
groups carrying their leaves in `children`. `Import` and `Settings` no longer exist
as menu entries at all.

- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.menu`
- THEN the top level MUST hold `Dashboard`, the groups `GatewayGroup`,
  `ConnectionsGroup`, `EventsGroup`, `AutomationGroup` and `OperationsGroup`, and the
  four `section: "footer"` chrome entries
- AND every domain leaf MUST sit inside a group's `children`, not at the top level

#### Scenario: the chrome entries carry section footer

`Import` and `Settings` are gone, and the surviving chrome moved from
`section: "settings"` to `section: "footer"` under ADR-114.

- GIVEN the manifest file is loaded
- WHEN inspecting the entries `Documentation`, `Store`, `ReportsMenu` and
  `FeaturesRoadmapMenu`
- THEN each MUST have `"section": "footer"`
- AND they MUST appear in that relative order, which ADR-114 Decision 1 fixes

#### Scenario: Documentation entry uses href not route
- GIVEN the manifest file is loaded
- WHEN inspecting the Documentation menu entry
- THEN it MUST have an `href` field pointing to `"https://integriq.conduction.nl"`
- AND it MUST NOT have a `route` field

#### Scenario: Primary nav entries have route not href
- GIVEN the manifest file is loaded
- WHEN inspecting every entry that navigates, at the top level or inside a group's
  `children` (`Import` is no longer among them)
- THEN each MUST have a `route` field
- AND the `route` value MUST match a `pages[].id` in the same manifest

---

### Requirement: Manifest MUST declare a page entry for every route

The manifest `pages` array MUST contain one entry for every distinct page component
registered in `src/router/index.js` (excluding redirects). Each page entry MUST have:
`id` (unique, used as vue-router route name), `route` (path pattern string), `type`
(one of: `dashboard`, `index`, `detail`, `logs`, `custom`, `settings`), `title`
(i18n key string).

Redirect-only routes (`/cloud-events` → `/cloud-events/events`, `*` → `/`) MUST NOT
produce manifest page entries.

Required pages (minimum 23 entries):

| id | route | type |
|----|-------|------|
| Dashboard | / | dashboard |
| Sources | /sources | index |
| SourceLogs | /sources/logs | logs |
| Endpoints | /endpoints | index |
| EndpointDetail | /endpoints/:id | detail |
| EndpointLogs | /endpoints/logs | logs |
| Consumers | /consumers | index |
| ConsumerDetail | /consumers/:id | detail |
| Webhooks | /webhooks | index |
| Jobs | /jobs | index |
| JobLogs | /jobs/logs | logs |
| Mappings | /mappings | index |
| MappingDetail | /mappings/:id | detail |
| Rules | /rules | index |
| RuleDetail | /rules/:id | detail |
| Synchronizations | /synchronizations | index |
| SynchronizationContracts | /synchronizations/contracts | index |
| SynchronizationLogs | /synchronizations/logs | logs |
| CloudEvents | /cloud-events/events | index |
| CloudEventDetail | /cloud-events/events/:id | detail |
| CloudEventLogs | /cloud-events/logs | logs |
| Import | /import | custom |
| Settings | /settings | settings |

#### Scenario: All routes from router are declared as pages
- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.pages` ids
- THEN an entry MUST exist for each of the 23 routes listed in the table above
- AND no page id MUST be duplicated

#### Scenario: Dashboard page type is dashboard
- GIVEN the manifest file is loaded
- WHEN inspecting the page with id `"Dashboard"`
- THEN its `type` field MUST be `"dashboard"`
- AND its `route` MUST be `"/"`

#### Scenario: Log pages use type logs
- GIVEN the manifest file is loaded
- WHEN inspecting pages with ids SourceLogs, EndpointLogs, JobLogs, SynchronizationLogs, CloudEventLogs
- THEN each MUST have `"type": "logs"`

#### Scenario: the Import page is gone, and stays gone

Inverted rather than deleted. `Import` was retired with the configuration
export/import rework; the manifest declares no such page and `/import` is not a
route. A deleted scenario says nothing if the page comes back by accident.

- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.pages`
- THEN no entry MUST carry the id `"Import"`

#### Scenario: settings live in the Nextcloud settings section, not a page

Inverted rather than deleted. There is no `Settings` page: ADR-114 puts the app's
settings behind the nav's settings foldout, which links to
`/settings/admin/integriq`, so a `type: "settings"` page would be a second home
for the same thing.

- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.pages`
- THEN no entry MUST carry the id `"Settings"`

#### Scenario: Detail pages carry :id parameter in route
- GIVEN the manifest file is loaded
- WHEN inspecting pages with ids EndpointDetail, ConsumerDetail, MappingDetail, RuleDetail, CloudEventDetail
- THEN each MUST have a `route` field ending in `/:id`

---

### Requirement: Manifest MUST validate against the canonical schema without errors

The file `src/manifest.json` MUST pass validation against
`app-manifest-v2.schema.json` (from `@conduction/nextcloud-vue`) with zero errors when
checked by the `validateManifest` utility function.

#### Scenario: validateManifest returns valid
- GIVEN `validateManifest(manifest)` is called with the contents of `src/manifest.json`
- WHEN the function executes
- THEN it MUST return `{ valid: true, errors: null }`
- @e2e exclude a unit behaviour of `validateManifest()`; `npm run check:manifest` runs exactly this against the real schema in CI

#### Scenario: Unknown page type fails validation
- GIVEN a modified manifest with `pages[0].type = "wizard"`
- WHEN `validateManifest(manifest)` is called
- THEN it MUST return `{ valid: false, errors: <non-empty array> }`
- @e2e exclude a unit behaviour: it requires MUTATING the manifest to an invalid state, which an e2e against a running app cannot do

#### Scenario: Missing required page field fails validation
- GIVEN a modified manifest with a page entry missing the `id` field
- WHEN `validateManifest(manifest)` is called
- THEN it MUST return `{ valid: false, errors: <non-empty array> }` referencing the missing `id`
- @e2e exclude a unit behaviour: it requires MUTATING the manifest to an invalid state, which an e2e against a running app cannot do

---

### Requirement: main.js MUST import and register the manifest composable

`src/main.js` MUST import `manifest.json` as a bundled static import and pass it to
`useAppManifest('integriq', bundledManifest)` from `@conduction/nextcloud-vue`
before `new Vue().$mount('#content')`. The composable call returns `{ manifest,
isLoading, validationErrors }` which MUST be stored in a variable accessible for
chain D2's `CnAppRoot` wiring.

#### Scenario: main.js contains manifest import
- GIVEN the file `src/main.js` is read
- WHEN scanning its import statements
- THEN `import bundledManifest from './manifest.json'` MUST be present
- @e2e exclude a source-text assertion over `src/main.js`; the browser sees the bundle, not the import statement

#### Scenario: main.js calls useAppManifest
- GIVEN the file `src/main.js` is read
- WHEN scanning its body
- THEN `useAppManifest('integriq', bundledManifest)` MUST be called
- AND `useAppManifest` MUST be imported from `@conduction/nextcloud-vue`
- @e2e exclude a source-text assertion over `src/main.js`. That the call took effect IS proven in the browser, by every page-mount test in `manifest-pages.spec.ts`

#### Scenario: useAppManifest call is before Vue mount
- GIVEN the file `src/main.js` is read
- WHEN checking line order
- THEN the `useAppManifest` call MUST appear before `.$mount('#content')`
- @e2e exclude a source line-order assertion. The consequence of getting it wrong (an app that mounts with no manifest) is what the page-mount tests would catch

---

### Requirement: package.json MUST include a check:manifest script

The `package.json` file MUST include a `"check:manifest"` script that invokes
`validateManifest` on `src/manifest.json`. The script MUST exit with code 0 on
validation success and non-zero on validation failure.

#### Scenario: check:manifest script is present in package.json
- GIVEN `package.json` is loaded
- WHEN inspecting the `scripts` object
- THEN a key `"check:manifest"` MUST be present
- @e2e exclude a package.json key assertion

#### Scenario: check:manifest script validates the manifest
- GIVEN the check:manifest script is executed in CI
- WHEN `src/manifest.json` is valid
- THEN the script MUST exit with code 0
- @e2e exclude the script's own exit code in CI, which is where `check:specs` already runs it

#### Scenario: check:manifest fails on invalid manifest
- GIVEN the check:manifest script is executed
- WHEN `src/manifest.json` contains an invalid page type
- THEN the script MUST exit with a non-zero code
- AND MUST print the validation error to stdout or stderr
- @e2e exclude requires feeding the script a deliberately invalid manifest, which an e2e against a running app cannot do

---

### Requirement: Manifest MUST declare a Catalog page and menu entry

The manifest `pages` array MUST contain an entry with `id: "Catalog"`, `route: "/catalog"`, `type: "index"`, backed by `config.register: "openconnector"` and `config.schema: "catalog_item"`, with `config.viewMode: "cards"` and a `config.cardComponent` set. The manifest `menu` array MUST contain a corresponding entry (`id: "Catalog"`, `route: "Catalog"`) so the page is reachable from primary navigation.

Notes: This requirement is scoped narrowly to the Catalog addition. It does not attempt to reconcile the base `openconnector-app-manifest` spec's existing drift against `src/manifest.json` at HEAD (missing `roadmap` type in the type enum, stale page count/list, a phantom `Import` page, `Settings` vs. `AppSettings` id mismatch, flat-vs-grouped menu) — that drift predates this change and is out of scope here (see connector-catalog-ui proposal.md Out of Scope).

#### Scenario: Catalog page entry is present and uses the cards index pattern
- GIVEN the manifest file is loaded
- WHEN inspecting the page with id `"Catalog"`
- THEN its `type` field MUST be `"index"`
- AND `config.viewMode` MUST be `"cards"`
- AND `config.register` MUST be `"openconnector"` and `config.schema` MUST be `"catalog_item"`

#### Scenario: Catalog menu entry is present and routes to the Catalog page
- GIVEN the manifest file is loaded
- WHEN inspecting `manifest.menu` (including nested `children` arrays, per the existing grouped-nav structure)
- THEN an entry with id `"Catalog"` MUST exist
- AND its `route` MUST equal `"Catalog"`, matching the `pages[].id` of the Catalog page entry

#### Scenario: Catalog page does not require a new manifest page type
- GIVEN the manifest schema's `pages[].type` enum
- WHEN validating the Catalog page entry against it
- THEN validation succeeds using the existing `"index"` type — no new type value is introduced by this change


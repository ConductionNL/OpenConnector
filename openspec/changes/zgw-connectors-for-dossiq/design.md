# Design: zgw-connectors-for-dossiq

Kind: config. Packaged configuration sets over existing engines; the only
code is the set loader, which exists.

## D1. Set layout

`lib/Settings/configurations/zgw-<component>.json`, one per component, in
the packaged-set format `vng-klantinteracties-adapter` introduced. Each set
holds a `source` template (base URL, auth `jwt-zgw` with client id and
secret, `apiVersion`), `synchronizations[]` and `mappings[]` referenced by
slug. Sets never reference each other by id.

## D2. Target binding

An operator installs a set against a target `register` and `schema`. The
synchronization writes objects with storage strategy `external`, keeping
the remote `url` as `@self.externalId`, so the case app reads them through
the OpenRegister objects API like any object and `synced-from-tab` shows
provenance. Nothing in the set names a fleet app.

## D3. Freshness

`zgw-notificaties` registers one abonnement per installed component on the
remote Open Notificaties, callback to the existing endpoint
(`notificaties-api-connector` REQ-002). An inbound notification triggers a
targeted pull of the one resource named in `resourceUrl`, not a full sync.
Full sync stays scheduled as the safety net.

## D4. Write-back

For `zaken`, `documenten`, `besluiten` and `objecten` the set includes a
push synchronization (intern to extern) over the mapped properties. A
remote refusal keeps the local change and marks the object with
`syncStatus = conflict`, which `synced-from-tab` renders.

## D5. Versions

Every mapping goes through `ZgwResourceTranslatorInterface`
(`zgw-version-translation`) so one set serves a 1.x and a 1.6 store; the
`apiVersion` on the source decides.

## Risks
- A remote store with millions of zaken. Initial sync is paged and
  resumable; the operator can scope by `zaaktype`.
- Two sets targeting one schema. The installer refuses a second binding on
  the same schema and says which set holds it.

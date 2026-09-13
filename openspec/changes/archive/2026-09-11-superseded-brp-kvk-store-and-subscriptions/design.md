# Design: brp-kvk-store-and-subscriptions

Kind: code. A register, a subscription service per source, one event, one
leaf.

## D1. `registryStore` register

`person`: `bsn` (through OpenRegister's `BsnFormat`), name fields, birth
date, address, `fetchedAt`, `sourceVersion`, `subscription` (`none`,
`active`, `failed`), `holders[]` (`{app, register, schema, id}`).
`organisation`: `kvkNumber`, `name`, `legalForm`, `addresses[]`, the same
bookkeeping. Rows are keyed on `bsn` or `kvkNumber`; a second lookup updates
the row and adds a holder.

RBAC: read for the apps that hold the subject; `holders[]` is the scope.
Retention: a row with no holders is dropped after 30 days, subscription
first.

## D2. Lookup hand-off

The case app's adapters keep calling Haal Centraal and KvK directly. After a
successful lookup they dispatch `RegistrySubjectLookedUpEvent(kind, key,
payload, holder)`; integriq upserts the row and starts the subscription.
Without integriq the event has no listener and nothing changes for the case
app.

## D3. Subscriptions

- BRP: `PUT /ingeschrevenpersonen/{bsn}/volgindicaties` on the Haal Centraal
  BRP Bevragen source (the seeded one), then a scheduled poll of
  `/ingeschrevenpersonen?volgindicatie=...&sinds=` for changed BSNs.
- KvK: the mutatieservice abonnement per KvK number, changes fetched on a
  schedule.
- A `SubscriptionProviderInterface` with `subscribe`, `unsubscribe`,
  `pollChanges`, with a `log` binding for development.

## D4. Change announcement

A change diffs the stored row against the fresh payload, writes the row,
and publishes a CloudEvent
`nl.conduction.integriq.registry.<kind>.changed` with `key`, `diff` and
`holders[]`, plus a typed `RegistrySubjectChangedEvent` for in-process
listeners. The case app subscribes with a rule and updates its case.

## D5. `integriq-registry-subject`, kind data-provider

`list(register, schema, objectId)` reads the host object's `bsn` or
`kvkNumber` property (configured per schema) and returns the stored row with
`fetchedAt` and subscription state. `app-local`, no `create`, no verb.

## Risks
- Volgindicaties need a BRP subscription contract with the RvIG. The
  provider reports `failed` with the source's error, never silently `none`.
- Holders leaking across tenants. `holders[]` scoping follows the
  multitenancy dialect; a row is visible only to its holders' organisation.

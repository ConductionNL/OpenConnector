# registry-subscriptions Specification

**Status**: proposed
**Scope**: integriq
**OpenSpec changes**:
- brp-kvk-store-and-subscriptions

## Purpose

A local store of every person (BRP) and organisation (KvK) a fleet app has
looked up, a subscription to changes on each at the source, and an
announcement per change so the holding app updates. Integriq owns the store
and the subscriptions; the lookup and the reaction stay with the holding
app. Requested by the dossiq competitor analysis, finding B22.

## ADDED Requirements

### Requirement: A local store of looked-up persons and organisations (REQ-RSUB-001)

Integriq MUST declare a `registryStore` register with `person` (keyed on
`bsn` through `BsnFormat`) and `organisation` (keyed on `kvkNumber`), each
with `fetchedAt`, `sourceVersion`, `subscription` state and `holders[]`.
On `RegistrySubjectLookedUpEvent` integriq MUST upsert the row and add the
holder. A row with no holders MUST be dropped after 30 days, subscription
first. Rows MUST be readable only within the holders' organisation.

#### Scenario: Two cases hold one person
- GIVEN two lookups of the same BSN from two case objects
- WHEN both events are handled
- THEN one `person` row exists with two holders
- @e2e exclude event handling; covered by PHPUnit on `RegistryStoreService`

### Requirement: A subscription per stored subject (REQ-RSUB-002)

Integriq MUST define `SubscriptionProviderInterface` with `subscribe`,
`unsubscribe` and `pollChanges`, a `brp` binding using Haal Centraal
volgindicaties, a `kvk` binding using the mutatieservice and a `log` binding.
The store MUST subscribe on the first holder and unsubscribe on the last
removal. A subscription failure MUST set `subscription = failed` with the
source's error, never `none`.

#### Scenario: The source refuses the volgindicatie
- GIVEN a BRP source without a volgindicatie contract
- WHEN a person row is created
- THEN the row carries `subscription = failed` and the source's error text
- @e2e exclude provider failure; covered by PHPUnit with a mock-mode source

### Requirement: A change is announced with its diff and holders (REQ-RSUB-003)

On a change from `pollChanges` integriq MUST diff the fresh payload against
the row, write the row, dispatch `RegistrySubjectChangedEvent` and publish
a CloudEvent `nl.conduction.integriq.registry.<person|organisation>.changed`
carrying `key`, `diff` and `holders[]`. An unchanged payload MUST announce
nothing.

#### Scenario: A person moves house
- GIVEN a stored person and a poll returning a new address
- WHEN the changes job runs
- THEN the row has the new address and one CloudEvent with the address diff and the holders is published
- @e2e exclude background job; covered by PHPUnit with a log provider fixture

### Requirement: The stored subject is a data-provider leaf (REQ-RSUB-004)

Integriq MUST register a leaf `integriq-registry-subject` of kind
`data-provider`, storage `app-local`, whose `list` reads the host object's
configured `bsn` or `kvkNumber` property and returns the stored row with
`fetchedAt` and subscription state. It MUST NOT offer `create` and MUST NOT
call any action in the consuming app.

#### Scenario: A case shows its applicant's stored data
- GIVEN a case app places `integriq-registry-subject` on a case with a `bsn`
- WHEN a handler opens the case
- THEN the widget shows the stored person, when it was fetched, and whether the subscription is active
- e2e: `tests/e2e/registry-subject-leaf.spec.ts`

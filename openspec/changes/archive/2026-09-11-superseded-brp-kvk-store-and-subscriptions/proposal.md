---
kind: code
---

> **SUPERSEDED (2026-09-11)** — Ruben rejected this design on 2026-09-11.
> This change and OpenRegister's `registry-subscriptions`
> (`openregister/openspec/changes/registry-subscriptions`) were written the
> same morning and contradicted each other: this one gave integriq its own
> `registryStore` register holding a second copy of every subscribed person
> and company; OpenRegister's design updates the app's own person and
> company records in place, where they already live. Decision: one record
> per person or company, refreshed where it already lives, no second copy
> of personal data — that is data minimisation and matches the
> app-to-OpenRegister boundary (apps own their objects; OpenRegister owns
> storage, schema and query for all of them). dossiq's `contacts-domain`
> task 4.3 was already written against the OpenRegister shape before this
> rejection landed.
>
> Never implemented (all tasks in `tasks.md` were unchecked). Superseded by
> **`registry-subscription-connector`**, which keeps only the half of this
> proposal that is genuinely integriq's: turning OpenRegister's
> `RegistrySubscriptionRequestedEvent` into a live BRP/KvK subscription and
> posting source changes back to OpenRegister's inbound update endpoint.
> The `registryStore` register, `holders[]`, the CloudEvent fan-out and the
> `integriq-registry-subject` data-provider leaf are dropped: OpenRegister's
> `@self.registry` and query lenses on the app's own object make them
> redundant. Archived without merging its delta spec.

# Proposal: brp-kvk-store-and-subscriptions

## Summary
Keep a local copy of every person and organisation a case app has looked up,
subscribe to changes on them at the source (BRP afnemerindicatie, KvK
mutaties), and announce each change so the case that holds them updates.
Integriq owns the store and the subscriptions. The case app keeps its lookup
adapters and reacts to the announcement.

## Motivation
Zaaksysteem's Gegevensmagazijn holds persons and organisations locally with
an afnemerindicatie per record and a CSV export
(`zs/pages/Gegevensmagazijn.md`); OpenCase's enterprise edition listens to
CPR events (`oc/code-census.md`). Both under
`concurrentie-analyse/procest/_round2/`. dossiq's `HaalCentraalBrpAdapter`
and `KvkApiAdapter` look up and forget; nothing subscribes (finding B22,
M1 5.11). The analysis splits the row: the store and the subscriptions are
integriq's, the case-side reaction is dossiq's (D11).

Integriq already seeds a BRP Haal Centraal source (`source-management`) and
owns synchronizations and the CloudEvents fan-out.

## Scope
- A `registryStore` register with `person` (BRP) and `organisation` (KvK)
  schemas, one row per looked-up subject, with `fetchedAt` and
  `subscription` state.
- A subscription per row: Haal Centraal BRP `ingeschrevenpersonen`
  volgindicaties, KvK mutatieservice. Set on first lookup, dropped when no
  holder remains.
- Change intake: a poll or a notification per source, a diff on the row, a
  CloudEvent `nl.conduction.integriq.registry.<person|organisation>.changed`
  with the diff and the holders.
- A `data-provider` leaf (ADR-066) `integriq-registry-subject` that lists
  the stored row for a host object that references a BSN or KvK number.

## Out of scope
- The lookup UI. The case app has it (`brp-register`, `kvk-register`).
- Consent and purpose binding on the lookup. That stays where the lookup is.

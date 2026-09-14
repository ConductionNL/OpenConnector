---
kind: umbrella
depends_on: []
---

# Proposal: competitor-parity-2026-09

## Summary

This is the integriq half of the dossiq competitor parity programme. The
source of record is the gap register at `procest/_gaps/` in
ConductionNL/market-intelligence: `README.md`, `gap-register.md`,
`gap-register.json` and `ownership-rules.md`, written 2026-09-13.

Ruben's rule from the ownership rules governs the split. Dossiq reaches
100% comparability with the competition. Logic that belongs to another app
is specified in that app, and dossiq consumes it. Integriq is one of those
owner apps, so this umbrella indexes the logic integriq owes.

Nothing here is implemented. Each indexed change carries its own
`proposal.md`, `design.md`, `specs/` and `tasks.md`.

## The changes

Three changes on integriq's `development` cite the register. A sweep of
every other proposal in `openspec/changes/` found no fourth. Sizes are the
register's own, quoted in each proposal.

| change | rows | size | dossiq consumer |
|---|---|---|---|
| `document-generation-vendor-adapter` | 12.11 | M | Dossiq binds `TemplateEngineAdapterInterface` to filinq and deletes `MockTemplateEngineAdapter`. The vendor sits one hop behind filinq. |
| `signed-outbound-webhooks` | Q6.20 | S | None of its own. Dossiq retires its `WebhookHandler` under `dossiq-delivers-nothing`, and its outbound traffic is then signed by default. |
| `objecten-api-facade` | 12.3 | M | Dossiq declares its `caseObject` types as objecttypes in its endpoint declaration and ships no controller for the standard. |

Row 12.3 started on openregister. The openregister lane handed it here and
wrote the argument in its own umbrella: ADR-091 §6 puts ZGW, StUF, DSO and
Notificaties in integriq, and openregister's `specs/zgw-api-mapping` is a
redirect stub.

## Build order

1. `signed-outbound-webhooks`. Start now. It extends `webhook-signing`,
   which already ships signing, rotation and a one-time reveal. The work is
   to make the secret part of what a subscription is, and to show an
   unsigned subscription as unsigned.
2. `objecten-api-facade`. Start now. Openregister's half exists and is not
   rebuilt: the endpoint dispatch, the objects API, the schema export and
   the RBAC behind them.
3. `document-generation-vendor-adapter`. The seam can be built now. Its
   value reaches dossiq only after filinq offers a vendor engine per
   template, so build it last of the three.

## Halves another app carries

Two of the three rows close only when another app does its part.

- **Filinq**, for row 12.11. Filinq owns document generation under ADR-075.
  It needs a template `engine` per template on
  `document-creatie-sjablonen`, so a template can point at a vendor source
  instead of its own Twig engine. Without that, nothing calls the seam.
- **Dossiq**, for rows 12.11 and 12.3. Both halves are one binding or one
  declaration plus a deletion. They are named in dossiq's own umbrella.
- **Openregister**, for row 12.3. Its half is already shipped. This change
  configures it, it does not extend it.

## Register corrections

- Row Q6.20's `covered` column reads "none (events-cloudevents specifies
  retry and dead letter, not signing)". Integriq's outbound signing lives in
  `openspec/specs/webhook-signing/`, with `WebhookSignatureService`
  implementing it. Re-point the row at `webhook-signing`.
- Row 12.3 lists openregister as owner. Re-point it at integriq, citing
  ADR-091 §6.

## Discovery wave 1

A second source of record sits beside the gap register: the round 4
discovery sweep, `procest/_round4/discovery/` in
ConductionNL/market-intelligence, written 2026-09-14. Thirty-six systems
read, 631 consolidated candidates, 70 capability clusters in
`build-plan.md`, 22 decisions in `decisions.md`. Ruben answered all 22 on
2026-09-14 and lifted the build hold.

The ownership rule moves 57 of the 631 candidates to integriq, in eight
clusters. This section indexes what integriq opens in wave 1.

| change | cluster | candidates | size | decision | dossiq consumer |
|---|---|---|---|---|---|
| `registry-backed-field-source` | 26 "Fields read live from a registry, not copied", and depth-study CT-5 | C-integrations-9 (matrix hole), C-intake-10, C-parties-and-contacts-15, C-parties-and-contacts-4, C-integrations-11, C-integrations-43 | L | D2 | dossiq declares a source on a `propertyDefinition`, in its CT-1 change `casetype-field-vocabulary`, and stops being limited to one fixed address slot and one fixed person slot |

### The mail account is Nextcloud Mail's, so integriq opens nothing for it

`build-plan.md` puts cluster 28, "Mail accounts, OAuth2 and alias
domains", on integriq in wave 1, and `decisions.md` D12 recommended that
integriq hold the account, the token and the alias domains.

Ruben answered D12 differently: **Nextcloud Mail owns the mail account**,
because the OAuth 2.0 flow is already Nextcloud Mail's. Integriq opens no
mail-account change. The work moves to dossiq, whose intake pipeline reads
the account Nextcloud Mail already holds, and which keeps the filter
pipeline and the sender-authentication half. dossiq's wave 1 change is
`inbound-mail-filters`, cluster 25.

Cluster 60, "Outbound sender identity and deliverability", stays integriq's
and stays in a later wave. It now builds on a Nextcloud Mail account rather
than on an integriq one, which is a re-read that cluster needs before it is
written.

### What openregister owes this wave, and by what name

`registry-backed-field-source` needs one key on a schema property, and the
key is openregister's under D2. This umbrella asks for it by name so two
lanes do not invent two:

- **`x-openregister-property-source`**, `{ provider, config, mode }`, on a
  schema property, to be specified in openregister, wave 1.
- It is not `x-openregister-object-source`, which openregister's open
  change `object-source-providers` already uses to serve a whole schema's
  objects from a provider.

### Two decisions that change how the clusters are read

- **D6 was answered relevance-led.** Every `must` candidate enters the
  corpus as a row, whatever its passer count. A `must` cluster is not
  skipped for having one passer. In integriq's wave 1 that admits
  C-intake-10, a `must` with one documented passer and no driven one.
- **D17 was answered for a broad market.** The 20 candidates the lanes
  rated `not` are not disqualified: the product serves MKB as well as
  municipalities, so a `not` for a gemeente can be a `could` for an MKB
  buyer. None of the 20 falls in integriq's wave 1 cluster, so nothing in
  this section changes on that count.

### Later waves

Seven further integriq clusters wait: 23 the outbound communication log,
27 delivery, retry and replay, 33 directory synchronisation, 45 intake
channels beyond mail, 56 the statutory gateways, and 60 outbound sender
identity. Each is added to the table above in the PR that opens it.

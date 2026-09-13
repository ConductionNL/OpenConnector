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

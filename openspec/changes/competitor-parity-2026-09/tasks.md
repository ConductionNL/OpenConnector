# Tasks: competitor-parity-2026-09

- [ ] 1.1 Build `signed-outbound-webhooks`. Archive it on merge and tick it here.
- [ ] 1.2 Build `objecten-api-facade`. Archive it on merge and tick it here.
- [ ] 1.3 Build `document-generation-vendor-adapter`. Archive it on merge and tick it here.
- [ ] 2.1 Ask the filinq lane for a template `engine` per template on `document-creatie-sjablonen`, so a template can select a vendor source.
- [ ] 2.2 Hand the dossiq halves of rows 12.11 and 12.3 to the dossiq lane, with the row ids and the owner slug each half consumes.
- [ ] 3.1 Re-point row Q6.20's `covered` column at `webhook-signing` instead of `events-cloudevents`.
- [ ] 3.2 Re-point row 12.3's owner from openregister to integriq, citing ADR-091 §6.
- [ ] 3.3 Add any later integriq change that cites the gap register to the index above, in the same PR that opens it.

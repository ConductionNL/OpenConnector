# Tasks: registry-subscription-connector

## Implementation tasks

### Task 1: `SubscriptionProviderInterface` and the `log` binding
- **spec_ref**: `openspec/changes/registry-subscription-connector/specs/registry-subscription-connector/spec.md#requirement-a-subscription-provider-per-registry-req-rsc-001`
- **files**: `lib/Service/Registry/SubscriptionProviderInterface.php`, `lib/Service/Registry/SubscriptionResult.php`, `lib/Service/Registry/SubscriptionChange.php`, `lib/Service/Registry/LogSubscriptionProvider.php`
- [ ] Implement
- [ ] Test
- [BLOCKED] Confirm with the OpenRegister team whether `RegistrySubscriptionRequestedEvent`
  (design.md D2) is fanned out as a CloudEvent or is in-process only inside
  OpenRegister. `registry-subscriptions` has no implementation yet to check
  against. Do not build the listener in Task 3 against a guessed wire shape.

### Task 2: `BrpVolgindicatieProvider` and `KvkMutatieProvider`
- **spec_ref**: `openspec/changes/registry-subscription-connector/specs/registry-subscription-connector/spec.md#requirement-a-subscription-provider-per-registry-req-rsc-001`
- **files**: `lib/Service/Registry/BrpVolgindicatieProvider.php`, `lib/Service/Registry/KvkMutatieProvider.php`, `lib/Settings/register.d/kvk-mutatieservice-source.json`
- [ ] Implement (structural: calls the seeded source through `CallService`, not exercised against a live BRP/KvK subscription contract in this change)
- [ ] Test (against a faked `CallService`, not a real source)

### Task 3: The `RegistrySubscriptionRequestedEvent` listener
- **spec_ref**: `openspec/changes/registry-subscription-connector/specs/registry-subscription-connector/spec.md#requirement-a-subscription-request-is-turned-into-a-live-subscription-req-rsc-002`
- **files**: `lib/EventListener/RegistrySubscriptionRequestedListener.php`
- **Depends on**: Task 1's blocked item.
- [ ] Implement
- [ ] Test

### Task 4: The poll job and the outbound update
- **spec_ref**: `openspec/changes/registry-subscription-connector/specs/registry-subscription-connector/spec.md#requirement-a-polled-change-is-posted-to-openregister-not-stored-locally-req-rsc-003`
- **files**: `lib/BackgroundJob/RegistrySubscriptionPollJob.php`
- [ ] Implement
- [ ] Test

## Why nothing is checked yet

This change was written 2026-09-11 to retire the rejected
`brp-kvk-store-and-subscriptions` design and replace it with a spec that
matches the chosen `registry-subscriptions` (OpenRegister) shape. It is not
implemented in the same session: OpenRegister's own capability has no
inbound endpoint to POST to yet, and Task 1's open question has to be
answered before Task 3 can be built against a real event shape rather than
a guess. Implement once `registry-subscriptions` ships its endpoint and its
event delivery mechanism is confirmed.

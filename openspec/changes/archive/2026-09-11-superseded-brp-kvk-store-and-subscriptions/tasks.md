# Tasks: brp-kvk-store-and-subscriptions

## Implementation tasks

### Task 1: `registryStore` register and the lookup hand-off
- **spec_ref**: `openspec/changes/brp-kvk-store-and-subscriptions/specs/registry-subscriptions/spec.md#requirement-a-local-store-of-looked-up-persons-and-organisations-req-rsub-001`
- **files**: `lib/Settings/integriq_register.json`, `lib/Event/RegistrySubjectLookedUpEvent.php`, `lib/Service/Registry/RegistryStoreService.php`
- [ ] Implement
- [ ] Test

### Task 2: Subscription providers
- **spec_ref**: `openspec/changes/brp-kvk-store-and-subscriptions/specs/registry-subscriptions/spec.md#requirement-a-subscription-per-stored-subject-req-rsub-002`
- **files**: `lib/Service/Registry/SubscriptionProviderInterface.php`, `BrpVolgindicatieProvider.php`, `KvkMutatieProvider.php`, `LogSubscriptionProvider.php`, `lib/BackgroundJob/RegistryChangesJob.php`
- [ ] Implement
- [ ] Test

### Task 3: Change announcement
- **spec_ref**: `openspec/changes/brp-kvk-store-and-subscriptions/specs/registry-subscriptions/spec.md#requirement-a-change-is-announced-with-its-diff-and-holders-req-rsub-003`
- **files**: `lib/Event/RegistrySubjectChangedEvent.php`, `lib/Service/Registry/RegistryStoreService.php`
- [ ] Implement
- [ ] Test

### Task 4: The leaf, retention, docs
- **spec_ref**: `openspec/changes/brp-kvk-store-and-subscriptions/specs/registry-subscriptions/spec.md#requirement-the-stored-subject-is-a-data-provider-leaf-req-rsub-004`
- **files**: `lib/Integration/RegistrySubjectLeafProvider.php`, docs
- [ ] Implement
- [ ] Test (`tests/e2e/registry-subject-leaf.spec.ts`)

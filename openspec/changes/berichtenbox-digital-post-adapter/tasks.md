# Tasks: berichtenbox-digital-post-adapter

## Implementation tasks

### Task 1: Provider interface and log binding
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-one-provider-seam-with-log-berichtenbox-and-postex-bindings-req-dpa-001`
- **files**: `lib/Service/DigitalPost/DigitalPostProviderInterface.php`, `lib/Service/DigitalPost/LogDigitalPostProvider.php`, `lib/Service/DigitalPost/DigitalPostProviderRegistry.php`
- [ ] Implement
- [ ] Test

### Task 2: `digitalPostMessage` schema and send path
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-a-send-is-a-typed-command-with-a-tracked-message-req-dpa-002`
- **files**: `lib/Settings/integriq_register.json`, `lib/Event/DigitalPostSendRequestedEvent.php`, `lib/Event/DigitalPostDeliveredEvent.php`, `lib/Service/DigitalPost/DigitalPostService.php`
- [ ] Implement
- [ ] Test

### Task 3: Berichtenbox and Postex bindings
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-one-provider-seam-with-log-berichtenbox-and-postex-bindings-req-dpa-001`
- **files**: `lib/Service/DigitalPost/BerichtenboxProvider.php`, `lib/Service/DigitalPost/PostexProvider.php`
- [ ] Implement (activation refused without certificate and OIN)
- [ ] Test (mock-mode fixtures)

### Task 4: Inbound post to filinq, status job, health
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-inbound-post-feeds-the-document-intake-inbox-req-dpa-003`
- **files**: `lib/BackgroundJob/DigitalPostStatusJob.php`, `lib/BackgroundJob/DigitalPostInboundJob.php`
- [ ] Implement
- [ ] Test

### Task 5: Source form, i18n, docs
- Provider picker and config fields on the source page; Dutch and English strings; docs with screenshots.
- [ ] Implement
- [ ] Test (`tests/e2e/digital-post-source.spec.ts`)

### Task 6: The feature flag selects the binding
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-the-feature-flag-selects-the-binding-and-a-flagged-instance-without-credentials-refuses-req-dpa-005`
- **files**: `lib/AppInfo/Application.php`, `lib/Sources/Berichtenbox/BerichtenboxSourceAdapter.php`
- **acceptance_criteria**:
  - GIVEN `logius.berichtenbox.feature_flag` is `1` and credentials are absent WHEN a send runs THEN it is refused naming the missing credential, and the mock is not served
- [ ] Implement (the DI factory branches on the flag; today it does not)
- [ ] Test (both flag states, and the flagged-without-credentials refusal)

### Task 7: Credentials by reference
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-signing-material-is-resolved-by-reference-never-passed-by-value-req-dpa-004`
- **files**: `lib/Adapters/Berichtenbox/BerichtenboxClient.php`, `lib/Adapters/Berichtenbox/BerichtenboxClientMock.php`, `lib/Adapters/Digikoppeling/PkiOverheidCredentialResolver.php`
- **acceptance_criteria**:
  - GIVEN a source with a `certificateRef` WHEN a send runs THEN material is resolved inside integriq and no PEM appears in a call argument
- [ ] Implement (`dispatch(array $message, string $certificateRef)`, resolving through the existing resolver as `WusProfileService` does)
- [ ] Test (resolution, and the fail-closed path when the broker cannot supply)

### Task 8: The live network leg. BLOCKED, do not start
- **spec_ref**: `openspec/changes/berichtenbox-digital-post-adapter/specs/digital-post-adapter/spec.md#requirement-one-berichtenbox-code-path-built-on-the-client-that-ships-req-dpa-006`
- **files**: `lib/Adapters/Berichtenbox/BerichtenboxClientHttp.php`
- **blocked_on**:
  - Logius BBK OAuth 2.0 client credentials (procurement)
  - A PKIoverheid Services-server certificate (procurement)
  - `CredentialBrokerService::issueSigningMaterial` in OpenRegister, which does not exist, so `PkiOverheidCredentialResolver` fails closed for every reference
- **acceptance_criteria**:
  - GIVEN real credentials WHEN a letter is sent THEN it appears in the recipient's Berichtenbox and the delivery receipt verifies
- [ ] Implement (only once all three blockers clear)
- [ ] Test (against the Logius preproduction environment, not a fixture)

## Verification

- [ ] `openspec validate berichtenbox-digital-post-adapter --strict` passes
- [ ] PHPUnit run in the container, exit code read rather than the summary line
- [ ] `composer check:strict` passes
- [ ] No PEM string appears in any method signature, source configuration or
      app-config key added by this change
- [ ] With the flag unset, every send is reported as simulated on the
      Integrations surface dossiq reads

## Cross-repo follow-ups

- [ ] Record in `absorb-dossiq-deliveries` that its open Berichtenbox item is
      closed by this change, so the repo does not hold two shapes for one
      capability (design D7)
- [ ] Raise the OpenRegister need for `CredentialBrokerService::issueSigningMaterial`,
      which blocks Digikoppeling signing as well as this
- [ ] Tell dossiq when a real transport binds, so it can register
      `BerichtenboxReadStatusJob`, which is deliberately unscheduled until then

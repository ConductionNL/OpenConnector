# Tasks: kcc-cti-adapter

## Implementation tasks

### Task 1: Provider interface, log and webhook bindings, endpoint
- **spec_ref**: `openspec/changes/kcc-cti-adapter/specs/kiss-kcc-bridge/spec.md#requirement-a-telephony-provider-seam-with-a-verified-webhook-req-005`
- **files**: `lib/Service/Kiss/CtiProviderInterface.php`, `lib/Service/Kiss/LogCtiProvider.php`, `lib/Service/Kiss/WebhookCtiProvider.php`, `lib/Controller/CtiController.php`, `appinfo/routes.php`
- [ ] Implement
- [ ] Test (unverified request is 401; duplicate event is idempotent)

### Task 2: Caller identification and `CallEvent`
- **spec_ref**: `openspec/changes/kcc-cti-adapter/specs/kiss-kcc-bridge/spec.md#requirement-a-call-carries-its-caller-context-as-a-typed-event-req-006`
- **files**: `lib/Event/CallEvent.php`, `lib/Service/Kiss/CallContextService.php`
- [ ] Implement
- [ ] Test

### Task 3: Contact moment on request, retention
- **spec_ref**: `openspec/changes/kcc-cti-adapter/specs/kiss-kcc-bridge/spec.md#requirement-a-contact-moment-is-written-only-when-the-agent-asks-req-007`
- **files**: `lib/Service/Kiss/CallContextService.php`, `lib/Settings/integriq_register.json` (`callEvent` log with 30-day retention)
- [ ] Implement
- [ ] Test

### Task 4: Source form, i18n, docs
- CTI provider picker and field mapping on the source page; Dutch and English strings; docs with screenshots.
- [ ] Implement
- [ ] Test (`tests/e2e/cti-source.spec.ts`)

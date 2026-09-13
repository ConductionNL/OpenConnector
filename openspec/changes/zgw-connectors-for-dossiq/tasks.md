# Tasks: zgw-connectors-for-dossiq

## Implementation tasks

### Task 1: The six packaged sets
- **spec_ref**: `openspec/changes/zgw-connectors-for-dossiq/specs/zgw-consumer-connectors/spec.md#requirement-six-packaged-slug-referenced-zgw-consumer-sets-req-zgwc-001`
- **files**: `lib/Settings/configurations/zgw-zaken.json`, `zgw-documenten.json`, `zgw-catalogi.json`, `zgw-besluiten.json`, `zgw-objecten.json`, `zgw-notificaties.json`
- [ ] Implement
- [ ] Test (each set installs against a mock-mode source and pulls the fixture)

### Task 2: Target binding and the installer guard
- **spec_ref**: `openspec/changes/zgw-connectors-for-dossiq/specs/zgw-consumer-connectors/spec.md#requirement-a-set-binds-to-an-operator-chosen-register-and-schema-req-zgwc-002`
- **files**: `lib/Service/ConfigurationSetInstaller.php`
- [ ] Implement
- [ ] Test

### Task 3: Notification-triggered pull and write-back
- **spec_ref**: `openspec/changes/zgw-connectors-for-dossiq/specs/zgw-consumer-connectors/spec.md#requirement-an-external-change-shows-within-a-minute-and-a-local-change-writes-back-req-zgwc-003`
- **files**: `lib/Service/Zgw/ZgwNotificationPullListener.php`, the push synchronizations in the sets
- [ ] Implement
- [ ] Test

### Task 4: Docs and i18n
- Install guide per set, Dutch and English strings on the installer.
- [ ] Implement
- [ ] Test (`tests/e2e/zgw-set-install.spec.ts`)

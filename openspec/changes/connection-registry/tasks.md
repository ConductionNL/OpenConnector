# Tasks: connection-registry (integriq)

Contract: hydra umbrella `openspec/changes/connection-registry/design.md`, branch `feat/connection-registry`.

## 1. Contract files

- [ ] 1.1 `lib/Settings/connections.schema.json`, the JSON Schema for `connections.json` (D2).
- [ ] 1.2 `lib/Settings/register.d/connection-schema.json`, admin-only `connection` schema (D3).

## 2. Backend

- [ ] 2.1 `ConnectionDeclarationValidator`, mirroring the JSON Schema, with a drift test against it.
- [ ] 2.2 `ConnectionStatusResolver`, the D4 rules in order.
- [ ] 2.3 `ConnectionRegistryService::sync()`, `report()`, `refresh()`, `probe()`, `link()` (D5, D6, D7, D9).
- [ ] 2.4 `ConnectionStatusReportedEvent` and `ConnectionRefreshRequestedEvent` exactly as D6, with listeners that never throw.
- [ ] 2.5 `SyncConnectionDeclarations` repair step on install and post-migration, and an app lifecycle listener for `AppEnableEvent` and `AppDisableEvent`.
- [ ] 2.6 `SourceTestService`, shared by `SourcesController::test` and the health job.
- [ ] 2.7 `ConnectionHealthJob`, hourly, 25 probes, open breaker without a call, stale declarations re-synced (D7).
- [ ] 2.8 `ConnectionsController::link`, admin-only `POST /api/connections/{id}/link`.

## 3. Frontend

- [ ] 3.1 `AppConnections` index page and menu entry under the Connections group (D8).
- [ ] 3.2 `connectionStatus` and `connectionSettingsLabel` formatters.
- [ ] 3.3 `LinkSourceDialog`, opened by Add integration and by `?link=1` (D9).
- [ ] 3.4 English and Dutch strings in `l10n/en.json` and `l10n/nl.json`, then `npm run l10n:build`.

## 4. Tests

- [ ] 4.1 PHPUnit: resolver (every D4 row and both orderings), validator, sync, listeners, health job, controller.
- [ ] 4.2 Playwright spec for the overview page and the dialog (runs nightly, not in this PR).

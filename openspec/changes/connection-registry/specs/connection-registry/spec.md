# connection-registry Specification Delta (integriq)

**Status**: proposed
**Scope**: integriq. Implements the integriq side of the hydra umbrella change `connection-registry` (REQ-CONN-001 to REQ-CONN-007). The contract shapes live in the umbrella design, D1 to D9.

## ADDED Requirements

### Requirement: The sync turns declaration files into connection rows (REQ-CONN-001)

Integriq SHALL read `lib/Settings/connections.json` of every enabled app through `IAppManager::getAppPath`. It MUST validate the file against `lib/Settings/connections.schema.json` and MUST refuse a file whose `app` differs from the id of the app it was read from. A valid file SHALL become one `connection` row per entry, with slug `connection-{app}-{key}`.

@e2e exclude The sync is a backend step with no browser surface. ConnectionRegistryServiceTest and ConnectionDeclarationValidatorTest prove every scenario here.

#### Scenario: a valid file becomes one row per entry

- GIVEN an enabled app `dossiq` ships a valid `connections.json` with two entries
- WHEN the sync runs
- THEN two `connection` rows exist with `app` equal to `dossiq`
- AND each row's slug is `connection-dossiq-{key}`

#### Scenario: an invalid file is skipped whole

- GIVEN an app ships a `connections.json` where one entry has no `title`
- WHEN the sync runs
- THEN no row is written for that app
- AND the log carries an error naming the app and the failing path

#### Scenario: a file claiming another app's id is refused

- GIVEN the app `pipelinq` ships a `connections.json` whose `app` is `dossiq`
- WHEN the sync runs
- THEN no row is written or deleted from that file

### Requirement: The sync is idempotent and keeps linked rows (REQ-CONN-002)

The sync SHALL upsert by app and key. It SHALL delete a row whose key left the declaration only when the row has no `source`. A row with a `source` SHALL stay, with status `unavailable` and message "No longer declared by {app}.". Every write MUST run inside OpenRegister's system operation context.

@e2e exclude Backend behaviour with no browser surface. ConnectionRegistryServiceTest covers each scenario.

#### Scenario: running the sync twice writes nothing the second time

- GIVEN the sync has run once for an app
- WHEN it runs again with an unchanged file
- THEN no row is created, saved or deleted

#### Scenario: a removed key without a source is deleted

- GIVEN a row for key `brp` has no source
- WHEN the app ships a file without `brp` and the sync runs
- THEN the row is deleted

#### Scenario: a removed key with a source is kept

- GIVEN a row for key `brp` has a linked source
- WHEN the app ships a file without `brp` and the sync runs
- THEN the row still exists with its `source`
- AND its status is `unavailable` with "No longer declared by dossiq."

### Requirement: The resolver applies the D4 rules in order (REQ-CONN-003)

Integriq SHALL resolve `status`, `statusMessage` and `checkedAt` by the first rule of umbrella design D4 that applies: app disabled, declared unavailable, empty adapter key, newest observation, required settings filled, otherwise not checked. It MUST read the declaring app's settings through `IAppConfig::getValueString`.

@e2e exclude The resolver is a pure backend rule set. ConnectionStatusResolverTest asserts every D4 row and both orderings.

#### Scenario: a disabled app shows unavailable

- GIVEN the declaring app is disabled
- WHEN the resolver runs
- THEN the status is `unavailable` with "The dossiq app is disabled."

#### Scenario: an empty adapter key shows simulated

- GIVEN the declaration names `adapter.configKey` `berichtenbox_adapter`
- AND the app's config holds no value for that key
- WHEN the resolver runs
- THEN the status is `simulated` with the declared `simulatedMessage`

#### Scenario: simulated outranks a passing probe

- GIVEN a row whose last probe passed
- AND its adapter key is empty
- WHEN the resolver runs
- THEN the status is `simulated`

#### Scenario: the newer observation wins

- GIVEN a row's last report says `configured` at 10:00
- AND its last probe says `error` at 11:00
- WHEN the resolver runs
- THEN the status is `error` and `checkedAt` is 11:00

#### Scenario: saved settings show configured

- GIVEN the declaration requires `register` and `case_schema`
- AND both hold values in the app's config
- AND the row has no probe and no report
- WHEN the resolver runs
- THEN the status is `configured` with "Saved in the admin settings."

### Requirement: Apps report and refresh through two typed events (REQ-CONN-004)

Integriq SHALL listen for `OCA\Integriq\Event\ConnectionStatusReportedEvent` and `OCA\Integriq\Event\ConnectionRefreshRequestedEvent`. The report listener MUST refuse an unknown status or an undeclared app and key with a warning. It SHALL write `lastReport` and resolve the row. Neither listener SHALL throw into the sender.

@e2e exclude An in-process event exchange with no browser surface. ConnectionEventListenersTest and ConnectionRegistryServiceTest prove it.

#### Scenario: a report reaches the row

- WHEN dossiq sends `ConnectionStatusReportedEvent('dossiq', 'mailbox', 'configured', 'Logged in')`
- THEN the mailbox row's `lastReport` holds that status and message
- AND the row has been resolved

#### Scenario: an unknown key is refused without an exception

- WHEN an app sends a report for a key it never declared
- THEN no row changes
- AND the log carries a warning naming the app and key
- AND the listener returns normally

### Requirement: The health job probes linked sources every hour (REQ-CONN-005)

`ConnectionHealthJob` SHALL run every 3600 seconds and probe at most 25 rows with a `source`, oldest `lastProbe.at` first. A source whose `circuitBreakerState` is `open` SHALL be recorded as `error` without a call. Otherwise the job SHALL make the call `SourcesController::test` makes, with `persistLog` false. The job SHALL also sync every app whose version differs from its rows' `declaredVersion`.

@e2e exclude A cron job with no browser surface. ConnectionHealthJobTest and ConnectionRegistryServiceTest prove the cap, the order and the breaker rule.

#### Scenario: an open breaker is not called

- GIVEN a linked source's `circuitBreakerState` is `open` after 5 failures
- WHEN the health job runs
- THEN no outbound call is made for that source
- AND the row's `lastProbe` is `error` with "The circuit breaker is open after 5 failures."

#### Scenario: at most 25 probes per run

- GIVEN 30 rows with a linked source
- WHEN the health job runs
- THEN 25 rows are probed, those with the oldest `lastProbe.at` first

### Requirement: Integriq shows all connections on one admin page (REQ-CONN-006)

Integriq SHALL render an admin-only `index` page over `integriq/connection` under the Connections menu group, with `app` as a column and as the folder sidebar field. The page MUST NOT offer the built-in add, edit, copy or import actions.

#### Scenario: the overview lists connection rows with their app

- GIVEN integriq holds connection rows
- WHEN an admin opens App connections from the Connections group
- THEN the table shows the app, connection, status, status message, last checked and settings columns

#### Scenario: the overview offers no free-form row

- WHEN an admin opens App connections
- THEN no Add button is shown

### Requirement: Add integration links a source and probes it at once (REQ-CONN-007)

The overview SHALL offer "Add integration", which opens `LinkSourceDialog`. The dialog SHALL list only declared connections without a source. The admin SHALL pick an existing source or create one from the connection's `sourceTemplate`. Saving SHALL link the source, probe it at once and show the result. The route query `link=1` SHALL open the same dialog, pre-filtered by `app`.

#### Scenario: Add integration opens the dialog

- WHEN an admin chooses Add integration on App connections
- THEN the link a source dialog opens
- AND it offers no field to type a new connection key

#### Scenario: the link query opens the dialog pre-filtered

- WHEN an admin opens App connections with `?app=dossiq&link=1`
- THEN the link a source dialog opens
- AND its app filter is dossiq

#### Scenario: linking a source probes it straight away

@e2e exclude Linking writes a source link and fires a live probe against an outside system, which the nightly instance cannot answer deterministically. ConnectionProbeServiceTest and ConnectionsControllerTest prove the link, the probe and the response.

- GIVEN a declared connection without a source
- WHEN an admin links an existing source through the link endpoint
- THEN the row's `source` holds that source's uuid
- AND the response carries the probe result

#### Scenario: a connection that already has a source is refused

@e2e exclude An API refusal with no page of its own. ConnectionProbeServiceTest and ConnectionsControllerTest assert the 409 and the unchanged row.

- GIVEN a connection with a linked source
- WHEN an admin posts a link for it
- THEN the response is 409 and the row keeps its source

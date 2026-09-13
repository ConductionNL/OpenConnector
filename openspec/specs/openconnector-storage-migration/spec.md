# openconnector-storage-migration Specification

## Purpose
TBD - created by archiving change openconnector-register-storage. Update Purpose after archive.
## Requirements
### Requirement: Migration class MUST provision the register via importFromApp

The system MUST ship a Nextcloud migration class at
`lib/Migration/Version2Date20260908000000.php` that on its `postSchemaChange`
hook calls
`OCA\OpenRegister\Service\ConfigurationService::importFromApp('openconnector',
<absolute-path-to-integriq_register.json>, <integriq-app-version>,
false)` exactly once per upgrade. The call MUST be idempotent — re-running the
migration is a no-op.

#### Scenario: Fresh install
- GIVEN integriq is being installed for the first time
- WHEN Nextcloud runs `occ app:enable integriq`
- THEN the migration class executes
- AND `oc_openregister_registers` gains one row with `slug='openconnector'`
- AND `oc_openregister_schemas` gains 15 rows
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path

#### Scenario: Idempotent re-run
- GIVEN `oc_openregister_registers` already contains a row with
  `slug='openconnector'`
- WHEN Nextcloud re-runs the migration (e.g. on app upgrade)
- THEN the migration class executes without error
- AND no duplicate register or schema rows are created
- AND schema-metadata updates are applied in-place
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path

### Requirement: Migrator MUST copy all legacy rows preserving uuids

The system MUST ship `lib/Service/Migration/LegacyToRegisterMigrator.php`
exposing a `migrateAll(bool $dryRun, ?string $entitySlug, int $batchSize): array`
method. For each of the 15 entities, the migrator MUST:

1. Read row count from the matching `oc_openconnector_<table>`.
2. Stream rows in batches of `$batchSize` (default 10,000).
3. INSERT each row into `oc_openregister_objects` with the existing
   `uuid` preserved verbatim and `register`/`schema` resolved to the
   openconnector register's primary key.
4. Build the `object` JSON column using platform-specific SQL functions:
   - On MySQL/MariaDB: `JSON_OBJECT(key1, val1, key2, val2, …)`
   - On PostgreSQL: `jsonb_build_object(key1, val1, key2, val2, …)`
   Platform detection MUST use `IDBConnection::getDatabasePlatform()`.
5. Log row counts before and after each entity to the migration log.
6. Return a per-entity result array (slug, legacyCount, migratedCount, skipped,
   skippedReason, fkRewrites, elapsedMs).

#### Scenario: Migrate sources entity
- GIVEN `oc_openconnector_sources` contains 12 rows
- WHEN the migrator runs against `entity='source'`
- THEN 12 rows are inserted into `oc_openregister_objects` with `register` =
  the openconnector register PK and `schema` = the source schema PK
- AND each inserted row's `uuid` matches the source row's `uuid` byte-for-byte
- AND the migration log records "source: 12 → 12, 0 skipped"
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path

#### Scenario: Postgres JSON build
- GIVEN the runtime database is PostgreSQL
- WHEN the migrator INSERTs into `oc_openregister_objects`
- THEN the SQL statement uses `jsonb_build_object(...)` to assemble the
  `object` column
- AND the resulting `object` value is JSONB type
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

#### Scenario: MySQL JSON build
- GIVEN the runtime database is MariaDB or MySQL
- WHEN the migrator INSERTs into `oc_openregister_objects`
- THEN the SQL statement uses `JSON_OBJECT(...)` to assemble the `object`
  column
- AND the resulting `object` value is JSON type
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

#### Scenario: UUID preservation
- GIVEN a `Source` row with `uuid='00000000-0000-0000-0000-000000000123'`
- WHEN the migrator processes it
- THEN the resulting `oc_openregister_objects` row has the same uuid
- AND any existing FK that referenced this uuid as a string-typed column
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one
  still resolves after migration

### Requirement: FK rewrite pass MUST translate 6 integer FK columns to uuids

The migrator MUST translate all 6 legacy integer foreign-key columns into target-uuid relation fields during a dedicated second SQL pass.

After the bulk INSERT pass completes for the two entities with integer FKs
(`call_log` and `event_message`), the migrator MUST run a second pass that
rewrites each integer FK column into the target schema's uuid. The 6 FKs
covered:

| Source schema   | Legacy column          | Target table           | Resulting field   | Failure handling          |
|-----------------|------------------------|------------------------|-------------------|----------------------------|
| `call_log`      | `source_id` (int)      | `oc_openconnector_sources` | `source` (uuid)    | Skip + log on missing target |
| `call_log`      | `synchronization_id` (int) | `oc_openconnector_synchronizations` | `synchronization` (uuid) | Skip + log     |
| `call_log`      | `action_id` (int)      | (ambiguous — see notes) | `action` (string) | Migrate as opaque, no FK rewrite |
| `event_message` | `event_id` (int)       | `oc_openconnector_events` | `event` (uuid)     | Skip + log                 |
| `event_message` | `consumer_id` (int)    | `oc_openconnector_consumers` | `consumer` (uuid) | Skip + log                 |
| `event_message` | `subscription_id` (int) | `oc_openconnector_event_subscriptions` | `subscription` (uuid) | Skip + log |

After the rewrite, BOTH the legacy `*Id` field (with the integer value) AND
the relation-named field (with the resolved uuid) MUST be present in the OR
object's payload, per chain A REQ-A-008.

#### Scenario: Rewrite CallLog.sourceId to source uuid
- GIVEN a `oc_openconnector_call_logs` row with `source_id=42` migrated as
  `{"sourceId": 42}` in the OR object
- AND `oc_openconnector_sources` row with `id=42` has
  `uuid='00000000-0000-0000-0000-000000000042'`
- WHEN the FK rewrite pass runs
- THEN the OR object's `object` JSON is updated to contain
  `"source": "00000000-0000-0000-0000-000000000042"`
- AND the legacy `"sourceId": 42` field is preserved (chain A REQ-A-008)
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

#### Scenario: Missing target row triggers skip + log
- GIVEN a `oc_openconnector_call_logs` row with `source_id=99` where no
  `oc_openconnector_sources.id=99` exists (orphaned FK in legacy data)
- WHEN the FK rewrite pass runs
- THEN the OR object's `source` field is NOT set
- AND the migration log records "call_log row {uuid}: sourceId=99 has no
  target — skipped FK rewrite, legacy sourceId preserved"
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

### Requirement: Synchronization.sourceId/targetId branching MUST handle 3 formats

The migrator MUST detect the format of every
`oc_openconnector_synchronizations.source_id` and `target_id` value and
rewrite according to the same logic as
`OCA\Integriq\Service\SynchronizationService` lines 141–545:

| Pattern                              | Action                                                   |
|--------------------------------------|----------------------------------------------------------|
| `^\d+$` (integer PK as string)       | JOIN to `oc_openconnector_sources` and substitute uuid   |
| `^[\w-]+\/[\w-]+$` (register/schema) | Leave as-is — already an OR slug pair                    |
| RFC 4122 uuid                        | Leave as-is — already an OR uuid                          |
| anything else                        | Skip + log; legacy value preserved; migrator counts the skip |

Per-format counts MUST be logged at the end of the synchronization migration
(e.g. "synchronization: integer-PK→uuid: 4, register/schema: 2, uuid: 1,
unrecognised: 1 (skipped)").

#### Scenario: Integer PK gets resolved
- GIVEN a synchronization row with `source_id='42'`
- AND `oc_openconnector_sources.id=42` has
  `uuid='00000000-0000-0000-0000-000000000042'`
- WHEN the migrator processes this row
- THEN the resulting OR object's `sourceId` field is
  `"00000000-0000-0000-0000-000000000042"`
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

#### Scenario: Register/schema slug pair preserved
- GIVEN a synchronization row with `source_id='zaken/zaak'`
- WHEN the migrator processes this row
- THEN the resulting OR object's `sourceId` field is `"zaken/zaak"`
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one
  unchanged

#### Scenario: Unrecognised format skipped
- GIVEN a synchronization row with `source_id='not-a-recognised-format'`
- WHEN the migrator processes this row
- THEN the migration log records the skip with row uuid + raw value
- AND the migrator's return tally increments `unrecognised` by 1
- AND the OR object's `sourceId` field is set to the raw legacy value (not
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one
  rewritten)

### Requirement: Migrator MUST set the storage_migrated app-config flag on success

The migrator MUST set `openconnector.storage_migrated = "true"` via IAppConfig only after every per-entity copy, the FK rewrite pass, and the sourceId branching pass have all completed without error.

After all 15 entities migrate successfully AND the FK-rewrite + branching
passes complete, the migrator MUST set
`IAppConfig::setValue('openconnector', 'storage_migrated', 'true')`. If any
entity fails (exception or post-migration row-count mismatch), the flag MUST
remain unset (or set to `"false"` if previously set in a partial earlier
run).

#### Scenario: Success path flips flag
- GIVEN all 15 entities migrate without skips or errors
- WHEN the migrator's `migrateAll()` returns
- THEN `IAppConfig::getValue('openconnector', 'storage_migrated', null)` is
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path. The `storage_migrated` flag itself is still read, by SynchronizationContractProvider among others
  `'true'`

#### Scenario: Failure path leaves flag false
- GIVEN entity 7 (job) raises an exception during migration
- WHEN the migrator's `migrateAll()` returns (or throws)
- THEN `IAppConfig::getValue('openconnector', 'storage_migrated', null)` is
  NOT `'true'` (either unset or `'false'`)
- AND mappers continue to use the legacy table path
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path

### Requirement: ObjectMapperFacade MUST translate the mapper API to ObjectService

The system MUST ship `lib/Service/Storage/ObjectMapperFacade.php` exposing
methods that match the existing mapper API:

- `find(string $registerSlug, string $schemaSlug, int $id): Entity`
- `findByUuid(string $registerSlug, string $schemaSlug, string $uuid): Entity`
- `findBySlug(string $registerSlug, string $schemaSlug, string $slug): Entity`
- `findAll(string $registerSlug, string $schemaSlug, ?int $limit, ?int $offset, array $filters, ?string $search, ?array $sort): Entity[]`
- `createFromArray(string $registerSlug, string $schemaSlug, array $data, string $entityClass): Entity`
- `updateFromArray(string $registerSlug, string $schemaSlug, string $uuid, array $data, string $entityClass): Entity`
- `delete(string $registerSlug, string $schemaSlug, string $uuid): void`

Each method MUST internally invoke OR's `ObjectService` with the appropriate
filter, then hydrate the returned `ObjectEntity` into the integriq
typed entity class (`Source`, `Job`, …) via the entity's `hydrate(array)`
method (already exists on all 15 entities).

The facade MUST maintain a per-mapper int-id→uuid cache. The cache MUST be
populated lazily on first `find(int)` call and invalidated on any
`createFromArray` or `delete` call for the same register/schema.

#### Scenario: find(int) resolves via the cache

> ⚠️ **Stale as written.** Verified 2026-09-07: the entity mappers this describes are deleted: `lib/Db` does not exist. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN a freshly-booted facade with an empty cache
- AND an OR object exists for `(openconnector, source, uuid=U)` whose legacy
  payload includes `"id": 42`
- WHEN the facade is asked `find(registerSlug='integriq', schemaSlug='source', id=42)`
- THEN it queries OR with filter `["id" => 42]`, retrieves the object, caches
  `42 → U`, and returns a hydrated `Source` entity
- AND a second `find(..., id=42)` call resolves via the cache without a new
  OR query

#### Scenario: createFromArray hydrates returned typed entity

> ⚠️ **Stale as written.** Verified 2026-09-07: the entity mappers this describes are deleted: `lib/Db` does not exist. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN the facade is asked `createFromArray('openconnector', 'source',
  ['name' => 'New Source', 'type' => 'api'], Source::class)`
- WHEN OR's `ObjectService::saveObject` returns an `ObjectEntity`
- THEN the facade returns a `Source` instance whose properties were populated
  via `(new Source())->hydrate($objectEntity->getObject())`

#### Scenario: delete invalidates the cache

> ⚠️ **Stale as written.** Verified 2026-09-07: the entity mappers this describes are deleted: `lib/Db` does not exist. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN the facade's int-id→uuid cache contains `42 → U` for
  `(openconnector, source)`
- WHEN the facade is asked `delete('openconnector', 'source', uuid=U)`
- THEN the cache entry for that register/schema is dropped
- AND a subsequent `find(..., id=42)` re-queries OR

### Requirement: 15 mappers MUST delegate to the facade behind a flag

Every `*Mapper.php` in `lib/Db/` MUST be rewritten as a thin wrapper. Each
mapper:

- Reads `openconnector.storage_migrated` once at construction time and caches
  the result.
- If `false`: continues to use its existing `QBMapper`-based SQL path
  (unchanged).
- If `true`: delegates every public method to `ObjectMapperFacade` with the
  appropriate register slug (`'openconnector'`), schema slug (per the
  mapper), and entity class.

Public method signatures, exception types, and return types MUST be
preserved byte-for-byte from before this change.

#### Scenario: Flag false routes to legacy path

> ⚠️ **Stale as written.** Verified 2026-09-07: there is no legacy path left to route to; no file under `lib/` carries one. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN `openconnector.storage_migrated = 'false'`
- WHEN any controller calls `SourceMapper::find(42)`
- THEN execution runs the legacy SQL query against `oc_openconnector_sources`
- AND the returned `Source` entity is identical to the pre-migration behaviour

#### Scenario: Flag true routes to facade path

> ⚠️ **Stale as written.** Verified 2026-09-07: there is no facade left to route to; no file under `lib/` names ObjectMapperFacade. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN `openconnector.storage_migrated = 'true'`
- WHEN any controller calls `SourceMapper::find(42)`
- THEN execution routes to `ObjectMapperFacade::find('openconnector', 'source', 42)`
- AND the returned `Source` entity has the same shape as the legacy-path
  return

#### Scenario: Append-only enforcement on log mappers

> ⚠️ **Stale as written.** Verified 2026-09-07: the log mappers this describes are deleted: `lib/Db` does not exist. The cutover this spec describes has COMPLETED, and its transitional machinery is gone. Left visible rather than annotated, because a waiver would record coverage for deleted code.
- GIVEN `openconnector.storage_migrated = 'true'`
- WHEN any controller calls `JobLogMapper::updateFromArray(uuid=U, data=[...])`
- THEN execution propagates OR's `AppendOnlyException` (or its facade
  translation to `LogicException`)
- AND no UPDATE is issued

### Requirement: OCC command MUST allow manual re-runnability

The system MUST ship an OCC command at
`lib/Command/MigrateToOpenRegister.php` invokable as
`occ integriq:migrate-storage [--dry-run] [--entity=<slug>] [--batch-size=<n>]`.
The command MUST:

- Validate args (entity in the 15 slugs, batch-size in [100, 100000]).
- Call the same `LegacyToRegisterMigrator::migrateAll(...)` that the
  migration class uses.
- Emit verbose progress (one line per batch).
- Exit 0 on success, non-zero on partial/total failure.

#### Scenario: Dry-run reports counts without writing
- GIVEN `oc_openconnector_sources` has 12 rows
- WHEN an admin runs `occ integriq:migrate-storage --entity=source --dry-run`
- THEN the command exits 0
- AND output reports "source: 12 rows would migrate"
- AND `oc_openregister_objects` count for the source schema is unchanged
- AND `openconnector.storage_migrated` is unchanged
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path

#### Scenario: Per-entity retry after partial failure
- GIVEN a previous migration failed during the `job` entity, leaving
  `storage_migrated` unset
- WHEN an admin runs `occ integriq:migrate-storage --entity=job`
- THEN only the `job` entity migrates
- AND on success, the flag is NOT flipped (because other entities are still
  legacy); a follow-up full run is required
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path (`lib/Command/MigrateToOpenRegister.php`)

### Requirement: Legacy tables MUST be dropped once every row has reached OpenRegister

The rollback buffer this requirement used to describe has served its purpose and
is now removed. `Version2Date20260908000000` drops all 15 `oc_openconnector_*`
tables in the same `occ upgrade` that drains them.

The drop is conditional. It runs only when the drain reports every entity copied,
or when `storage_migrated` was already `'true'` from an earlier run. A drain that
reports a skip or an error leaves every table in place, because dropping a table
whose rows did not reach OpenRegister would destroy them.

A fresh install never creates these tables at all, so there is nothing to drop
there.

#### Scenario: Legacy tables dropped after a successful drain
- GIVEN an instance carrying the 15 legacy tables
- WHEN `occ upgrade` runs and the drain reports every entity copied
- THEN `storage_migrated` is `'true'`
- AND none of the 15 `oc_openconnector_*` tables remains
- @e2e exclude a database state reached through `occ upgrade`. A browser session never sees a table list, and the drop runs on the console path only

#### Scenario: Legacy tables kept when the drain is incomplete
- GIVEN an instance carrying the 15 legacy tables
- WHEN `occ upgrade` runs and the drain reports a skip or an error for any entity
- THEN `storage_migrated` is NOT set
- AND every legacy table is left in place, so no unmigrated row is lost
- AND the migration reports that `occ integriq:migrate-storage` should be retried before the next upgrade
- @e2e exclude the same `occ upgrade` path as the scenario above, and it turns on a partial drain that a browser cannot provoke

#### Scenario: Rollback to the legacy read path is no longer possible

> Recorded as removed rather than deleted. The legacy read path was deleted at
> the cutover, and the tables that backed it are now dropped too, so setting
> `storage_migrated` back to `'false'` no longer restores anything.

- GIVEN an instance where the migration has dropped the legacy tables
- WHEN an admin runs `occ config:app:set integriq storage_migrated --value=false`
- THEN no read routes to a legacy table, because neither the path nor the tables exist
- @e2e exclude an `occ` console behaviour over dropped tables, with no browser surface

### Requirement: Credential columns on Source MUST be copied verbatim during migration (currently plaintext)

The migrator MUST copy every credential column on `Source` verbatim into the OR object's JSON body — no decrypt, re-encrypt, or transformation steps.

The 6 string columns on `Source` that store credentials (`apikey`,
`password`, `secret`, `jwt`, `username`, plus any string-typed entries inside
`authenticationConfig`) are currently stored as **plaintext** in
`oc_openconnector_sources` — per ADR-007, no `EncryptionService` class exists
in integriq, and `SourceMapper::insert()` does NOT apply encryption.
The migrator MUST copy these columns into OR storage exactly as they appear
in the legacy tables, preserving the plaintext content as-is.

The migrator MUST assert (at startup) that the codebase is in the expected
state (no `OCA\Integriq\Service\EncryptionService` class present, no
encryption hook in `lib/Db/Source.php` setters). If the assertion fails —
indicating encryption has been introduced since this spec was written — the
migrator MUST abort BEFORE any write to OR storage and emit a message
pointing the operator at this spec and ADR-007.

Once a real `EncryptionService` is implemented (see "MISSING-ADR-5" in
[audit-2026-05-20]), a follow-up change MUST decide whether to decrypt
during migration and re-encrypt via OR, or keep verbatim copy. That decision
belongs in the follow-up change, not here.

#### Scenario: Plaintext-credentials state confirmed at startup (current codebase)
- GIVEN no `OCA\Integriq\Service\EncryptionService` class exists in the codebase
- AND `lib/Db/Source.php` setters apply no encryption (verified by grep for `encrypt(` returning zero matches in `lib/Db/Source.php`)
- WHEN the migrator's startup assertion runs
- THEN the assertion passes
- AND the migrator copies the credential columns verbatim from `oc_openconnector_sources` to `oc_openregister_objects` with no transformation
- @e2e exclude a boot-time assertion about the codebase, made before any request is served

#### Scenario: Encryption-was-introduced-since-this-spec aborts (defensive)
- GIVEN an `OCA\Integriq\Service\EncryptionService` class HAS BEEN ADDED to the codebase since this spec was written (hypothetical future state)
- WHEN the migrator's startup assertion runs and detects the class
- THEN it raises `\LogicException` "encryption layer introduced since chain B spec was written; the verbatim-copy strategy no longer applies — see ADR-007 follow-up and revise this requirement"
- AND no rows are written to OR
- @e2e exclude a defensive abort that requires encryption to have been introduced, a state this codebase is not in

### Requirement: Owner field MUST be left null on every migrated object

Every OR object the migrator writes MUST have its `owner` column set to NULL, regardless of whether the source row carries a `userId` value.

Integriq is a system-level integration platform; rows are not user-owned
data. The migrator MUST leave OR's `owner` column null (treated by OR as
system-owned) for every migrated object, regardless of whether the source row
has a `userId` value. The legacy `userId` value (where present on log entities
like CallLog/JobLog/EventMessage) MUST still be copied into the object's JSON
body as `userId` for provenance — only the OR-managed `owner` column is left
null.

#### Scenario: Job row migrates with null owner
- GIVEN a `oc_openconnector_jobs` row with `user_id='ruben'`
- WHEN the migrator processes it
- THEN the resulting OR object has `owner IS NULL`
- AND the object JSON body retains `userId: 'ruben'` as a regular property
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

#### Scenario: Source row migrates with null owner
- GIVEN a `oc_openconnector_sources` row (Source has no `userId` column)
- WHEN the migrator processes it
- THEN the resulting OR object has `owner IS NULL`
- @e2e exclude a PHPUnit behaviour of the migration service, not a DOM one

### Requirement: Migrator MUST NOT emit per-object audit-trail entries

The migrator MUST bypass OR's per-object audit-trail emission during the bulk migration and MUST emit exactly one summary audit-trail entry at the end of the run.

OR's `audit-trail-immutable` capability normally emits one audit-trail entry
per object write. For bulk migration this would multiply storage by ~2× and
take prohibitively long. The migrator MUST:

- Bypass per-object audit emission during the bulk INSERT.
- Emit exactly ONE summary audit-trail entry at the end of the migration with
  per-entity counts and the storage_migrated flag flip event.

This is permitted by the `audit-trail-immutable` spec's bulk-migration
exception.

#### Scenario: One summary audit entry per migration run
- GIVEN the migrator processes 4,193,847 CallLog rows + 15 other entities
- WHEN the migrator completes
- THEN exactly ONE audit-trail entry exists for the migration run
- AND that entry contains a `perEntity` summary with all 15 entities' counts
- @e2e exclude an `occ upgrade` / console behaviour. A migration, its repair step and the retry command run there and nowhere else, so a browser session never reaches this path


# Tasks: allowlisted-expression-sources

Kind: code. Size S. Round 4 discovery depth study D-casetype-20, consolidated
candidate C-access-and-privacy-40, passer Valtimo `V-vr` and D-valtimo-48.
Openregister keeps the expression language under decision D3 and the rest of
cluster 4. Waits on nothing.

## Implementation tasks

### Task 1: The prefixed source contract
- **spec_ref**: `openspec/changes/allowlisted-expression-sources/specs/expression-value-sources/spec.md#requirement-a-prefixed-value-source-is-resolved-through-one-contract-req-evs-001`
- **files**: `lib/Expression/ExpressionValueSourceInterface.php`, `lib/Expression/ExpressionValueSourceRegistry.php`, `lib/AppInfo/Application.php` (DI tag)
- [ ] Implement (`prefix`, `resolve`, `describe`; first-wins collision policy as `IntegrationRegistry`; no parsing and no evaluation)
- [ ] Test (an unregistered prefix fails naming itself and consults no other source)

### Task 2: The `env:` source and its allowlist
- **spec_ref**: `openspec/changes/allowlisted-expression-sources/specs/expression-value-sources/spec.md#requirement-env-resolves-only-an-allowlisted-key-req-evs-002`
- **files**: `lib/Expression/Source/EnvironmentValueSource.php`, the allowlist storage
- [ ] Implement (exact-key match only; a refusal that names the key and returns neither the value nor an empty string; wildcard, pattern and empty entries refused at save)
- [ ] Test (an allowlisted key, a key absent from the list, and a `*` entry)

### Task 3: The administration surface
- **spec_ref**: `openspec/changes/allowlisted-expression-sources/specs/expression-value-sources/spec.md#requirement-the-allowlist-is-administered-and-every-change-is-recorded-req-evs-003`
- **files**: the admin settings panel, the audit write
- [ ] Implement (administrator only, each key with its principal and timestamp, additions and removals recorded, the value never printed)
- [ ] Test (a non-administrator refused, and a rendered list with no values)

### Task 4: Redaction before buffering
- **spec_ref**: `openspec/changes/allowlisted-expression-sources/specs/expression-value-sources/spec.md#requirement-a-resolved-external-value-is-redacted-before-it-is-buffered-req-evs-004`
- **files**: the resolver, the redaction path of `execution-trace` REQ-003
- [ ] Implement (a source may declare a resolved value a secret; redaction before the write, never on read)
- [ ] Test (a buffered step inspected directly holds no value)

### Task 5: Declared write capability
- **spec_ref**: `openspec/changes/allowlisted-expression-sources/specs/expression-value-sources/spec.md#requirement-writing-back-is-declared-and-absent-unless-declared-req-evs-005`
- **files**: the contract, the `env:` source
- [ ] Implement (`describe()` states it; `env:` declares no write; a refused write is an error, never a silent success)
- [ ] Test

### Task 6: Coordination, docs and the hand-offs
- **files**: `docs/`, Dutch and English strings, this change's row in `competitor-parity-2026-09`
- [ ] Tell the openregister lane that the prefix registry exists, so `field-rules-by-state`, `lifecycle-declarative-conditions` and the JSON-AST evaluator ask it rather than reading the environment themselves
- [ ] Say in the same message that C-access-and-privacy-40 sits in cluster 4, which is openregister's, and that this change takes only the source half
- [ ] Point `rule-pipeline` and `flow-token-helper` at the registry so integriq has one reach outward and not three
- [ ] Test (`tests/e2e/expression-value-sources.spec.ts`, `openspec validate allowlisted-expression-sources --type change --strict`)

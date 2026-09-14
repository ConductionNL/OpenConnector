# Tasks: statutory-gateways-and-frameworks

Kind: code. Size L. Round 4 discovery cluster 56, candidates C-integrations-27,
28, 29, 37, 47, 3, 4, 30, 36, 39, 46 and 23, rows 12.9 and 12.17. Decisions
D21 and D6. Waits on nothing.

## Implementation tasks

### Task 1: The gateway catalogue entry carries its law
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-a-gateway-declares-its-standard-and-its-conformance-claim-req-sg-001`
- **files**: the PHP adapter metadata registry behind `connector-catalog` REQ-003, the catalogue page filter
- [ ] Implement (`standard`, `claimLevel`, `claimEvidence`, a standard facet, wording that says claim and not certificate)
- [ ] Test (an entry with no standard fails registration naming itself)

### Task 2: The Digikoppeling broker becomes configuration
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-the-digikoppeling-broker-is-chosen-per-instance-req-sg-002`
- **files**: the Digikoppeling adapter configuration schema, its key resolution through REQ-DK-005
- [ ] Implement (at least two selectable brokers, audit on change, fail before the call when unconfigured)
- [ ] Test (two configured brokers, and an unconfigured one)

### Task 3: CORV and GGK adapters
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-corv-and-ggk-ship-as-sector-gateways-req-sg-003`
- **files**: `lib/Adapter/Corv/`, `lib/Adapter/Ggk/`, following `iwmo-ijw-adapter`
- [ ] Implement (message schemas, catalogue entries, delivery over the existing machinery, mock-mode fixtures per `source-management`)
- [ ] Test (a valid send, and an invalid message refused before transmission)

### Task 4: Wmebv obligations declared per route
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-an-electronic-route-declares-the-wmebv-obligations-it-meets-req-sg-004`
- **files**: the gateway entry shape, the inbound receipt record
- [ ] Implement (two lists per route, the route id and its met obligations on each received message)
- [ ] Test

### Task 5: Publication by reference
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-official-publication-is-a-gateway-and-the-document-is-published-by-reference-req-sg-005`
- **files**: `lib/Adapter/Publication/`, the failed-delivery path
- [ ] Implement (reference plus instruction, returned identifier recorded, refusal visible as a failed delivery)
- [ ] Test (a refused publication is replayable)

### Task 6: The ZGW registry binding
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-the-zgw-registry-is-a-deployment-binding-req-sg-006`
- **files**: the registry resolution behind the ZGW routes, the configuration test path
- [ ] Implement (one shape whichever registry resolves, configuration-time reachability test)
- [ ] Test (a mock-mode external registry, and a wrong base URL)

### Task 7: The on-premise bridge
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-an-on-premise-bridge-reaches-a-system-behind-the-firewall-req-sg-007`
- **files**: `lib/Bridge/`, the transport selection on a gateway, the call log
- [ ] Implement (outbound-initiated connection, bridge authentication, revocation, the bridge as a logged transport)
- [ ] Test (a loopback bridge, and a revoked one)

### Task 8: Jurisdiction on every gateway
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-a-gateway-declares-where-its-endpoint-sits-req-sg-008`
- **files**: the gateway entry shape, the gateway overview screen and its export
- [ ] Implement (declared jurisdiction, overview with export, `unknown` rendered as `unknown`)
- [ ] Test

### Task 9: The WKPB gateway
- **spec_ref**: `openspec/changes/statutory-gateways-and-frameworks/specs/statutory-gateways/spec.md#requirement-a-wkpb-restriction-is-registered-through-a-gateway-req-sg-009`
- **files**: `lib/Adapter/Wkpb/`
- [ ] Implement (registration, returned identifier recorded, unresolvable property refused before sending)
- [ ] Test

### Task 10: Coordination, docs and the hand-offs
- **files**: `docs/`, Dutch and English strings, the catalogue entries, this change's row in `competitor-parity-2026-09`
- [ ] Hand dossiq the case-type half: the WKPB flag and the registry binding declaration, and say that C-integrations-3 stays with dossiq's term model, cluster 18
- [ ] Tell the opencatalogi lane that the Wet elektronisch publiceren gateway sits under cluster 50, and agree the instruction shape
- [ ] Record C-integrations-23 as already answered, so it is not rediscovered
- [ ] Test (`openspec validate statutory-gateways-and-frameworks --type change --strict`)

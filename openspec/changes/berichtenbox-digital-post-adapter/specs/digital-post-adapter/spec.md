# digital-post-adapter Specification

**Status**: proposed
**Scope**: integriq
**OpenSpec changes**:
- berichtenbox-digital-post-adapter

## Purpose

One provider seam for digital post: MijnOverheid Berichtenbox, Postex and a
log binding. A fleet app asks for a send through a typed event (ADR-041),
integriq tracks delivery and announces status changes, and inbound post
feeds filinq's intake inbox. Requested by the dossiq competitor analysis,
finding B19.

## ADDED Requirements

### Requirement: One provider seam with log, Berichtenbox and Postex bindings (REQ-DPA-001)

Integriq MUST define `DigitalPostProviderInterface` with `getProviderId()`,
`getConfigSchema()`, `send()`, `status()` and `pollInbound()`, resolved by
`providerId` from the source configuration, with a `log` binding, a
`berichtenbox` binding over the Digikoppeling transport and a `postex` REST
binding. The Berichtenbox binding MUST refuse activation without a
PKIoverheid certificate and a sender OIN and MUST say which is missing.

#### Scenario: A source without a certificate cannot activate Berichtenbox
- GIVEN a digital post source with `providerId = berichtenbox` and no certificate
- WHEN an operator activates it
- THEN activation is refused with a message naming the certificate
- e2e: `tests/e2e/digital-post-source.spec.ts`

#### Scenario: The log binding answers delivered
- GIVEN a source with `providerId = log`
- WHEN a message is sent
- THEN the provider logs it and the message reaches `delivered`
- @e2e exclude the log provider is a backend fixture; covered by PHPUnit

### Requirement: A send is a typed command with a tracked message (REQ-DPA-002)

Integriq MUST handle `DigitalPostSendRequestedEvent` by creating a
`digitalPostMessage` (`recipient`, `subject`, `body`, `attachments[]`,
`requestedBy`, lifecycle `queued`, `sent`, `delivered`, `read`, `failed`),
calling the source's provider, and answering the event's result slot with
the message id or a structured refusal. Every status change MUST dispatch
`DigitalPostDeliveredEvent` with the message id and `requestedBy`. A failed
send MUST keep the message and its attachments.

#### Scenario: dossiq sends a letter from a case
- GIVEN a case app dispatches `DigitalPostSendRequestedEvent` with a PDF and a BSN recipient
- WHEN integriq handles it against a mock-mode Berichtenbox source
- THEN a `digitalPostMessage` in `sent` exists, the result slot carries its id, and a later status poll moves it to `delivered` and dispatches `DigitalPostDeliveredEvent`
- @e2e exclude cross-app typed events; covered by PHPUnit with a stub requester and a mock-mode source

#### Scenario: A failed send keeps the letter
- GIVEN the provider answers an error
- WHEN the send runs
- THEN the message is `failed` with `lastError` set and the PDF still attached
- @e2e exclude failure path; covered by PHPUnit

### Requirement: Inbound post feeds the document intake inbox (REQ-DPA-003)

A scheduled job MUST call `pollInbound()` per active source and dispatch one
`IntakeDocumentReceivedEvent` with channel `digitalPost` per received item,
carrying the sender identity as metadata. Health per source (last send,
last error, queue depth) MUST be readable on the source page.

#### Scenario: A citizen reply arrives
- GIVEN a mock-mode Berichtenbox source with one inbound fixture
- WHEN the inbound job runs
- THEN one `IntakeDocumentReceivedEvent` with channel `digitalPost` was dispatched
- @e2e exclude background job; covered by PHPUnit with a recording dispatcher

### Requirement: Signing material is resolved by reference, never passed by value (REQ-DPA-004)

The Berichtenbox binding MUST accept a `certificateRef` and MUST resolve
signing material inside integriq through `PkiOverheidCredentialResolver`. It
MUST NOT accept a certificate or a private key as a method argument, a source
configuration value, or an app-config string. When the credential broker
cannot supply material, the send MUST fail closed with the broker's reason and
MUST NOT fall back to any local key.

#### Scenario: A send names a certificate reference, not a key
- GIVEN a Berichtenbox source configured with a `certificateRef`
- WHEN a send runs
- THEN the binding resolves the material through the credential resolver
- AND no certificate or key appears in the call arguments
- @e2e exclude backend credential path; covered by PHPUnit

#### Scenario: An unavailable broker fails closed
- GIVEN the credential broker cannot supply signing material
- WHEN a send runs
- THEN the send fails with the broker's reason
- AND no message reaches `sent`
- @e2e exclude backend credential path; covered by PHPUnit

### Requirement: The feature flag selects the binding, and a flagged instance without credentials refuses (REQ-DPA-005)

`logius.berichtenbox.feature_flag` MUST select the live Berichtenbox client
over the mock. The mock MUST NOT be reachable on an instance whose flag is
set. An instance with the flag set and no usable credentials MUST refuse a
send with a message naming what is missing, and MUST NOT serve the mock, so an
operator can never be shown a delivered status for a letter that never left
the instance.

#### Scenario: The flag actually changes the binding
- GIVEN `logius.berichtenbox.feature_flag` is `0`
- WHEN the client is resolved
- THEN it is the mock, and every send is reported as simulated
- AND WHEN the flag is `1` and credentials are present, the resolved client is the live one
- @e2e exclude dependency-injection binding; covered by PHPUnit

#### Scenario: Flag set and credentials missing is a refusal, not a simulation
- GIVEN the flag is `1` and no PKIoverheid certificate reference is configured
- WHEN an operator sends a letter
- THEN the send is refused with a message naming the missing certificate
- AND no message reaches `sent` or `delivered`
- e2e: `tests/e2e/digital-post-source.spec.ts`

### Requirement: One Berichtenbox code path, built on the client that ships (REQ-DPA-006)

The `berichtenbox` binding MUST be implemented over the existing
`lib/Adapters/Berichtenbox/BerichtenboxClient.php` contract and its mock.
Integriq MUST NOT carry a second, parallel Berichtenbox client. The catalog
descriptor `adapter:berichtenbox` MUST name the Logius product the binding
actually addresses and the credentials it requires.

#### Scenario: The binding uses the shipped client
- GIVEN the digital post provider registry resolves `berichtenbox`
- WHEN the binding sends
- THEN it calls the existing `BerichtenboxClient` contract
- AND no second Berichtenbox client class exists in the repo
- @e2e exclude structural rule; covered by PHPUnit and review

#### Scenario: The catalog entry names the product and its credentials
- GIVEN an operator reads the connector catalog
- WHEN they open the Berichtenbox descriptor
- THEN it names the Logius product the binding addresses
- AND it names the OAuth credentials and the PKIoverheid certificate it needs
- e2e: `tests/e2e/digital-post-source.spec.ts`

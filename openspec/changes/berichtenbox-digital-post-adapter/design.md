# Design: berichtenbox-digital-post-adapter

Kind: code. One interface, three bindings, one schema, two typed events.

## D1. Provider seam

`lib/Service/DigitalPost/DigitalPostProviderInterface.php`:
- `getProviderId(): string`
- `getConfigSchema(): array`
- `send(array $sourceConfiguration, DigitalPostMessage $message): SendResult`
- `status(array $sourceConfiguration, string $externalId): DeliveryStatus`
- `pollInbound(array $sourceConfiguration, ?string $cursor): InboundBatch`

Bindings resolve by `providerId` from the source configuration, like the
klantinteracties providers. `LogDigitalPostProvider` writes to the log and
answers `delivered`. `BerichtenboxProvider` wraps the Berichtenbox client that already ships
(`lib/Adapters/Berichtenbox/BerichtenboxClient.php` and its mock) rather than
opening a second Berichtenbox code path, and carries the Berichtenbox message
schema (BSN, subject, body, PDF attachments, sender OIN).
`PostexProvider` posts to the Postex REST API.

## D2. `digitalPostMessage` schema

`sourceId`, `providerId`, `recipient` (`{bsn|kvk, name}`), `subject`,
`body`, `attachments[]`, `requestedBy` (`{app, objectRef}`), `externalId`,
`status` lifecycle `queued`, `sent`, `delivered`, `read`, `failed`,
`lastError`, `sentAt`, `deliveredAt`. BSN is stored through OpenRegister's
existing `BsnFormat`, never as a plain string.

## D3. Typed events (ADR-041)

- In: `DigitalPostSendRequestedEvent(message, sourceId)` with a result slot
  carrying the `digitalPostMessage` id or a structured refusal. dossiq's
  `BerichtenboxService` dispatches it and keeps its own UI.
- Out: `DigitalPostDeliveredEvent(messageId, requestedBy, status)` on every
  status change, so the requesting app updates its case without polling.

Status updates arrive by `status()` from a scheduled job, or by the
provider's callback where it offers one.

## D4. Inbound

`pollInbound()` on a schedule. Each inbound item becomes an
`IntakeDocumentReceivedEvent` with channel `digitalPost` and the sender's
identity as metadata, so filinq's intake inbox shows it
(`document-intake-inbox`).

## D5. Health

Per source: last successful send, last error, queue depth. Surfaced on the
source page and in the health status dossiq's Integrations page reads
(analysis row A34).

## D6. Which Berichtenbox

Berichtenbox voor Burgers on MijnOverheid, addressed by BSN. That is what
dossiq's compose dialog, its Awb templates and its read-status job were
written for, and it is what a beschikking is sent to.

The shipped catalog descriptor `adapter:berichtenbox` says "Berichtenbox voor
Bedrijven (BBK 1.7)", which is the business message box, a different Logius
product with a different koppelvlak and a KvK-addressed recipient. That
descriptor is corrected as part of this change rather than left as a second
answer. Business post to a KvK recipient stays possible through the same
seam later, as its own `providerId`, because the seam is provider-shaped.

## D7. One architecture, and the task it closes

The provider seam under `lib/Service/DigitalPost/` is the shape.
`absorb-dossiq-deliveries` carries an open task describing the same
capability as a provider quintet on the StufZkn pattern with a `*_message`
schema and `deliveryKind: 'berichtenbox'`. Both would work. Two of them will
not.

The seam wins for one reason: digital post has more than one destination.
Berichtenbox, Postex and later a KvK message box are the same act with
different transports, which is what a provider registry is for, and the quintet
pattern is built for one protocol per quintet. Implementing this change closes
that open item in `absorb-dossiq-deliveries`, and that must be recorded there
rather than left for a reader to infer.

## D8. Credentials by reference, never by value

`BerichtenboxClient::dispatch()` currently takes
`(array $message, string $pkiCert, string $pkiKey)`: raw PEM passed by value,
by a caller that does not exist. Shipping that would put private key material
in a method signature, a stack trace and any log that dumps arguments, which
is what REQ-DK-005 and ADR-007 forbid, and which the sibling Digikoppeling
adapter already refuses to do.

The signature becomes `(array $message, string $certificateRef)` and the
material is resolved inside, through `PkiOverheidCredentialResolver`, exactly
as `WusProfileService` does. That resolver fails closed today, so this is the
line where the third blocker actually bites, and it bites in one identified
place instead of everywhere.

## D9. The feature flag has to branch

`isActive()` reads `logius.berichtenbox.feature_flag` and nothing branches on
it. `Application.php` binds `BerichtenboxClientMock` unconditionally, so an
operator who sets the flag to `1` changes a log field. The flag becomes the
thing that selects the binding, and an instance with the flag set and no
credentials refuses rather than silently serving the mock. A mock that is
reachable while the instance believes it is live is the failure dossiq spent a
release removing.

## D10. Sequencing

Tasks 1, 2, 5, 6 and 7 are unblocked and land first. Task 3's Berichtenbox
binding is written and tested against fixtures, and its live leg is task 8,
which waits on the two procurement items and on OpenRegister's
`issueSigningMaterial`. Nothing in tasks 1 to 7 makes an instance look
connected when it is not.

## Risks

- **The blockers are named at the top of the proposal, not here.** They are
  the first thing a reader sees because the most likely failure of this
  change is somebody implementing it, seeing green tests, and believing post
  is being delivered.
- Berichtenbox needs a PKIoverheid certificate and an OIN. The provider
  refuses to activate without both and says which is missing.
- A failed send must not lose the letter. The message stays `failed` with
  the PDF attached; retry is an operator action.
- Correcting the catalog descriptor in D6 changes a string an operator may
  have read as a commitment to the business message box. Say so in the
  release note.

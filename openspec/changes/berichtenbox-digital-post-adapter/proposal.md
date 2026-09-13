---
kind: code
---

# Proposal: berichtenbox-digital-post-adapter

## Blocked, and here is exactly on what

Nothing reaches MijnOverheid until two things arrive that no amount of code
will produce.

1. **Logius BBK OAuth 2.0 client credentials.** Procurement. Integriq cannot
   authenticate to the koppelvlak without them.
2. **A PKIoverheid Services-server certificate.** Procurement. Outbound
   envelopes are signed with it.

There is a third blocker, and it is engineering in another repo. Integriq's
`PkiOverheidCredentialResolver` resolves signing material through
OpenRegister's `CredentialBrokerService`, and the method it needs,
`issueSigningMaterial`, does not exist. The resolver therefore fails closed
for every `certificateRef`, which is correct behaviour under ADR-007 and
REQ-DK-005 and means that **even with a purchased certificate in hand,
in-process signing stays refused until OpenRegister ships that capability.**
Digikoppeling is blocked on the same thing today.

**What the blockers do not gate.** Everything below the live network leg is
ordinary work that can be done now: the provider seam, the feature-flag
branch that today does not exist, the route or event entry point, the
`digitalPostMessage` schema, the credential-reference plumbing, the tests,
and replacing the mock's `signatureValid => false` with a real verification
path behind the flag. The change is written so that work lands first and the
last task is the one that waits.

**What not to do while waiting.** Do not flip the feature flag and bind
something that reports success. dossiq's own Berichtenbox history is exactly
that failure: everything around the mock worked, the compose dialog, the
routing service, the read-status job and four Awb templates, a send returned
a message id, and nothing left the instance.

## Summary

Send a letter from a case to a citizen's MijnOverheid Berichtenbox, or to a
postal service such as Postex, through one integriq provider seam. Receive
digital post the same way. dossiq keeps its `BerichtenboxService` and compose
dialog and stops shipping only a mock adapter.

## Motivation

Zaaksysteem sends a document from the case by e-mail, Postex or MijnOverheid
(`zs/pages/Case-Documenten.md`, Versturen); OpenCase has a
`DigitalPostController` in its enterprise edition (`oc/code-census.md`).
Both under `concurrentie-analyse/procest/_round2/`. dossiq's
`BerichtenboxService` and `BerichtenboxComposeDialog.vue` exist with a mock
adapter only (finding B19, M1 6.6). The analysis puts the adapter with
integriq and asks integriq to write it now (D11).

Integriq already owns the Digikoppeling transport (`digikoppeling-adapter`)
and a provider-interface pattern with log and REST bindings
(`kiss-kcc-bridge`, REQ-001). This change is that pattern for digital post.

## What integriq already has, and what it does not

Written down because the first draft of this change proposed a
`BerichtenboxProvider` from nothing while three Berichtenbox classes were
already on disk.

**Present.** `lib/Adapters/Berichtenbox/BerichtenboxClient.php`, an abstract
client with `flavour()`, `dispatch()`, `verifyWebhook()` and `checkMailbox()`.
`BerichtenboxClientMock`, an honest stub that answers
`signatureValid => false` on purpose. `lib/Sources/Berichtenbox/BerichtenboxSourceAdapter.php`,
a logging facade over the client that reads
`logius.berichtenbox.feature_flag` in `isActive()`. A catalog descriptor
`adapter:berichtenbox` carrying the BBK standard. A DI registration in
`Application.php`.

**Absent.** `BerichtenboxClientHttp`, which three docblocks name. Any branch
on the feature flag: the DI factory returns the mock unconditionally, so
setting the flag to `1` today changes a log field and nothing else. Any
route. Any caller of `BerichtenboxSourceAdapter`. Any test. Any use of
`PkiOverheidCredentialResolver` from this path.

So the seam this change describes is mostly a matter of connecting parts that
exist, not writing them.

## Two things this change must settle before it is implemented

**Which Berichtenbox.** The shipped catalog entry says "Berichtenbox voor
Bedrijven (BBK 1.7)", the business message box. This proposal and dossiq's
compose dialog describe Berichtenbox voor Burgers on MijnOverheid. Those are
different Logius products with different koppelvlakken. The design settles
this in D6 and the catalog entry follows it.

**Which architecture.** `absorb-dossiq-deliveries` carries an open task
describing Berichtenbox as a provider quintet on the StufZkn pattern, with a
`*_message` schema and `deliveryKind: 'berichtenbox'`. This change describes a
provider seam under `lib/Service/DigitalPost/`. Two shapes for one capability
is how a repo ends up with neither. D7 picks one and says which task it
closes.

## Scope

- `DigitalPostProviderInterface` with `send`, `status`, `pollInbound`.
- Three bindings: `log` (development), `berichtenbox` (over the existing
  Berichtenbox client, promoted to a provider), `postex` (REST).
- A `digitalPostMessage` object per send with delivery status.
- Typed events per ADR-041: `DigitalPostSendRequestedEvent` in, with a
  result slot, and `DigitalPostDeliveredEvent` out.
- Inbound post to filinq through `IntakeDocumentReceivedEvent`, channel
  `digitalPost`.
- The feature flag actually selecting the binding.
- Credentials by reference through `PkiOverheidCredentialResolver`, never as
  raw PEM passed by value.

## Out of scope

- The compose dialog and the case-side send button. dossiq has them
  (`berichtenbox-integration`).
- A postal address register. The sender passes the recipient.
- OpenRegister's `issueSigningMaterial` capability. It is named here as a
  blocker and owned there.
- Registering dossiq's `BerichtenboxReadStatusJob`. It is deliberately
  unscheduled on the dossiq side and stays that way until a real transport
  binds, which is dossiq's change to make.

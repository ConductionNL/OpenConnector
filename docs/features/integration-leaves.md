# Integration leaves

## Overview

An **integration leaf** links a Nextcloud entity that lives in another app to an
Integriq object. A leaf is not a copy and not a sync: the file stays in Files,
the card stays in Deck, the conversation stays in Talk, the event stays in
Calendar. Integriq stores the link and renders the other app's own surface
beside the object it belongs to.

Integriq declares four leaves on two schemas. Everything else is deliberately
off, and the reasons are below.

| Schema | Leaf | Where it renders | What it is for |
|---|---|---|---|
| `source` | Files | Source detail, "Supplier documents" | The supplier's API documentation, an OAS export, onboarding paperwork |
| `source` | Deck | Source detail, "Incident follow-ups" | The cards tracking what has to happen after this connection broke |
| `source` | Talk | Source detail, "Incident war-room" | The conversation held while it was broken |
| `synchronization` | Calendar | Object sidebar | Planned maintenance windows and cutover dates for this sync |

## Why these four

Integration operations is coordination work, and today the artefacts scatter.
When a supplier API breaks, the incident card sits in a personal Deck board, the
conversation happens in an unlinked Talk room, the supplier's PDF is in
somebody's mail, and the maintenance window is in a private agenda. Nothing
points at the connection that caused any of it.

The source is the natural anchor, and OpenRegister already links all four entity
types to objects. So the whole feature is two `configuration.linkedTypes`
declarations and three widget definitions. No PHP, no Vue, no migration.

## Where the calendar leaf appears

`SynchronizationDetail` is a `type: custom` page, so the manifest cannot place a
widget on it. The calendar leaf therefore surfaces through the shared object
sidebar rather than in the page body. Open a synchronization, open the sidebar,
and the calendar leaf is there.

This is a real limitation rather than a preference. Claiming a widget on a page
the manifest cannot reach would be a spec that describes nothing. When
`SynchronizationDetail` becomes manifest-driven, the leaf gets a widget like the
other three.

## What is off, and why

Every other schema and every other leaf type stays off until a spec change
argues for it. The reasoning is per-schema, not a blanket rule:

- **`endpoint`, `mapping`, `job`, `rule`, `consumer`** carry no coordination
  work of their own. What goes wrong with them goes wrong at the source, and
  that is where the incident trail belongs.
- **Every `*_log` schema** is append-only machine output. A log row is evidence,
  not a thing anybody links a conversation to.
- **`sync_item_dead_letter`** is **deferred, not refused**. A files leaf holding
  exported payload samples is a genuinely good idea; there is just no per-object
  surface to render it on, because the Dead letters page is `type: custom` and
  bulk-oriented.
- **Mail intake** (`mailObjectTemplate`) is refused outright. No Integriq
  archetype maps to "an email becomes an object".
- **Consumer-style leaves** (photos, polls, contacts, bookmarks, maps) would be
  decoration on a technical control plane.

## Security

A leaf on `source` sits on a page that also renders credential fields. The
leaves add no read path to them: a leaf links external entities by object id and
reads no object property at all. The e2e spec asserts this rather than trusting
it, by checking that no stored credential value appears anywhere in the rendered
source page.

Access to the leaves follows the source's own authorization, which
`99-source-lockdown.json` restricts to admins for every verb.

## Runtime behaviour

Deck, Talk and Calendar are optional Nextcloud apps. OpenRegister's providers
report themselves disabled when the app behind them is not installed, and the
widget then does not render. An instance without Deck simply has no "Incident
follow-ups" card. Files needs nothing extra.

## For implementers

The declaration lives in `lib/Settings/register.d/leaf-integrations.json`. Two
things about it are easy to get wrong and fail silently:

1. **`linkedTypes` belongs under `configuration`.** A top-level `linkedTypes`,
   as a sibling of `properties`, is dispatched to a setter that does not exist
   and swallowed by `Schema::hydrate()`'s own catch. It imports cleanly and does
   nothing.
2. **The schema version has to be bumped.** OpenRegister re-applies a stored
   schema only when the incoming version is newer, or when `properties`,
   `required`, `authorization` or an `x-openregister-*` annotation differs. A
   configuration-only change matches none of those, so without a bump the
   fragment imports and changes nothing.

Both failure modes are why `tests/e2e/spec-coverage/integration-leaves.spec.ts`
reads the stored schema back through OpenRegister's API instead of asserting
against the file on disk.

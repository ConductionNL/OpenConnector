# Tasks: outbound-sender-identity-and-deliverability

## 1. The identity

- [ ] 1.1 Add the sender identity object: display name, address, reply-to, signature, Nextcloud Mail account reference.
- [ ] 1.2 Let a message name an identity, and fall back to the instance default.
- [ ] 1.3 Record the identity on the outbound log row, as the umbrella said this cluster would.
- [ ] 1.4 PHPUnit on selection and on the fallback.

## 2. Domain alignment

- [ ] 2.1 Check SPF, DKIM and DMARC per identity domain and store the result with its time.
- [ ] 2.2 Print the exact record to publish where one is absent or misaligned.
- [ ] 2.3 Show the risk on the identity screen and on the log rows it produces, without blocking a send.
- [ ] 2.4 PHPUnit on the record comparison, with a stubbed resolver.

## 3. Signing and encryption

- [ ] 3.1 Administer S/MIME and PGP keys per identity, and recipient keys per address.
- [ ] 3.2 Sign outgoing mail per identity, encrypt where a recipient key is known.
- [ ] 3.3 Record signed, encrypted, both or neither on each log row.
- [ ] 3.4 Verify signed inbound mail and record the result.
- [ ] 3.5 PHPUnit on the signed-not-encrypted path and on verification.

## 4. The opt-out

- [ ] 4.1 Add the per-address opt-out list, one per instance.
- [ ] 4.2 Check it in the send path for every sender in the product.
- [ ] 4.3 Declare the protected categories and record every override.
- [ ] 4.4 PHPUnit on suppression and on the protected override.

## 5. The unsubscribe link

- [ ] 5.1 Mint a signed token per recipient and case, and render the link in case notification mail.
- [ ] 5.2 Handle the link without a login, confirm what was stopped, create no account.
- [ ] 5.3 Render no link at all in a protected category.
- [ ] 5.4 PHPUnit on the token, and a test that a besluit template contains no link.

## 6. Quoting

- [ ] 6.1 Add the quoting level per identity, defaulting to `last-message`.
- [ ] 6.2 Apply it at composition and record the level used on the log row.
- [ ] 6.3 PHPUnit on all three levels.

## 7. Undo send

- [ ] 7.1 Add the hold window per identity, defaulting to zero.
- [ ] 7.2 Queue for the window, allow withdrawal during it, record the withdrawal.
- [ ] 7.3 Refuse a withdrawal after the window with a message that says why.
- [ ] 7.4 PHPUnit with a frozen clock on both sides of the window.

## 8. No-reply handling

- [ ] 8.1 Mark an identity as taking no replies, with divert or refuse.
- [ ] 8.2 Divert to the configured address, or refuse naming where to write, and record either.
- [ ] 8.3 PHPUnit on both, including that nothing is dropped silently.

## 9. Signature stripping

- [ ] 9.1 Detect quoted signature and disclaimer blocks.
- [ ] 9.2 Strip them from the timeline entry only, keeping the stored message whole.
- [ ] 9.3 Offer the original from the entry.
- [ ] 9.4 PHPUnit on a message with no signature, to prove nothing else is removed.

## 10. Handover

- [ ] 10.1 Give the dossiq lane its half: the sender identity declared per team on the case type, replacing the single `EmailSettings` address.
- [ ] 10.2 Confirm with the Nextcloud Mail boundary that an identity only references an account, per decision D12.
- [ ] 10.3 Add this change to the integriq umbrella index and tick it there when it archives.

# Security model

Human Gate generates CAPTCHAs and verifies answers on the server. It limits their lifetime and number of incorrect attempts, and prevents a solved CAPTCHA from being used again.

## Server-side verification

When issuing a CAPTCHA, the application supplies a server-side [`Purpose`](reference/api_en.md#core-types) key that identifies the form or operation. For example, `contact.send` can identify a contact form and `account.register` registration. A CAPTCHA issued for one form cannot be used to verify the other. The correct answer and verification data remain on the server; the browser receives only an identifier and presentation data.

When the form is submitted, the application passes the identifier and answer to [`ChallengeService::verify()`](reference/api_en.md#verify). The server checks correctness; an identifier alone does not mean the CAPTCHA has been solved.

The package does not store the correct answer in plaintext. For [`CategorySelection`](challenges/category-selection_en.md) and [`IconSequence`](challenges/icon-sequence_en.md), the browser receives random choice tokens but not which choices are correct. Card token order does not matter, while icon sequence order does.

## Expiration and reuse

Expiration and the limit on wrong answers are configured through [`Policy`](reference/api_en.md#policy). The default lifetime is 180 seconds; the third incorrect answer ends the verification.

A correct answer returns `VerificationCode::Accepted`, after which the CAPTCHA cannot be reused. While the success record is retained, reuse returns `AlreadyConsumed`; after it is removed, `NotFound`. Neither result permits another successful verification with the old identifier.

Expired CAPTCHAs are also rejected. See [refresh and retention rules](reference/lifecycle_en.md).

## Storing data

The built-in [`NativeSessionChallengeStore`](reference/api_en.md#storage-and-time) works with a PHP session opened by the application using the `files` handler. When data changes, the package saves it and checks the write. A storage failure causes an exception, not a successful verification.

The [integration guide](guides/integration_en.md) shows how to connect storage and work with PHP sessions.

## Limits on active CAPTCHAs

[`Policy::maxActive` and `Policy::maxActivePerPurpose`](reference/api_en.md#policy) limit how many active CAPTCHAs can be stored at once. They do not measure the number of HTTP requests within a time interval.

# CAPTCHA lifecycle

Human Gate tracks each issued CAPTCHA, its expiration, and incorrect attempts. A correct answer cannot be reused. Shared limits are set through [`Policy`](api_en.md#policy).

## Expiration and limits

| Parameter | Default | Meaning |
| --- | --- |
| `ttlSeconds` | `180` | How many seconds a CAPTCHA remains valid |
| `maxWrongAttempts` | `3` | Allowed incorrect answers |
| `maxActive` | `12` | Maximum active CAPTCHAs in one store |
| `maxActivePerPurpose` | `4` | Maximum active CAPTCHAs for one form or operation (`Purpose`) |
| `maxTombstones` | `32` | Maximum retained records for finished CAPTCHAs |
| `terminalRetentionSeconds` | `180` | How long finished-CAPTCHA records are retained, unless the count limit removes them sooner |

With the standard PHP-session store, these limits apply to one session. For example, up to four CAPTCHAs may be active for `contact.send` and four for `account.register`, but no more than twelve in total for the session. Limits on the number and lifetime of finished records prevent unbounded growth.

All values must be positive, and `maxActivePerPurpose` cannot exceed `maxActive`. To allow five minutes, use `new Policy(ttlSeconds: 300)`. These limits do not rate-limit HTTP requests.

## Issuing a CAPTCHA

[`ChallengeService::issue()`](api_en.md#issue) creates a CAPTCHA of the selected type. On success, the application receives its identifier, expiration time, and presentation data.

If the active-CAPTCHA limit has been reached, the result is `LifecycleCode::CapacityExceeded` and no CAPTCHA is issued. The default type is the [text CAPTCHA](../challenges/text-image_en.md); choose another type via `ChallengeKind`.

## Verifying an answer

[`ChallengeService::verify()`](api_en.md#verify) receives the CAPTCHA identifier, the server-side form key (`Purpose`), and the user answer. Use the same key as when issuing the CAPTCHA. The type is read from saved data, not supplied by the browser.

Each type has its own answer rules. Token order is irrelevant for [`CategorySelection`](../challenges/category-selection_en.md) but matters for [`IconSequence`](../challenges/icon-sequence_en.md). An invalid answer format counts as an incorrect attempt.

## Incorrect answers

Each incorrect answer increments the attempt count. While attempts remain, the result is `VerificationCode::Incorrect`; the user may retry the same CAPTCHA.

When the limit is reached, the result is `VerificationCode::AttemptsExhausted`. With default settings, the **third wrong answer** ends the CAPTCHA. Further attempts using its identifier are rejected, and the expiration time is not extended.

To continue, issue a new CAPTCHA with [`issue()`](api_en.md#issue): a CAPTCHA with exhausted attempts cannot be refreshed via `replace()`.

## Correct answer

A correct answer returns `VerificationCode::Accepted`. That CAPTCHA cannot pass again. While the success record remains, another attempt returns `AlreadyConsumed`.

## Refreshing a CAPTCHA

[`ChallengeService::replace()`](api_en.md#replace) generates a new CAPTCHA in place of the previous one. On success, the application receives a new identifier and presentation data; the old answer and selections are no longer valid.

An active CAPTCHA may be replaced, as can an expired one while its record remains stored. Replacing an active CAPTCHA does not increase the active count; replacing an expired one may require free capacity.

A solved, already replaced, or attempts-exhausted CAPTCHA cannot be refreshed by this method. The new type defaults to text; explicitly supply another type when needed.

## Expiration and record removal

A CAPTCHA is valid while the current time is earlier than `expiresAt`. From that instant onward, no answer is accepted.

Information about an expired CAPTCHA may remain for a limited time. While present, `verify()` returns `Expired`, and `replace()` can create a fresh CAPTCHA. After the record has been removed, the old identifier returns `NotFound`.

Finished-CAPTCHA records are retained for a limited period. If their count exceeds `maxTombstones`, older records may be removed sooner. Therefore an old identifier is not guaranteed to return `Expired`; after removal, it returns `NotFound`.

## Binding a CAPTCHA to a form

`Purpose` is a string key representing a form or operation. For example, `contact.send` means sending a message and `account.register` means registration. The application sets this key on the server both when issuing and verifying a CAPTCHA. Verifying it with a different form key returns `PurposeMismatch` and does not accept the answer. See the [API reference](api_en.md#core-types) for the key format.

## Method results

| Method | On success | Other results |
| --- | --- |
| [`issue()`](api_en.md#issue) | `Issued` and a new CAPTCHA | `CapacityExceeded` |
| [`replace()`](api_en.md#replace) | `Refreshed` and a new CAPTCHA | `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced` |
| [`verify()`](api_en.md#verify) | `Accepted` | `Incorrect`, `AttemptsExhausted`, `Expired`, `NotFound`, `PurposeMismatch`, `AlreadyConsumed`, `Replaced` |

Storage or image-generation failures throw exceptions rather than returning `Incorrect`. See the [API reference](api_en.md#exceptions).

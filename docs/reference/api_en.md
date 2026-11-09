# API reference

The main entry point is `ChallengeService`. It creates and refreshes CAPTCHAs, verifies answers, and returns typed results. This page documents signatures and options; see the [integration guide](../guides/integration_en.md) for their usage.

All listed types are under the `Yaleksandr\HumanGate\` namespace. Subnamespaces in tables are relative to it.

## ChallengeService

The constructor receives a store, shared options, a current-time source, and CAPTCHA strategies:

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

One strategy per CAPTCHA type is sufficient. If different types are still active in a session, the service needs their strategies to verify them.

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Creates a new CAPTCHA. On success, `IssueResult` contains `LifecycleCode::Issued` and an `IssuedChallenge`. If the active-CAPTCHA limit is reached, the code is `CapacityExceeded` and `challenge === null`.

By default it creates [`TextImage`](../challenges/text-image_en.md). Use `ChallengeKind::CategorySelection` or `ChallengeKind::IconSequence` for the others.

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Creates a new CAPTCHA in place of the supplied one. A successful result contains `LifecycleCode::Refreshed` and a new `IssuedChallenge`. The old identifier can no longer be verified.

Refresh works for active CAPTCHAs and for expired ones while their records are retained. The default new type is `TextImage`; specify `kind` for another type. See the [lifecycle](lifecycle_en.md).

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Verifies an answer and returns `VerificationCode`. `Accepted` means the answer was correct and the same CAPTCHA cannot be used again.

| `VerificationCode` value | Meaning |
| --- | --- |
| `Accepted` | Correct answer; CAPTCHA solved |
| `Incorrect` | Wrong answer; attempts remain |
| `AttemptsExhausted` | Incorrect-attempt limit exhausted |
| `Expired` | CAPTCHA has expired |
| `NotFound` | CAPTCHA not found or the old record has been removed |
| `PurposeMismatch` | CAPTCHA belongs to a different form or operation (`Purpose` mismatch) |
| `AlreadyConsumed` | This CAPTCHA already had a correct answer accepted |
| `Replaced` | The CAPTCHA has already been replaced |

The application determines the HTTP status and message shown to the user. See the [`verify()` example](../guides/integration_en.md).

## Policy

`Challenge\Policy` configures lifetime, incorrect attempts, and storage limits. Default settings:

```php
<?php

use Yaleksandr\HumanGate\Challenge\Policy;

$policy = new Policy(
    ttlSeconds: 180,
    maxWrongAttempts: 3,
    maxActive: 12,
    maxActivePerPurpose: 4,
    maxTombstones: 32,
    terminalRetentionSeconds: 180,
);
```

| Parameter | Default | Purpose |
| --- | --- | --- |
| `ttlSeconds` | `180` | CAPTCHA lifetime in seconds |
| `maxWrongAttempts` | `3` | Allowed incorrect answers |
| `maxActive` | `12` | Maximum active CAPTCHAs in one store |
| `maxActivePerPurpose` | `4` | Maximum active CAPTCHAs for one form or operation (`Purpose`) |
| `maxTombstones` | `32` | Maximum retained records for finished CAPTCHAs |
| `terminalRetentionSeconds` | `180` | Time in seconds to retain finished records, unless the count limit removes them first |

For the standard [`NativeSessionChallengeStore`](#storage-and-time), limits apply **per PHP session**, not to the entire site. For instance, `contact.send` and `account.register` can each have up to four active CAPTCHAs, but the session can contain at most twelve active CAPTCHAs in total. This prevents a single form from consuming unlimited storage capacity.

After success, replacement, expiration, or exhausted attempts, the package temporarily keeps the reason the CAPTCHA ended, allowing `AlreadyConsumed` to be distinguished from `Expired`. `maxTombstones` and `terminalRetentionSeconds` bound the number and lifetime of these records. Once removed, an old identifier may return `NotFound`.

All values must be positive; `maxActivePerPurpose` cannot exceed `maxActive`. To allow five minutes, use `new Policy(ttlSeconds: 300)`. These counts **are not HTTP request rate limits**. See the [lifecycle guide](lifecycle_en.md).

## Core types

| Type | Purpose |
| --- | --- |
| `Challenge\ChallengeId` | CAPTCHA ID: `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Form/operation key: `__construct(string $value)`, `value(): string` |
| `Challenge\ChallengeKind` | CAPTCHA type: `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Result `code` and `challenge` or `null` |
| `Challenge\IssuedChallenge` | New CAPTCHA: `id`, `kind`, `expiresAt`, `presentation` |

`ChallengeId` is exactly 64 lowercase hexadecimal characters. `Purpose` contains 1–64 ASCII bytes: first a lowercase Latin letter or digit, followed by those characters, dots, underscores, or hyphens.

The application defines `Purpose` on the server: for example, `contact.send` for messages and `account.register` for registration. The same key is used when issuing and verifying a CAPTCHA; it is not taken from a browser form. `IssuedChallenge::expiresAt` is the expiration instant in Unix seconds.

## TextImage

The relevant classes are in `TextImage\`:

```php
TextImageStrategy::__construct(
    TextImageRenderer $renderer,
    TextImageAnswerOptions $answerOptions = new TextImageAnswerOptions(),
)

TextImageAnswerOptions::__construct(bool $caseSensitive = false)

GdTextImageRenderer::__construct(
    TextImageRenderOptions $options = new TextImageRenderOptions(),
)

TextImageRenderOptions::__construct(
    int $scalePercent = 100,
    TextImageBlur $blur = TextImageBlur::Standard,
    int $lightNoisePercent = 100,
    int $maxRotationDegrees = 28,
)
```

`TextImageBlur` supports `None`, `Light`, `Standard`, `Strong`. Scale: `100`, `105`, `110`, `115`, `120`; noise: `0`–`150`; rotation: `0`–`28` degrees. Use `caseSensitive` to control letter case. The answer always has six characters. See the [text CAPTCHA guide](../challenges/text-image_en.md).

## CategorySelection

The relevant classes are in `CategorySelection\`:

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

Use 2–16 categories, each with 1–32 items. `cardCount` is 3–12, and `targetCount` must be at least 1 and less than `cardCount`. Labels are unique UTF-8 text of 1–80 bytes without control characters or surrounding whitespace.

Answers are comma-separated card tokens, and order is irrelevant. See the [category selection guide](../challenges/category-selection_en.md) for setup and answer details.

## IconSequence

The relevant classes are in `IconSequence\`:

```php
IconSequenceStrategy::__construct(
    IconSequenceOptions $options = new IconSequenceOptions(),
    IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
)

IconSequenceOptions::__construct(int $sequenceLength = 4, int $choiceCount = 8)

IconSequenceRenderOptions::__construct(
    int $minIconSize = 72,
    int $maxIconSize = 80,
    IconSequenceBlur $blur = IconSequenceBlur::Strong,
    int $noisePercent = 150,
    int $maxRotationDegrees = 90,
)
```

Sequence length: 3–6 icons. Number of choices: at least two more than the sequence length, up to 12 (e.g. 6–12 choices for four target icons). Icon size: 72–96 pixels; noise: 0–150; rotation: 0–90 degrees. `IconSequenceBlur` supports `None`, `Light`, `Standard`, `Strong`.

Tokens must be submitted in selection order. See the [icon sequence guide](../challenges/icon-sequence_en.md) for configuration and answer rules.

## Presentation data

All types are under `Presentation\`:

| Type | Fields |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` contains raw image bytes. The type supports `image/png`, `image/jpeg`, and `image/webp`, dimensions of 1–4096 pixels per side, and up to 131072 data bytes. Built-in text and icon renderers generate PNG.

Category selection provides category and card text. Icon sequence provides the target image and choices, each with a token and image. Neither presentation includes the correct answer.

## Storage and time

| Type | Purpose |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Captures the context of an already opened PHP session |
| `Session\NativeSessionChallengeStore` | Stores CAPTCHA state in a PHP session |
| `Clock\SystemClock` | Current-time source: `now(): int` returns Unix seconds |

`NativeSessionChallengeStore` receives `ActiveNativeSessionScope`. It supports the native `files` handler and `php` or `php_serialize` serialization. The application first calls `session_start()`; the adapter closes the session after data mutation. See the [integration guide](../guides/integration_en.md).

## Exceptions

Wrong user answers are represented by `VerificationCode`. Configuration or runtime failures throw exceptions:

| Exception | Cause |
| --- | --- |
| `InvalidArgumentException` | Invalid arguments or options |
| `Exception\UnsupportedChallengeKindException` | No strategy registered for the requested CAPTCHA type |
| `Exception\RenderingException` | Failure to generate an image |
| `Random\RandomException` | Failure in the random number generator |
| `Session\NativeSessionStorageException` | Failure reading, writing, or verifying CAPTCHA data in the PHP session |

`NativeSessionStorageException::reason` contains `NativeSessionStorageFailureReason`: `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. Do not expose low-level failure details directly to end users.

## Extending the package

Custom storage, current-time sources, or CAPTCHA strategies can be provided through `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy`, and `Port\TextImageRenderer`.

`ChallengeStore::atomic()` must safely apply changes across concurrent requests and confirm storage before returning. Storage errors must never produce a successful CAPTCHA verification.

`Clock::now()` returns the current Unix time in seconds for calculating expiration; it is a time source, not a countdown timer. `ChallengeStrategy::kind()`, `prepare()`, and `verify()` define a strategy; `TextImageRenderer::render()` returns an `ImagePresentation`. For ordinary use, prefer the implementations in the [integration guide](../guides/integration_en.md).

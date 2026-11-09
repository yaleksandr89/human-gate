# Integrating Human Gate

Human Gate creates CAPTCHAs and verifies answers on the server. The application displays the CAPTCHA in its form and proceeds with form processing only after a correct answer.

## How verification works

The application calls [`ChallengeService::issue()`](../reference/api_en.md#issue) and receives the CAPTCHA identifier and presentation data. It sends the presentation data to the browser.

On form submission, the application receives the identifier and user answer, then calls [`ChallengeService::verify()`](../reference/api_en.md#verify). `VerificationCode::Accepted` indicates a successful check. The correct answer and correct-choice details remain on the server.

[`ChallengeService::replace()`](../reference/api_en.md#replace) creates a replacement CAPTCHA with a new identifier.


## Connecting the package

`ChallengeService` is assembled from a store, shared [`Policy` options](../reference/api_en.md#policy), a `SystemClock` time source, and strategies for the chosen CAPTCHA types. This example registers all three types with the standard PHP session. Category labels are illustrative.

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require 'vendor/autoload.php';

function createChallengeServiceForActiveSession(): ChallengeService
{
    return new ChallengeService(
        new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
        new Policy(),
        new SystemClock(),
        new TextImageStrategy(new GdTextImageRenderer()),
        new CategorySelectionStrategy([
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
        ]),
        new IconSequenceStrategy(),
    );
}
```

Call this function after `session_start()`. Registering all three strategies also lets the service verify CAPTCHAs of different types that were created earlier in the same session. Detailed options are in the [`TextImage`](../challenges/text-image_en.md), [`CategorySelection`](../challenges/category-selection_en.md), and [`IconSequence`](../challenges/icon-sequence_en.md) guides.


## Creating a CAPTCHA

In the request handler, the application starts the session and issues the desired type. Example for category selection:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);

if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

Successful [`issue()`](../reference/api_en.md#issue) returns an `IssueResult` with `LifecycleCode::Issued` and an `IssuedChallenge` containing `id`, `kind`, `expiresAt`, and `presentation`. If the active-CAPTCHA limit has been reached, `challenge` is `null`; the result code explains why.

`Purpose` is the key of a form or operation, for example `contact.send` for contact messages or `account.register` for registration. Use the same key when creating and verifying a CAPTCHA. Set it in server-side PHP code, not from a browser field.

Without an explicit second argument, `issue()` creates a text CAPTCHA. For other types, pass `ChallengeKind::CategorySelection` or `ChallengeKind::IconSequence`.


## Displaying the CAPTCHA

The package returns presentation data; the application determines its HTML markup and styling:

| CAPTCHA type | Presentation data | User answer |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image_en.md) | `ImagePresentation`: image type, bytes, and dimensions | Entered characters |
| [`CategorySelection`](../challenges/category-selection_en.md) | `CategorySelectionPresentation`: category label and cards | Comma-separated selected-card tokens; order is irrelevant |
| [`IconSequence`](../challenges/icon-sequence_en.md) | `IconSequencePresentation`: sequence image and icon choices | Comma-separated tokens in selection order |

`ImagePresentation::bytes` contains raw image bytes. The application chooses how to deliver the image to the browser. Category and card labels are plain text, not ready-made HTML.

All three CAPTCHA types are verified on the server. The browser receives only the data required to display the options and collect the answer.


## Checking an answer

The answer arrives in a separate request. Open **the same user session**, recreate `ChallengeService`, and pass the submitted identifier and answer:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

$rawId = $_POST['challengeId'] ?? null;
$rawAnswer = $_POST['answer'] ?? null;
if (!is_string($rawId) || !is_string($rawAnswer)) {
    throw new InvalidArgumentException('Ожидаются строковые идентификатор и ответ.');
}

$id = ChallengeId::fromString($rawId);

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->verify($id, new Purpose('contact.send'), $rawAnswer);
```

Here, `challengeId` and `answer` are example field names. Parse the identifier using `ChallengeId::fromString()`. Pass the raw answer to `verify()` without changing case, sorting tokens, or other transformations; each CAPTCHA type has its own rules.

When the result is `VerificationCode::Accepted`, the CAPTCHA has been solved. A correct answer is accepted only once; a second check using the same identifier cannot succeed.


## Refreshing the CAPTCHA

[`ChallengeService::replace()`](../reference/api_en.md#replace) accepts an old identifier and returns a new CAPTCHA. Example for category selection:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): IssueResult
{
    if (!session_start()) {
        throw new RuntimeException('Не удалось открыть сессию PHP.');
    }

    return createChallengeServiceForActiveSession()->replace(
        $oldId,
        new Purpose('contact.send'),
        ChallengeKind::CategorySelection,
    );
}
```

After a successful refresh, display the new data and clear previous input and selections. An expired CAPTCHA can be refreshed while its record remains stored; once removed, the result is `NotFound`. See the [lifecycle guide](../reference/lifecycle_en.md).


## Storing CAPTCHAs in PHP sessions

The examples use the built-in [`NativeSessionChallengeStore`](../reference/api_en.md#storage-and-time). The application opens a normal PHP session using `session_start()`, then calls `ActiveNativeSessionScope::capture()` and supplies that context to the store. No additional storage service is needed for this option.

The adapter supports the standard `files` session handler with `php` or `php_serialize` serialization.

After successfully changing CAPTCHA data, the adapter saves the change and closes the session. If the application needs to modify `$_SESSION` afterward, it must reopen the session. A no-op operation can leave the session open; the application controls any subsequent session work.

Operations that change CAPTCHA data must occur before HTTP headers are sent. Run lengthy external operations after finishing session work. A storage failure raises `NativeSessionStorageException` and does not count as a wrong answer.

You can provide a different storage backend by implementing `ChallengeStore`; its requirements are outlined in the [API reference](../reference/api_en.md#extending-the-package).


## Handling results

| Code | Meaning |
| --- | --- |
| `Issued`, `Refreshed` | CAPTCHA created or refreshed; new data returned |
| `CapacityExceeded` | Active CAPTCHA limit reached |
| `Accepted` | Correct answer accepted; CAPTCHA cannot be reused |
| `Incorrect` | Wrong answer; attempts remain |
| `AttemptsExhausted` | Wrong-answer limit exhausted |
| `Expired` | CAPTCHA has expired |
| `AlreadyConsumed`, `Replaced` | This identifier can no longer verify an answer |
| `NotFound` | CAPTCHA not found or record removed |
| `PurposeMismatch` | CAPTCHA belongs to a different form or operation (`Purpose`) |

`issue()` and `replace()` return `IssueResult`, while `verify()` returns `VerificationCode`. Some code names overlap, but the result type depends on the method. See the [API reference](../reference/api_en.md).


## Common mistakes

- Calling `ActiveNativeSessionScope::capture()` before `session_start()`.
- Writing to `$_SESSION` after it was closed, without reopening the session.
- Omitting a strategy for a CAPTCHA type that may still be active in the session.
- Omitting `ChallengeKind` when issuing category-selection or icon-sequence CAPTCHAs.
- Taking `Purpose` from a submitted form instead of defining it on the server.
- Sending card labels instead of tokens, or sorting `IconSequence` tokens.
- Continuing form processing after a storage failure or a result other than `Accepted`.

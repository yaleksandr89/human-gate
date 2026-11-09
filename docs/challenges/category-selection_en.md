# CategorySelection — selecting cards by category

The user sees a category name and selects all matching cards. For example, they might have to choose only fruit from a set of fruit and tools. **Selection order does not matter.**

## Setup and categories

The application provides categories and card labels. `CategorySelectionStrategy` chooses random cards and checks the selected set.

```php
<?php

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionOptions;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;

$strategy = new CategorySelectionStrategy(
    [
        new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
        new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
    ],
    new CategorySelectionOptions(cardCount: 6, targetCount: 2),
);
```

Register the strategy in `ChallengeService` and pass `ChallengeKind::CategorySelection` to [`ChallengeService::issue()`](../reference/api_en.md#issue). See the [integration guide](../guides/integration_en.md) for complete service construction and session handling.

A catalog supports 2–16 categories, each containing 1–32 items. There must be enough matching items in every possible target category and enough distractors elsewhere. Category names and item labels must be unique. Avoid categories that overlap semantically.

Labels must be valid UTF-8, 1–80 **bytes** long (not characters), without control characters or whitespace at the edges. The package returns plain text labels, not HTML markup.

## Card counts

`CategorySelectionOptions` controls the total number of cards and the required selections:

| Parameter | Default | Allowed values |
| --- | --- | --- |
| `cardCount` | `6` | From `3` to `12` cards |
| `targetCount` | `2` | From `1` to fewer than `cardCount` |

The remaining cards come from other categories. CAPTCHA expiration and attempt limits use the shared [`Policy`](../reference/api_en.md#policy), not `CategorySelectionOptions`.

## Presentation data

[`CategorySelectionPresentation`](../reference/api_en.md) contains `category` and a `cards` collection. Each `CategorySelectionCard` has a visible text `label` and a random `token`.

The browser receives card labels and tokens, but not which cards are correct. Human Gate checks correctness against the data stored on the server.

## Answer format

The application joins the selected card tokens with commas and passes the string to [`ChallengeService::verify()`](../reference/api_en.md#verify). **Token order does not matter**; the package compares the selected set.

Each token is 32 lowercase hexadecimal characters (`0–9`, `a–f`). Tokens cannot repeat; the complete answer is at most 512 bytes. Spaces and case changes are not allowed. There is no need to sort or normalize the answer before passing it to the package.

After a successful [`ChallengeService::replace()`](../reference/api_en.md#replace), display the new category and cards and clear previous selections. Old tokens are invalid for the new CAPTCHA. See the shared [lifecycle rules](../reference/lifecycle_en.md).

## Errors

Invalid catalogs or options cause `InvalidArgumentException` when constructing the strategy. A wrong answer returns `VerificationCode::Incorrect` or `AttemptsExhausted` when attempts run out. Storage errors are handled separately. See the [security model](../security-model_en.md) and [API exception reference](../reference/api_en.md#exceptions).

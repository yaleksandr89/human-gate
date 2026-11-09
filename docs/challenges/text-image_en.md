# TextImage — text CAPTCHA

The user sees an image with six characters and enters them into a form field. Human Gate generates the image and checks the answer on the server. By default, letter case is ignored.

## Setup

A text CAPTCHA uses `TextImageStrategy` and `GdTextImageRenderer`. Example with image and case-sensitivity settings:

```php
<?php

use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageAnswerOptions;
use Yaleksandr\HumanGate\TextImage\TextImageBlur;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

$strategy = new TextImageStrategy(
    new GdTextImageRenderer(new TextImageRenderOptions(
        scalePercent: 110,
        blur: TextImageBlur::Standard,
        lightNoisePercent: 100,
        maxRotationDegrees: 28,
    )),
    new TextImageAnswerOptions(caseSensitive: false),
);
```

Pass the strategy to `ChallengeService`. The text CAPTCHA is the default for [`ChallengeService::issue()`](../reference/api_en.md#issue), so its type need not be specified. See the [integration guide](../guides/integration_en.md) for a complete session and verification example.

## Answer options

`TextImageAnswerOptions::caseSensitive` controls letter-case handling:

| Value | Answer verification |
| --- | --- |
| `false` | Lowercase and uppercase Latin letters are treated as equal |
| `true` | The letter case must match the image |

**The answer is always six characters long**; its length cannot be configured. Characters are selected to avoid some visually similar combinations. Case-sensitive mode also uses additional lowercase letters.

Pass the raw user-entered string to [`ChallengeService::verify()`](../reference/api_en.md#verify). The package performs its allowed case conversion and verification. Only spaces, tabs, and `CR`/`LF` line breaks are trimmed at the string edges. Spaces inside the answer and arbitrary Unicode normalization are not supported. The maximum raw answer size is 64 bytes.

## Image options

`TextImageRenderOptions` controls image generation:

| Parameter | Default | Allowed values |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | From `0` to `150` |
| `maxRotationDegrees` | `28` | From `0` to `28` degrees |

Scale changes image size while preserving its proportions. Blur, noise, and rotation affect the image appearance but not the correct answer.

The shared CAPTCHA lifetime is set by [`Policy::ttlSeconds`](../reference/api_en.md#policy), **not** image options. The default is 180 seconds; `new Policy(ttlSeconds: 300)` makes it five minutes.

## Presentation data

After issuing a text CAPTCHA, the application receives [`ImagePresentation`](../reference/api_en.md) with `mimeType`, `bytes`, `width`, and `height`. The type is `image/png`; `bytes` contains raw PNG bytes, not base64-encoded text.

With scale `100`, the image is 240×80 pixels. The application decides how to deliver the image to the browser and provides the answer input field.

Rendering requires `ext-gd` with FreeType support. Noto Sans Regular is bundled; its [source](../../resources/fonts/SOURCE.txt) and [license](../../resources/fonts/OFL.txt) are included in the resources.

## Errors

Invalid options cause `InvalidArgumentException`; image-generation failures cause `RenderingException`. Wrong answers return `VerificationCode::Incorrect` or `AttemptsExhausted` when no attempts remain. These are separate from rendering failures.

The browser receives the character image, not the correct answer. See [server-side verification](../security-model_en.md).

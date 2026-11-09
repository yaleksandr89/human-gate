# IconSequence — icon sequence

The user sees icons in an image and selects the same icons in the displayed order. **Selection order matters.**

## Setup

This CAPTCHA uses `IconSequenceStrategy`. The default configuration is:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

To change the options, pass them explicitly:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy(
    new IconSequenceOptions(sequenceLength: 4, choiceCount: 8),
    new IconSequenceRenderOptions(
        minIconSize: 72,
        maxIconSize: 80,
        blur: IconSequenceBlur::Strong,
        noisePercent: 150,
        maxRotationDegrees: 90,
    ),
);
```

Register `IconSequenceStrategy` in `ChallengeService` and pass `ChallengeKind::IconSequence` to [`ChallengeService::issue()`](../reference/api_en.md#issue). See the [integration guide](../guides/integration_en.md).

## Number and order of icons

| `IconSequenceOptions` parameter | Default | Allowed values |
| --- | --- | --- |
| `sequenceLength` | `4` | From `3` to `6` icons |
| `choiceCount` | `8` | At least two more choices than the sequence length; at most `12` |

At least two extra choices are added to the correct sequence. For four target icons, there can be 6–12 choices (eight by default). The user selects the four correct icons in the displayed order.

## Image options

| `IconSequenceRenderOptions` parameter | Default | Allowed values |
| --- | --- | --- |
| `minIconSize` | `72` | From `72` to `96` pixels |
| `maxIconSize` | `80` | From `72` to `96`, not below `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`, `Light`, `Standard`, `Strong` |
| `noisePercent` | `150` | From `0` to `150` |
| `maxRotationDegrees` | `90` | From `0` to `90` degrees |

Blur, noise, and rotation affect the target sequence image; the choice images remain sharp. Icon sizes and rotation must be combined so the rotated icons fit within the target image height of 128 pixels.

The lifetime is controlled by the shared [`Policy::ttlSeconds`](../reference/api_en.md#policy), not image options. The default is 180 seconds.

## Presentation data

[`IconSequencePresentation`](../reference/api_en.md) contains:

| Field | Data provided to the application |
| --- | --- |
| `target` | The image of the target icon sequence |
| `requiredSelections` | Number of icons to select |
| `choices` | `IconSequenceChoice` options with `token` and `image` |

Images are `ImagePresentation` objects containing PNG bytes. Each choice image is 96×96 pixels; the target image is 128 pixels high, with width depending on the sequence length.

`IconSequenceChoice` exposes only a token and an image. Icon names and the correct order are not sent to the browser.

## Answer format

The application joins selected icon tokens with commas **in the exact selection order** and passes them to [`ChallengeService::verify()`](../reference/api_en.md#verify).

Each token has 32 lowercase hexadecimal characters. An answer contains 3–6 distinct tokens and is at most 197 bytes long. Spaces, duplicate tokens, and reordering are invalid. The package does not sort or normalize the answer.

After a successful [`ChallengeService::replace()`](../reference/api_en.md#replace), clear the old selection and display the new images. See the [CAPTCHA lifecycle](../reference/lifecycle_en.md).

## Verification and errors

The server checks the sequence. The browser does not receive icon names or hidden information revealing the correct order. See the [security model](../security-model_en.md).

The package includes 32 icons derived from Tabler Icons `v3.48.0` under the MIT license. Their [source](../../resources/icons/tabler/SOURCE.txt) and [license](../../resources/icons/tabler/LICENSE.txt) are included in the resources.

Invalid options cause `InvalidArgumentException`, image-generation failures `RenderingException`, and incorrect answers `VerificationCode::Incorrect` or `AttemptsExhausted` when attempts run out.

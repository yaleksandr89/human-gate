# IconSequence — последовательность значков

Пользователь видит несколько значков на изображении и выбирает такие же значки в указанном порядке. **Порядок выбора важен.**

## Подключение

Для этого вида капчи используется `IconSequenceStrategy`. Со стандартными настройками достаточно:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

При необходимости параметры указываются отдельно:

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

`IconSequenceStrategy` передаётся в `ChallengeService`. При создании капчи через [`ChallengeService::issue()`](../reference/api.md#issue) указывается `ChallengeKind::IconSequence`. Полный порядок подключения показан в [руководстве по интеграции](../guides/integration.md).

## Количество и порядок значков

| Параметр `IconSequenceOptions` | По умолчанию | Допустимые значения |
| --- | --- | --- |
| `sequenceLength` | `4` | От `3` до `6` значков |
| `choiceCount` | `8` | От `sequenceLength + 2` до `12` вариантов |

По умолчанию показана последовательность из четырёх значков и восемь вариантов на выбор. Пользователь должен выбрать четыре нужных значка в том же порядке, что на изображении.

## Настройка изображения

| Параметр `IconSequenceRenderOptions` | По умолчанию | Допустимые значения |
| --- | --- | --- |
| `minIconSize` | `72` | От `72` до `96` пикселей |
| `maxIconSize` | `80` | От `72` до `96`, не меньше `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`, `Light`, `Standard`, `Strong` |
| `noisePercent` | `150` | От `0` до `150` |
| `maxRotationDegrees` | `90` | От `0` до `90` градусов |

Размытие, шум и поворот применяются к изображению с последовательностью; изображения вариантов остаются чёткими. Размер и угол поворота должны сочетаться так, чтобы повёрнутые значки помещались в высоту изображения 128 пикселей.

Время действия капчи настраивается общим [`Policy::ttlSeconds`](../reference/api.md#policy), а не параметрами изображения. По умолчанию это 180 секунд.

## Данные для отображения

[`IconSequencePresentation`](../reference/api.md#данные-для-интерфейса) содержит:

| Поле | Что получает приложение |
| --- | --- |
| `target` | Изображение с последовательностью значков |
| `requiredSelections` | Сколько значков нужно выбрать |
| `choices` | Варианты `IconSequenceChoice` с полями `token` и `image` |

Изображения передаются как `ImagePresentation` с байтами PNG. Каждое изображение варианта имеет размер 96×96 пикселей, высота изображения с последовательностью — 128 пикселей. Ширина последнего зависит от числа значков.

`IconSequenceChoice` содержит только токен и изображение. Названия значков и их правильный порядок не передаются в браузер.

## Формат ответа

Приложение соединяет токены выбранных значков запятыми **строго в порядке выбора** и передаёт строку в [`ChallengeService::verify()`](../reference/api.md#verify).

Каждый токен состоит из 32 строчных шестнадцатеричных символов. Ответ содержит от трёх до шести разных токенов; длина строки не больше 197 байт. Пробелы, повторяющиеся токены и изменение порядка недопустимы. Пакет не сортирует ответ и не исправляет его формат.

После успешного [`ChallengeService::replace()`](../reference/api.md#replace) нужно очистить выбранную последовательность и показать новые изображения. Подробнее об обновлении — в [жизненном цикле капчи](../reference/lifecycle.md).

## Безопасность и доступность

Правильную последовательность проверяет сервер. В браузер не передаются названия значков или скрытые данные, по которым можно восстановить ответ. Подробнее о серверной проверке — в [модели безопасности](../security-model.md).

Для управления кнопками можно использовать нейтральные названия, например «Вариант 1», и отображать порядок выбранных значков. Такие подписи не заменяют [доступный альтернативный способ проверки](../accessibility.md), если пользователь не может различать значки.

В пакет включены 32 значка, подготовленных из Tabler Icons `v3.48.0` под лицензией MIT. Сведения об их [происхождении](../../resources/icons/tabler/SOURCE.txt) и [лицензии](../../resources/icons/tabler/LICENSE.txt) находятся в ресурсах пакета.

Некорректные настройки вызывают `InvalidArgumentException`, сбои создания изображения — `RenderingException`. Неверный ответ возвращает `VerificationCode::Incorrect` или `AttemptsExhausted`, если попытки закончились.

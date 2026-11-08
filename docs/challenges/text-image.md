# TextImage — текстовая капча

Пользователь видит изображение с шестью символами и вводит их в поле формы. Human Gate создаёт изображение и проверяет ответ на сервере. По умолчанию регистр букв не учитывается.

## Подключение

Для текстовой капчи используются `TextImageStrategy` и `GdTextImageRenderer`. Пример с настройками изображения и регистра:

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

Стратегия передаётся в `ChallengeService`. Текстовая капча создаётся через [`ChallengeService::issue()`](../reference/api.md#issue) по умолчанию, без указания вида. Полный пример подключения сессии и проверки ответа есть в [руководстве по интеграции](../guides/integration.md).

## Настройка ответа

`TextImageAnswerOptions::caseSensitive` определяет, учитывается ли регистр букв:

| Значение | Как проверяется ответ |
| --- | --- |
| `false` (по умолчанию) | Строчные и прописные латинские буквы считаются одинаковыми |
| `true` | Регистр букв должен совпадать с изображением |

**Длина ответа всегда шесть символов**, менять её нельзя. Буквы и цифры подбираются так, чтобы избегать ряда похожих символов. В режиме с учётом регистра доступны также отдельные строчные буквы.

В [`ChallengeService::verify()`](../reference/api.md#verify) передаётся строка, введённая пользователем. Пакет сам выполняет допустимое преобразование регистра и проверяет ответ. По краям строки удаляются только пробел, табуляция и переводы строк `CR`/`LF`. Пробелы внутри ответа и произвольная нормализация Unicode не допускаются. Максимальный размер входной строки — 64 байта.

## Настройка изображения

`TextImageRenderOptions` задаёт параметры создания изображения:

| Параметр | По умолчанию | Допустимые значения |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | От `0` до `150` |
| `maxRotationDegrees` | `28` | От `0` до `28` градусов |

Масштаб меняет размер изображения без изменения пропорций, размытие и шум усложняют чтение, поворот меняет положение символов. Эти параметры не влияют на правильный ответ.

Общее время действия капчи задаётся через [`Policy::ttlSeconds`](../reference/api.md#policy), **не** через параметры изображения. По умолчанию это 180 секунд; `new Policy(ttlSeconds: 300)` устанавливает пять минут.

## Данные для отображения

После создания текстовой капчи приложение получает [`ImagePresentation`](../reference/api.md#данные-для-интерфейса) с полями `mimeType`, `bytes`, `width`, `height`. Изображение имеет формат `image/png`; `bytes` содержит байты PNG, а не строку с кодированием base64.

При масштабе `100` размер изображения составляет 240×80 пикселей. Способ передачи изображения в браузер и поле для ввода ответа создаёт приложение.

Для работы генератора требуется `ext-gd` с поддержкой FreeType. В пакет включён шрифт Noto Sans Regular; [источник](../../resources/fonts/SOURCE.txt) и [условия использования](../../resources/fonts/OFL.txt) приведены в ресурсах.

## Ошибки и доступность

Ошибки настроек приводят к `InvalidArgumentException`; сбой создания изображения — к `RenderingException`. Неверный ответ возвращает `VerificationCode::Incorrect`, либо `AttemptsExhausted`, если попытки закончились. Это не ошибки создания изображения.

Поскольку текст нужно прочитать с картинки, приложение должно предусмотреть [альтернативный доступный способ проверки](../accessibility.md). Правильный текст нельзя добавлять в HTML или в описание изображения. Дополнительные рекомендации для защиты формы приведены в [модели безопасности](../security-model.md).

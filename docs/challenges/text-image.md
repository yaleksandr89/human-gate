# TextImage

Текстовая CAPTCHA показывает изображение с шестью символами. Пользователь вводит их в поле формы, а библиотека проверяет ответ на сервере. По умолчанию регистр букв не учитывается.

## Подключение и пример

Для этого вида проверки используются `TextImageStrategy` и генератор изображений `GdTextImageRenderer`. Например, стратегия с настройками отрисовки:

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

Стратегия передаётся в `ChallengeService`, после чего новое задание создаётся через [`issue()`](../reference/api.md#issue). Полный пример с сессией PHP приведён в [руководстве по интеграции](../guides/integration.md).

## Настройка ответа

`TextImageAnswerOptions::caseSensitive` определяет, учитывается ли регистр:

| Значение | Поведение |
| --- | --- |
| `false` (по умолчанию) | Строчные и прописные латинские буквы считаются одинаковыми |
| `true` | Буквы должны совпадать с изображением по регистру |

Длина ответа всегда составляет **шесть символов**. Она не меняется настройками. Алфавит подобран без ряда легко путаемых символов; при чувствительности к регистру добавляются отдельные строчные буквы.

Пользовательский ответ передаётся в [`verify()`](../reference/api.md#verify) как строка. Библиотека сама выполняет допустимое преобразование регистра. Внешние пробелы и переводы строк обрабатываются по строго заданным правилам, но внутренние пробелы и произвольная нормализация Unicode не поддерживаются. Предел входной строки — 64 байта.

## Настройка изображения

Параметры `TextImageRenderOptions` передаются в `GdTextImageRenderer`:

| Параметр | По умолчанию | Допустимые значения |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | От `0` до `150` |
| `maxRotationDegrees` | `28` | От `0` до `28` градусов |

Масштаб увеличивает размер изображения при сохранении пропорций. Размытие, шум и поворот меняют внешний вид символов, не изменяя правильный ответ. Настройки отображения и чувствительности к регистру независимы.

Срок действия задания задаётся отдельно через [`Policy::ttlSeconds`](../reference/api.md#policy), как и для других видов CAPTCHA. По умолчанию это 180 секунд; например, `new Policy(ttlSeconds: 300)` задаёт пять минут.

## Данные для отображения

Созданное задание содержит [`ImagePresentation`](../reference/api.md#данные-для-интерфейса) с полями `mimeType`, `bytes`, `width` и `height`. Текстовая CAPTCHA формируется в формате `image/png`; поле `bytes` содержит сами байты PNG, а не строку base64.

Обычно при масштабе `100` изображение имеет размер 240×80 пикселей. Приложение выбирает способ передачи картинки в браузер и создаёт поле для ввода ответа.

## Ошибки и ограничения

Некорректные настройки вызывают `InvalidArgumentException`. Ошибки генерации изображения, GD или FreeType вызывают `RenderingException`. Неверный ответ пользователя возвращает обычный результат `Incorrect` либо `AttemptsExhausted`, когда попытки закончились; это не ошибка отрисовки.

Для создания изображения нужны `ext-gd` и поддержка FreeType. Используется включённый в пакет шрифт Noto Sans Regular. Данные о его происхождении и лицензии находятся в [метаданных шрифта](../../resources/fonts/SOURCE.txt) и [OFL](../../resources/fonts/OFL.txt).

Поскольку задание основано на чтении изображения, приложение предусматривает [доступный альтернативный способ проверки](../accessibility.md). Правильный текст с картинки не помещают в HTML, `alt` или данные, передаваемые браузеру. Дополнительные рекомендации приведены в [модели безопасности](../security-model.md).

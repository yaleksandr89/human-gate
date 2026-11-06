# TextImage

## Назначение

Выдайте изображение с шестью ASCII-символами и попросите пользователя ввести их. `TextImageStrategy` работает через общий `ChallengeService`; `issue()` и `replace()` выбирают этот тип по умолчанию.

## Что видит пользователь

Пользователь видит PNG с текстом и помехами. По умолчанию регистр ответа не учитывается. Если приложение включает чувствительность к регистру, укажите это в инструкции рядом с полем ввода.

## Быстрый пример

Код конфигурации стратегии в composition root после подключения Composer autoload:

```php
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

Передайте `$strategy` в конструктор `ChallengeService` и вызовите `issue()` с серверным `Purpose`. Полная сборка, отдельные запросы выдачи и проверки — в [руководстве интеграции](../guides/integration.md).

## Настройки ответа

`TextImageAnswerOptions(caseSensitive: false)` — настройка по умолчанию. При `false` ASCII-буквы нижнего регистра преобразуются в верхний перед строгой проверкой. При `true` регистр значим.

Используйте согласованную настройку при выдаче и проверке ещё действующих заданий: опция принадлежит зарегистрированной стратегии.

Генератор использует следующие алфавиты:

| Режим | Алфавит |
| --- | --- |
| Без учёта регистра | `23456789ACDEFGHJKMNPQRTUVWXY` |
| С учётом регистра | `23456789ACDEFGHJKMNPQRTUVWXYadefhmnrt` |

## Настройки отрисовки

`TextImageRenderOptions` передаётся в `GdTextImageRenderer` независимо от настроек ответа.

| Поле | По умолчанию | Допустимые значения |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | `0..150` |
| `maxRotationDegrees` | `28` | `0..28` |

Профиль `100` даёт 240×80 пикселей; масштабирование сохраняет пропорции. `blur` управляет размытием текста, `lightNoisePercent` — только светлыми помехами. Угол задаёт максимальный абсолютный поворот каждого символа; `0` отключает поворот.

## Presentation

Стратегия возвращает `ImagePresentation`: `mimeType`, `bytes`, `width`, `height`. Для TextImage MIME — `image/png`, а `bytes` содержит сырые PNG-байты, не base64.

`ImagePresentation` допускает максимум 131072 байта изображения. Приложение доставляет изображение и строит поле ответа; готового HTML-виджета пакет не выдаёт.

## Формат ответа

Передайте в `ChallengeService::verify()` исходную строку пользователя. Пакет проверяет предел 64 байта до нормализации, удаляет по краям только ASCII `space`, `tab`, `CR`, `LF`, затем применяет настройку регистра и проверяет алфавит.

Канонический ответ содержит ровно 6 ASCII-байт. Внутренние пробелы, Unicode-нормализация и расширенное удаление пробельных символов не входят в контракт.

Некорректный формат, неправильный ответ или несовпадение регистра в чувствительном режиме дают обычный отрицательный результат проверки. Для активного задания это `Incorrect` либо `AttemptsExhausted` на границе лимита; ошибки состояния определяются [жизненным циклом](../reference/lifecycle.md).

## Отрисовка и runtime

Поставляемый `GdTextImageRenderer` реализует порт `TextImageRenderer::render(string $canonicalAnswer): ImagePresentation`. Он принимает канонический шестибайтовый ответ, сохраняет регистр и возвращает изображение без вывода в HTTP-ответ.

Нужен `ext-gd` с используемыми возможностями FreeType `imagettfbbox` и `imagettftext`. В пакет включён Noto Sans Regular из NotoSans `v2.015` под OFL-1.1. Источник и контрольные сведения находятся в [метаданных шрифта](../../resources/fonts/SOURCE.txt), условия — в [OFL](../../resources/fonts/OFL.txt).

## Ошибки

Некорректные options и неканонический ответ при прямом вызове renderer приводят к `InvalidArgumentException`. Это ошибки конфигурации или вызова, а не неверный пользовательский ответ в `verify()`.

Сбои GD/FreeType, чтения шрифта и обработки изображения приводят к `RenderingException`. Ошибка криптографического источника случайности может передаться как `Random\RandomException`. Генерация и отрисовка выполняются до изменения storage, поэтому неуспешная подготовка не выдаёт новое задание и не заменяет старое.

Сбои session storage отдельно выражаются через `NativeSessionStorageException`; [справочник API](../reference/api.md) разделяет эти категории ошибок.

## Безопасность

Открытый ответ не хранится в авторитетном состоянии. На сервере хранится SHA-256 proof, связанный с ID, purpose, kind и каноническим ответом. Раскрытие proof всё ещё позволяет перебирать короткий ответ без обращения к серверу, поэтому он остаётся серверным.

TextImage — дополнительный барьер в защите приложения; устойчивость к любым средствам автоматического распознавания не гарантируется. Авторизация и ограничение частоты запросов принадлежат приложению. Общие границы описаны в [модели безопасности](../security-model.md).

## Доступность

Визуальный текст может быть недоступен людям, которым трудно различать изображение. Приложение предоставляет альтернативный путь и понятную инструкцию к полю ответа. Не помещайте правильный ответ в `alt`, подпись или скрытый текст. [Рекомендации по доступности](../accessibility.md) описывают работу с изображением, обновлением и статусами.

## См. также

[Интеграция](../guides/integration.md), [жизненный цикл](../reference/lifecycle.md), [API](../reference/api.md), [безопасность](../security-model.md), [доступность](../accessibility.md).

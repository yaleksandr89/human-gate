# Справочник API

Основной класс пакета — `ChallengeService`. Он создаёт и обновляет капчу, проверяет ответы и возвращает результат. Здесь приведены сигнатуры и настройки; последовательность использования показана в [руководстве по интеграции](../guides/integration.md).

Все перечисленные типы находятся в пространстве имён `Yaleksandr\HumanGate\`. В таблицах подпространства имён указаны относительно него.

## ChallengeService

В конструктор передаются хранилище, общие настройки, источник текущего времени и стратегии капчи:

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

Для каждого вида капчи достаточно одной стратегии. Если в сессии остаются активные капчи разных видов, сервису нужны стратегии для их проверки.

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Создаёт новую капчу. При успехе возвращает `IssueResult` с кодом `LifecycleCode::Issued` и объектом `IssuedChallenge`. Если достигнут лимит активных капч, код равен `CapacityExceeded`, а `challenge === null`.

По умолчанию создаётся [`TextImage`](../challenges/text-image.md). Для остальных видов используется `ChallengeKind::CategorySelection` или `ChallengeKind::IconSequence`.

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Создаёт новую капчу вместо указанной. При успехе возвращает код `LifecycleCode::Refreshed` и новый `IssuedChallenge`. Старый идентификатор больше не подходит для проверки.

Обновление возможно для активной капчи или для истёкшей, пока запись о ней сохраняется. Новый вид по умолчанию — `TextImage`; для другого вида укажите `kind` явно. Условия обновления и удаления старых записей описаны в [жизненном цикле](lifecycle.md).

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Проверяет ответ и возвращает `VerificationCode`. Значение `Accepted` означает, что ответ правильный и та же капча больше не может быть использована.

| Значение `VerificationCode` | Что означает |
| --- | --- |
| `Accepted` | Правильный ответ, капча пройдена |
| `Incorrect` | Неверный ответ; попытки ещё есть |
| `AttemptsExhausted` | Попытки закончились |
| `Expired` | Срок действия капчи истёк |
| `NotFound` | Капча не найдена или её прежняя запись уже удалена |
| `PurposeMismatch` | Капча была создана для другого действия |
| `AlreadyConsumed` | Правильный ответ по этой капче уже был принят |
| `Replaced` | Капча уже была обновлена |

HTTP-код и сообщение для пользователя приложение определяет самостоятельно. Пример вызова [`verify()`](../guides/integration.md#проверка-ответа) есть в руководстве по интеграции.

## Policy

`Challenge\Policy` задаёт срок действия капчи, число ошибочных попыток и ограничения хранилища. Пример со стандартными значениями:

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

| Параметр | По умолчанию | Что настраивает |
| --- | --- | --- |
| `ttlSeconds` | `180` | Срок действия капчи в секундах |
| `maxWrongAttempts` | `3` | Допустимое число неверных попыток |
| `maxActive` | `12` | Максимальное число одновременно активных капч |
| `maxActivePerPurpose` | `4` | Число активных капч для одного назначения |
| `maxTombstones` | `32` | Число сохраняемых записей о завершённых капч |
| `terminalRetentionSeconds` | `180` | Время хранения записей о завершённых капч в секундах |

Все значения должны быть положительными; `maxActivePerPurpose` не может превышать `maxActive`. Для увеличения времени до пяти минут используется `new Policy(ttlSeconds: 300)`. Эти параметры ограничивают хранение капч, а **не частоту HTTP-запросов**. Подробнее о сроках и обновлении — в [жизненном цикле капчи](lifecycle.md).

## Основные типы

| Тип | Назначение |
| --- | --- |
| `Challenge\ChallengeId` | Идентификатор капчи: `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Назначение капчи: `__construct(string $value)`, `value(): string` |
| `Challenge\ChallengeKind` | Вид капчи: `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Код результата `code` и объект `challenge` либо `null` |
| `Challenge\IssuedChallenge` | Новая капча: `id`, `kind`, `expiresAt`, `presentation` |

Значение `ChallengeId` содержит ровно 64 строчных шестнадцатеричных символа. `Purpose` содержит от 1 до 64 ASCII-байт: первый символ — строчная латинская буква или цифра, далее допускаются те же символы, точка, подчёркивание и дефис.

`Purpose` выбирается на сервере, а не из поля формы. `IssuedChallenge::expiresAt` — момент окончания действия капчи в секундах Unix-времени.

## TextImage

Основные классы находятся в `TextImage\`:

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

Для `TextImageBlur` доступны `None`, `Light`, `Standard`, `Strong`. Масштаб: `100`, `105`, `110`, `115`, `120`; шум: от `0` до `150`; поворот: от `0` до `28` градусов. Регистр задаётся через `caseSensitive`. Ответ всегда содержит шесть символов. [Руководство по текстовой капче](../challenges/text-image.md) содержит рабочий пример и правила проверки ответа.

## CategorySelection

Основные классы находятся в `CategorySelection\`:

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

Поддерживается от 2 до 16 категорий с 1–32 элементами в каждой. `cardCount` задаёт от 3 до 12 карточек, `targetCount` — от 1 до числа меньше `cardCount`. Подписи — текст UTF-8 длиной 1–80 байт без управляющих символов и пробелов по краям, с уникальными значениями в каталоге.

Ответ состоит из токенов выбранных карточек через запятую. Порядок не важен. Категории, пример создания и формат ответа приведены в [руководстве по выбору карточек](../challenges/category-selection.md).

## IconSequence

Основные классы находятся в `IconSequence\`:

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

Длина последовательности: от 3 до 6 значков. Число вариантов: на два больше длины последовательности или выше, но не более `12` (например, при четырёх значках допускается от шести до двенадцати вариантов). Размер значков: 72–96 пикселей; шум: 0–150; поворот: 0–90 градусов. `IconSequenceBlur` принимает `None`, `Light`, `Standard`, `Strong`.

Ответ передаётся в порядке выбора значков. Пример настройки и правила ответа — в [руководстве по последовательности значков](../challenges/icon-sequence.md).

## Данные для интерфейса

Все типы находятся в `Presentation\`:

| Тип | Поля |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` содержит непосредственно байты изображения. Общий тип поддерживает `image/png`, `image/jpeg` и `image/webp`, размер от 1 до 4096 пикселей по каждой стороне и до 131072 байт данных. Встроенные генераторы текстовой капчи и последовательности значков создают PNG.

У выбора карточек приложение получает текст категории и карточек. У последовательности значков — изображение нужного порядка и варианты, каждый с токеном и изображением. Правильный ответ не включается в эти данные.

## Хранилище и время

| Тип | Назначение |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Получает контекст открытой сессии PHP |
| `Session\NativeSessionChallengeStore` | Хранит данные капчи в сессии PHP |
| `Clock\SystemClock` | Источник текущего времени: `now(): int` возвращает секунды Unix-времени |

`NativeSessionChallengeStore` принимает `ActiveNativeSessionScope`. Для стандартного варианта используется обработчик сессий `files` и формат сериализации `php` или `php_serialize`. Сессию сначала открывает приложение через `session_start()`. После изменения данных адаптер закрывает её. Подробности — в [интеграции](../guides/integration.md#хранение-в-сессии-php).

## Исключения

Неверный ответ пользователя обрабатывается через `VerificationCode`. Ошибки настроек или работы окружения приводят к исключениям:

| Исключение | Причина |
| --- | --- |
| `InvalidArgumentException` | Некорректные аргументы или настройки |
| `Exception\UnsupportedChallengeKindException` | В сервисе нет стратегии нужного вида |
| `Exception\RenderingException` | Ошибка создания изображения |
| `Random\RandomException` | Сбой генератора случайных значений |
| `Session\NativeSessionStorageException` | Ошибка чтения, записи или проверки сохранения данных в сессии |

`NativeSessionStorageException::reason` содержит `NativeSessionStorageFailureReason`. Возможные значения: `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. Технические подробности сбоев не следует передавать напрямую пользователю.

## Расширение пакета

При необходимости можно добавить собственное хранилище, источник времени или стратегию капчи через `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy` и `Port\TextImageRenderer`.

`ChallengeStore::atomic()` должен выполнять изменение данных без конфликтов между одновременными запросами и подтверждать сохранение до возврата результата. Если сохранение завершилось ошибкой, операция не должна возвращать успешную проверку.

`Clock::now()` возвращает текущее Unix-время в секундах для расчёта срока действия капчи. Это источник времени, а не таймер с обратным отсчётом. Методы `ChallengeStrategy::kind()`, `prepare()` и `verify()` задают вид, создание и проверку капчи; `TextImageRenderer::render()` формирует `ImagePresentation`. Для обычного подключения используются готовые реализации, показанные в [руководстве по интеграции](../guides/integration.md).

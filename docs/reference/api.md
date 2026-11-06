# Справочник API

Основная точка входа в библиотеку — `ChallengeService`. Она создаёт задания, обновляет их и проверяет ответы. Остальные классы используются для настройки задания, хранения состояния и построения интерфейса.

Все указанные ниже типы находятся в пространстве имён `Yaleksandr\HumanGate\`; полные имена показаны там, где это помогает подключить класс.

## ChallengeService

Конструктор сервиса принимает хранилище, правила работы, часы и одну или несколько стратегий:

```text
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

Повторно регистрировать стратегию одного вида нельзя. При проверке задания сервис выбирает стратегию по типу, сохранённому на сервере; поэтому для всех ещё действующих видов CAPTCHA соответствующие стратегии должны оставаться зарегистрированными.

### issue()

```text
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Создаёт задание заданного вида. При успешной выдаче `IssueResult::code` равен `LifecycleCode::Issued`, а `challenge` содержит `IssuedChallenge`. Если лимит действующих заданий исчерпан, результатом будет `CapacityExceeded` и `challenge === null`.

Без второго аргумента создаётся `TextImage`. Другие виды задаются через `ChallengeKind::CategorySelection` или `ChallengeKind::IconSequence`.

### replace()

```text
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Создаёт новое задание взамен указанного. Успех возвращает `LifecycleCode::Refreshed` и новый `IssuedChallenge`. Старый идентификатор больше не подходит для проверки. Возможно обновление ещё действующего или недавно истёкшего задания, если его запись сохранилась.

Новый тип тоже по умолчанию равен `TextImage`. Для обновления задания другого вида аргумент `kind` указывается явно. Подробные ограничения описаны в [жизненном цикле](lifecycle.md).

### verify()

```text
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Проверяет ответ по сохранённым на сервере данным. Возвращает одно из значений `VerificationCode`. Значение `Accepted` означает, что ответ правильный и повторно использовать это задание уже нельзя. Неверный ответ и окончание срока возвращаются отдельными кодами, а не исключениями.

| Значение `VerificationCode` | Что означает |
| --- | --- |
| `Accepted` | Ответ правильный; задание уже использовано |
| `Incorrect` | Ответ неверный, попытки ещё есть |
| `AttemptsExhausted` | Попытки закончились |
| `Expired` | Срок задания истёк, запись ещё сохранилась |
| `NotFound` | Задание не найдено либо старая запись удалена |
| `PurposeMismatch` | Назначение задания не соответствует действию |
| `AlreadyConsumed` | Правильный ответ по этому заданию уже был принят |
| `Replaced` | Задание ранее было обновлено |

HTTP-коды и сообщения пользователю определяет приложение. Практический пример проверки находится в [руководстве по интеграции](../guides/integration.md#проверка-ответа).

## Policy

Объект `Challenge\Policy` позволяет изменить время действия, число попыток и ограничения на количество заданий:

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

| Параметр | По умолчанию | Назначение |
| --- | --- | --- |
| `ttlSeconds` | `180` | Время действия задания в секундах |
| `maxWrongAttempts` | `3` | Число неверных попыток |
| `maxActive` | `12` | Максимум действующих заданий |
| `maxActivePerPurpose` | `4` | Максимум действующих заданий одного назначения |
| `maxTombstones` | `32` | Предел сохраняемых записей о завершённых заданиях |
| `terminalRetentionSeconds` | `180` | Время хранения завершённых записей в секундах |

Все параметры положительные; `maxActivePerPurpose` не превышает `maxActive`. Лимиты действующих заданий не ограничивают частоту HTTP-запросов. Условия истечения срока и обновления описаны в [жизненном цикле](lifecycle.md).

## Основные типы

| Тип | Для чего используется |
| --- | --- |
| `Challenge\ChallengeId` | Идентификатор задания; `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Назначение защищаемого действия; создаётся из строки, `value(): string` |
| `Challenge\ChallengeKind` | Вид CAPTCHA: `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Результат создания или обновления, поля `code` и `challenge` |
| `Challenge\IssuedChallenge` | Новое задание с полями `id`, `kind`, `expiresAt`, `presentation` |

`ChallengeId` представлен строкой из 64 строчных шестнадцатеричных символов. `Purpose` имеет длину от 1 до 64 байт и допускает строчные латинские буквы, цифры, точки, подчёркивания и дефисы; первый символ — буква или цифра.

Назначение выбирает сервер. Идентификатор можно передавать через браузер, но он не подтверждает прохождение проверки сам по себе. `IssuedChallenge::expiresAt` содержит время окончания действия в формате Unix.

## TextImage

Основные классы находятся в `TextImage\`:

```text
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

`TextImageBlur`: `None`, `Light`, `Standard`, `Strong`. Масштаб — `100`, `105`, `110`, `115`, `120`; шум — от `0` до `150`, поворот — от `0` до `28` градусов. Чувствительность к регистру задаётся отдельно. Длина ответа всегда шесть символов. Полный пример и правила ответа находятся в [руководстве TextImage](../challenges/text-image.md).

## CategorySelection

Основные классы находятся в `CategorySelection\`:

```text
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

Стратегия принимает от 2 до 16 категорий с 1–32 предметами в каждой. `cardCount` — от 3 до 12, `targetCount` — от 1 до значения строго меньше `cardCount`. Текстовые подписи должны быть корректным UTF-8 длиной до 80 байт; названия и подписи в каталоге уникальны.

Ответ состоит из токенов выбранных карточек через запятую, порядок не важен. Подробное описание подготовки каталога и интерфейса — в [руководстве CategorySelection](../challenges/category-selection.md).

## IconSequence

Основные классы находятся в `IconSequence\`:

```text
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

В последовательности от 3 до 6 значков; вариантов от `sequenceLength + 2` до 12. Размер значка от 72 до 96 пикселей, шум от 0 до 150, поворот от 0 до 90 градусов. `IconSequenceBlur`: `None`, `Light`, `Standard`, `Strong`.

Ответ состоит из токенов через запятую **в порядке выбора пользователя**. Параметры изображения и точный формат ответа описаны в [руководстве IconSequence](../challenges/icon-sequence.md).

## Данные для интерфейса

Все типы представления находятся в `Presentation\`:

| Тип | Поля |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` хранит сырые байты изображения. Общий тип принимает `image/png`, `image/jpeg` и `image/webp`, размер изображения от 1 до 4096 пикселей по каждой стороне и не более 131072 байт данных. Встроенные генераторы TextImage и IconSequence используют PNG.

`CategorySelectionPresentation` даёт текст категории и карточек. `IconSequencePresentation` даёт изображение с нужной последовательностью и варианты значков; отдельный вариант содержит токен и изображение, но не название значка или его правильную позицию.

## Хранилище и время

| Тип | Назначение |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Получает контекст уже открытой встроенной сессии PHP |
| `Session\NativeSessionChallengeStore` | Хранит задания в поддерживаемой сессии PHP |
| `Clock\SystemClock` | Возвращает текущее Unix-время через `now(): int` |

`NativeSessionChallengeStore` принимает объект `ActiveNativeSessionScope`. Поддерживаются обработчик `files` и форматы сериализации `php` или `php_serialize`. Начальную сессию открывает приложение. После изменения данных адаптер может закрыть сессию; полный порядок работы описан в [интеграции](../guides/integration.md#хранение-в-сессии-php).

## Исключения

Неверный ответ пользователя возвращает значение `VerificationCode`. Ошибки настройки или работы окружения могут приводить к исключениям:

| Тип | Когда возникает |
| --- | --- |
| `InvalidArgumentException` | Некорректные настройки, идентификатор или другие аргументы |
| `Exception\UnsupportedChallengeKindException` | Запрошена стратегия, не зарегистрированная в сервисе |
| `Exception\RenderingException` | Не удалось сформировать изображение |
| `Random\RandomException` | Сбой безопасного генератора случайных данных |
| `Session\NativeSessionStorageException` | Не удалось прочитать, изменить или подтвердить хранение задания в сессии |

У `NativeSessionStorageException` есть поле `reason` типа `NativeSessionStorageFailureReason`. Возможные причины: `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. Технические сведения об ошибке не следует передавать пользователю напрямую.

## Расширение библиотеки

Для собственного хранилища, часов или генератора заданий предусмотрены интерфейсы `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy` и `Port\TextImageRenderer`.

`ChallengeStore::atomic()` принимает преобразование состояния и возвращает его результат. Реализация должна защищать данные от одновременного изменения и подтверждать сохранение **до возврата успешного результата**. Ошибка записи не должна выглядеть как успешная проверка; из-за этого реализация собственного хранилища требует отдельной проверки атомарности и обработки сбоев.

`Clock::now()` возвращает Unix-время в секундах. `ChallengeStrategy` задаёт тип, создание и проверку задания через `kind()`, `prepare()` и `verify()`. `TextImageRenderer::render()` возвращает `ImagePresentation`. Для обычного использования достаточно уже включённых реализаций; примеры работы с ними находятся в [руководстве по интеграции](../guides/integration.md).

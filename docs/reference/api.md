# Справочник публичного API

## Основная интеграция

Все типы пакета ниже относятся к namespace `Yaleksandr\HumanGate\`; стандартные типы PHP указаны отдельно. В таблицах указаны подпространства имён; `ChallengeService` находится непосредственно в корневом namespace.

Соберите сервис из явно переданных зависимостей. Его публичные сигнатуры:

```text
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)

public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult

public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult

public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

`ChallengeStore`, `ChallengeStrategy` и `Clock` находятся в `Port\`; параметры и результаты — в `Challenge\`.

`issue()` и `replace()` по умолчанию используют TextImage. Для CategorySelection и IconSequence передайте kind явно. `verify()` выбирает стратегию по kind в серверной записи. Зарегистрируйте все стратегии для ещё действующих заданий; повторная регистрация одного kind недопустима.

| Тип | Публичный контракт |
| --- | --- |
| `Challenge\ChallengeId` | `generate(): self`, `fromString(string $value): self`, `value(): string`; ровно 64 строчных hex-символа |
| `Challenge\Purpose` | `__construct(string $value)`, `value(): string`; 1..64 ASCII-байта, первый символ `[a-z0-9]`, остальные `[a-z0-9._-]` |
| `Challenge\ChallengeKind` | String enum: `TextImage = 'text_image'`, `CategorySelection = 'category_selection'`, `IconSequence = 'icon_sequence'` |

Purpose и выбор kind принадлежат серверу. ID можно переносить через браузер как непрозрачное значение. Разбор ID не доказывает существование или корректность задания.

## Жизненный цикл и результаты

`Challenge\Policy` — неизменяемая конфигурация. Все значения положительны, `maxActivePerPurpose <= maxActive`.

| Параметр конструктора / поле | По умолчанию |
| --- | --- |
| `ttlSeconds` | `180` |
| `maxWrongAttempts` | `3` |
| `maxActive` | `12` |
| `maxActivePerPurpose` | `4` |
| `maxTombstones` | `32` |
| `terminalRetentionSeconds` | `180` |

Задание действительно при `now < expiresAt`. `maxActive*` ограничивает активное состояние, а не частоту HTTP-запросов. Правила истечения срока, замены и удаления записей описаны в [жизненном цикле](lifecycle.md).

| Тип | Поля конструктора / публичные поля |
| --- | --- |
| `Challenge\IssueResult` | `State\LifecycleCode $code`, `?IssuedChallenge $challenge` |
| `Challenge\IssuedChallenge` | `ChallengeId $id`, `ChallengeKind $kind`, `int $expiresAt`, `Presentation $presentation` |

`expiresAt` — Unix-время в секундах. `IssueResult` требует непустой `challenge` для `Issued` и `Refreshed`; для допустимых отрицательных кодов `challenge` равен `null`.

Обычная выдача возвращает `LifecycleCode::Issued` или `CapacityExceeded`. Замена возвращает `Refreshed`, `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed` или `Replaced`. DTO допускает также отрицательный `Expired`; сохранённую истёкшую запись текущая замена обрабатывает как кандидат на обновление.

`State\LifecycleCode` содержит `Active`, `Issued`, `Refreshed`, `Consumed`, `WrongAttempt`, `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `Expired`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced`. `ChallengeService::verify()` возвращает отдельный публичный `VerificationCode`:

| Case | Строковое значение | Смысл |
| --- | --- | --- |
| `Accepted` | `accepted` | Ответ принят, задание потреблено |
| `Incorrect` | `incorrect` | Неверный ответ, лимит ещё не достигнут |
| `AttemptsExhausted` | `attempts_exhausted` | Попытки исчерпаны |
| `NotFound` | `not_found` | Записи нет, в том числе после удаления |
| `PurposeMismatch` | `purpose_mismatch` | Назначение не совпало |
| `Expired` | `expired` | Срок истёк, терминальная запись сохранена |
| `AlreadyConsumed` | `already_consumed` | Задание уже потреблено, запись сохранена |
| `Replaced` | `replaced` | Задание заменено, запись сохранена |

HTTP-коды и публичные тексты ошибок выбирает приложение. Внутренние переходы состояния не являются рекомендуемой точкой входа вместо `ChallengeService`.

## Storage и Clock

| Тип | Конструктор / метод |
| --- | --- |
| `Session\NativeSessionChallengeStore` | `__construct(ActiveNativeSessionScope $scope)`; реализация `ChallengeStore` |
| `Clock\SystemClock` | `now(): int`; реализация `Port\Clock`, возвращает системное Unix-время в секундах |
| `Port\Clock` | `now(): int`; часы для жизненного цикла |

Поставляемый storage adapter работает с уже активной native files session. Порт `ChallengeStore` предназначен для атомарного состояния; его требования приведены в разделе расширения. Начальную session открывает приложение.

## TextImage

Namespace настроек и реализации: `TextImage\`; порт отрисовки: `Port\TextImageRenderer`.

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

Здесь и ниже приведены справочные сигнатуры конструкторов. Исполняемые примеры находятся в тематических руководствах.

`TextImageBlur`: `None`, `Light`, `Standard`, `Strong`. `scalePercent`: `100|105|110|115|120`, `lightNoisePercent`: `0..150`, `maxRotationDegrees`: `0..28`.

`GdTextImageRenderer::render(string $canonicalAnswer): ImagePresentation` принимает канонические 6 ASCII-байт, сохраняет регистр и выдаёт PNG без вывода. Стратегия реализует `kind()`, `prepare()` и `verify()` порта `ChallengeStrategy`. Сырой пользовательский ответ ограничен 64 байтами; подробности нормализации, алфавитов и runtime — в [TextImage](../challenges/text-image.md).

## CategorySelection

Namespace: `CategorySelection\`.

```text
CategorySelectionCategory::__construct(string $label, array $items)

CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)

CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

`CategorySelectionCategory` открывает `label` и `items` как список строк. Стратегия принимает список 2..16 таких категорий, по 1..32 предмета. Подписи — UTF-8 текст длиной 1..80 байт без C0, DEL и крайних ASCII space/tab/CR/LF. Названия категорий уникальны, подписи предметов уникальны точным сравнением во всём каталоге.

`cardCount`: `3..12`; `targetCount >= 1` и `< cardCount`. Каждая категория содержит достаточно целевых предметов; остальные вместе — достаточно отвлекающих. Семантику каталога определяет приложение.

Стратегия реализует `ChallengeStrategy`. Ответ — уникальные непрозрачные токены через запятую; порядок не важен, предел сырой строки — 512 байт. [CategorySelection](../challenges/category-selection.md) описывает каталог, канонизацию и замену.

## IconSequence

Namespace: `IconSequence\`.

```text
IconSequenceOptions::__construct(int $sequenceLength = 4, int $choiceCount = 8)

IconSequenceRenderOptions::__construct(
    int $minIconSize = 72,
    int $maxIconSize = 80,
    IconSequenceBlur $blur = IconSequenceBlur::Strong,
    int $noisePercent = 150,
    int $maxRotationDegrees = 90,
)

IconSequenceStrategy::__construct(
    IconSequenceOptions $options = new IconSequenceOptions(),
    IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
)
```

`sequenceLength`: `3..6`; `choiceCount`: от `sequenceLength + 2` до `12`. Размеры: `72..96`, `maxIconSize >= minIconSize`; шум: `0..150`; поворот: `0..90`. Габарит повёрнутого квадрата должен помещаться в высоту цели 128 пикселей. `IconSequenceBlur`: `None`, `Light`, `Standard`, `Strong`.

Стратегия реализует `ChallengeStrategy`. Ответ — 3..6 уникальных токенов через запятую в порядке выбора, максимум 197 байт. Сортировка и нормализация пробелов не выполняются. [IconSequence](../challenges/icon-sequence.md) описывает размеры, rendering и изображения вариантов.

## Presentation types

Все типы находятся в `Presentation\`. DTO неизменяемы; их конструкторы проверяют собственные ограничения. `Presentation` — общий маркерный интерфейс, без методов.

| Тип | Параметры конструктора / поля |
| --- | --- |
| `ImagePresentation` | `string $mimeType`, `string $bytes`, `int $width`, `int $height` |
| `CategorySelectionPresentation` | `string $category`, `array $cards` — список `CategorySelectionCard` |
| `CategorySelectionCard` | `string $token`, `string $label` |
| `IconSequencePresentation` | `ImagePresentation $target`, `int $requiredSelections`, `array $choices` — список `IconSequenceChoice` |
| `IconSequenceChoice` | `string $token`, `ImagePresentation $image` |

Общий `ImagePresentation` допускает `image/png`, `image/jpeg`, `image/webp`, непустые сырые байты до 131072 и размеры `1..4096` по каждой оси. Поставляемые TextImage и IconSequence используют PNG; оба изображения IconSequence требуют MIME `image/png`.

CategorySelection presentation содержит 3..12 карточек, уникальные токены и подписи. Категория и подписи проходят текстовую границу 1..80 UTF-8 байт. В IconSequence `requiredSelections` равен `3..6`, вариантов от `requiredSelections + 2` до `12`, токены и байты изображений вариантов уникальны. Токены обоих типов — ровно 32 строчных hex-символа.

Presentation не выдаёт готовый HTML. Семантических названий, путей файлов, target membership и правильных позиций IconSequence в этих DTO нет. Рекомендации по выводу — в [интеграции](../guides/integration.md) и [доступности](../accessibility.md).

## Native PHP session

`Session\ActiveNativeSessionScope::capture(): self` захватывает полномочие только из активной поддерживаемой session. Передайте его в `NativeSessionChallengeStore` для одной границы изменяющей операции.

Scope также имеет `assertActive(): void` и `commitAndRead(): mixed` для работы adapter. Обычная интеграция вызывает `capture()`; фиксацией и проверкой записи управляет storage adapter.

Поддерживаются native module/save handler `files` и serializer `php` или `php_serialize`. Реальное изменение закрывает session и проверяет запись через повторное открытие только для чтения. Истинная операция без изменения может оставить session активной. После изменения приложение самостоятельно управляет последующими записями в `$_SESSION`. [Руководство интеграции](../guides/integration.md) объясняет порядок вызовов и требования к заголовкам.

## Exceptions

| Категория | Тип | Смысл |
| --- | --- | --- |
| Ошибка конфигурации или вызова | `InvalidArgumentException` | Некорректные options, DTO, прямой вход renderer, повтор стратегии |
| Отсутствует стратегия | `Exception\UnsupportedChallengeKindException` | Kind не зарегистрирован; наследует `InvalidArgumentException` |
| Rendering/runtime | `Exception\RenderingException` | Сбой GD/FreeType, ресурсов или обработки изображения; наследует `RuntimeException` |
| Случайность | `Random\RandomException` | Ошибка безопасного источника случайности может передаться вызывающему коду |
| Native session infrastructure | `Session\NativeSessionStorageException` | Сбой storage с типизированным `reason`; наследует `RuntimeException`, может сохранять `previous` |

`Incorrect`, `AttemptsExhausted`, `Expired`, `AlreadyConsumed`, `Replaced`, `NotFound`, `PurposeMismatch` — обычные результаты, отдельно от инфраструктурных исключений. При инфраструктурном сбое не продолжайте защищаемое действие и не подменяйте сбой результатом `Incorrect`.

`NativeSessionStorageFailureReason` — string enum в `Session\`:

| Case | Строковое значение | Граница сбоя |
| --- | --- | --- |
| `UnsupportedSession` | `unsupported_session` | Неактивная, неподдерживаемая session или израсходованный scope |
| `SessionIdentity` | `session_identity` | Нарушение идентичности session |
| `HeadersSent` | `headers_sent` | Заголовки уже отправлены при изменении |
| `MalformedState` | `malformed_state` | Некорректное состояние пакета |
| `SizeLimit` | `size_limit` | Превышен предел состояния |
| `Randomness` | `randomness` | Не удалось получить случайные данные для storage |
| `Commit` | `commit` | Сбой фиксации |
| `Verification` | `verification` | Сохранённую запись не удалось подтвердить |

`NativeSessionStorageException` конструируется с `NativeSessionStorageFailureReason $reason` и необязательным `?Throwable $previous = null`; публичное readonly поле `reason` позволяет различить причины. Детали инфраструктуры не назначаются автоматически сообщениями браузеру.

## Расширение пакета

Эти контракты нужны при реальном требовании собственного адаптера, часов, средства отрисовки или типа стратегии. Для обычной интеграции используйте поставляемые реализации и `ChallengeService`.

| Контракт | Методы / поля | Требование |
| --- | --- | --- |
| `Port\ChallengeStore` | `atomic(Closure $transition): mixed` | Атомарное преобразование `State\ChallengeBucket`, возвращающее результат callback |
| `Port\ChallengeStrategy` | `kind(): ChallengeKind`; `prepare(ChallengeId $id, Purpose $purpose): PreparedChallenge`; `verify(ActiveChallenge $challenge, string $submittedAnswer): bool` | Подготовка до сохранения; ограниченная проверка без побочных эффектов |
| `Port\Clock` | `now(): int` | Unix-время в секундах; жизненный цикл требует неотрицательное значение |
| `Port\TextImageRenderer` | `render(string $canonicalAnswer): ImagePresentation` | Канонические 6 ASCII-байт с сохранением регистра; результат без вывода, сбой через исключение |
| `Challenge\PreparedChallenge` | `State\AnswerProof $proof`, `Presentation $presentation` | Серверный proof и отдельное клиентское представление |
| `State\ActiveChallenge` | `id`, `purpose`, `kind`, `issuedAt`, `expiresAt`, `proof`, `wrongAttempts = 0` | Неизменяемая запись; время выдачи неотрицательно, срок позже выдачи, число ошибок неотрицательно |
| `State\AnswerProof` | `int $format`, `string $digest` | Format `1`, digest — 64 строчных hex-символа; только сервер |
| `State\ChallengeBucket` | Доступ к активным и терминальным записям | Изменяется только внутри `ChallengeStore::atomic()` |

`ChallengeStore` устанавливает исключительное владение или сериализацию доступа до чтения актуального состояния. Callback получает изолированный авторитетный bucket. Изменения фиксируются до возврата результата; сбой фиксации должен выбросить исключение. Сбой callback сохраняет предыдущее состояние без частичной записи. Сохранённая ссылка на bucket или результат не должна позволять менять уже зафиксированное состояние.

Callback не выполняет необратимых внешних действий и не удерживает bucket для дальнейших изменений; вложенные `atomic()` не поддерживаются. Гарантии ограничены поддерживаемой средой конкретного adapter и не обещают распределённого консенсуса.

`ActiveChallenge::withWrongAttempt(): self` создаёт запись с увеличенным счётчиком. Параметры конструктора: `ChallengeId $id`, `Purpose $purpose`, `ChallengeKind $kind`, `int $issuedAt`, `int $expiresAt`, `AnswerProof $proof`, `int $wrongAttempts = 0`.

`ChallengeBucket` предоставляет `active()`, `tombstone()`, `setActive()`, `setTombstone()`, `removeTombstone()`, `activeChallenges()`, `tombstones()`, `activeCount(?Purpose $purpose = null)` и `copyFrom()`. Терминальный ID нельзя вернуть в активное состояние. Терминальная запись использует `State\TerminalReason`: `Expired`, `AttemptsExhausted`, `Consumed`, `Replaced`. Эти state types остаются серверными.

`State\LifecycleResult` сочетает `LifecycleCode` и необязательный `ActiveChallenge`; запись обязательна для `Active`, `Issued`, `Refreshed`, `WrongAttempt` и отсутствует для других кодов. В приложении используйте `IssueResult` и `VerificationCode` сервиса.

## См. также

[Интеграция](../guides/integration.md) показывает исполняемые примеры, [жизненный цикл](lifecycle.md) — правила результатов, [модель безопасности](../security-model.md) — серверные данные и ответственность приложения.

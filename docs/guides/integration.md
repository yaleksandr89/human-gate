# Интеграция Human Gate

## Модель интеграции

Соберите `ChallengeService` в composition root приложения и передайте его коду, который выдаёт, заменяет или проверяет задание. Ядро управляет состоянием проверки; приложение управляет HTTP, интерфейсом и защищаемым действием.

Выберите `Purpose` на сервере, например `contact.send`. Выдайте задание, передайте клиенту ID и данные представления, затем вызовите `verify()` непосредственно перед защищаемым действием. Действие допускается только при `VerificationCode::Accepted`.

## Сборка ChallengeService

Конструктор принимает `ChallengeStore`, `Policy`, `Clock` и стратегии. `issue()` и `replace()` по умолчанию выбирают `ChallengeKind::TextImage`. Для других типов передавайте kind явно.

`verify()` выбирает стратегию по kind в авторитетном состоянии. Зарегистрируйте каждую стратегию, которая может понадобиться для проверки ещё действующих заданий, даже если сейчас выдаёте только один тип.

Ниже общий код composition root для последующих примеров. Подключите его и Composer autoload в каждом запросе; сам вызов сборки выполняйте после открытия session. Категории здесь учебные, принадлежат приложению.

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require 'vendor/autoload.php';

function createChallengeServiceForActiveSession(): ChallengeService
{
    $scope = ActiveNativeSessionScope::capture();

    return new ChallengeService(
        new NativeSessionChallengeStore($scope),
        new Policy(),
        new SystemClock(),
        new TextImageStrategy(new GdTextImageRenderer()),
        new CategorySelectionStrategy([
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
        ]),
        new IconSequenceStrategy(),
    );
}
```

В приложении с другим хранилищем передайте свою реализацию [ChallengeStore](../reference/api.md). Поставляемый adapter использует native PHP session; его граница описана ниже.

## Создание проверки

Запрос выдачи, после подключения общего composition root:

```php
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть session приложения.');
}

$service = createChallengeServiceForActiveSession();
$purpose = new Purpose('contact.send');
$result = $service->issue($purpose, ChallengeKind::CategorySelection);

if ($result->challenge !== null) {
    $challenge = $result->challenge;
    $id = $challenge->id->value();
    $presentation = $challenge->presentation;
}
```

`IssueResult` содержит `code` и `challenge`. Успешная выдача возвращает `LifecycleCode::Issued` и `IssuedChallenge` с полями `id`, `kind`, `expiresAt`, `presentation`. При отказе проверьте код, прежде чем строить интерфейс.

`Purpose` — 1..64 ASCII-байта: первый символ — строчная латинская буква или цифра, остальные — строчные латинские буквы, цифры, `.`, `_`, `-`. Авторитетное назначение выбирает сервер, а не поле браузера.

`ChallengeId` создаётся сервером. `value()` возвращает ровно 64 строчных hex-символа. Клиент переносит этот непрозрачный ID, но не определяет по нему purpose, kind или правильный ответ.

## Отображение Presentation

Выбирайте интерфейс по типизированному представлению. Human Gate не выдаёт готовый HTML, маршруты изображений или нормативную JSON-оболочку.

| Тип | Данные для интерфейса | Что отправляет пользователь |
| --- | --- | --- |
| `ImagePresentation` для [TextImage](../challenges/text-image.md) | `mimeType`, сырые `bytes`, `width`, `height` | Введённую строку |
| [CategorySelectionPresentation](../challenges/category-selection.md) | `category`, карточки `token` + `label` | Выбранные токены через запятую; порядок не важен |
| [IconSequencePresentation](../challenges/icon-sequence.md) | `target`, `requiredSelections`, варианты `token` + `image` | Токены через запятую в порядке выбора |

PNG передаётся как сырые байты, без base64. Приложение выбирает способ доставки изображения и корректный `Content-Type`. Сведения о проверке и её изображения должны оставаться связанными с текущим заданием; HTTP-доступ и кэширование определяет приложение.

`category` и `label` — обычный текст: экранируйте его для конкретного контекста вывода. Варианты IconSequence не имеют семантических названий, признака принадлежности к цели или позиции в правильном ответе.

Клиенту передавайте только нужные данные из `IssuedChallenge` и `Presentation`. `AnswerProof`, правильный ответ и состояние storage остаются на сервере.

## Проверка ответа

Это отдельный запрос. После подключения того же composition root приложение заново открывает session, захватывает новый scope при сборке и использует тот же серверный `Purpose`. Поля `challengeId` и `answer` в примере — выбор приложения, а не HTTP-контракт пакета.

```php
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

$rawId = $_POST['challengeId'] ?? null;
$rawAnswer = $_POST['answer'] ?? null;
if (!is_string($rawId) || !is_string($rawAnswer)) {
    throw new InvalidArgumentException('Ожидаются строковые ID и ответ.');
}
$id = ChallengeId::fromString($rawId);

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть session приложения.');
}

$service = createChallengeServiceForActiveSession();
$code = $service->verify($id, new Purpose('contact.send'), $rawAnswer);
```

На HTTP-границе приложение перехватывает некорректный ID как ошибку входных данных. Перед вызовом сервиса также ограничьте размер входящего запроса. Передавайте строковый ответ без собственной канонизации: правила каждого типа применяет пакет.

При `VerificationCode::Accepted` выполняйте защищаемое действие в этом же запросе или потоке обработки. Если действие затем завершится ошибкой, проверка уже потреблена. Идемпотентность и транзакции действия принадлежат приложению; отдельного протокола «решённого токена» в v1 нет.

## Замена проверки

Замена — ещё один самостоятельный запрос, с новым открытием session и сборкой сервиса. Ниже функция принимает уже разобранный ID от HTTP-границы:

```php
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): IssueResult
{
    if (!session_start()) {
        throw new RuntimeException('Не удалось открыть session приложения.');
    }

    $service = createChallengeServiceForActiveSession();

    return $service->replace($oldId, new Purpose('contact.send'), ChallengeKind::CategorySelection);
}
```

При `LifecycleCode::Refreshed` установите новый ID и представление, очистите ответ и выбор в интерфейсе. Старый ID становится `Replaced`, пока его терминальная запись сохранена.

Активное задание и сохранённая истёкшая запись могут допускать замену. После удаления записи старый ID возвращает `NotFound`. Подробные условия и ёмкость описаны в [жизненном цикле](../reference/lifecycle.md).

## Native PHP session

Приложение владеет исходной конфигурацией session и первым `session_start()`. Human Gate не открывает исходную session неявно. Вызывайте `ActiveNativeSessionScope::capture()` только после её успешного открытия.

Текущая поддерживаемая среда — native модуль session `files`, save handler `files` и serializer `php` либо `php_serialize`. Другие handlers и serializers этим adapter не поддерживаются.

Захваченный scope рассчитан на одну границу изменяющей операции пакета. Не храните его, adapter или сервис с таким adapter как повторно используемый singleton приложения. Для следующей изменяющей операции заново обеспечьте активную session и соберите зависимости с новым scope.

Реальное изменение состояния фиксирует и закрывает session, затем adapter повторно открывает её только для проверки сохранённой записи и снова оставляет закрытой. Поэтому после успешного изменения поздние записи в `$_SESSION` сами по себе не сохранятся: приложение должно снова открыть session по своему жизненному циклу. Изменения приложения, которым нужно попасть в ту же фиксацию, сделайте до вызова Human Gate.

Истинная операция без изменения состояния может оставить session активной. Код отрицательного результата сам по себе не доказывает отсутствие изменения: операция могла выполнить очистку истёкших записей. Управляйте дальнейшей session явно.

Изменяющий вызов выполняйте до отправки заголовков. Медленную работу и внешние обращения выполняйте после того, как Human Gate зафиксировал и закрыл свою работу с session. Если операция оставила session активной, приложение само закрывает её перед такой работой.

`NativeSessionStorageException` означает инфраструктурный сбой, а не неверный ответ пользователя. При нём защищаемое действие не выполняется; технические детали не передаются клиенту. Причины перечислены в [справочнике API](../reference/api.md).

## Обработка результатов

| Результат | Действие приложения |
| --- | --- |
| `Issued`, `Refreshed` в `IssueResult` | Показать полученное задание |
| `CapacityExceeded` в `IssueResult` | Обработать отсутствие места для активного задания |
| `Accepted` в `VerificationCode` | Продолжить защищаемое действие |
| `Incorrect` | Дать повторить ответ на ещё активное задание |
| `AttemptsExhausted` | Запросить новое задание через выдачу |
| `Expired` | Предложить замену сохранённого задания либо новую выдачу |
| `AlreadyConsumed`, `Replaced` | Не принимать старое задание повторно |
| `NotFound` | Получить новое задание; ID отсутствует либо запись уже удалена |
| `PurposeMismatch` | Отклонить запрос, не раскрывая состояние чужого назначения |

Это рекомендации для потока приложения. Пакет не назначает HTTP-коды. Все значения результатов и различия между кодами и исключениями приведены в [API](../reference/api.md) и [жизненном цикле](../reference/lifecycle.md).

## Ответственность приложения

Приложение владеет маршрутами, controllers, HTML/CSS/JS, CSRF, авторизацией, экранированием вывода, идентификацией клиента, trusted-proxy/IP policy и ограничением частоты запросов. `Policy::maxActive*` ограничивает состояние жизненного цикла, а не частоту HTTP-запросов.

Приложение обеспечивает идемпотентность защищаемого действия и [доступный альтернативный путь](../accessibility.md). Проверка CAPTCHA сама по себе не выдаёт полномочий пользователю. Подробная [граница доверия](../security-model.md) относится ко всем трём типам заданий.

## Типичные ошибки интеграции

- Захват scope до `session_start()` или повторное использование уже израсходованного scope.
- Запись в `$_SESSION` после изменения Human Gate без повторного открытия session.
- Регистрация только текущего типа выдачи, когда в storage остаются активные задания других типов.
- Пропуск явного kind при выдаче или замене CategorySelection и IconSequence.
- Получение авторитетного `Purpose` или kind из полей клиента.
- Передача подписей вместо токенов, сортировка IconSequence или исправление некорректных токенов.
- Сохранение старого ID или выбранных токенов после замены.
- Обработка инфраструктурного исключения как `Incorrect` либо продолжение действия без `Accepted`.

## См. также

[TextImage](../challenges/text-image.md), [CategorySelection](../challenges/category-selection.md) и [IconSequence](../challenges/icon-sequence.md) описывают настройки и ответы. [API](../reference/api.md) фиксирует сигнатуры, [жизненный цикл](../reference/lifecycle.md) — переходы состояния, [безопасность](../security-model.md) и [доступность](../accessibility.md) — границы интеграции.

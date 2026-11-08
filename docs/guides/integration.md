# Интеграция Human Gate

Пакет создаёт капчу и проверяет ответы на сервере. Приложение показывает капчу в форме и продолжает обработку формы только после правильного ответа.

## Как работает капча

Приложение вызывает [`ChallengeService::issue()`](../reference/api.md#issue) и получает идентификатор новой капчи вместе с данными для её отображения. Эти данные передаются в браузер.

При отправке формы приложение получает идентификатор и введённый ответ и вызывает [`ChallengeService::verify()`](../reference/api.md#verify). Результат `VerificationCode::Accepted` разрешает продолжить обработку формы. Правильный ответ и сведения о правильных вариантах остаются на сервере.

Для изменения капчи используется [`ChallengeService::replace()`](../reference/api.md#replace). При этом создаётся новая капча с новым идентификатором.

## Подключение пакета

`ChallengeService` создаётся из хранилища, общих [настроек `Policy`](../reference/api.md#policy), часов и стратегий выбранных видов капчи. Ниже пример подключения всех трёх видов со стандартной сессией PHP. Категории в нём приведены для примера.

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
    return new ChallengeService(
        new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
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

Эта функция вызывается после `session_start()`. В ней зарегистрированы все три стратегии: это позволяет проверять капчи разных видов, созданные ранее в той же сессии. Подробные настройки есть в руководствах по [`TextImage`](../challenges/text-image.md), [`CategorySelection`](../challenges/category-selection.md) и [`IconSequence`](../challenges/icon-sequence.md).

## Создание капчи

В обработчике запроса приложение открывает сессию и создаёт капчу нужного вида. Пример для выбора карточек:

```php
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);

if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

Успешный [`issue()`](../reference/api.md#issue) возвращает `IssueResult` с кодом `LifecycleCode::Issued` и объектом `IssuedChallenge`. В последнем доступны `id`, `kind`, `expiresAt` и `presentation`. Если лимит активных капч достигнут, `challenge` равен `null`, а код результата объясняет причину.

`Purpose` обозначает форму или другое защищаемое действие, например `contact.send`. Его значение выбирается в PHP-коде приложения и не берётся из данных браузера.

Без явного второго аргумента `issue()` создаёт текстовую капчу. Для выбора карточек и последовательности значков указываются соответственно `ChallengeKind::CategorySelection` и `ChallengeKind::IconSequence`.

## Отображение капчи

Пакет возвращает данные, из которых приложение собирает свой интерфейс:

| Вид капчи | Данные для отображения | Ответ пользователя |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image.md) | `ImagePresentation`: тип, байты и размер изображения | Введённые символы |
| [`CategorySelection`](../challenges/category-selection.md) | `CategorySelectionPresentation`: название категории и карточки | Токены выбранных карточек, разделённые запятыми; порядок не важен |
| [`IconSequence`](../challenges/icon-sequence.md) | `IconSequencePresentation`: изображение последовательности и варианты значков | Токены через запятую в порядке выбора |

`ImagePresentation::bytes` содержит непосредственно байты изображения. Для его отображения приложение выбирает подходящий способ передачи в браузер. Текст категорий и карточек экранируется при выводе в HTML.

Для всех трёх видов капчи проверку ответа выполняет сервер. В интерфейс передаются только данные, необходимые для показа и выбора вариантов.

## Проверка ответа

Ответ отправляется отдельным запросом. Приложение открывает **ту же пользовательскую сессию**, создаёт `ChallengeService` заново и передаёт идентификатор и ответ, полученные из формы:

```php
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

$rawId = $_POST['challengeId'] ?? null;
$rawAnswer = $_POST['answer'] ?? null;
if (!is_string($rawId) || !is_string($rawAnswer)) {
    throw new InvalidArgumentException('Ожидаются строковые идентификатор и ответ.');
}

$id = ChallengeId::fromString($rawId);

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->verify($id, new Purpose('contact.send'), $rawAnswer);
```

В примере `challengeId` и `answer` — названия полей конкретной формы, а не обязательные названия Human Gate. Перед вызовом `verify()` приложение проверяет формат входящего запроса. Полученный ответ передаётся без изменения регистра, сортировки или других преобразований: у каждого вида капчи свои правила проверки.

При `VerificationCode::Accepted` форма обрабатывается в этом же запросе. Например, если отправка письма после правильного ответа завершилась ошибкой, предыдущая капча уже использована. Повторную отправку письма и обработку сбоя определяет приложение.

## Обновление капчи

[`ChallengeService::replace()`](../reference/api.md#replace) получает старый идентификатор и возвращает новую капчу. Для выбора карточек:

```php
use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): \Yaleksandr\HumanGate\Challenge\IssueResult
{
    if (!session_start()) {
        throw new RuntimeException('Не удалось открыть сессию PHP.');
    }

    return createChallengeServiceForActiveSession()->replace(
        $oldId,
        new Purpose('contact.send'),
        ChallengeKind::CategorySelection,
    );
}
```

После успешного обновления приложение отображает новые данные и сбрасывает прежний ввод и выбранные варианты. Если срок старой капчи истёк, обновление возможно, пока данные о ней ещё хранятся; после их удаления возвращается `NotFound`. Подробнее — в [жизненном цикле капчи](../reference/lifecycle.md).

## Хранение в сессии PHP

В примерах используется готовый [`NativeSessionChallengeStore`](../reference/api.md#хранилище-и-время). Это хранилище работает с обычными сессиями PHP: приложение открывает сессию через `session_start()`, после чего создаёт `ActiveNativeSessionScope::capture()` и передаёт его хранилищу. Дополнительный сервис хранения для такого варианта не нужен.

Адаптер поддерживает стандартный обработчик сессий `files` и форматы сериализации `php` и `php_serialize`.

После успешного изменения данных капчи адаптер сохраняет их и закрывает сессию. Если приложению нужно затем изменить `$_SESSION`, сессию необходимо открыть повторно. Если операция не изменяла данные, она может остаться открытой. Поэтому дальнейшую работу с сессией приложение контролирует само.

Вызовы, которые изменяют данные капчи, выполняются до отправки HTTP-заголовков. Длительные внешние операции лучше запускать после завершения работы с сессией. Сбой хранения вызывает `NativeSessionStorageException` и не считается неверным ответом пользователя.

Для другого способа хранения можно подключить собственную реализацию `ChallengeStore`; требования к ней кратко описаны в [справочнике API](../reference/api.md#расширение-пакета).

## Обработка результатов

| Результат | Значение | Действие приложения |
| --- | --- | --- |
| `Issued`, `Refreshed` | Капча создана или обновлена | Показать новые данные |
| `CapacityExceeded` | Достигнут лимит одновременно активных капч | Обработать отказ в создании |
| `Accepted` | Ответ правильный | Обработать форму |
| `Incorrect` | Ответ неверный, попытки ещё есть | Предложить повторить ввод |
| `AttemptsExhausted` | Попытки закончились | Создать новую капчу |
| `Expired` | Срок действия истёк | Обновить капчу или создать новую |
| `AlreadyConsumed`, `Replaced` | Идентификатор больше не подходит | Не принимать прежний ответ |
| `NotFound` | Капча не найдена или её данные удалены | Создать новую капчу |
| `PurposeMismatch` | Капча относится к другой форме или действию | Отклонить проверку |

Пакет возвращает перечисленные значения, а HTTP-коды и сообщения пользователю определяет приложение. Все коды и типы результатов перечислены в [справочнике API](../reference/api.md#verify).

## Что делает приложение

Human Gate проверяет капчу. Само приложение отвечает за форму, HTML-интерфейс, проверку прав пользователя, защиту от поддельных запросов (CSRF), экранирование данных и ограничение частоты запросов. Если обращение ограничивается по IP-адресу, приложение самостоятельно определяет адрес клиента с учётом доверенных прокси. Эти вопросы подробнее описаны в [модели безопасности](../security-model.md).

Для визуальной капчи приложение предусматривает [доступный способ проверки](../accessibility.md) для пользователя, который не может прочитать символы или различить значки на изображении.

## Частые ошибки

- Вызывать `ActiveNativeSessionScope::capture()` до `session_start()`.
- Пытаться сохранить данные в `$_SESSION`, не открыв сессию повторно после её закрытия.
- Не регистрировать стратегию для вида капчи, который ещё может находиться в сессии.
- Не передавать `ChallengeKind` для выбора карточек или последовательности значков.
- Получать `Purpose` из формы вместо определения его на сервере.
- Отправлять подписи карточек вместо токенов или сортировать токены `IconSequence`.
- Продолжать обработку формы при ошибке хранилища или ответе, отличном от `Accepted`.

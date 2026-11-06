# Интеграция Human Gate

Human Gate создаёт CAPTCHA и проверяет ответы. Приложение решает, где показать задание, как получить ответ пользователя и какое действие выполнить после успешной проверки.

## Как устроена проверка

Сначала приложение создаёт задание через [`ChallengeService::issue()`](../reference/api.md#issue). Оно получает идентификатор и данные для отображения. Браузер показывает задание и передаёт введённый ответ вместе с идентификатором обратно на сервер.

Перед выполнением защищаемого действия приложение вызывает [`ChallengeService::verify()`](../reference/api.md#verify). Если метод возвращает `VerificationCode::Accepted`, действие продолжается. Ответы проверяются на сервере; браузеру не передаётся правильное решение.

## Подключение библиотеки

Объект `ChallengeService` собирается в месте настройки зависимостей приложения. Он получает хранилище заданий, правила их действия, часы и стратегии поддерживаемых видов CAPTCHA.

Ниже показан пример для встроенных сессий PHP. Категории вымышлены и служат только примером. Функция вызывается **после открытия сессии** в каждом запросе, где она нужна.

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

В примере зарегистрированы все три стратегии. Это важно, если в одной сессии могут существовать задания разных типов: при проверке сервис должен знать стратегию, которая создала соответствующее задание.

## Создание задания

В запросе выдачи приложение открывает сессию, собирает сервис и выбирает тип задания. Например, для [`CategorySelection`](../challenges/category-selection.md):

```php
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);

if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать задание CAPTCHA.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

`IssueResult` содержит код результата и новое задание либо `null` при отказе. У созданного задания есть идентификатор, тип, время окончания и данные для интерфейса. Сервер задаёт [`Purpose`](../reference/api.md#основные-типы) для защищаемого действия, например `contact.send`. Он не должен зависеть от значения поля, присланного браузером.

Метод [`issue()`](../reference/api.md#issue) без аргумента типа создаёт [`TextImage`](../challenges/text-image.md). Для [`CategorySelection`](../challenges/category-selection.md) и [`IconSequence`](../challenges/icon-sequence.md) тип нужно указать явно.

## Отображение задания

Каждый тип CAPTCHA возвращает данные своего представления. Приложение использует их для создания интерфейса:

| Вид проверки | Что получает приложение | Что отправляет пользователь |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image.md) | Изображение `ImagePresentation` с типом, байтами и размерами | Строку с символами |
| [`CategorySelection`](../challenges/category-selection.md) | `CategorySelectionPresentation`: категория и карточки с текстом и токенами | Токены выбранных карточек, разделённые запятыми |
| [`IconSequence`](../challenges/icon-sequence.md) | `IconSequencePresentation`: целевая картинка и варианты значков с токенами | Токены в порядке выбора |

`ImagePresentation::bytes` содержит байты изображения, а не текст в кодировке base64. Способ доставки изображения и HTML-разметку определяет приложение. Текстовые подписи категорий и карточек экранируются при выводе в HTML.

Правильный ответ, сведения о правильных вариантах и серверные данные проверки в браузер не отправляются.

## Проверка ответа

Ответ обычно приходит в другом запросе. Приложение снова открывает ту же пользовательскую сессию и создаёт новый объект сервиса. Названия полей `challengeId` и `answer` в примере выбраны приложением, а не заданы библиотекой.

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

Проверка формата идентификатора и размера входящего запроса выполняется в обработчике приложения. Передаваемый в `verify()` ответ не нужно предварительно приводить к верхнему регистру, сортировать или исправлять: правила для каждого вида CAPTCHA применяет сама библиотека.

При `VerificationCode::Accepted` приложение выполняет защищаемое действие. Если, например, отправка письма затем не удалась, тот же ответ CAPTCHA больше не подходит: повторную отправку и обработку такого сбоя должно предусмотреть приложение.

## Обновление задания

Для обновления используется [`ChallengeService::replace()`](../reference/api.md#replace). Приложение получает новое задание и заменяет им старое. Для `CategorySelection` вызов выглядит так:

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

После успешной замены приложение показывает новый идентификатор и новые данные, очищает прежний ответ и выбранные варианты. Сохранённое истёкшее задание также может допускать обновление; после удаления старой записи сервис возвращает `NotFound`. Подробности приведены в [описании жизненного цикла](../reference/lifecycle.md).

## Хранение в сессии PHP

Поставляемый адаптер [`NativeSessionChallengeStore`](../reference/api.md#хранилище-и-время) работает со встроенными сессиями PHP. Приложение сначала настраивает и открывает сессию через `session_start()`, затем вызывает `ActiveNativeSessionScope::capture()` и передаёт результат в хранилище.

Поддерживается обработчик сессий `files` и форматы сериализации `php` или `php_serialize`. Установленная область работы с сессией рассчитана на одну операцию изменения данных библиотеки: не следует повторно использовать тот же объект после закрытия сессии.

После изменения данных адаптер сохраняет их, закрывает сессию и проверяет результат записи. Если сессия закрылась, последующие изменения `$_SESSION` не сохранятся автоматически: приложение должно открыть её снова. Если данные задания не менялись, сессия может остаться открытой.

Вызовы, изменяющие сессию, выполняются до отправки HTTP-заголовков. Длительную работу, например внешние обращения, лучше начинать после завершения операций с сессией. При ошибке хранения возникает `NativeSessionStorageException`; защищаемое действие не выполняется.

## Обработка результатов

| Результат | Что означает | Типичное действие приложения |
| --- | --- | --- |
| `Issued` или `Refreshed` | Создано задание | Показать полученные данные |
| `CapacityExceeded` | Достигнут лимит действующих заданий | Обработать невозможность выдачи |
| `Accepted` | Ответ правильный | Выполнить защищаемое действие |
| `Incorrect` | Ответ неверный, попытки ещё есть | Предложить повторить ввод |
| `AttemptsExhausted` | Попытки закончились | Создать новое задание |
| `Expired` | Время задания истекло | Обновить задание или создать новое |
| `AlreadyConsumed` или `Replaced` | Старый идентификатор больше не подходит | Не принимать прежний ответ |
| `NotFound` | Задание не найдено либо запись уже удалена | Создать новое задание |
| `PurposeMismatch` | Задание относится к другому действию | Отклонить запрос |

Эти результаты возвращает библиотека; HTTP-коды и сообщения для пользователя определяет приложение. Точные значения и исключения описаны в [справочнике API](../reference/api.md).

## Что остаётся на стороне приложения

Библиотека не создаёт HTML, CSS и JavaScript, не обрабатывает HTTP-запросы и не определяет пользователя. Приложение отвечает за CSRF, авторизацию, безопасный вывод текста, ограничение частоты обращений, сохранение результатов защищаемого действия и [доступный альтернативный способ проверки](../accessibility.md).

Если запросы ограничиваются по IP-адресу, приложение само определяет адрес клиента с учётом доверенных прокси. Подробнее эта граница описана в [модели безопасности](../security-model.md).

## Частые ошибки

- Открывать сессию после `ActiveNativeSessionScope::capture()`, а не до него.
- Использовать уже закрытую сессию для записи в `$_SESSION`, не открыв её повторно.
- Регистрировать только одну стратегию, хотя остаются действующие задания других типов.
- Не указывать тип при выдаче или обновлении `CategorySelection` и `IconSequence`.
- Принимать значение `Purpose` из браузера вместо серверной настройки.
- Передавать подписи вместо токенов или менять порядок токенов `IconSequence`.
- Считать ошибку хранилища обычным неверным ответом.

Полный список доступных методов, типов и ошибок приведён в [справочнике API](../reference/api.md).

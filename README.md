# Human Gate

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fhuman--gate-blue.svg?style=flat-square)](https://github.com/yaleksandr89/human-gate)
[![CI](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

Библиотека для создания и проверки CAPTCHA в приложениях на PHP. Помогает защитить формы и другие действия от автоматических запросов без привязки к конкретному фреймворку.

Human Gate создаёт задания, проверяет ответы и хранит сведения о проверках на сервере. Приложение самостоятельно оформляет задания и решает, какое действие выполнить после успешной проверки.

## Для чего нужен пакет

Проверка CAPTCHA может использоваться перед отправкой формы, регистрацией или другим действием, для которого важно сократить число автоматических обращений. Правильность ответа определяет сервер, а не код в браузере.

## Возможности

- Три вида CAPTCHA: [ввод символов с изображения](docs/challenges/text-image.md), [выбор карточек по категории](docs/challenges/category-selection.md) и [повторение порядка значков](docs/challenges/icon-sequence.md).
- Настраиваемые срок действия задания, количество попыток и ограничения на число одновременно созданных проверок.
- Обновление задания и защита от повторного использования уже принятого ответа.
- Работа без привязки к фреймворку; готовое [хранилище на основе сессий PHP](docs/guides/integration.md#хранение-в-сессии-php).
- Типизированные данные для интерфейса без дополнительных библиотек, отвечающих за HTML, CSS и JavaScript.

## Требования

- PHP `^8.4`.
- Расширение `ext-gd`; для текстовых изображений необходима поддержка FreeType в GD.
- Composer для подключения пакета.

## Установка

```bash
composer require yaleksandr89/human-gate
```

## Быстрый старт

Human Gate поддерживает три вида проверок. Примеры ниже показывают их создание средствами PHP. Для каждого примера предполагается, что Composer уже подключил пакет, а приложение использует встроенные сессии PHP.

### Текстовая CAPTCHA (`TextImage`)

Пользователь вводит шесть символов, показанных на изображении. По умолчанию регистр букв не учитывается.

<details>
<summary>Показать PHP-пример создания текстовой CAPTCHA</summary>

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require 'vendor/autoload.php';

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = new ChallengeService(
    new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
    new Policy(),
    new SystemClock(),
    new TextImageStrategy(new GdTextImageRenderer()),
);

$result = $service->issue(new Purpose('contact.send'));
if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать задание CAPTCHA.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

Для текстовой CAPTCHA настраиваются чувствительность к регистру, масштаб изображения, размытие, шум и поворот символов. [Все параметры `TextImage`](docs/challenges/text-image.md) приведены в отдельном руководстве.

### Выбор карточек по категории (`CategorySelection`)

Пользователь выбирает все карточки, относящиеся к заданной категории. Названия категорий и карточек определяет приложение; порядок выбора не влияет на результат.

<details>
<summary>Показать PHP-пример создания CAPTCHA с выбором карточек</summary>

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;

require 'vendor/autoload.php';

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = new ChallengeService(
    new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
    new Policy(),
    new SystemClock(),
    new CategorySelectionStrategy([
        new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
        new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
    ]),
);

$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);
if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать задание CAPTCHA.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

Количество карточек, число правильных вариантов и содержание категорий можно менять. [Настройки `CategorySelection`](docs/challenges/category-selection.md) описаны с правилами составления каталога.

### Повторение порядка значков (`IconSequence`)

Пользователь выбирает значки в том же порядке, в котором они показаны на изображении. Здесь порядок выбора важен.

<details>
<summary>Показать PHP-пример создания CAPTCHA с последовательностью значков</summary>

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\Challenge\Purpose;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;

require 'vendor/autoload.php';

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = new ChallengeService(
    new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
    new Policy(),
    new SystemClock(),
    new IconSequenceStrategy(),
);

$result = $service->issue(new Purpose('contact.send'), ChallengeKind::IconSequence);
if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать задание CAPTCHA.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

Для `IconSequence` можно изменить длину последовательности, количество значков, их размер, размытие, шум и поворот. [Описание настроек `IconSequence`](docs/challenges/icon-sequence.md) содержит допустимые значения.

Срок действия любого задания настраивается через [`Policy::ttlSeconds`](docs/reference/api.md#policy); по умолчанию это 180 секунд. Создание проверки, отправка ответа и его проверка выполняются в разных запросах. [Руководство по интеграции](docs/guides/integration.md) показывает полный порядок работы, включая [`ChallengeService::verify()`](docs/reference/api.md#verify).

Human Gate отвечает за создание, проверку и хранение заданий. Оформление, передача данных в браузер и обработка действий пользователя остаются на стороне приложения, поэтому библиотека не навязывает дополнительные зависимости для интерфейса.

## Примеры оформления

Ниже показан один из вариантов интерфейса для трёх видов CAPTCHA. Это примеры оформления приложения, а не готовые виджеты библиотеки.

### Текстовая CAPTCHA

<img src="docs/images/examples/text-image-light-desktop.png" alt="Текстовая CAPTCHA в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/text-image-dark-desktop.png" alt="Текстовая CAPTCHA в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/text-image-light-mobile.png" alt="Текстовая CAPTCHA в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/text-image-dark-mobile.png" alt="Текстовая CAPTCHA в тёмной теме на телефоне" width="360">

</details>

### Выбор карточек по категории

<img src="docs/images/examples/category-selection-light-desktop.png" alt="CAPTCHA с выбором карточек в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/category-selection-dark-desktop.png" alt="CAPTCHA с выбором карточек в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/category-selection-light-mobile.png" alt="CAPTCHA с выбором карточек в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/category-selection-dark-mobile.png" alt="CAPTCHA с выбором карточек в тёмной теме на телефоне" width="360">

</details>

### Повторение порядка значков

<img src="docs/images/examples/icon-sequence-light-desktop.png" alt="CAPTCHA с последовательностью значков в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/icon-sequence-dark-desktop.png" alt="CAPTCHA с последовательностью значков в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/icon-sequence-light-mobile.png" alt="CAPTCHA с последовательностью значков в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/icon-sequence-dark-mobile.png" alt="CAPTCHA с последовательностью значков в тёмной теме на телефоне" width="360">

</details>

Один из примеров использования можно посмотреть на [alexanderyurchenko.ru](https://alexanderyurchenko.ru/). Внешний вид и пользовательский интерфейс на этом сайте разработаны отдельно от библиотеки.

## Жизненный цикл проверки

Задание действительно ограниченное время. По умолчанию оно действует три минуты и допускает до трёх неверных ответов. После правильного ответа задание считается использованным: повторно пройти проверку с тем же идентификатором нельзя. [Подробнее о сроках, попытках и обновлении заданий](docs/reference/lifecycle.md).

## Безопасность и доступность

Ответ проверяется на сервере. Приложение самостоятельно отвечает за авторизацию, защиту от поддельных запросов и ограничение частоты обращений. [Подробнее о безопасности](docs/security-model.md).

Для пользователей, которым не подходят визуальные задания, приложение предусматривает альтернативный способ продолжить действие. [Рекомендации по доступности](docs/accessibility.md).

## Обратная связь

- воспроизводимые ошибки — [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues).

---

<p align="center">
  Если пакет оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>

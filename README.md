# Human Gate

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fhuman--gate-blue.svg?style=flat-square)](https://github.com/yaleksandr89/human-gate)
[![CI](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

Composer-пакет для создания капчи и проверки ответов в PHP-приложениях. Подходит для форм регистрации, обратной связи и отправки комментариев, где нужно сократить количество автоматических отправок.

Капча создаётся и проверяется на сервере без обращения к внешним сервисам. Пакет можно использовать с любым PHP-фреймворком или без него.

## Для чего нужен пакет

Перед обработкой формы приложение показывает капчу. Пользователь вводит ответ, пакет проверяет его на сервере, и при правильном ответе приложение продолжает обработку формы. Повторно использовать пройденную капчу нельзя.

## Возможности

- Три вида капчи:
  - [**Текстовая капча (`TextImage`)**](docs/challenges/text-image.md): ввод символов с изображения.
  - [**Выбор карточек (`CategorySelection`)**](docs/challenges/category-selection.md): поиск карточек нужной категории.
  - [**Последовательность значков (`IconSequence`)**](docs/challenges/icon-sequence.md): повторение порядка значков с изображения.
- Настройка срока действия капчи, числа неверных попыток и количества одновременно активных капч.
- Обновление капчи и защита от повторного использования правильного ответа.
- Готовое [хранилище на основе стандартных сессий PHP](docs/guides/integration.md#хранение-в-сессии-php).
- Реализация на PHP без сторонних Composer-зависимостей и внешних сервисов проверки. Внешний вид капчи задаётся самим приложением, поэтому пакет не навязывает оформление или средства разработки интерфейса.

## Требования

- `PHP ^8.4`.
- Расширение `ext-gd` с поддержкой FreeType.
- Composer.

## Установка

```bash
composer require yaleksandr89/human-gate
```

## Быстрый старт

Ниже три самостоятельных PHP-примера создания капчи. В них используется обычная сессия PHP: каждый пример сам открывает её через `session_start()` и сохраняет в ней данные проверки. Другой способ хранения можно подключить через `ChallengeStore`, как показано в [руководстве по интеграции](docs/guides/integration.md).

Скриншоты сделаны на [alexanderyurchenko.ru](https://alexanderyurchenko.ru/) и показывают один из вариантов оформления. Сам пакет отвечает за создание капчи и проверку ответов, а не за HTML-интерфейс.

### Текстовая капча (`TextImage`)

Пользователь вводит шесть символов с изображения. Регистр букв по умолчанию не учитывается.

<details>
<summary>Показать PHP-пример создания текстовой капчи</summary>

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
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

#### Пример оформления текстовой капчи на сайте

<img src="docs/images/examples/text-image-light-desktop.png" alt="Текстовая капча в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/text-image-dark-desktop.png" alt="Текстовая капча в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/text-image-light-mobile.png" alt="Текстовая капча в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/text-image-dark-mobile.png" alt="Текстовая капча в тёмной теме на телефоне" width="360">

</details>

Можно менять регистр, масштаб изображения, размытие, шум и поворот символов. Длина ответа всегда составляет шесть символов. [Параметры текстовой капчи](docs/challenges/text-image.md) описаны отдельно.

### Выбор карточек по категории (`CategorySelection`)

Пользователь выбирает все карточки нужной категории. Категории и названия карточек задаёт приложение, порядок выбора значения не имеет.

<details>
<summary>Показать PHP-пример создания капчи с выбором карточек</summary>

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
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

#### Пример оформления капчи с выбором карточек на сайте

<img src="docs/images/examples/category-selection-light-desktop.png" alt="Капча с выбором карточек в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/category-selection-dark-desktop.png" alt="Капча с выбором карточек в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/category-selection-light-mobile.png" alt="Капча с выбором карточек в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/category-selection-dark-mobile.png" alt="Капча с выбором карточек в тёмной теме на телефоне" width="360">

</details>

Настраиваются количество карточек и число правильных вариантов; список категорий задаёт приложение. [Подробности настройки выбора карточек](docs/challenges/category-selection.md).

### Последовательность значков (`IconSequence`)

Пользователь выбирает значки в том порядке, в котором они показаны на изображении. Здесь порядок выбора важен.

<details>
<summary>Показать PHP-пример создания капчи с последовательностью значков</summary>

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
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

</details>

#### Пример оформления капчи с последовательностью значков на сайте

<img src="docs/images/examples/icon-sequence-light-desktop.png" alt="Капча с последовательностью значков в светлой теме на компьютере" width="720">

<details>
<summary>Другие варианты: тёмная тема и мобильный экран</summary>

Тёмная тема на компьютере:

<img src="docs/images/examples/icon-sequence-dark-desktop.png" alt="Капча с последовательностью значков в тёмной теме на компьютере" width="720">

Светлая тема на телефоне:

<img src="docs/images/examples/icon-sequence-light-mobile.png" alt="Капча с последовательностью значков в светлой теме на телефоне" width="360">

Тёмная тема на телефоне:

<img src="docs/images/examples/icon-sequence-dark-mobile.png" alt="Капча с последовательностью значков в тёмной теме на телефоне" width="360">

</details>

Можно менять длину последовательности, число вариантов, размер значков, размытие, шум и поворот. [Настройки последовательности значков](docs/challenges/icon-sequence.md).

Все виды капчи используют общие [настройки срока действия и числа попыток](docs/reference/api.md#policy). По умолчанию капча действует 180 секунд; время можно изменить через `Policy::ttlSeconds`.

Примеры выше показывают создание капчи. Полученные идентификатор и данные изображения или карточек приложение использует для показа проверки в своей форме. Отправка ответа, вызов [`ChallengeService::verify()`](docs/reference/api.md#verify) и обновление капчи показаны в [руководстве по интеграции](docs/guides/integration.md).

## Жизненный цикл капчи

По умолчанию капча действует три минуты, а после третьего неверного ответа попытки заканчиваются. Правильный ответ принимается один раз: повторно пройти проверку с тем же идентификатором нельзя. Подробнее об [истечении срока, попытках и обновлении капчи](docs/reference/lifecycle.md).

## Безопасность и доступность

Ответ проверяется на сервере. Пакет помогает защитить формы от автоматических отправок, а проверку прав пользователя, защиту от поддельных запросов и ограничение частоты обращений обеспечивает приложение. Подробнее в [модели безопасности](docs/security-model.md).

Для людей, которым трудно выполнить визуальную проверку, приложение предусматривает другой доступный способ продолжить действие. [Рекомендации по доступности](docs/accessibility.md).

## Обратная связь

- Воспроизводимые ошибки — [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues).

---

<p align="center">
  Если пакет оказался полезен, поставьте звезду на GitHub — так его будет проще найти другим разработчикам. 🤘
</p>

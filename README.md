# Human Gate

[![Source Code](https://img.shields.io/badge/source-yaleksandr89%2Fhuman--gate-blue.svg?style=flat-square)](https://github.com/yaleksandr89/human-gate)
[![CI](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml/badge.svg)](https://github.com/yaleksandr89/human-gate/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)

Human Gate — лёгкая first-party библиотека CAPTCHA и anti-bot проверок для PHP. Ядро не зависит от фреймворка, а авторитетное состояние проверки хранится на сервере приложения.

Пакет создаёт задания TextImage, CategorySelection и IconSequence, проверяет ответы и управляет их жизненным циклом. Приложение получает типизированные данные представления и строит свой интерфейс.

## Для чего нужен пакет

Добавьте проверку перед отправкой формы или другим защищаемым действием. Приложение выбирает назначение проверки на сервере и выполняет действие только после `VerificationCode::Accepted` в том же запросе или потоке обработки.

## Возможности

- Три типа заданий: текст на изображении, выбор категории и последовательность значков.
- Привязка к серверному `Purpose`, TTL, лимит неверных ответов и одноразовое принятие ответа.
- Замена задания с новым ID и атомарное изменение состояния через `ChallengeStore`.
- Готовый адаптер для native PHP session и часы `SystemClock`.
- Типизированные `Presentation`, без готовых HTML-виджетов и сторонних Composer-библиотек в runtime.

## Требования

- PHP `^8.4`; целевая версия разработки — PHP 8.5.
- `ext-gd`; для TextImage нужны возможности GD/FreeType `imagettf*`.
- Для поставляемого session adapter — активная native `files` session с serializer `php` или `php_serialize`.

Подробности окружения и владения session описаны в [руководстве интеграции](docs/guides/integration.md).

## Установка

Пакет ещё не создан на Packagist, стабильный релиз не опубликован. После публикации установка будет выполняться командой:

```bash
composer require yaleksandr89/human-gate
```

Для текущего исходного дерева приложение должно отдельно настроить получение пакета через Composer. Примеры ниже предполагают доступный Composer autoload.

## Быстрый старт

Выдача TextImage в одном запросе. Приложение настраивает и открывает session до захвата scope и до вывода ответа:

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
    throw new RuntimeException('Не удалось открыть session приложения.');
}

$scope = ActiveNativeSessionScope::capture();
$service = new ChallengeService(
    new NativeSessionChallengeStore($scope),
    new Policy(),
    new SystemClock(),
    new TextImageStrategy(new GdTextImageRenderer()),
);

$result = $service->issue(new Purpose('contact.send'));
if ($result->challenge !== null) {
    $challenge = $result->challenge;
    $id = $challenge->id->value();
    $presentation = $challenge->presentation;
}
```

При отсутствии `challenge` обработайте `$result->code`. После реального изменения состояния adapter фиксирует и закрывает session: дальнейшие записи в `$_SESSION` требуют управления session со стороны приложения.

В следующем запросе заново откройте session, захватите scope и соберите сервис. Передайте ID и ответ в `verify()` с тем же серверным `Purpose`. Полный пример, отображение данных и обработка результатов — в [руководстве интеграции](docs/guides/integration.md).

## Типы проверок

### TextImage

Пользователь вводит шесть символов с PNG-изображения. По умолчанию регистр не учитывается. [Настройки ответа и отрисовки](docs/challenges/text-image.md).

### CategorySelection

Пользователь выбирает все карточки целевой категории. Каталог задаёт приложение; порядок выбранных токенов не важен. [Каталог, представление и формат ответа](docs/challenges/category-selection.md).

### IconSequence

Пользователь повторяет порядок значков с целевого изображения. Порядок выбранных токенов значим. [Настройки задания и изображения](docs/challenges/icon-sequence.md).

## Примеры интерфейса

Скриншоты показывают один из вариантов интерфейса приложения в светлой и тёмной темах, в настольной и мобильной компоновках. Human Gate предоставляет типизированные `Presentation`, а HTML/CSS/JS-интерфейс строит приложение: готовые виджеты в пакет не входят.

### TextImage

<img src="docs/images/examples/text-image-light-desktop.png" alt="Интерфейс TextImage в светлой теме: настольная компоновка с изображением задания и полем ввода" width="720">

<details>
<summary>TextImage: тёмная тема и мобильные компоновки</summary>

Тёмная тема, настольная компоновка:

<img src="docs/images/examples/text-image-dark-desktop.png" alt="Интерфейс TextImage в тёмной теме, настольная компоновка" width="720">

Светлая тема, мобильная компоновка:

<img src="docs/images/examples/text-image-light-mobile.png" alt="Интерфейс TextImage в светлой теме, мобильная компоновка" width="360">

Тёмная тема, мобильная компоновка:

<img src="docs/images/examples/text-image-dark-mobile.png" alt="Интерфейс TextImage в тёмной теме, мобильная компоновка" width="360">

</details>

### CategorySelection

<img src="docs/images/examples/category-selection-light-desktop.png" alt="Интерфейс CategorySelection в светлой теме: настольная компоновка с карточками для выбора" width="720">

<details>
<summary>CategorySelection: тёмная тема и мобильные компоновки</summary>

Тёмная тема, настольная компоновка:

<img src="docs/images/examples/category-selection-dark-desktop.png" alt="Интерфейс CategorySelection в тёмной теме, настольная компоновка" width="720">

Светлая тема, мобильная компоновка:

<img src="docs/images/examples/category-selection-light-mobile.png" alt="Интерфейс CategorySelection в светлой теме, мобильная компоновка" width="360">

Тёмная тема, мобильная компоновка:

<img src="docs/images/examples/category-selection-dark-mobile.png" alt="Интерфейс CategorySelection в тёмной теме, мобильная компоновка" width="360">

</details>

### IconSequence

<img src="docs/images/examples/icon-sequence-light-desktop.png" alt="Интерфейс IconSequence в светлой теме: настольная компоновка с изображением задания и вариантами выбора" width="720">

<details>
<summary>IconSequence: тёмная тема и мобильные компоновки</summary>

Тёмная тема, настольная компоновка:

<img src="docs/images/examples/icon-sequence-dark-desktop.png" alt="Интерфейс IconSequence в тёмной теме, настольная компоновка" width="720">

Светлая тема, мобильная компоновка:

<img src="docs/images/examples/icon-sequence-light-mobile.png" alt="Интерфейс IconSequence в светлой теме, мобильная компоновка" width="360">

Тёмная тема, мобильная компоновка:

<img src="docs/images/examples/icon-sequence-dark-mobile.png" alt="Интерфейс IconSequence в тёмной теме, мобильная компоновка" width="360">

</details>

## Жизненный цикл

По умолчанию проверка действует 180 секунд, а третий неверный ответ исчерпывает попытки. Успешная проверка потребляет задание; повтор при сохранённой терминальной записи возвращает `AlreadyConsumed`. После удаления записи старый ID возвращает `NotFound`. [Policy, замена, истечение срока и хранение записей](docs/reference/lifecycle.md).

## Безопасность и доступность

CAPTCHA — дополнительный барьер в защите приложения. Авторизация, CSRF, ограничение частоты запросов и защищаемое действие принадлежат приложению. [Модель безопасности](docs/security-model.md) описывает границу доверия и серверное состояние.

Для визуальных заданий приложение предоставляет альтернативный путь. Human Gate выдаёт данные, а доступный интерфейс строит приложение. [Рекомендации по доступности](docs/accessibility.md).

## Живой пример

[alexanderyurchenko.ru](https://alexanderyurchenko.ru/) — реальный пример интеграции потребителя. Это пример использования, без утверждения, что на сайте развёрнуты все текущие возможности пакета.

## Обратная связь

Воспроизводимые ошибки отправляйте в [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues), указывая окружение, шаги и ожидаемое поведение.

---

<p align="center">Если пакет полезен, поддержите его ⭐ на <a href="https://github.com/yaleksandr89/human-gate">GitHub</a>.</p>

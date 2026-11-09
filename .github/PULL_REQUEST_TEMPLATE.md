## Что изменено / What changed

<!-- Опишите проблему и внесённые изменения. / Describe the problem and the changes made. -->

## Как проверено / How verified

<!-- Перечислите реально выполненные команды, тесты и проверки. / List the commands, tests, and checks actually run. -->

## Публичный API и поведение капчи / Public API and CAPTCHA behavior

<!-- Укажите влияние на issue/replace/verify, Purpose, Policy, форматы ответов, данные капчи и PHP-сессии. Если влияния нет, так и напишите. / Describe the impact on issue/replace/verify, Purpose, Policy, answer formats, presentation data, and PHP sessions. State explicitly when there is no impact. -->

## Контрольный список / Checklist

- [ ] Изменения ограничены заявленной задачей; посторонние файлы не затронуты. / Changes stay within the agreed scope; unrelated files are untouched.
- [ ] Нет реальных данных сессий, ответов пользователей, секретов или другой приватной информации. / No real session data, user answers, secrets, or other private information are included.
- [ ] Для изменения поведения добавлены или обновлены соответствующие тесты. / Relevant tests were added or updated when behavior changed.
- [ ] `composer check` пройден, если изменены PHP-код, зависимости, тесты или настройки проверок; иначе указаны выполненные проверки. / `composer check` passed for PHP, dependency, test, or tooling changes; otherwise the checks actually run are listed.
- [ ] При изменении публичного API оценено влияние на совместимость и выпуск пакета. / Public API compatibility and release impact were considered when applicable.
- [ ] Документация и переводы синхронизированы, если изменилось описанное поведение. / Documentation and translations are synchronized when documented behavior changed.
- [ ] При изменении `SECURITY.md` или `CONTRIBUTING.md` обновлены соответствующие переводы. / Matching translations were updated if `SECURITY.md` or `CONTRIBUTING.md` changed.

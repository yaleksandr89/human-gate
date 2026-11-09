# Contributing

## Choose a language

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/blob/master/.github/CONTRIBUTING.md) | **Selected** | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Thank you for helping improve Human Gate. This Composer package generates and verifies three types of CAPTCHA in PHP: `TextImage`, `CategorySelection`, and `IconSequence`. Bug fixes and improvements are welcome when they make the public API clearer or CAPTCHA verification more reliable.

## Before you start

- Report reproducible bugs through [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues/new/choose).
- Use the feature request form for suggestions. Explain the problem and desired behavior before discussing an implementation.
- Report potential vulnerabilities according to the [security policy](https://github.com/yaleksandr89/human-gate/security/policy); do not publish bypass instructions in a public Issue.
- Discuss significant changes to the public API, expiration rules, verification semantics, or answer format in an Issue first.

## Package behavior to preserve

- The package supports `PHP ^8.4`, requires `ext-gd`, and has no third-party Composer runtime libraries.
- Answers are verified on the server. Each CAPTCHA is tied to a form or operation key (`Purpose`).
- A successful CAPTCHA cannot be reused. The `Policy` settings limit expiration time and incorrect attempts.
- [`TextImage`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/text-image_en.md) checks image characters; [`CategorySelection`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/category-selection_en.md) accepts a set of selected cards regardless of order; [`IconSequence`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/icon-sequence_en.md) checks icon order.
- The package generates presentation data and verifies answers; HTML rendering is handled by the application.
- Changes should respect the current architecture and avoid unnecessary dependencies or public features.

Usage details and method signatures are in the [API reference](https://github.com/yaleksandr89/human-gate/blob/master/docs/reference/api_en.md).

## Branches and commits

Use a separate branch whose name reflects the change, for example:

```text
fix/icon-sequence-verification
feat/text-image-rendering
docs/integration-example
```

Use Conventional Commits with a Russian description after the prefix:

```text
fix: исправить повторную проверку капчи
feat: добавить настройку отображения
test: добавить проверку обновления капчи
docs: уточнить интеграцию с сессией PHP
```

Each commit should contain one coherent change without unrelated refactoring. Explain the reason for your PR, its effect on the API, and the checks actually performed.

## Local checks

Install dependencies and run the full check suite:

```bash
composer install
composer check
```

Individual checks are also available:

```bash
composer test
composer analyse
composer cs:check
```

For Markdown-only changes, check links and run `git diff --check`. For PHP changes, run the relevant tests, style checks, and static analysis. Coverage is a diagnostic tool, not a target percentage.

## Tests and examples

- Test successful and failure cases, especially expiration, attempt limits, replay, refresh, and session failures.
- Use synthetic answers, categories, identifiers, and session data. Never commit real user data.
- Examples must match the current public API and work without undocumented setup.
- If behavior changes, update the Russian documentation and corresponding translations together.

## Pull Request

Before submitting a PR, check that it is limited to the proposed change, tests cover updated behavior, and documentation matches the code. Use the PR template; the maintainer may request clarification or additional checks.

# AGENTS.md

## Назначение

Этот файл — постоянный repository-level contract для Codex и других coding agents, работающих с `yaleksandr89/human-gate`.

Он применяется ко всему дереву репозитория. Отдельный `tests/AGENTS.md` по умолчанию не нужен: правила production-кода и tests достаточно согласованы. Nested `AGENTS.md` добавлять только если у конкретного subtree появятся действительно отличающиеся правила, которые нельзя ясно выразить здесь.

Прямые system/developer/user instructions и текущая явно согласованная задача имеют приоритет над этим файлом.

## Repository

```text
package: yaleksandr89/human-gate
namespace: Yaleksandr\HumanGate\
PHP support floor: ^8.4
development target: PHP 8.5
architecture: lightweight hexagonal / Ports & Adapters
runtime dependencies target: zero third-party Composer runtime libraries
visual challenges: ext-gd
generated development state: .build/
default branch: master
remote mutation policy: READ_ONLY_FOR_AI
```

## Главный принцип работы

Делай **чистую целевую реализацию**, а не слой археологии вокруг вчерашнего незарелизованного кода.

Не увеличивай сложность ради гипотетической совместимости, несуществующих consumers или «на всякий случай».

Один coherent engineering goal должен реализовываться одним bounded batch. `Minimal diff` означает отсутствие unrelated изменений, а не минимум changed files.

Перед write:

1. прочитай затрагиваемый код и его реальные contracts;
2. проверь applicable repository instructions;
3. зафиксируй exact scope/allowlist;
4. определи security/runtime/dependency impact;
5. пойми ожидаемый результат и stop conditions.

Не исправляй unrelated проблемы «по пути».

## Backward compatibility / release state

### До публикации на Packagist

Пока пакет **не опубликован на Packagist**, backward compatibility по owner policy **не является обязательным ограничением**, даже если owner использует pre-release package в собственном контролируемом application/сайте для проверки.

В этом состоянии:

- owner-controlled pre-release consumer является validation target, а не compatibility anchor;
- clean target design имеет приоритет над сохранением незарелизованных API;
- допустимо менять собственные текущие public/internal contracts, tests и implementation, если это делает итоговый design правильнее;
- не добавляй legacy aliases;
- не добавляй deprecated wrappers/shims;
- не добавляй migration branches;
- не добавляй dual protocol / dual verification;
- не добавляй fallback path только ради старого незарелизованного поведения;
- не сохраняй старую сигнатуру или структуру только потому, что она уже существует в Git;
- не называй такую совместимость «безопасным default», если она раздувает код без реального consumer requirement.

Если старый тест защищает уже отвергнутый незарелизованный contract, исправь тест вместе с production design, а не строй production workaround вокруг теста.

### После публикации на Packagist

Сам факт публикации **не даёт автоматического решения** «сохранять всё любой ценой».

Если задача касается public API, persisted format, protocol, behavior или migration после публикации:

1. сначала определи фактических consumers/dependents и release state;
2. не добавляй compatibility layer автоматически;
3. не ломай contract автоматически;
4. явно запроси/получи owner decision, нужно ли сохранять backward compatibility, делать migration/deprecation path или выпускать breaking version;
5. только после этого проектируй решение.

Если compatibility decision не определён, **остановись до implementation**, а не генерируй код, который потом придётся откатывать.

## PHP coding style

Authoritative baseline:

- PHP-FIG **PER Coding Style 3.1** — основной нормативный code-style standard;
- **PSR-1** — обязательная базовая спецификация, которую требует PER Coding Style 3.1;
- PSR-12 не является отдельным параллельным baseline: PER Coding Style 3.1 расширяет и заменяет его;
- `.php-cs-fixer.dist.php` — executable enforcement для машинно-проверяемых правил: `@PER-CS`, strict types, ordered/global class imports, no unused imports, `new_expression_parentheses.use_parentheses=false`;
- `phpstan.neon.dist` — PHPStan `max` с PHP 8.4 compatibility floor и deprecation rules.

Если prose-rule, formatter/config и PER Coding Style 3.1 расходятся, не выбирать молча удобный вариант:

1. сначала определить, является ли правило нормативным требованием PER-CS, сознательной owner-deviation или пробелом tooling;
2. без явно согласованной owner-deviation следовать PER Coding Style 3.1;
3. синхронизировать prose и executable config в отдельном согласованном scope;
4. если formatter ещё не умеет новое правило актуальной версии PER-CS, стандарт остаётся нормативным источником, а gap временно контролируется review.

Для нового и изменяемого first-party PHP:

- `declare(strict_types=1);` по умолчанию;
- LF;
- одна финальная newline;
- без closing `?>` в PHP-only files;
- 4 spaces, без tabs;
- без trailing whitespace;
- short arrays `[]`;
- built-in types и keywords в lowercase;
- native types везде, где contract известен;
- не заменяй ясный type на `mixed` без причины;
- strict comparisons по умолчанию;
- избегай визуально плотного clever code ради экономии строк;
- soft line limit около 120 символов, разбивай выражение, когда так яснее.

### Braces и layout

Следовать PER Coding Style 3.1.

Управляющие конструкции всегда используют `{}`:

- `if`;
- `elseif`;
- `else`;
- `for`;
- `foreach`;
- `while`;
- `do`;
- `try`;
- `catch`;
- `finally`.

Не писать:

```php
if ($value === null) return;
```

Для functions/methods:

- если непустой method/function имеет однострочный список параметров, opening brace идёт на следующей строке;
- если список параметров разбит на несколько строк, closing `)` и opening `{` идут вместе на отдельной строке как `) {`;
- если method/function не содержит statements или comments, empty body используется в компактной форме `{}` на той же строке, что и предыдущий символ; это относится и к constructor property promotion.

Корректные примеры:

```php
public function run(): void
{
    doSomething();
}

public function run(
    FirstDependency $first,
    SecondDependency $second,
): void {
    doSomething();
}

public function __construct(private SomeDependency $dependency) {}

public function __construct(
    private FirstDependency $first,
    private SecondDependency $second,
) {}
```

Для многострочных parameter/argument/array lists:

- один элемент на строку, когда список уже многострочный;
- trailing comma;
- closing delimiter на отдельной строке.

Для многострочных boolean conditions операторы ставить в начале continuation line:

```php
if (
    $enabled
    && $requestIsValid
    && $userIsAllowed
) {
    // ...
}
```

Не использовать nested ternary, если обычный `if`/`match` читается яснее.

### Modern PHP

Для support floor PHP 8.4+:

- использовать supported current syntax;
- direct new dereference допустим и предпочтителен, когда он улучшает читаемость: `new Foo()->bar()`;
- global classes по умолчанию импортировать через `use`, если это делает код яснее;
- не писать legacy-compatible syntax под PHP старее declared support floor.

### `final` / `readonly`

Использовать семантически, а не механически.

`final` уместен для leaf classes, которые не являются extension point.

`readonly` уместен для immutable state/config/value objects.

Не создавать inheritance/extensibility point «на будущее» без реального требования.

## Имена и архитектурная читаемость

Identifiers — English:

- namespaces;
- classes;
- interfaces;
- enums;
- methods;
- properties;
- variables;
- constants.

Избегай бессодержательных имён `Manager`, `Helper`, `Utils`, `Common`, `BaseService`, если имя не объясняет responsibility.

Предпочитай:

- explicit dependencies;
- constructor injection;
- explicit exceptions;
- framework-agnostic core;
- minimal dependencies;
- small typed public API.

Не использовать service locator, global registry или скрытый container lookup вместо явной dependency injection без доказанной framework-причины.

Use-site по умолчанию получает application dependency извне и не строит её сам.

Composition root отвечает за concrete implementations и dependency graph.

Stable package ownership boundary:

- Human Gate core владеет challenge lifecycle, strategy contracts, authoritative package state, rendering и typed presentation data;
- host application владеет HTTP/routes/controllers, HTML/CSS/JS, client identity, public endpoint throttling/rate limiting, trusted-proxy/IP policy и initial session start;
- core не должен читать `REMOTE_ADDR`, HTTP globals или выбирать per-IP/per-user abuse keys;
- `Policy::maxActive*` и tombstone limits — lifecycle/state-capacity controls, не request-rate limiting.

Для native-session adapter:

- приложение открывает исходную PHP session; Human Gate не стартует её неявно;
- поддерживаемая concrete boundary основана на native files-session handler и уже принятом serializer contract;
- после успешной mutating operation adapter может оставить session закрытой по своему documented contract; caller не должен молча продолжать менять `$_SESSION`, ожидая автоматического persistence.

DDD/hexagonal/patterns применяй только там, где они снижают реальную сложность. Не создавай interface/factory/strategy/DTO/value object только ради структуры.

## Comments и PHPDoc: EN + RU

Комментарии и PHPDoc добавлять **только когда они несут информацию**, которую не выражает сам код/native types.

Не комментировать очевидное.

Если в first-party PHP появляется meaningful human-readable comment или PHPDoc prose, он должен иметь **семантический parity на двух языках**, English first, Russian second:

```php
/**
 * EN: Preserves the canonical answer case during rendering.
 * RU: Сохраняет регистр канонического ответа при отрисовке.
 */
```

Для нескольких смысловых предложений сохраняй тот же порядок парами `EN:` / `RU:`.

Требования:

- English и Russian должны говорить одно и то же;
- русский должен быть естественным русским, без бессмысленной смеси языков;
- exact API/code identifiers могут оставаться English;
- machine-readable PHPDoc tags/type shapes не дублировать дважды;
- `@param`, `@return`, `@throws`, generics/array shapes пишутся один раз;
- PHPDoc не должен полностью дублировать native signature;
- production comments описывают текущий contract, а не историю разработки и не roadmap.

Пример:

```php
/**
 * EN: Returns the canonical payload accepted by the current protocol.
 * RU: Возвращает каноническую нагрузку, принятую текущим протоколом.
 *
 * @return array<string, scalar|null>
 */
```

## Exceptions и failure semantics

Не использовать одно `false`/`null` для разных failure semantics, если caller должен их различать.

В security/runtime-sensitive flow:

- ordinary negative business result отделять от infrastructure/runtime failure;
- не скрывать infrastructure failure как обычное «неверно»;
- исключения не должны раскрывать secrets;
- сохранять `previous` exception, когда это полезно;
- не подавлять ошибки через `@` без узкой boundary-причины и немедленной проверки результата.

## Strings / bytes / Unicode

Явно различай bytes и user-visible characters.

`strlen()` допустим, когда contract байтовый.

Не вводи Unicode normalization/case-folding/whitespace expansion без явного product contract.

Security-sensitive canonicalization должна быть минимальной, bounded и тестируемой.

## Filesystem / streams

Filesystem operations fallible.

Проверяй результат операций, если failure влияет на contract:

- `fopen`;
- `flock`;
- `fread` / `stream_get_contents`;
- `fwrite`;
- `fflush`;
- `ftruncate`;
- `rename`;
- `unlink`;
- `mkdir`.

Для byte-oriented files/streams предпочитай binary-safe modes.

Не добавляй broad `chmod`/`chown`/delete cleanup без exact scope и ownership reasoning.

## Dependencies

Не добавляй unknown/new dependency по памяти.

Перед добавлением:

1. подтверди exact package name;
2. official/trusted registry/source;
3. maintenance state;
4. PHP compatibility;
5. security/advisory implications;
6. реальную необходимость.

Не обновляй unrelated dependencies «заодно».

## Tests

Tests защищают meaningful package-owned behavior и regressions, а не assertion count/coverage vanity.

Tests должны быть:

- deterministic;
- независимыми от order;
- без real production credentials;
- без неожиданных external network calls;
- с понятными assertions;
- ориентированными на contract, boundary и regression.

Не тестируй только встроенное поведение PHP/library.

Для bugfix по возможности добавляй regression test.

Не загрязняй production API test-only abstraction, если можно изолированно доказать behavior иначе.

`#[TestDox(...)]` для PHP/PHPUnit по умолчанию писать на русском. Test method names и technical identifiers могут оставаться English.

## Deprecated API

Не использовать deprecated API при наличии supported replacement.

Не добавлять suppression только ради зелёного analyzer/IDE.

Если есть сомнение, сверить фактическую установленную версию и официальную документацию/source.

## Security impact

Перед `rw` коротко классифицируй security impact.

Если sensitive boundary не меняется, broad security audit не нужен.

Если меняется:

- проверь только затронутую trust boundary;
- failure/abuse cases;
- bounded input;
- authorization/state ownership;
- secret/logging exposure;
- relevant regression tests;
- stop conditions.

Не превращай OWASP/OpenSSF в ceremonial checklist.

## Git и remote

Без отдельного явного разрешения AI/Codex запрещены:

- commit;
- push;
- merge;
- rebase;
- reset;
- restore;
- clean;
- stash;
- force operations;
- delete branch/tag;
- release;
- production operations.

Remote policy:

`READ_ONLY_FOR_AI`

Remote read/search/verification разрешены.

Remote writes выполняет пользователь.

Не просить production secrets/API keys/tokens/OAuth credentials/production `.env`.

## Human-facing service text

Основной human-facing язык проекта — русский, если task/source не задаёт иной язык.

Technical identifiers — English.

Commit message:

```text
<conventional-type>: <описание на русском>
```

Например:

```text
feat: добавить чувствительность CAPTCHA к регистру
fix: исправить проверку канонического ответа
test: добавить регрессию для повторной проверки
```

PR title и Release title/notes: человеческое описание на русском; technical identifiers не переводить.

## Checks

Established repository commands:

```text
composer test
composer cs:check
composer analyse
composer check
```

`composer check` выполняет Composer validation/audit, style, static analysis и tests. Coverage/generated caches принадлежат `.build/`.

После write:

1. покажи actual diff/result;
2. проверь `git diff --check`;
3. запусти smallest relevant tests/checks;
4. запусти `composer cs:check` и `composer analyse`, если изменённый scope это требует;
5. aggregate `composer check` запускай как final gate для meaningful package change; не повторяй дорогой aggregate без причины;
6. покажи final `git status`;
7. не скрывай skipped/blocked checks.

`composer cs:fix` допустим только внутри approved `rw`, если его фактические изменения остаются в allowlist и не затрагивают unrelated code.

Если repository script/config задаёт authoritative command, используй его вместо придуманной альтернативы.

## Human review

Repository-changing task считается готовым только после review фактического diff.

Вердикт review:

- `ACCEPTED`
- `NOT ACCEPTED`

Известный исправимый in-scope defect нельзя переносить в «потом» и одновременно ставить `ACCEPTED`.

После `ACCEPTED` remote mutation всё равно выполняет пользователь.

## Stop conditions

Остановись и сообщи точную причину, если:

- current branch/HEAD materially отличается от task baseline;
- требуется выйти за approved scope/allowlist;
- обнаружен конфликт applicable instructions;
- требуется новая dependency, не согласованная task contract;
- compatibility decision обязателен, но не определён;
- runtime ownership неизвестен для write-producing action;
- найден security/data-loss blocker;
- выполнение потребовало бы запрещённой remote/destructive operation.

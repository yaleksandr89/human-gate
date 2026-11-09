# 集成 Human Gate

Human Gate 在服务器端创建验证码并校验答案。应用在表单中展示验证码，只有答案正确时才继续处理表单。

## 验证码工作流程

应用调用 [`ChallengeService::issue()`](../reference/api_zh.md#issue)，获得验证码标识符和展示数据，并将展示数据发送给浏览器。

提交表单时，应用取得标识符与用户答案，调用 [`ChallengeService::verify()`](../reference/api_zh.md#verify)。返回 `VerificationCode::Accepted` 表示成功。正确答案和正确选项保留在服务器端。

[`ChallengeService::replace()`](../reference/api_zh.md#replace) 创建具有新标识符的替换验证码。


## 接入包

`ChallengeService` 由存储、公共 [`Policy` 配置](../reference/api_zh.md#policy)、时间源 `SystemClock` 和各验证码策略组成。下面的示例使用标准 PHP 会话注册全部三种类型，类别数据仅用于演示。

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

在 `session_start()` 之后调用此函数。注册全部策略还能校验同一会话中之前创建的不同类型验证码。设置详见 [`TextImage`](../challenges/text-image_zh.md)、[`CategorySelection`](../challenges/category-selection_zh.md) 和 [`IconSequence`](../challenges/icon-sequence_zh.md) 指南。


## 创建验证码

在请求处理器中打开会话并创建需要的类型。以下是分类卡片示例：

```php
<?php

declare(strict_types=1);

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

成功调用 [`issue()`](../reference/api_zh.md#issue) 返回 `IssueResult`，其代码为 `LifecycleCode::Issued`，并包含 `IssuedChallenge` 的 `id`、`kind`、`expiresAt`、`presentation`。达到有效验证码数量上限时 `challenge` 为 `null`，结果代码说明原因。

`Purpose` 是表单或操作的键，例如 `contact.send` 表示发送消息、`account.register` 表示注册。创建和校验时使用同一个键，必须在服务器端设置，不能直接来自浏览器。

不指定第二个参数时，`issue()` 创建文字验证码。其他类型需指定 `ChallengeKind::CategorySelection` 或 `ChallengeKind::IconSequence`。


## 显示验证码

包返回展示数据，HTML 和样式由应用实现：

| 类型 | 展示数据 | 用户答案 |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image_zh.md) | `ImagePresentation`：图片格式、字节和尺寸 | 输入的字符 |
| [`CategorySelection`](../challenges/category-selection_zh.md) | `CategorySelectionPresentation`：类别名称和卡片 | 逗号分隔的卡片令牌，顺序无关 |
| [`IconSequence`](../challenges/icon-sequence_zh.md) | `IconSequencePresentation`：目标图和图标选项 | 按选择顺序逗号分隔的令牌 |

`ImagePresentation::bytes` 包含图片原始字节。应用自行决定如何传输到浏览器。类别和卡片标签是纯文本，不是 HTML。

三种验证码都由服务器校验。浏览器仅接收展示选项和收集答案所需的数据。


## 校验答案

答案在独立请求中送达。重新打开**同一用户会话**，重新创建 `ChallengeService`，并传入表单标识符和答案：

```php
<?php

declare(strict_types=1);

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

`challengeId` 和 `answer` 是示例字段名。使用 `ChallengeId::fromString()` 解析标识符。将原始答案传给 `verify()`，不要改变大小写、排序令牌或额外转换；不同类型规则不同。

返回 `VerificationCode::Accepted` 表示验证成功。正确答案只能接受一次，使用同一标识符再次校验不能成功。


## 刷新验证码

[`ChallengeService::replace()`](../reference/api_zh.md#replace) 接收旧标识符并返回新验证码，以下是卡片类型示例：

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): IssueResult
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

刷新成功后显示新数据，并清除先前输入和选择。过期验证码在记录仍存在时可以刷新；记录删除后返回 `NotFound`。参见[生命周期](../reference/lifecycle_zh.md)。


## 在 PHP 会话中存储

示例使用内置的 [`NativeSessionChallengeStore`](../reference/api_zh.md#存储与时间)。应用通过 `session_start()` 打开普通 PHP 会话，再调用 `ActiveNativeSessionScope::capture()` 并将上下文传给存储。无需额外存储服务。

适配器支持标准 `files` 会话处理器，以及 `php` 和 `php_serialize` 序列化格式。

成功修改验证码数据后，适配器保存数据并关闭会话。若随后还要修改 `$_SESSION`，应用必须重新打开会话。没有修改的操作可能保持会话开启；后续会话操作由应用管理。

修改验证码数据的操作必须在发送 HTTP 响应头之前完成。耗时的外部操作最好在会话操作结束后执行。存储失败会抛出 `NativeSessionStorageException`，不算用户答案错误。

可以实现 `ChallengeStore` 接入其他存储，要求见 [API 参考](../reference/api_zh.md#扩展包)。


## 结果处理

| 代码 | 含义 |
| --- | --- |
| `Issued`, `Refreshed` | 验证码已创建或刷新，返回新数据 |
| `CapacityExceeded` | 达到有效验证码数量上限 |
| `Accepted` | 正确答案已接受，不能重复使用 |
| `Incorrect` | 错误答案，仍有次数 |
| `AttemptsExhausted` | 错误次数已耗尽 |
| `Expired` | 验证码已过期 |
| `AlreadyConsumed`, `Replaced` | 标识符不能再次用于校验 |
| `NotFound` | 未找到验证码或记录已删除 |
| `PurposeMismatch` | 验证码属于其他表单或操作（`Purpose`） |

`issue()` 和 `replace()` 返回 `IssueResult`，`verify()` 返回 `VerificationCode`。部分结果名称相似，但类型取决于方法。参见 [API 参考](../reference/api_zh.md)。


## 常见错误

- 在 `session_start()` 之前调用 `ActiveNativeSessionScope::capture()`。
- 会话关闭后未重新打开就修改 `$_SESSION`。
- 没有注册同一会话中仍可能存在的验证码类型的策略。
- 创建卡片或图标验证码时未指定 `ChallengeKind`。
- 从表单接收 `Purpose`，而不是在服务器端定义。
- 发送卡片标签而不是令牌，或排序 `IconSequence` 的令牌。
- 存储出错或结果不是 `Accepted` 时仍继续处理表单。

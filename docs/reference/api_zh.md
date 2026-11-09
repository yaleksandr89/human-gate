# API 参考

主要入口是 `ChallengeService`，用于创建、刷新验证码、校验答案并返回类型化结果。这里列出签名与配置；用法参见[集成指南](../guides/integration_zh.md)。

以下类型都位于 `Yaleksandr\HumanGate\` 命名空间，表格中的子命名空间均相对该命名空间表示。

## ChallengeService

构造函数接收存储、公共配置、当前时间源和验证码策略：

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

每种类型注册一个策略即可。同一会话内仍存在不同类型的有效验证码时，必须注册相应策略才能校验。

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

创建新验证码。成功时 `IssueResult` 包含 `LifecycleCode::Issued` 和 `IssuedChallenge`。达到有效验证码数量上限时返回 `CapacityExceeded`，且 `challenge === null`。

默认创建 [`TextImage`](../challenges/text-image_zh.md)。其他类型使用 `ChallengeKind::CategorySelection` 或 `ChallengeKind::IconSequence`。

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

用新验证码替换指定的旧验证码。成功时返回 `LifecycleCode::Refreshed` 和新的 `IssuedChallenge`，旧标识符不能再用于校验。

可刷新有效验证码，或仍保留记录的过期验证码。新类型默认为 `TextImage`，需要其他类型时明确指定 `kind`。参见[生命周期](lifecycle_zh.md)。

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

校验答案并返回 `VerificationCode`。`Accepted` 表示答案正确，当前验证码不能重复使用。

| `VerificationCode` 值 | 含义 |
| --- | --- |
| `Accepted` | 正确答案，验证成功 |
| `Incorrect` | 错误答案，仍有尝试次数 |
| `AttemptsExhausted` | 错误次数已用完 |
| `Expired` | 验证码已过期 |
| `NotFound` | 未找到验证码或旧记录已删除 |
| `PurposeMismatch` | 验证码属于其他表单或操作（`Purpose` 不匹配） |
| `AlreadyConsumed` | 此验证码的正确答案已经被接受 |
| `Replaced` | 验证码已被刷新 |

HTTP 状态码和用户消息由应用决定。调用示例见[集成指南](../guides/integration_zh.md)。

## Policy

`Challenge\Policy` 配置有效期、错误次数和存储上限。默认值如下：

```php
<?php

use Yaleksandr\HumanGate\Challenge\Policy;

$policy = new Policy(
    ttlSeconds: 180,
    maxWrongAttempts: 3,
    maxActive: 12,
    maxActivePerPurpose: 4,
    maxTombstones: 32,
    terminalRetentionSeconds: 180,
);
```

| 参数 | 默认值 | 作用 |
| --- | --- | --- |
| `ttlSeconds` | `180` | 验证码有效秒数 |
| `maxWrongAttempts` | `3` | 允许的错误答案次数 |
| `maxActive` | `12` | 一个存储中最多同时有效的验证码数 |
| `maxActivePerPurpose` | `4` | 每个表单或操作（`Purpose`）最多有效的验证码数 |
| `maxTombstones` | `32` | 最多保留的已结束验证码记录数 |
| `terminalRetentionSeconds` | `180` | 结束记录的保留秒数；数量上限可能使其提前删除 |

使用标准 [`NativeSessionChallengeStore`](#存储与时间) 时，上限**作用于一个 PHP 会话**，而不是整个网站。`contact.send` 和 `account.register` 各自最多四个有效验证码，但会话总数不得超过十二个。

成功校验、刷新、过期或错误次数耗尽后，包会短暂保留结束原因，以区分 `AlreadyConsumed` 与 `Expired`。`maxTombstones` 和 `terminalRetentionSeconds` 限制这些记录的数量和保留时间。删除后旧标识符可能返回 `NotFound`。

所有数值都必须大于零，且 `maxActivePerPurpose` 不得超过 `maxActive`。设置五分钟可使用 `new Policy(ttlSeconds: 300)`。这些数量限制**不是 HTTP 请求频率限制**。参见[生命周期](lifecycle_zh.md)。

## 主要类型

| 类型 | 用途 |
| --- | --- |
| `Challenge\ChallengeId` | 验证码标识符：`generate()`、`fromString(string $value)`、`value(): string` |
| `Challenge\Purpose` | 表单或操作键：`__construct(string $value)`、`value(): string` |
| `Challenge\ChallengeKind` | 验证码类型：`TextImage`、`CategorySelection`、`IconSequence` |
| `Challenge\IssueResult` | 结果 `code` 和 `challenge` 或 `null` |
| `Challenge\IssuedChallenge` | 新验证码：`id`、`kind`、`expiresAt`、`presentation` |

`ChallengeId` 必须是 64 个小写十六进制字符。`Purpose` 长度 1–64 个 ASCII 字节：首字符为小写拉丁字母或数字，其后可包含这些字符、点、下划线和连字符。

`Purpose` 在服务器端由应用定义，例如 `contact.send` 表示发送消息、`account.register` 表示注册。创建和校验时使用相同的键，不能直接从浏览器表单获取。`IssuedChallenge::expiresAt` 为 Unix 秒数表示的过期时刻。

## TextImage

相关类位于 `TextImage\`：

```php
TextImageStrategy::__construct(
    TextImageRenderer $renderer,
    TextImageAnswerOptions $answerOptions = new TextImageAnswerOptions(),
)

TextImageAnswerOptions::__construct(bool $caseSensitive = false)

GdTextImageRenderer::__construct(
    TextImageRenderOptions $options = new TextImageRenderOptions(),
)

TextImageRenderOptions::__construct(
    int $scalePercent = 100,
    TextImageBlur $blur = TextImageBlur::Standard,
    int $lightNoisePercent = 100,
    int $maxRotationDegrees = 28,
)
```

`TextImageBlur` 支持 `None`、`Light`、`Standard`、`Strong`。缩放值为 `100`、`105`、`110`、`115`、`120`；噪点为 `0`–`150`；旋转为 `0`–`28` 度。通过 `caseSensitive` 控制大小写。答案始终为六个字符。参见[文字验证码指南](../challenges/text-image_zh.md)。

## CategorySelection

相关类位于 `CategorySelection\`：

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

支持 2–16 个类别，每类 1–32 个元素。`cardCount` 为 3–12；`targetCount` 至少为 1 且小于 `cardCount`。标签必须唯一，为 1–80 字节有效 UTF-8，不含控制字符或首尾空白。

答案使用逗号分隔的卡片令牌，顺序无关。参见[卡片选择指南](../challenges/category-selection_zh.md)。

## IconSequence

相关类位于 `IconSequence\`：

```php
IconSequenceStrategy::__construct(
    IconSequenceOptions $options = new IconSequenceOptions(),
    IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
)

IconSequenceOptions::__construct(int $sequenceLength = 4, int $choiceCount = 8)

IconSequenceRenderOptions::__construct(
    int $minIconSize = 72,
    int $maxIconSize = 80,
    IconSequenceBlur $blur = IconSequenceBlur::Strong,
    int $noisePercent = 150,
    int $maxRotationDegrees = 90,
)
```

序列长度 3–6 个图标。选项数量至少比序列长度多两个，最多 12（例如四个目标图标对应 6–12 个选项）。图标尺寸 72–96 像素，噪点 0–150，旋转 0–90 度。`IconSequenceBlur` 支持 `None`、`Light`、`Standard`、`Strong`。

答案令牌必须按照选择顺序传入。参见[图标顺序指南](../challenges/icon-sequence_zh.md)。

## 展示数据

所有展示类型位于 `Presentation\`：

| 类型 | 字段 |
| --- | --- |
| `ImagePresentation` | `mimeType`、`bytes`、`width`、`height` |
| `CategorySelectionPresentation` | `category`、`cards` |
| `CategorySelectionCard` | `token`、`label` |
| `IconSequencePresentation` | `target`、`requiredSelections`、`choices` |
| `IconSequenceChoice` | `token`、`image` |

`ImagePresentation` 包含图片原始字节。支持 `image/png`、`image/jpeg`、`image/webp`，宽高各 1–4096 像素，数据最多 131072 字节。内置文字与图标生成器输出 PNG。

卡片类型向应用提供类别和卡片文本；图标类型提供目标图和带令牌、图片的选项。正确答案不会包含在展示数据中。

## 存储与时间

| 类型 | 用途 |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | 获取已开启 PHP 会话的上下文 |
| `Session\NativeSessionChallengeStore` | 在 PHP 会话中存储验证码状态 |
| `Clock\SystemClock` | 当前时间源：`now(): int` 返回 Unix 秒数 |

`NativeSessionChallengeStore` 接收 `ActiveNativeSessionScope`，支持原生 `files` 处理器和 `php`、`php_serialize` 序列化。应用先调用 `session_start()`，适配器在修改数据后关闭会话。参见[集成指南](../guides/integration_zh.md)。

## 异常

用户答案错误通过 `VerificationCode` 表示；设置或运行错误会抛出异常：

| 异常 | 原因 |
| --- | --- |
| `InvalidArgumentException` | 参数或配置无效 |
| `Exception\UnsupportedChallengeKindException` | 没有注册指定类型的策略 |
| `Exception\RenderingException` | 图片生成失败 |
| `Random\RandomException` | 随机数生成失败 |
| `Session\NativeSessionStorageException` | PHP 会话中验证码数据的读取、写入或保存校验失败 |

`NativeSessionStorageException::reason` 包含 `NativeSessionStorageFailureReason`：`UnsupportedSession`、`SessionIdentity`、`HeadersSent`、`MalformedState`、`SizeLimit`、`Randomness`、`Commit`、`Verification`。不要向用户直接暴露底层错误细节。

## 扩展包

可以通过 `Port\ChallengeStore`、`Port\Clock`、`Port\ChallengeStrategy` 和 `Port\TextImageRenderer` 提供自定义存储、时间源或验证码策略。

`ChallengeStore::atomic()` 必须在并发请求中安全地应用修改，并在返回前确认保存成功。存储失败时不能返回成功的验证码校验。

`Clock::now()` 返回用于计算有效期的当前 Unix 秒数；它是时间源，而不是倒计时器。`ChallengeStrategy::kind()`、`prepare()`、`verify()` 定义策略，`TextImageRenderer::render()` 返回 `ImagePresentation`。一般使用场景推荐[集成指南](../guides/integration_zh.md)中的现成实现。

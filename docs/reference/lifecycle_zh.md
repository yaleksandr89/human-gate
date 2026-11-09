# 验证码生命周期

Human Gate 记录已创建验证码的状态、有效期和错误次数。正确答案不能重复使用。公共限制通过 [`Policy`](api_zh.md#policy) 配置。

## 有效期与限制

| 参数 | 默认值 | 含义 |
| --- | --- |
| `ttlSeconds` | `180` | 验证码有效的秒数 |
| `maxWrongAttempts` | `3` | 允许的错误答案次数 |
| `maxActive` | `12` | 一个存储中最多同时有效的验证码数 |
| `maxActivePerPurpose` | `4` | 每个表单或操作（`Purpose`）最多有效的验证码数 |
| `maxTombstones` | `32` | 最多保留的已结束验证码记录数 |
| `terminalRetentionSeconds` | `180` | 结束记录的保留秒数；数量超限时可能提前删除 |

使用标准 PHP 会话存储时，限制作用于单个会话。例如，`contact.send` 最多可以有四个有效验证码，`account.register` 也最多四个，但整个会话总数不能超过十二个。记录数量和保留时间限制可防止过期数据不断积累。

所有值必须为正数，且 `maxActivePerPurpose` 不能大于 `maxActive`。将有效期设置为五分钟可使用 `new Policy(ttlSeconds: 300)`。这些限制并不控制 HTTP 请求频率。

## 创建验证码

[`ChallengeService::issue()`](api_zh.md#issue) 创建指定类型的验证码。成功时返回标识符、过期时间和展示数据。

达到有效验证码数量上限时，返回 `LifecycleCode::CapacityExceeded`，不会创建新验证码。默认类型是[文字验证码](../challenges/text-image_zh.md)；通过 `ChallengeKind` 选择其他类型。

## 校验答案

[`ChallengeService::verify()`](api_zh.md#verify) 接收验证码标识符、服务器端表单键（`Purpose`）和用户答案。必须使用创建时相同的键。验证码类型从保存的数据中读取，而不是由浏览器提供。

每种类型的答案规则不同。[`CategorySelection`](../challenges/category-selection_zh.md) 不要求令牌顺序一致；[`IconSequence`](../challenges/icon-sequence_zh.md) 则必须按顺序提交。答案格式错误也计为一次失败。

## 错误答案

每次错误都会增加计数。尚有次数时返回 `VerificationCode::Incorrect`，用户可以继续尝试当前验证码。

达到上限时返回 `VerificationCode::AttemptsExhausted`。默认情况下，**第三次错误**将结束验证码，之后不能再使用该标识符验证，有效期也不会延长。

需要继续时通过 [`issue()`](api_zh.md#issue) 创建新验证码；次数耗尽的验证码不能用 `replace()` 刷新。

## 正确答案

正确答案返回 `VerificationCode::Accepted`。之后该验证码不能再次通过。只要成功记录还在，重复校验返回 `AlreadyConsumed`。

## 刷新验证码

[`ChallengeService::replace()`](api_zh.md#replace) 用新验证码替换旧验证码。成功时返回新的标识符和展示数据，旧答案及选择不再有效。

可替换有效验证码，也可替换仍保留记录的过期验证码。替换有效验证码不会增加有效数量；替换过期验证码可能需要可用容量。

已通过、已被替换或次数耗尽的验证码不能通过此方法刷新。默认生成文字验证码，需要其他类型时必须明确指定。

## 过期与记录删除

当前时间早于 `expiresAt` 时验证码有效。从该时间点起，任何答案都不再被接受。

过期验证码的信息可能暂时保留。记录存在期间，`verify()` 返回 `Expired`，`replace()` 可以创建新验证码；删除记录后旧标识符返回 `NotFound`。

结束记录仅保留有限时间。若数量超过 `maxTombstones`，旧记录可能提前删除。因此很旧的验证码不一定返回 `Expired`，删除后将返回 `NotFound`。

## 与表单绑定

`Purpose` 是表单或操作的字符串键，例如 `contact.send` 表示发送消息，`account.register` 表示注册。应用在服务器端创建和校验验证码时使用相同的键。若使用不同表单的键，服务返回 `PurposeMismatch` 并拒绝答案。键格式见 [API 参考](api_zh.md#主要类型)。

## 方法结果

| 方法 | 成功时 | 其他结果 |
| --- | --- |
| [`issue()`](api_zh.md#issue) | `Issued` 和新验证码 | `CapacityExceeded` |
| [`replace()`](api_zh.md#replace) | `Refreshed` 和新验证码 | `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced` |
| [`verify()`](api_zh.md#verify) | `Accepted` | `Incorrect`, `AttemptsExhausted`, `Expired`, `NotFound`, `PurposeMismatch`, `AlreadyConsumed`, `Replaced` |

存储或图片生成失败会抛出异常，而不是返回 `Incorrect`。参见 [API 参考](api_zh.md#异常)。

# 安全模型

Human Gate 在服务器端生成验证码并校验答案。它限制验证码的有效期和错误次数，成功通过的验证码不能重复使用。

## 服务器端校验

创建验证码时，应用提供服务器端的 [`Purpose`](reference/api_zh.md#主要类型) 键，用于标识表单或操作。例如，`contact.send` 可表示联系表单，`account.register` 表示注册。为一个表单创建的验证码不能用于另一个表单。正确答案和验证数据保留在服务器；浏览器仅收到标识符和展示数据。

提交表单时，应用将标识符和答案传给 [`ChallengeService::verify()`](reference/api_zh.md#verify)。服务器负责判断答案是否正确；仅持有标识符不代表验证成功。

包不以明文保存正确答案。对于 [`CategorySelection`](challenges/category-selection_zh.md) 和 [`IconSequence`](challenges/icon-sequence_zh.md)，浏览器只收到随机选项令牌，不会收到哪些选项正确的信息。卡片令牌顺序无关紧要，图标序列的顺序则必须正确。

## 过期与重复使用

有效期和错误次数上限由 [`Policy`](reference/api_zh.md#policy) 配置。默认有效期 180 秒，第三次错误将结束验证。

正确答案返回 `VerificationCode::Accepted`，之后不能再次使用该验证码。只要成功记录仍存在，再次校验返回 `AlreadyConsumed`；记录删除后返回 `NotFound`。两种结果都不能再次通过旧验证码。

过期验证码同样不能通过。参见[刷新与记录保留规则](reference/lifecycle_zh.md)。

## 数据存储

内置的 [`NativeSessionChallengeStore`](reference/api_zh.md#存储与时间) 使用应用已开启的 PHP 会话和 `files` 处理器。数据发生变化时，包会保存并核验写入。存储失败会抛出异常，而不会被当作验证成功。

[集成指南](guides/integration_zh.md)介绍存储接入和 PHP 会话的用法。

## 有效验证码数量限制

[`Policy::maxActive` 和 `Policy::maxActivePerPurpose`](reference/api_zh.md#policy) 限制可同时保存的有效验证码数量。它们并不统计一定时间内的 HTTP 请求次数。

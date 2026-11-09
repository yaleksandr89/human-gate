# 安全政策

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/security/policy) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_es.md) | **当前语言** | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_de.md) |

## 支持的版本

Human Gate 为当前稳定的 `1.x` 系列提供安全修复。

| 版本 | 支持 |
| --- | --- |
| `1.x` | 是 |

## 哪些问题属于安全漏洞

Human Gate 的潜在安全问题包括：

- 错误答案被接受，或验证码校验可以被绕过；
- 已成功通过的验证码再次被接受；
- 绕过有效期或错误次数限制；
- 使用某个表单生成的验证码，通过具有不同 `Purpose` 的另一个表单的验证；
- 向浏览器泄露正确答案、正确的分类卡片或图标顺序；
- 读取或保存受支持的 PHP 会话时破坏验证码数据的完整性；
- 处理不可信输入或生成图片时发生具有安全影响的边界错误；
- 源代码、依赖项、CI 或发布构件遭到破坏。

## 安全模型

Human Gate 在服务器端生成并校验验证码。每个验证码绑定表单或操作键（`Purpose`），具有有效期和错误次数上限；校验成功后不能再次使用。

验证数据保存在服务器端。内置存储使用 PHP 会话。详情请参阅[包的安全模型](https://github.com/yaleksandr89/human-gate/blob/master/docs/security-model_zh.md)。

## 如何报告漏洞

如果仓库启用了 **GitHub Private Vulnerability Reporting**，请优先使用：

1. 打开 **Security** 标签页。
2. 进入 **Advisories**。
3. 选择 **Report a vulnerability**。
4. 通过私密表单提交报告。

如果无法使用私密报告功能，请创建 [Issue](https://github.com/yaleksandr89/human-gate/issues/new/choose)，**只请求提供私密沟通渠道，不包含漏洞细节**。不要公开绕过方法、利用代码或敏感信息。

在协调披露之前，请勿公开：

- 可实际绕过验证码的操作说明或利用代码；
- 真实用户会话的标识符和内容、表单数据或个人信息；
- 密钥、凭据、私有日志或生产系统材料。

## 报告应包含哪些信息

请尽可能提供：

- Human Gate 版本或提交 SHA；
- PHP 版本、`ext-gd` 是否可用以及相关环境信息；
- 验证码类型：`TextImage`、`CategorySelection` 或 `IconSequence`；
- 受影响的操作：`issue()`、`replace()`、`verify()`、图片生成或会话存储；
- 使用模拟数据的最小复现步骤；
- 预期行为和实际行为；
- 潜在影响以及已知的修复建议。

请使用模拟数据，不要附上真实会话的内容。

## 报告处理

本项目由一位维护者负责。报告会在条件允许时审查，不承诺固定响应时间。确认的问题将修复并通过回归检查。公开技术细节前请先与维护者协调。

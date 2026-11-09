# 参与贡献

## 选择语言

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_es.md) | **当前语言** | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_de.md) |

感谢你帮助改进 Human Gate。这个 Composer 包使用 PHP 生成并校验三种验证码：`TextImage`、`CategorySelection` 和 `IconSequence`。欢迎让公共 API 更清晰、验证码校验更可靠的修复和改进。

## 开始之前

- 请通过 [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues/new/choose) 报告可重现的错误。
- 功能建议请使用相应表单。先说明用户问题和期望行为，再讨论实现方式。
- 潜在漏洞请按照[安全政策](https://github.com/yaleksandr89/human-gate/security/policy)报告，不要在公开 Issue 中发布绕过方法。
- 对公共 API、有效期、验证规则或答案格式的重大修改，请先在 Issue 中讨论。

## 需要保持的包行为

- 包支持 `PHP ^8.4`，依赖 `ext-gd`，运行时无需第三方 Composer 库。
- 答案在服务器端校验；每个验证码都绑定表单或操作键（`Purpose`）。
- 成功通过的验证码不能重复使用。`Policy` 限制有效期和错误次数。
- [`TextImage`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/text-image_zh.md) 校验图片字符；[`CategorySelection`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/category-selection_zh.md) 接受不计顺序的卡片集合；[`IconSequence`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/icon-sequence_zh.md) 校验图标顺序。
- 包生成展示数据并校验答案；HTML 界面由应用实现。
- 修改应遵守现有架构，避免添加不必要的依赖项或公共功能。

用法详情与方法签名参见 [API 参考](https://github.com/yaleksandr89/human-gate/blob/master/docs/reference/api_zh.md)。

## 分支与提交

为每项修改创建名称明确的分支，例如：

```text
fix/icon-sequence-verification
feat/text-image-rendering
docs/integration-example
```

采用 Conventional Commits，前缀后使用俄语描述：

```text
fix: исправить повторную проверку капчи
feat: добавить настройку отображения
test: добавить проверку обновления капчи
docs: уточнить интеграцию с сессией PHP
```

每次提交应包含一项相关修改，不夹带无关重构。PR 中应说明修改原因、API 影响以及实际完成的检查。

## 本地检查

安装依赖并运行完整检查：

```bash
composer install
composer check
```

也可以分别执行：

```bash
composer test
composer analyse
composer cs:check
```

仅修改 Markdown 时，检查链接并运行 `git diff --check`。修改 PHP 时，运行相关测试、代码风格检查和静态分析。覆盖率用于诊断，不是必须达到的百分比。

## 测试与示例

- 覆盖成功和失败场景，尤其是超时、错误次数、重复验证、刷新和会话错误。
- 使用模拟答案、类别、标识符和会话数据。不要提交真实用户数据。
- 示例必须与当前公共 API 一致，且无需未记录的配置。
- 修改行为时，同时更新俄语文档及对应翻译。

## Pull Request

提交 PR 前，确认修改范围符合任务、测试覆盖变更后的行为，并且文档与代码一致。请使用 PR 模板；维护者可能要求进一步说明或检查。

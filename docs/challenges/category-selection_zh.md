# CategorySelection — 按类别选择卡片

用户看到类别名称，选择所有符合该类别的卡片。例如，从水果和工具中只选择水果。**选择顺序不影响结果。**

## 接入与类别

应用提供类别和卡片标签。`CategorySelectionStrategy` 随机挑选卡片，并校验选中的集合。

```php
<?php

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionOptions;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;

$strategy = new CategorySelectionStrategy(
    [
        new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
        new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
    ],
    new CategorySelectionOptions(cardCount: 6, targetCount: 2),
);
```

将策略注册到 `ChallengeService`，并为 [`ChallengeService::issue()`](../reference/api_zh.md#issue) 指定 `ChallengeKind::CategorySelection`。完整的创建与会话示例见[集成指南](../guides/integration_zh.md)。

一个目录可包含 2–16 个类别，每个类别 1–32 个元素。每个可能的目标类别都需要足够多的正确卡片，其他类别也需要足够的干扰项。类别名和标签必须唯一，并避免语义重叠。

标签必须为有效 UTF-8，长度 1–80 **字节**（不是字符），不能含控制字符或首尾空白。包返回纯文本，而不是 HTML。

## 卡片数量

`CategorySelectionOptions` 设置卡片总数和正确选项数量：

| 参数 | 默认值 | 允许值 |
| --- | --- | --- |
| `cardCount` | `6` | `3` 到 `12` 张卡片 |
| `targetCount` | `2` | 至少 `1` 且小于 `cardCount` |

其余卡片来自其他类别。有效期和错误次数通过公共 [`Policy`](../reference/api_zh.md#policy) 配置，而不是 `CategorySelectionOptions`。

## 展示数据

[`CategorySelectionPresentation`](../reference/api_zh.md) 包含 `category` 和 `cards`。每个 `CategorySelectionCard` 都有显示给用户的 `label` 和随机 `token`。

浏览器收到卡片与令牌，但不知道哪些卡片正确。Human Gate 根据服务器保存的数据校验答案。

## 答案格式

应用用逗号连接所选卡片的令牌，并将字符串交给 [`ChallengeService::verify()`](../reference/api_zh.md#verify)。**令牌顺序无关紧要**；包比较所选集合。

每个令牌由 32 个小写十六进制字符（`0–9`、`a–f`）组成。不能重复，总答案长度不超过 512 字节。不允许空格或大小写改变，无需排序或规范化。

成功调用 [`ChallengeService::replace()`](../reference/api_zh.md#replace) 后，应显示新类别和卡片并清除旧选择。旧令牌对新验证码无效。参见[生命周期规则](../reference/lifecycle_zh.md)。

## 错误

无效目录或参数会在创建策略时抛出 `InvalidArgumentException`。错误答案返回 `VerificationCode::Incorrect`，次数耗尽时返回 `AttemptsExhausted`。存储错误单独处理。参见[安全模型](../security-model_zh.md)和 [API 异常](../reference/api_zh.md#异常)。

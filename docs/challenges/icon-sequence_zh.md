# IconSequence — 图标顺序验证码

用户看到图片中的几个图标，并按照显示顺序选择相同图标。**选择顺序必须正确。**

## 接入

此验证码使用 `IconSequenceStrategy`，默认配置如下：

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

需要调整时，明确传入参数：

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy(
    new IconSequenceOptions(sequenceLength: 4, choiceCount: 8),
    new IconSequenceRenderOptions(
        minIconSize: 72,
        maxIconSize: 80,
        blur: IconSequenceBlur::Strong,
        noisePercent: 150,
        maxRotationDegrees: 90,
    ),
);
```

将 `IconSequenceStrategy` 注册到 `ChallengeService`，并在 [`ChallengeService::issue()`](../reference/api_zh.md#issue) 中指定 `ChallengeKind::IconSequence`。参见[集成指南](../guides/integration_zh.md)。

## 图标数量与顺序

| `IconSequenceOptions` 参数 | 默认值 | 允许值 |
| --- | --- | --- |
| `sequenceLength` | `4` | `3` 到 `6` 个图标 |
| `choiceCount` | `8` | 可选项至少比序列长度多两个，最多 `12` 个 |

正确序列之外至少添加两个干扰项。若目标有四个图标，可以提供 6–12 个选项（默认八个）。用户按图中顺序选择四个正确图标。

## 图片设置

| `IconSequenceRenderOptions` 参数 | 默认值 | 允许值 |
| --- | --- | --- |
| `minIconSize` | `72` | `72` 到 `96` 像素 |
| `maxIconSize` | `80` | `72` 到 `96`，不小于 `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`、`Light`、`Standard`、`Strong` |
| `noisePercent` | `150` | `0` 到 `150` |
| `maxRotationDegrees` | `90` | `0` 到 `90` 度 |

模糊、噪点与旋转只作用于目标序列图片；选项图片保持清晰。尺寸和角度的组合必须让旋转后的图标容纳在 128 像素的目标图高度内。

有效期由公共 [`Policy::ttlSeconds`](../reference/api_zh.md#policy) 控制，而不是图片设置，默认 180 秒。

## 展示数据

[`IconSequencePresentation`](../reference/api_zh.md) 包含：

| 字段 | 应用得到的数据 |
| --- | --- |
| `target` | 目标图标序列图片 |
| `requiredSelections` | 需要选择的图标数量 |
| `choices` | 包含 `token` 和 `image` 的 `IconSequenceChoice` 选项 |

图片使用包含 PNG 字节的 `ImagePresentation`。每个选项图片为 96×96 像素，目标图片高 128 像素，宽度随序列长度变化。

`IconSequenceChoice` 只暴露令牌和图片。图标名称和正确顺序不会传给浏览器。

## 答案格式

应用用逗号连接选中的图标令牌，**严格按选择顺序**传给 [`ChallengeService::verify()`](../reference/api_zh.md#verify)。

每个令牌为 32 个小写十六进制字符。答案包含 3–6 个互不相同的令牌，总长不超过 197 字节。不能有空格、重复令牌或改动顺序；包不会排序或规范化答案。

成功调用 [`ChallengeService::replace()`](../reference/api_zh.md#replace) 后，清除旧选择并显示新图片。参见[验证码生命周期](../reference/lifecycle_zh.md)。

## 校验与错误

序列由服务器校验。浏览器不会得到图标名称或能推断顺序的隐藏数据。参见[安全模型](../security-model_zh.md)。

包内含 32 个基于 Tabler Icons `v3.48.0` 的图标，采用 MIT 许可证。[来源](../../resources/icons/tabler/SOURCE.txt)和[许可证](../../resources/icons/tabler/LICENSE.txt)见资源目录。

无效设置抛出 `InvalidArgumentException`，图片生成失败抛出 `RenderingException`，错误答案返回 `VerificationCode::Incorrect` 或次数耗尽时的 `AttemptsExhausted`。

# TextImage — 文字验证码

用户看到包含六个字符的图片，并将其输入表单。Human Gate 在服务器端生成图片并校验答案，默认不区分字母大小写。

## 接入

文字验证码使用 `TextImageStrategy` 和 `GdTextImageRenderer`。下面是图片和大小写设置示例：

```php
<?php

use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageAnswerOptions;
use Yaleksandr\HumanGate\TextImage\TextImageBlur;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

$strategy = new TextImageStrategy(
    new GdTextImageRenderer(new TextImageRenderOptions(
        scalePercent: 110,
        blur: TextImageBlur::Standard,
        lightNoisePercent: 100,
        maxRotationDegrees: 28,
    )),
    new TextImageAnswerOptions(caseSensitive: false),
);
```

将策略传入 `ChallengeService`。[`ChallengeService::issue()`](../reference/api_zh.md#issue) 默认生成文字验证码，无需指定类型。完整的会话和校验示例见[集成指南](../guides/integration_zh.md)。

## 答案设置

`TextImageAnswerOptions::caseSensitive` 控制字母大小写：

| 值 | 校验方式 |
| --- | --- |
| `false` | 拉丁字母大小写等同 |
| `true` | 必须与图片中的大小写一致 |

**答案始终为六个字符**，不能修改长度。字符选择会避开部分容易混淆的组合。大小写敏感模式还会使用额外的小写字母。

将用户输入的原始字符串传给 [`ChallengeService::verify()`](../reference/api_zh.md#verify)。包自行执行允许的大小写转换。只移除字符串首尾的空格、制表符和 `CR`/`LF` 换行；不支持内部空格或任意 Unicode 规范化。原始答案最多 64 字节。

## 图片设置

`TextImageRenderOptions` 控制图片生成：

| 参数 | 默认值 | 允许值 |
| --- | --- | --- |
| `scalePercent` | `100` | `100`、`105`、`110`、`115`、`120` |
| `blur` | `TextImageBlur::Standard` | `None`、`Light`、`Standard`、`Strong` |
| `lightNoisePercent` | `100` | `0` 到 `150` |
| `maxRotationDegrees` | `28` | `0` 到 `28` 度 |

缩放会保持图片比例；模糊、噪点和旋转只影响外观，不改变正确答案。

验证码有效期由公共的 [`Policy::ttlSeconds`](../reference/api_zh.md#policy) 控制，**不属于**图片参数。默认 180 秒；`new Policy(ttlSeconds: 300)` 设置为五分钟。

## 展示数据

创建后，应用收到 [`ImagePresentation`](../reference/api_zh.md)，包含 `mimeType`、`bytes`、`width`、`height`。格式为 `image/png`；`bytes` 是 PNG 原始字节，而不是 base64 字符串。

缩放为 `100` 时尺寸是 240×80 像素。应用自行决定图片的浏览器传输方式和答案输入框。

图片生成需要支持 FreeType 的 `ext-gd`。包附带 Noto Sans Regular 字体；[来源](../../resources/fonts/SOURCE.txt)与[许可证](../../resources/fonts/OFL.txt)位于资源目录。

## 错误

无效选项会抛出 `InvalidArgumentException`；图片生成失败会抛出 `RenderingException`。错误答案返回 `VerificationCode::Incorrect`，次数耗尽时返回 `AttemptsExhausted`。这些结果与图片生成错误不同。

浏览器只收到字符图片，不会收到正确答案。详情参见[服务器端校验](../security-model_zh.md)。

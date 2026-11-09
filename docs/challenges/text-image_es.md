# TextImage — captcha de texto

El usuario ve una imagen con seis caracteres y los introduce en un campo. Human Gate genera la imagen y comprueba la respuesta en el servidor. Por defecto no distingue entre mayúsculas y minúsculas.

## Configuración

El captcha de texto utiliza `TextImageStrategy` y `GdTextImageRenderer`. Ejemplo con ajustes de imagen y mayúsculas:

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

Pasa la estrategia a `ChallengeService`. El captcha de texto es el tipo predeterminado de [`ChallengeService::issue()`](../reference/api_es.md#issue), por lo que no necesitas indicarlo. La [guía de integración](../guides/integration_es.md) muestra las sesiones y la verificación.

## Opciones de respuesta

`TextImageAnswerOptions::caseSensitive` determina si se distingue entre mayúsculas y minúsculas:

| Valor | Cómo se comprueba |
| --- | --- |
| `false` | Las letras latinas mayúsculas y minúsculas se consideran equivalentes |
| `true` | Las mayúsculas y minúsculas deben coincidir con la imagen |

**La respuesta siempre contiene seis caracteres**; su longitud no se puede cambiar. Se eligen caracteres evitando algunas semejanzas visuales. El modo sensible a mayúsculas también utiliza letras minúsculas adicionales.

Pasa la cadena original a [`ChallengeService::verify()`](../reference/api_es.md#verify). El paquete realiza la conversión permitida y la comprueba. Solo elimina espacios, tabuladores y saltos de línea `CR`/`LF` de los extremos. No admite espacios internos ni normalización Unicode arbitraria. Tamaño máximo: 64 bytes.

## Opciones de imagen

`TextImageRenderOptions` configura la generación de la imagen:

| Parámetro | Por defecto | Valores válidos |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | De `0` a `150` |
| `maxRotationDegrees` | `28` | De `0` a `28` grados |

La escala cambia el tamaño sin alterar la proporción. El desenfoque, el ruido y la rotación afectan a la imagen, pero no a la respuesta correcta.

La validez se configura con [`Policy::ttlSeconds`](../reference/api_es.md#policy), **no** con las opciones de imagen. Por defecto son 180 segundos; `new Policy(ttlSeconds: 300)` establece cinco minutos.

## Datos de presentación

Al crear el captcha, la aplicación recibe [`ImagePresentation`](../reference/api_es.md) con `mimeType`, `bytes`, `width` y `height`. El tipo es `image/png`; `bytes` contiene bytes PNG sin codificar en base64.

Con escala `100`, la imagen mide 240×80 píxeles. La aplicación decide cómo entregarla al navegador y crea el campo de respuesta.

Se requiere `ext-gd` con soporte FreeType. Se incluye Noto Sans Regular; su [procedencia](../../resources/fonts/SOURCE.txt) y [licencia](../../resources/fonts/OFL.txt) están en los recursos.

## Errores

Las opciones incorrectas provocan `InvalidArgumentException`; los fallos de imagen, `RenderingException`. Las respuestas erróneas devuelven `VerificationCode::Incorrect` o `AttemptsExhausted` cuando se agotan los intentos. No son errores de generación.

El navegador recibe la imagen, no la respuesta correcta. Consulta la [verificación en el servidor](../security-model_es.md).

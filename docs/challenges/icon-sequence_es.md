# IconSequence — secuencia de iconos

El usuario ve varios iconos en una imagen y selecciona los mismos en el orden indicado. **El orden es importante.**

## Configuración

Este captcha utiliza `IconSequenceStrategy`. Con valores predeterminados:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

Para modificar los parámetros, pásalos explícitamente:

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

Registra `IconSequenceStrategy` en `ChallengeService` e indica `ChallengeKind::IconSequence` a [`ChallengeService::issue()`](../reference/api_es.md#issue). Consulta la [guía de integración](../guides/integration_es.md).

## Cantidad y orden de los iconos

| Parámetro de `IconSequenceOptions` | Por defecto | Valores válidos |
| --- | --- | --- |
| `sequenceLength` | `4` | De `3` a `6` iconos |
| `choiceCount` | `8` | Como mínimo dos opciones más que iconos en la secuencia; máximo `12` |

A la secuencia correcta se añaden al menos dos alternativas adicionales. Para cuatro iconos correctos puede haber de 6 a 12 opciones (ocho por defecto). El usuario elige los cuatro iconos en el mismo orden que la imagen.

## Opciones de imagen

| Parámetro de `IconSequenceRenderOptions` | Por defecto | Valores válidos |
| --- | --- | --- |
| `minIconSize` | `72` | De `72` a `96` píxeles |
| `maxIconSize` | `80` | De `72` a `96`, no menos de `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`, `Light`, `Standard`, `Strong` |
| `noisePercent` | `150` | De `0` a `150` |
| `maxRotationDegrees` | `90` | De `0` a `90` grados |

El desenfoque, el ruido y la rotación afectan a la secuencia objetivo; las imágenes de opciones permanecen nítidas. Tamaño y ángulo deben permitir que los iconos rotados quepan en 128 píxeles de altura.

La validez la configura [`Policy::ttlSeconds`](../reference/api_es.md#policy), no las opciones de imagen. Por defecto son 180 segundos.

## Datos de presentación

[`IconSequencePresentation`](../reference/api_es.md) contiene:

| Campo | Datos que recibe la aplicación |
| --- | --- |
| `target` | Imagen con la secuencia objetivo |
| `requiredSelections` | Número de iconos que se deben elegir |
| `choices` | Opciones `IconSequenceChoice` con `token` e `image` |

Las imágenes son objetos `ImagePresentation` con bytes PNG. Cada opción mide 96×96 píxeles; la imagen objetivo tiene 128 píxeles de altura y anchura variable.

`IconSequenceChoice` solo contiene token e imagen. El navegador no recibe nombres ni el orden correcto.

## Formato de respuesta

La aplicación une los tokens de los iconos elegidos con comas **en el orden exacto de selección** y los pasa a [`ChallengeService::verify()`](../reference/api_es.md#verify).

Cada token tiene 32 caracteres hexadecimales en minúsculas. La respuesta contiene de 3 a 6 tokens diferentes y no supera 197 bytes. No se permiten espacios, duplicados ni cambios de orden. El paquete no ordena ni normaliza.

Tras un [`ChallengeService::replace()`](../reference/api_es.md#replace) correcto, borra la selección anterior y muestra imágenes nuevas. Consulta el [ciclo de vida](../reference/lifecycle_es.md).

## Verificación y errores

La secuencia se comprueba en el servidor. El navegador no recibe nombres de iconos ni datos ocultos que revelen el orden. Consulta el [modelo de seguridad](../security-model_es.md).

El paquete incluye 32 iconos basados en Tabler Icons `v3.48.0` bajo licencia MIT. La [procedencia](../../resources/icons/tabler/SOURCE.txt) y [licencia](../../resources/icons/tabler/LICENSE.txt) figuran en los recursos.

Los parámetros incorrectos provocan `InvalidArgumentException`, los fallos de imagen `RenderingException` y las respuestas incorrectas `VerificationCode::Incorrect` o `AttemptsExhausted` si no quedan intentos.

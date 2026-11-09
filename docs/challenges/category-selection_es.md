# CategorySelection — selección de tarjetas por categoría

El usuario ve una categoría y selecciona todas sus tarjetas. Por ejemplo, entre frutas y herramientas debe elegir solo las frutas. **El orden no importa.**

## Configuración y categorías

La aplicación proporciona categorías y etiquetas. `CategorySelectionStrategy` elige tarjetas al azar y comprueba el conjunto elegido.

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

Registra la estrategia en `ChallengeService` y pasa `ChallengeKind::CategorySelection` a [`ChallengeService::issue()`](../reference/api_es.md#issue). La [guía de integración](../guides/integration_es.md) muestra la configuración completa y las sesiones.

Se admiten de 2 a 16 categorías con 1–32 elementos cada una. Debe haber suficientes tarjetas correctas y distractores para cada posible categoría. Los nombres y etiquetas son únicos; evita categorías que se solapen en significado.

Las etiquetas deben ser UTF-8 válido de 1–80 **bytes**, no caracteres, sin controles ni espacios al principio o final. El paquete devuelve texto plano, no HTML.

## Número de tarjetas

`CategorySelectionOptions` configura el número total y las selecciones correctas:

| Parámetro | Por defecto | Valores válidos |
| --- | --- | --- |
| `cardCount` | `6` | De `3` a `12` tarjetas |
| `targetCount` | `2` | De `1` a menos de `cardCount` |

Las tarjetas restantes proceden de otras categorías. La validez y los intentos utilizan [`Policy`](../reference/api_es.md#policy), no `CategorySelectionOptions`.

## Datos de presentación

[`CategorySelectionPresentation`](../reference/api_es.md) contiene `category` y una lista `cards`. Cada `CategorySelectionCard` ofrece la etiqueta visible `label` y un `token` aleatorio.

El navegador recibe tarjetas y tokens, pero no la información de cuáles son correctas. La comprobación se hace con los datos del servidor.

## Formato de respuesta

La aplicación une los tokens seleccionados con comas y pasa la cadena a [`ChallengeService::verify()`](../reference/api_es.md#verify). **El orden no importa**; se compara el conjunto elegido.

Cada token contiene 32 caracteres hexadecimales en minúsculas (`0–9`, `a–f`). No puede haber duplicados y la respuesta completa admite hasta 512 bytes. No se permiten espacios ni cambios de mayúsculas. No hace falta ordenar ni normalizar.

Tras un [`ChallengeService::replace()`](../reference/api_es.md#replace) correcto, muestra tarjetas y categoría nuevas y borra las selecciones anteriores. Los tokens antiguos ya no sirven. Consulta el [ciclo de vida](../reference/lifecycle_es.md).

## Errores

Los catálogos u opciones no válidos provocan `InvalidArgumentException` al crear la estrategia. Una respuesta incorrecta devuelve `VerificationCode::Incorrect` o `AttemptsExhausted` al agotar los intentos. Los fallos de almacenamiento son independientes. Consulta el [modelo de seguridad](../security-model_es.md) y las [excepciones de API](../reference/api_es.md#excepciones).

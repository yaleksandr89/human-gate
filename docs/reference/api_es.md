# Referencia de API

La entrada principal es `ChallengeService`. Crea y renueva captchas, comprueba las respuestas y devuelve resultados tipados. Esta página reúne firmas y opciones; la [guía de integración](../guides/integration_es.md) muestra su uso.

Todos los tipos enumerados pertenecen al espacio de nombres `Yaleksandr\HumanGate\`. Las tablas indican los subespacios relativos.

## ChallengeService

El constructor recibe almacenamiento, ajustes comunes, una fuente de hora actual y estrategias:

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

Basta una estrategia por tipo. Si quedan captchas de varios tipos en la sesión, el servicio necesita las estrategias correspondientes para verificarlos.

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Crea un captcha. Si tiene éxito, `IssueResult` contiene `LifecycleCode::Issued` y `IssuedChallenge`. Si se alcanza el límite de activos, devuelve `CapacityExceeded` y `challenge === null`.

Por defecto crea [`TextImage`](../challenges/text-image_es.md). Para los demás tipos se utiliza `ChallengeKind::CategorySelection` o `ChallengeKind::IconSequence`.

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Crea un captcha nuevo en lugar del indicado. Si tiene éxito, devuelve `LifecycleCode::Refreshed` y un nuevo `IssuedChallenge`. El identificador anterior ya no sirve.

Se puede renovar uno activo o uno caducado mientras conserve su registro. El nuevo tipo es `TextImage` por defecto; indica `kind` para otro tipo. Consulta el [ciclo de vida](lifecycle_es.md).

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Comprueba la respuesta y devuelve `VerificationCode`. `Accepted` significa que es correcta y el captcha ya no puede reutilizarse.

| Valor de `VerificationCode` | Significado |
| --- | --- |
| `Accepted` | Respuesta correcta, captcha superado |
| `Incorrect` | Respuesta incorrecta; quedan intentos |
| `AttemptsExhausted` | Intentos incorrectos agotados |
| `Expired` | Captcha caducado |
| `NotFound` | Captcha no encontrado o registro anterior eliminado |
| `PurposeMismatch` | Captcha de otro formulario u operación (`Purpose` distinto) |
| `AlreadyConsumed` | Respuesta correcta ya aceptada para este captcha |
| `Replaced` | Captcha ya renovado |

La aplicación decide el estado HTTP y el mensaje mostrado. Consulta el [ejemplo de `verify()`](../guides/integration_es.md).

## Policy

`Challenge\Policy` configura la validez, los errores y los límites de almacenamiento. Valores predeterminados:

```php
<?php

use Yaleksandr\HumanGate\Challenge\Policy;

$policy = new Policy(
    ttlSeconds: 180,
    maxWrongAttempts: 3,
    maxActive: 12,
    maxActivePerPurpose: 4,
    maxTombstones: 32,
    terminalRetentionSeconds: 180,
);
```

| Parámetro | Por defecto | Significado |
| --- | --- | --- |
| `ttlSeconds` | `180` | Validez del captcha en segundos |
| `maxWrongAttempts` | `3` | Respuestas incorrectas permitidas |
| `maxActive` | `12` | Máximo de captchas activos en un almacenamiento |
| `maxActivePerPurpose` | `4` | Máximo de activos por formulario u operación (`Purpose`) |
| `maxTombstones` | `32` | Máximo de registros conservados de captchas terminados |
| `terminalRetentionSeconds` | `180` | Tiempo de conservación de registros terminados, salvo eliminación anticipada por cantidad |

Con [`NativeSessionChallengeStore`](#almacenamiento-y-tiempo), los límites se aplican **a una sesión PHP**, no a todo el sitio. Por ejemplo, `contact.send` y `account.register` pueden tener hasta cuatro captchas activos cada uno, pero la sesión admite como máximo doce en total.

Después de una respuesta correcta, renovación, caducidad o agotamiento de intentos, el paquete conserva temporalmente la causa de finalización para distinguir `AlreadyConsumed` de `Expired`. `maxTombstones` y `terminalRetentionSeconds` limitan el número y duración de estos registros. Tras su eliminación, un identificador antiguo puede devolver `NotFound`.

Todos los valores deben ser positivos y `maxActivePerPurpose` no puede superar `maxActive`. Para cinco minutos utiliza `new Policy(ttlSeconds: 300)`. Estos límites **no regulan la frecuencia de las peticiones HTTP**. Consulta el [ciclo de vida](lifecycle_es.md).

## Tipos principales

| Tipo | Uso |
| --- | --- |
| `Challenge\ChallengeId` | Identificador: `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Clave de formulario u operación: `__construct(string $value)`, `value(): string` |
| `Challenge\ChallengeKind` | Tipo de captcha: `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Código `code` y `challenge` o `null` |
| `Challenge\IssuedChallenge` | Captcha nuevo: `id`, `kind`, `expiresAt`, `presentation` |

`ChallengeId` contiene exactamente 64 caracteres hexadecimales en minúsculas. `Purpose` contiene 1–64 bytes ASCII: empieza con letra latina minúscula o dígito, seguido de esos caracteres, punto, guion bajo o guion.

La aplicación establece `Purpose` en el servidor: por ejemplo, `contact.send` para mensajes y `account.register` para registro. Se usa la misma clave al crear y verificar y nunca se toma del navegador. `IssuedChallenge::expiresAt` representa el instante de caducidad en segundos Unix.

## TextImage

Clases del espacio `TextImage\`:

```php
TextImageStrategy::__construct(
    TextImageRenderer $renderer,
    TextImageAnswerOptions $answerOptions = new TextImageAnswerOptions(),
)

TextImageAnswerOptions::__construct(bool $caseSensitive = false)

GdTextImageRenderer::__construct(
    TextImageRenderOptions $options = new TextImageRenderOptions(),
)

TextImageRenderOptions::__construct(
    int $scalePercent = 100,
    TextImageBlur $blur = TextImageBlur::Standard,
    int $lightNoisePercent = 100,
    int $maxRotationDegrees = 28,
)
```

`TextImageBlur` admite `None`, `Light`, `Standard`, `Strong`. Escala: `100`, `105`, `110`, `115`, `120`; ruido: `0`–`150`; rotación: `0`–`28` grados. `caseSensitive` controla las mayúsculas. La respuesta siempre tiene seis caracteres. Consulta la [guía del captcha de texto](../challenges/text-image_es.md).

## CategorySelection

Clases del espacio `CategorySelection\`:

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

Se admiten 2–16 categorías con 1–32 elementos. `cardCount` admite 3–12 y `targetCount` al menos 1, siempre menos que `cardCount`. Las etiquetas son UTF-8 único de 1–80 bytes, sin controles ni espacios a los lados.

La respuesta contiene tokens separados por comas y el orden no importa. Consulta la [guía de tarjetas](../challenges/category-selection_es.md).

## IconSequence

Clases del espacio `IconSequence\`:

```php
IconSequenceStrategy::__construct(
    IconSequenceOptions $options = new IconSequenceOptions(),
    IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
)

IconSequenceOptions::__construct(int $sequenceLength = 4, int $choiceCount = 8)

IconSequenceRenderOptions::__construct(
    int $minIconSize = 72,
    int $maxIconSize = 80,
    IconSequenceBlur $blur = IconSequenceBlur::Strong,
    int $noisePercent = 150,
    int $maxRotationDegrees = 90,
)
```

Secuencia: 3–6 iconos. Opciones: al menos dos más que la secuencia, hasta 12 (por ejemplo, 6–12 opciones para cuatro iconos). Tamaño: 72–96 píxeles; ruido: 0–150; rotación: 0–90 grados. `IconSequenceBlur` admite `None`, `Light`, `Standard`, `Strong`.

Los tokens se envían en el orden de selección. Consulta la [guía de iconos](../challenges/icon-sequence_es.md).

## Datos de presentación

Todos los tipos están en `Presentation\`:

| Tipo | Campos |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` contiene bytes de imagen. Acepta `image/png`, `image/jpeg` y `image/webp`, 1–4096 píxeles por lado y hasta 131072 bytes. Los generadores de texto e iconos producen PNG.

Para tarjetas se reciben categoría y etiquetas; para iconos, imagen objetivo y alternativas con token e imagen. La respuesta correcta no está incluida.

## Almacenamiento y tiempo

| Tipo | Uso |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Obtiene el contexto de una sesión PHP ya abierta |
| `Session\NativeSessionChallengeStore` | Guarda el estado del captcha en una sesión PHP |
| `Clock\SystemClock` | Fuente de hora: `now(): int` devuelve segundos Unix |

`NativeSessionChallengeStore` recibe `ActiveNativeSessionScope`. Admite el controlador `files` y serialización `php` o `php_serialize`. La aplicación llama primero a `session_start()`; el adaptador cierra la sesión después de modificar datos. Consulta la [integración](../guides/integration_es.md).

## Excepciones

Las respuestas incorrectas se representan mediante `VerificationCode`. Los errores de configuración o ejecución lanzan excepciones:

| Excepción | Motivo |
| --- | --- |
| `InvalidArgumentException` | Argumentos u opciones incorrectos |
| `Exception\UnsupportedChallengeKindException` | No existe estrategia del tipo solicitado |
| `Exception\RenderingException` | Error al generar imagen |
| `Random\RandomException` | Fallo del generador de números aleatorios |
| `Session\NativeSessionStorageException` | Fallo al leer, escribir o verificar datos del captcha en la sesión PHP |

`NativeSessionStorageException::reason` contiene `NativeSessionStorageFailureReason`: `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. No muestres detalles técnicos directamente al usuario.

## Ampliación del paquete

Se pueden proporcionar almacenamientos, fuentes de tiempo o estrategias propias mediante `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy` y `Port\TextImageRenderer`.

`ChallengeStore::atomic()` debe aplicar cambios sin conflictos entre peticiones concurrentes y confirmar la escritura antes de devolver el resultado. Un fallo de almacenamiento nunca debe generar una verificación correcta.

`Clock::now()` devuelve la hora Unix actual en segundos para calcular la validez; es una fuente de tiempo, no un temporizador. `ChallengeStrategy::kind()`, `prepare()` y `verify()` definen la estrategia; `TextImageRenderer::render()` devuelve `ImagePresentation`. Para uso normal consulta la [integración](../guides/integration_es.md).

# Integración de Human Gate

Human Gate crea captchas y comprueba las respuestas en el servidor. La aplicación los muestra en el formulario y solo continúa el procesamiento después de una respuesta correcta.

## Cómo funciona

La aplicación llama a [`ChallengeService::issue()`](../reference/api_es.md#issue) y recibe el identificador y los datos de presentación del captcha, que se envían al navegador.

Al enviar el formulario, la aplicación obtiene el identificador y la respuesta, y llama a [`ChallengeService::verify()`](../reference/api_es.md#verify). `VerificationCode::Accepted` indica que la comprobación ha tenido éxito. La respuesta correcta y los aciertos permanecen en el servidor.

[`ChallengeService::replace()`](../reference/api_es.md#replace) crea un captcha nuevo con otro identificador.


## Conectar el paquete

`ChallengeService` se construye con un almacenamiento, los [ajustes comunes de `Policy`](../reference/api_es.md#policy), el reloj `SystemClock` y las estrategias deseadas. El ejemplo registra los tres tipos con una sesión PHP estándar. Las categorías son ilustrativas.

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require 'vendor/autoload.php';

function createChallengeServiceForActiveSession(): ChallengeService
{
    return new ChallengeService(
        new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
        new Policy(),
        new SystemClock(),
        new TextImageStrategy(new GdTextImageRenderer()),
        new CategorySelectionStrategy([
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
        ]),
        new IconSequenceStrategy(),
    );
}
```

Llama a esta función después de `session_start()`. Registrar las tres estrategias permite verificar captchas de distintos tipos creados previamente en la misma sesión. Consulta las guías [`TextImage`](../challenges/text-image_es.md), [`CategorySelection`](../challenges/category-selection_es.md) e [`IconSequence`](../challenges/icon-sequence_es.md).


## Crear un captcha

En el controlador de la petición, la aplicación inicia la sesión y crea el tipo deseado. Ejemplo de selección de tarjetas:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);

if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

Si [`issue()`](../reference/api_es.md#issue) tiene éxito, devuelve `IssueResult` con `LifecycleCode::Issued` y un `IssuedChallenge` con `id`, `kind`, `expiresAt` y `presentation`. Al alcanzar el límite de captchas activos, `challenge` es `null` y el código indica el motivo.

`Purpose` identifica el formulario u operación: `contact.send` para mensajes y `account.register` para registro, por ejemplo. Usa la misma clave al crear y verificar; defínela en el servidor, no a partir del navegador.

Sin el segundo argumento, `issue()` crea un captcha de texto. Para otros tipos, indica `ChallengeKind::CategorySelection` o `ChallengeKind::IconSequence`.


## Mostrar el captcha

El paquete devuelve datos; la aplicación crea el HTML y el diseño:

| Tipo de captcha | Datos de presentación | Respuesta del usuario |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image_es.md) | `ImagePresentation`: tipo, bytes y dimensiones | Caracteres introducidos |
| [`CategorySelection`](../challenges/category-selection_es.md) | `CategorySelectionPresentation`: categoría y tarjetas | Tokens separados por comas; el orden no importa |
| [`IconSequence`](../challenges/icon-sequence_es.md) | `IconSequencePresentation`: imagen de secuencia y opciones | Tokens separados por comas en el orden elegido |

`ImagePresentation::bytes` contiene los bytes de la imagen. La aplicación decide cómo enviarla al navegador. Las etiquetas de categorías y tarjetas son texto, no HTML.

Los tres tipos se comprueban en el servidor. El navegador recibe solo lo necesario para mostrar opciones y recoger la respuesta.


## Comprobar la respuesta

La respuesta llega en otra petición. Abre **la misma sesión del usuario**, vuelve a crear `ChallengeService` y pasa el identificador y la respuesta:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

$rawId = $_POST['challengeId'] ?? null;
$rawAnswer = $_POST['answer'] ?? null;
if (!is_string($rawId) || !is_string($rawAnswer)) {
    throw new InvalidArgumentException('Ожидаются строковые идентификатор и ответ.');
}

$id = ChallengeId::fromString($rawId);

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->verify($id, new Purpose('contact.send'), $rawAnswer);
```

`challengeId` y `answer` son nombres de campos de ejemplo. Analiza el identificador con `ChallengeId::fromString()`. Pasa la respuesta original a `verify()`, sin modificar mayúsculas ni ordenar tokens: cada tipo tiene reglas propias.

`VerificationCode::Accepted` significa que el captcha se ha superado. Una respuesta correcta solo se acepta una vez; repetir la comprobación con el mismo identificador no tendrá éxito.


## Renovar el captcha

[`ChallengeService::replace()`](../reference/api_es.md#replace) recibe el identificador anterior y crea otro captcha. Ejemplo para tarjetas:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): IssueResult
{
    if (!session_start()) {
        throw new RuntimeException('Не удалось открыть сессию PHP.');
    }

    return createChallengeServiceForActiveSession()->replace(
        $oldId,
        new Purpose('contact.send'),
        ChallengeKind::CategorySelection,
    );
}
```

Tras renovar, muestra los nuevos datos y borra los anteriores. Un captcha caducado puede renovarse mientras su registro exista; después devuelve `NotFound`. Consulta el [ciclo de vida](../reference/lifecycle_es.md).


## Almacenamiento en sesiones PHP

Los ejemplos utilizan [`NativeSessionChallengeStore`](../reference/api_es.md#almacenamiento-y-tiempo). La aplicación abre la sesión con `session_start()`, después obtiene `ActiveNativeSessionScope::capture()` y lo entrega al almacenamiento. No hace falta un servicio adicional.

El adaptador admite el controlador `files` y los formatos `php` y `php_serialize`.

Al modificar correctamente datos del captcha, el adaptador los guarda y cierra la sesión. Para cambiar después `$_SESSION`, la aplicación debe reabrirla. Una operación sin cambios puede dejarla abierta; la aplicación gestiona su continuación.

Las operaciones que cambian datos del captcha se realizan antes de enviar las cabeceras HTTP. Las operaciones externas largas se ejecutan mejor después. Un fallo de almacenamiento lanza `NativeSessionStorageException`, no cuenta como respuesta incorrecta.

Puedes aportar otro almacenamiento mediante `ChallengeStore`; sus requisitos están en la [referencia de API](../reference/api_es.md#ampliación-del-paquete).


## Tratamiento de resultados

| Código | Significado |
| --- | --- |
| `Issued`, `Refreshed` | Captcha creado o renovado; se devuelven datos nuevos |
| `CapacityExceeded` | Se alcanzó el límite de captchas activos |
| `Accepted` | Respuesta correcta aceptada; no se puede reutilizar |
| `Incorrect` | Respuesta incorrecta; quedan intentos |
| `AttemptsExhausted` | Intentos incorrectos agotados |
| `Expired` | Captcha caducado |
| `AlreadyConsumed`, `Replaced` | El identificador ya no sirve para comprobar |
| `NotFound` | Captcha no encontrado o registro eliminado |
| `PurposeMismatch` | Captcha de otro formulario u operación (`Purpose`) |

`issue()` y `replace()` devuelven `IssueResult`; `verify()` devuelve `VerificationCode`. Algunos nombres se repiten, pero el tipo depende del método. Consulta la [referencia de API](../reference/api_es.md).


## Errores frecuentes

- Llamar a `ActiveNativeSessionScope::capture()` antes de `session_start()`.
- Modificar `$_SESSION` tras el cierre sin reabrir la sesión.
- Omitir una estrategia para un tipo de captcha que aún puede estar activo.
- No indicar `ChallengeKind` para tarjetas o secuencias de iconos.
- Tomar `Purpose` del formulario en vez de establecerlo en el servidor.
- Enviar etiquetas en vez de tokens o reordenar tokens de `IconSequence`.
- Continuar procesando el formulario tras un error de almacenamiento o un resultado distinto de `Accepted`.

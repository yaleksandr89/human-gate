# Ciclo de vida del captcha

Human Gate controla cada captcha creado, su caducidad y los intentos incorrectos. Una respuesta correcta no puede reutilizarse. Los límites comunes se establecen mediante [`Policy`](api_es.md#policy).

## Validez y límites

| Parámetro | Por defecto | Significado |
| --- | --- |
| `ttlSeconds` | `180` | Segundos durante los que el captcha es válido |
| `maxWrongAttempts` | `3` | Número de respuestas incorrectas permitidas |
| `maxActive` | `12` | Máximo de captchas activos en un almacenamiento |
| `maxActivePerPurpose` | `4` | Máximo de captchas activos por formulario u operación (`Purpose`) |
| `maxTombstones` | `32` | Máximo de registros conservados de captchas terminados |
| `terminalRetentionSeconds` | `180` | Tiempo de conservación de registros terminados, salvo que el límite de cantidad los elimine antes |

Con el almacenamiento de sesiones PHP estándar, los límites se aplican a una sesión. Por ejemplo, puede haber hasta cuatro captchas activos para `contact.send` y cuatro para `account.register`, pero como máximo doce en total. Los límites de cantidad y duración evitan acumular registros terminados.

Todos los valores deben ser positivos y `maxActivePerPurpose` no puede superar `maxActive`. Para cinco minutos utiliza `new Policy(ttlSeconds: 300)`. Estos límites no restringen la frecuencia de las peticiones HTTP.

## Creación del captcha

[`ChallengeService::issue()`](api_es.md#issue) crea un captcha del tipo elegido. Si tiene éxito, devuelve el identificador, la fecha de caducidad y los datos para mostrarlo.

Si se alcanza el límite de captchas activos, devuelve `LifecycleCode::CapacityExceeded` y no crea uno nuevo. Por defecto crea un [captcha de texto](../challenges/text-image_es.md); otro tipo se indica con `ChallengeKind`.

## Comprobación de la respuesta

[`ChallengeService::verify()`](api_es.md#verify) recibe el identificador, la clave del formulario definida en el servidor (`Purpose`) y la respuesta. Utiliza la misma clave que al crear el captcha. El tipo se obtiene de los datos guardados, no del navegador.

Cada tipo tiene sus reglas. El orden de los tokens no importa para [`CategorySelection`](../challenges/category-selection_es.md), pero sí para [`IconSequence`](../challenges/icon-sequence_es.md). Un formato de respuesta incorrecto también cuenta como intento fallido.

## Respuesta incorrecta

Cada error incrementa el contador. Mientras queden intentos, el resultado es `VerificationCode::Incorrect` y se puede volver a intentar con el mismo captcha.

Al alcanzar el límite se devuelve `VerificationCode::AttemptsExhausted`. Con la configuración habitual, **el tercer error** termina el captcha. Los siguientes intentos se rechazan y la validez no se prolonga.

Para continuar se crea otro captcha con [`issue()`](api_es.md#issue): uno sin intentos restantes no se puede renovar con `replace()`.

## Respuesta correcta

Una respuesta correcta devuelve `VerificationCode::Accepted` y el mismo captcha no se puede superar de nuevo. Mientras se conserva el registro, otro intento devuelve `AlreadyConsumed`.

## Renovación del captcha

[`ChallengeService::replace()`](api_es.md#replace) crea un captcha nuevo en lugar del anterior. Devuelve otro identificador y nuevos datos; la respuesta y las selecciones antiguas dejan de servir.

Se puede renovar uno activo o uno caducado mientras se conserve su registro. Renovar uno activo no aumenta la cantidad de activos; renovar uno caducado puede necesitar capacidad libre.

No se pueden renovar por este método captchas superados, ya reemplazados o sin intentos. El nuevo tipo es texto por defecto; indica otro tipo cuando corresponda.

## Caducidad y eliminación de registros

Un captcha es válido mientras la hora actual sea anterior a `expiresAt`. Desde ese momento ninguna respuesta se acepta.

Un registro de captcha caducado puede conservarse temporalmente. Mientras exista, `verify()` devuelve `Expired` y `replace()` puede generar uno nuevo. Al eliminarlo, el identificador antiguo devuelve `NotFound`.

Los registros terminados se conservan un tiempo limitado. Si su cantidad supera `maxTombstones`, algunos se eliminan antes. Por tanto, un identificador antiguo no siempre devuelve `Expired`: tras eliminar el registro devuelve `NotFound`.

## Vinculación al formulario

`Purpose` es una clave de texto para un formulario u operación. Por ejemplo, `contact.send` indica enviar un mensaje y `account.register` registrarse. La aplicación establece esta clave en el servidor al crear y verificar el captcha. Una clave distinta devuelve `PurposeMismatch` y rechaza la respuesta. Formato en la [referencia de API](api_es.md#tipos-principales).

## Resultados de los métodos

| Método | Si tiene éxito | Otros resultados |
| --- | --- |
| [`issue()`](api_es.md#issue) | `Issued` y nuevo captcha | `CapacityExceeded` |
| [`replace()`](api_es.md#replace) | `Refreshed` y nuevo captcha | `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced` |
| [`verify()`](api_es.md#verify) | `Accepted` | `Incorrect`, `AttemptsExhausted`, `Expired`, `NotFound`, `PurposeMismatch`, `AlreadyConsumed`, `Replaced` |

Los fallos de almacenamiento o generación de imágenes lanzan excepciones, no devuelven `Incorrect`. Consulta la [referencia de API](api_es.md#excepciones).

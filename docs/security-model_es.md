# Modelo de seguridad

Human Gate genera captchas y comprueba las respuestas en el servidor. Limita su validez y los intentos incorrectos, e impide reutilizar un captcha superado.

## Verificación en el servidor

Al crear un captcha, la aplicación proporciona una clave [`Purpose`](reference/api_es.md#tipos-principales) del servidor que identifica el formulario u operación. Por ejemplo, `contact.send` puede representar un formulario de contacto y `account.register` el registro. No se puede verificar en un formulario un captcha creado para otro. La respuesta correcta y los datos de comprobación permanecen en el servidor; el navegador recibe solo el identificador y los datos de presentación.

Al enviar el formulario, la aplicación pasa el identificador y la respuesta a [`ChallengeService::verify()`](reference/api_es.md#verify). El servidor decide si es correcta: tener el identificador no significa haber superado el captcha.

El paquete no guarda la respuesta correcta en texto plano. Para [`CategorySelection`](challenges/category-selection_es.md) e [`IconSequence`](challenges/icon-sequence_es.md), el navegador recibe tokens aleatorios, pero no los aciertos. El orden de las tarjetas no importa; el de los iconos sí.

## Caducidad y reutilización

La validez y el límite de errores se configuran mediante [`Policy`](reference/api_es.md#policy). La duración predeterminada es de 180 segundos; el tercer error termina la comprobación.

Una respuesta correcta devuelve `VerificationCode::Accepted` y ya no puede reutilizarse el captcha. Mientras se conserva el resultado, un nuevo intento devuelve `AlreadyConsumed`; tras eliminarlo, `NotFound`. Ninguno permite superar de nuevo la comprobación con el identificador antiguo.

Los captchas caducados tampoco se aceptan. Consulta las [reglas de renovación y conservación](reference/lifecycle_es.md).

## Almacenamiento de datos

[`NativeSessionChallengeStore`](reference/api_es.md#almacenamiento-y-tiempo) funciona con una sesión PHP abierta por la aplicación y el controlador `files`. Al modificar datos, el paquete los guarda y verifica la escritura. Si falla el almacenamiento, se lanza una excepción y no se acepta la respuesta.

La [guía de integración](guides/integration_es.md) muestra cómo conectar el almacenamiento y trabajar con sesiones PHP.

## Límites de captchas activos

[`Policy::maxActive` y `Policy::maxActivePerPurpose`](reference/api_es.md#policy) limitan la cantidad de captchas activos que pueden almacenarse a la vez. No cuentan las peticiones HTTP durante un intervalo.

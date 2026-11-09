# Política de seguridad

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/security/policy) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_en.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_de.md) |

## Versiones compatibles

Las correcciones de seguridad se publican para la versión estable actual de la serie `1.x` de Human Gate.

| Versión | Compatible |
| --- | --- |
| `1.x` | Sí |

## Qué se considera una vulnerabilidad

Entre los posibles problemas de seguridad de Human Gate se encuentran:

- aceptar una respuesta incorrecta u omitir la verificación del captcha;
- aceptar de nuevo un captcha que ya se ha resuelto;
- eludir el plazo de validez o el límite de respuestas incorrectas;
- usar un captcha emitido para un formulario al verificar otro con un `Purpose` distinto;
- exponer al navegador la respuesta correcta, las tarjetas elegidas o el orden de los iconos;
- alterar los datos del captcha al leer o guardar una sesión PHP compatible;
- vulnerar los límites de tratamiento de entradas no fiables o generación de imágenes con consecuencias de seguridad;
- comprometer el código fuente, las dependencias, CI o los archivos de publicación del paquete.

## Modelo de seguridad

Human Gate genera y verifica captchas en el servidor. Cada captcha está vinculado a una clave de formulario u operación (`Purpose`), tiene una validez y un límite de errores, y no puede reutilizarse tras una verificación correcta.

El paquete conserva los datos de verificación en el servidor. Su almacenamiento incluido utiliza sesiones PHP. Consulta el [modelo de seguridad del paquete](https://github.com/yaleksandr89/human-gate/blob/master/docs/security-model_es.md).

## Cómo comunicar una vulnerabilidad

Utiliza **GitHub Private Vulnerability Reporting** si está disponible en el repositorio:

1. Abre la pestaña **Security**.
2. Entra en **Advisories**.
3. Selecciona **Report a vulnerability**.
4. Envía el informe de forma privada.

Si no hay informe privado, crea un [Issue](https://github.com/yaleksandr89/human-gate/issues/new/choose) solicitando un canal privado **sin incluir detalles de la vulnerabilidad**. No publiques métodos de evasión, exploits ni datos sensibles.

Hasta acordar la divulgación, no publiques:

- instrucciones funcionales para eludir el captcha o código de explotación;
- identificadores o contenido de sesiones reales, formularios o datos personales;
- secretos, credenciales, registros privados ni material de sistemas de producción.

## Datos que debe contener el informe

Si es posible, indica:

- versión de Human Gate o SHA del commit;
- versión de PHP, disponibilidad de `ext-gd` y detalles del entorno relevantes;
- tipo de captcha: `TextImage`, `CategorySelection` o `IconSequence`;
- operación afectada: `issue()`, `replace()`, `verify()`, generación de imágenes o almacenamiento en sesiones;
- pasos mínimos para reproducir con datos ficticios;
- resultado esperado y observado;
- posibles consecuencias y propuesta de corrección, si la conoces.

Utiliza datos ficticios; no adjuntes contenido de sesiones reales.

## Tratamiento de informes

El proyecto lo mantiene una sola persona. Los informes se revisan cuando es posible, sin promesa de plazo fijo. Los fallos confirmados se corrigen y se comprueba que no reaparezcan. Coordina la divulgación de detalles técnicos con la persona responsable.

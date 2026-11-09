# Contribuir

## Elige un idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_en.md) | **Seleccionado** | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_fr.md) | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Gracias por contribuir a Human Gate. Este paquete Composer genera y verifica tres tipos de captcha en PHP: `TextImage`, `CategorySelection` e `IconSequence`. Se agradecen las correcciones y mejoras que aclaren la API pública o hagan más fiable la comprobación.

## Antes de empezar

- Comunica los errores reproducibles mediante [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues/new/choose).
- Para proponer funciones, utiliza el formulario correspondiente. Explica el problema y el comportamiento esperado antes de debatir la implementación.
- Comunica posibles vulnerabilidades siguiendo la [política de seguridad](https://github.com/yaleksandr89/human-gate/security/policy); no publiques instrucciones para eludir el captcha.
- Debate primero en un Issue los cambios importantes de API pública, caducidad, comprobación o formato de respuesta.

## Comportamiento que debe mantenerse

- El paquete admite `PHP ^8.4`, necesita `ext-gd` y no tiene dependencias externas de Composer en ejecución.
- Las respuestas se verifican en el servidor y cada captcha se vincula a una clave de formulario u operación (`Purpose`).
- Un captcha superado no se puede reutilizar. `Policy` limita el tiempo de validez y los intentos incorrectos.
- [`TextImage`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/text-image_es.md) comprueba los caracteres de una imagen; [`CategorySelection`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/category-selection_es.md) acepta tarjetas elegidas sin importar el orden; [`IconSequence`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/icon-sequence_es.md) comprueba el orden de los iconos.
- El paquete devuelve datos para mostrar y comprueba respuestas; la aplicación implementa la interfaz HTML.
- Los cambios deben respetar la arquitectura y evitar dependencias o funciones públicas innecesarias.

Los detalles de uso y las firmas están en la [referencia de API](https://github.com/yaleksandr89/human-gate/blob/master/docs/reference/api_es.md).

## Ramas y commits

Crea una rama independiente con un nombre que describa el cambio, por ejemplo:

```text
fix/icon-sequence-verification
feat/text-image-rendering
docs/integration-example
```

Utiliza Conventional Commits con una descripción en ruso después del prefijo:

```text
fix: исправить повторную проверку капчи
feat: добавить настройку отображения
test: добавить проверку обновления капчи
docs: уточнить интеграцию с сессией PHP
```

Cada commit debe recoger un cambio coherente sin refactorizaciones ajenas. Explica en el PR la razón, el efecto sobre la API y las comprobaciones realizadas.

## Comprobaciones locales

Instala las dependencias y ejecuta todas las comprobaciones:

```bash
composer install
composer check
```

También puedes ejecutar comprobaciones específicas:

```bash
composer test
composer analyse
composer cs:check
```

Para cambios solo de Markdown, comprueba los enlaces y ejecuta `git diff --check`. Si cambia PHP, ejecuta las pruebas pertinentes, el estilo y el análisis estático. La cobertura sirve como diagnóstico, no como objetivo porcentual.

## Pruebas y ejemplos

- Comprueba casos correctos e incorrectos, sobre todo caducidad, límites de intentos, reutilización, renovación y fallos de sesión.
- Utiliza respuestas, categorías, identificadores y datos de sesión ficticios. No incluyas datos de usuarios reales.
- Los ejemplos deben ajustarse a la API pública y funcionar sin configuración no documentada.
- Si cambia el comportamiento, actualiza conjuntamente la documentación rusa y sus traducciones.

## Pull Request

Antes de enviar un PR, verifica que los cambios correspondan a la tarea, que las pruebas cubran el comportamiento nuevo y que la documentación coincida con el código. Utiliza la plantilla; el mantenedor podrá solicitar aclaraciones o comprobaciones adicionales.

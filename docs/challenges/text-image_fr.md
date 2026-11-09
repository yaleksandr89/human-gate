# TextImage — CAPTCHA textuel

L’utilisateur voit une image avec six caractères et les saisit dans un formulaire. Human Gate génère l’image et vérifie la réponse côté serveur. Par défaut, la casse est ignorée.

## Configuration

Un CAPTCHA textuel utilise `TextImageStrategy` et `GdTextImageRenderer`. Exemple avec les réglages d’image et de casse :

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

Transmettez la stratégie à `ChallengeService`. [`ChallengeService::issue()`](../reference/api_fr.md#issue) crée un CAPTCHA textuel par défaut, sans préciser son type. Voir le [guide d’intégration](../guides/integration_fr.md) pour les sessions et la vérification.

## Options de réponse

`TextImageAnswerOptions::caseSensitive` détermine la prise en compte de la casse :

| Valeur | Vérification |
| --- | --- |
| `false` | Les majuscules et minuscules latines sont équivalentes |
| `true` | La casse doit correspondre à l’image |

**La réponse comporte toujours six caractères**, longueur non configurable. Les caractères sont choisis pour éviter certaines ressemblances. Le mode sensible à la casse utilise aussi d’autres lettres minuscules.

Passez la chaîne saisie à [`ChallengeService::verify()`](../reference/api_fr.md#verify) telle quelle. Le paquet effectue la conversion de casse autorisée. Seuls les espaces, tabulations et sauts de ligne `CR`/`LF` aux extrémités sont supprimés. Les espaces internes et la normalisation Unicode arbitraire sont refusés. Taille maximale : 64 octets.

## Options d’image

`TextImageRenderOptions` règle la génération de l’image :

| Paramètre | Par défaut | Valeurs autorisées |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | De `0` à `150` |
| `maxRotationDegrees` | `28` | De `0` à `28` degrés |

L’échelle modifie la taille en conservant les proportions. Le flou, le bruit et la rotation changent l’apparence, pas la bonne réponse.

La durée de validité se règle via [`Policy::ttlSeconds`](../reference/api_fr.md#policy), **pas** dans les options d’image. Par défaut : 180 secondes ; `new Policy(ttlSeconds: 300)` donne cinq minutes.

## Données d’affichage

Après la création, l’application reçoit [`ImagePresentation`](../reference/api_fr.md) avec `mimeType`, `bytes`, `width` et `height`. Le type est `image/png` ; `bytes` contient les octets PNG, pas une chaîne base64.

Avec l’échelle `100`, l’image mesure 240×80 pixels. L’application choisit comment envoyer l’image au navigateur et crée le champ de réponse.

Le rendu nécessite `ext-gd` avec FreeType. Noto Sans Regular est fourni ; sa [source](../../resources/fonts/SOURCE.txt) et sa [licence](../../resources/fonts/OFL.txt) se trouvent dans les ressources.

## Erreurs

Des options invalides provoquent `InvalidArgumentException`, un échec de rendu `RenderingException`. Une mauvaise réponse renvoie `VerificationCode::Incorrect` ou `AttemptsExhausted` quand les essais sont épuisés. Ce ne sont pas des erreurs de rendu.

Le navigateur reçoit l’image, pas la bonne réponse. Voir la [vérification côté serveur](../security-model_fr.md).

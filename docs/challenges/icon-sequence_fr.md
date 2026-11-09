# IconSequence — séquence d’icônes

L’utilisateur voit des icônes sur une image et choisit les mêmes dans l’ordre indiqué. **L’ordre de sélection est important.**

## Configuration

Ce CAPTCHA utilise `IconSequenceStrategy`. Avec les options par défaut :

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

Pour personnaliser les paramètres, passez-les explicitement :

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

Enregistrez `IconSequenceStrategy` dans `ChallengeService` et indiquez `ChallengeKind::IconSequence` à [`ChallengeService::issue()`](../reference/api_fr.md#issue). Voir le [guide d’intégration](../guides/integration_fr.md).

## Nombre et ordre des icônes

| Paramètre de `IconSequenceOptions` | Par défaut | Valeurs autorisées |
| --- | --- | --- |
| `sequenceLength` | `4` | De `3` à `6` icônes |
| `choiceCount` | `8` | Au moins deux choix de plus que la séquence, maximum `12` |

Au moins deux choix supplémentaires sont ajoutés à la séquence correcte. Pour quatre icônes, 6 à 12 choix sont possibles (huit par défaut). L’utilisateur sélectionne les quatre bonnes icônes dans l’ordre affiché.

## Options d’image

| Paramètre de `IconSequenceRenderOptions` | Par défaut | Valeurs autorisées |
| --- | --- | --- |
| `minIconSize` | `72` | De `72` à `96` pixels |
| `maxIconSize` | `80` | De `72` à `96`, pas moins que `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`, `Light`, `Standard`, `Strong` |
| `noisePercent` | `150` | De `0` à `150` |
| `maxRotationDegrees` | `90` | De `0` à `90` degrés |

Le flou, le bruit et la rotation s’appliquent à l’image de la séquence ; les images des choix restent nettes. La taille et l’angle doivent permettre de loger les icônes tournées dans les 128 pixels de hauteur de l’image cible.

La validité dépend du réglage commun [`Policy::ttlSeconds`](../reference/api_fr.md#policy), pas des options d’image. Par défaut : 180 secondes.

## Données d’affichage

[`IconSequencePresentation`](../reference/api_fr.md) contient :

| Champ | Données reçues par l’application |
| --- | --- |
| `target` | Image de la séquence à reproduire |
| `requiredSelections` | Nombre d’icônes à choisir |
| `choices` | Choix `IconSequenceChoice` avec `token` et `image` |

Les images sont des `ImagePresentation` contenant des octets PNG. Chaque image de choix mesure 96×96 pixels ; l’image cible mesure 128 pixels de haut, avec une largeur variable.

`IconSequenceChoice` contient uniquement un jeton et une image. Aucun nom d’icône ni ordre correct n’est envoyé au navigateur.

## Format de réponse

L’application joint les jetons des icônes choisies par des virgules **dans leur ordre exact de sélection** et transmet la chaîne à [`ChallengeService::verify()`](../reference/api_fr.md#verify).

Chaque jeton contient 32 caractères hexadécimaux minuscules. Une réponse comporte 3 à 6 jetons distincts et au plus 197 octets. Espaces, doublons et changement d’ordre sont interdits. Le paquet ne trie ni ne normalise.

Après un [`ChallengeService::replace()`](../reference/api_fr.md#replace) réussi, effacez la sélection et affichez les nouvelles images. Voir le [cycle de vie](../reference/lifecycle_fr.md).

## Vérification et erreurs

Le serveur vérifie l’ordre. Le navigateur ne reçoit ni noms d’icônes ni données cachées révélant la bonne séquence. Voir le [modèle de sécurité](../security-model_fr.md).

Le paquet contient 32 icônes issues de Tabler Icons `v3.48.0`, sous licence MIT. Leur [source](../../resources/icons/tabler/SOURCE.txt) et leur [licence](../../resources/icons/tabler/LICENSE.txt) se trouvent dans les ressources.

Des options invalides déclenchent `InvalidArgumentException`, des erreurs d’image `RenderingException` et une mauvaise réponse `VerificationCode::Incorrect` ou `AttemptsExhausted` lorsque les essais sont épuisés.

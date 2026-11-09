# CategorySelection — choix de cartes par catégorie

L’utilisateur voit une catégorie et sélectionne toutes les cartes correspondantes. Par exemple, il choisit les fruits parmi des fruits et des outils. **L’ordre de sélection est indifférent.**

## Configuration et catégories

L’application fournit les catégories et les libellés. `CategorySelectionStrategy` choisit des cartes aléatoires et vérifie l’ensemble sélectionné.

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

Enregistrez la stratégie dans `ChallengeService` puis indiquez `ChallengeKind::CategorySelection` à [`ChallengeService::issue()`](../reference/api_fr.md#issue). Le [guide d’intégration](../guides/integration_fr.md) présente le montage complet et les sessions.

Le catalogue contient de 2 à 16 catégories avec 1 à 32 éléments chacune. Chaque catégorie cible doit disposer d’assez de cartes correctes et les autres de distracteurs. Noms et libellés doivent être uniques ; évitez les catégories dont le sens se chevauche.

Les libellés sont en UTF-8 valide, de 1 à 80 **octets** et non caractères, sans caractères de contrôle ni espaces aux extrémités. Le paquet renvoie du texte brut, pas du HTML.

## Nombre de cartes

`CategorySelectionOptions` règle le total des cartes et le nombre de bonnes sélections :

| Paramètre | Par défaut | Valeurs autorisées |
| --- | --- | --- |
| `cardCount` | `6` | De `3` à `12` cartes |
| `targetCount` | `2` | De `1` à moins de `cardCount` |

Les autres cartes proviennent d’autres catégories. La validité et le nombre d’essais dépendent de [`Policy`](../reference/api_fr.md#policy), pas de `CategorySelectionOptions`.

## Données d’affichage

[`CategorySelectionPresentation`](../reference/api_fr.md) contient `category` et une liste `cards`. Chaque `CategorySelectionCard` possède le libellé visible `label` et un `token` aléatoire.

Le navigateur reçoit les cartes et leurs jetons, pas l’information sur les bons choix. La vérification se fait avec les données du serveur.

## Format de réponse

L’application joint les jetons des cartes sélectionnées par des virgules et transmet la chaîne à [`ChallengeService::verify()`](../reference/api_fr.md#verify). **L’ordre ne compte pas** ; le paquet compare l’ensemble.

Chaque jeton comporte 32 caractères hexadécimaux minuscules (`0–9`, `a–f`). Les doublons sont interdits et la réponse complète ne dépasse pas 512 octets. Pas d’espaces ni de changement de casse ; inutile de trier ou normaliser.

Après un [`ChallengeService::replace()`](../reference/api_fr.md#replace) réussi, affichez les nouvelles cartes et effacez les choix précédents. Les anciens jetons ne sont plus valables. Voir le [cycle de vie](../reference/lifecycle_fr.md).

## Erreurs

Un catalogue ou des options invalides provoquent `InvalidArgumentException`. Une mauvaise réponse renvoie `VerificationCode::Incorrect` ou `AttemptsExhausted` lorsque les essais sont épuisés. Les erreurs de stockage sont distinctes. Voir le [modèle de sécurité](../security-model_fr.md) et les [exceptions API](../reference/api_fr.md#exceptions).

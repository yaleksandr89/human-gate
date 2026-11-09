# Cycle de vie du CAPTCHA

Human Gate suit chaque CAPTCHA créé, son expiration et les mauvaises tentatives. Une bonne réponse ne peut pas être réutilisée. Les limites communes se règlent avec [`Policy`](api_fr.md#policy).

## Validité et limites

| Paramètre | Par défaut | Signification |
| --- | --- |
| `ttlSeconds` | `180` | Nombre de secondes pendant lesquelles le CAPTCHA est valide |
| `maxWrongAttempts` | `3` | Nombre de mauvaises réponses autorisées |
| `maxActive` | `12` | Nombre maximal de CAPTCHA actifs dans un stockage |
| `maxActivePerPurpose` | `4` | Nombre maximal de CAPTCHA actifs pour un formulaire ou une opération (`Purpose`) |
| `maxTombstones` | `32` | Nombre maximal d’enregistrements conservés pour les CAPTCHA terminés |
| `terminalRetentionSeconds` | `180` | Durée de conservation des enregistrements terminés, sauf suppression anticipée par la limite de quantité |

Avec le stockage standard en session PHP, ces limites s’appliquent à une seule session. Par exemple, jusqu’à quatre CAPTCHA peuvent être actifs pour `contact.send` et quatre pour `account.register`, mais jamais plus de douze au total. Les limites sur les enregistrements terminés évitent leur accumulation.

Toutes les valeurs doivent être positives et `maxActivePerPurpose` ne doit pas dépasser `maxActive`. Pour cinq minutes, utilisez `new Policy(ttlSeconds: 300)`. Ces limites ne régulent pas la fréquence des requêtes HTTP.

## Création du CAPTCHA

[`ChallengeService::issue()`](api_fr.md#issue) crée un CAPTCHA du type choisi. En cas de succès, l’application reçoit son identifiant, sa date d’expiration et ses données d’affichage.

Si la limite de CAPTCHA actifs est atteinte, le résultat est `LifecycleCode::CapacityExceeded` et aucun CAPTCHA n’est créé. Le type par défaut est le [CAPTCHA textuel](../challenges/text-image_fr.md) ; les autres sont choisis via `ChallengeKind`.

## Vérification de la réponse

[`ChallengeService::verify()`](api_fr.md#verify) reçoit l’identifiant, la clé de formulaire serveur (`Purpose`) et la réponse. Utilisez la même clé qu’à la création. Le type est lu dans les données sauvegardées, non fourni par le navigateur.

Chaque type a ses règles. L’ordre des jetons est indifférent pour [`CategorySelection`](../challenges/category-selection_fr.md), mais essentiel pour [`IconSequence`](../challenges/icon-sequence_fr.md). Un format incorrect compte comme mauvaise tentative.

## Mauvaises réponses

Chaque mauvaise réponse incrémente le compteur. Tant qu’il reste des essais, le résultat est `VerificationCode::Incorrect` et l’utilisateur peut réessayer le même CAPTCHA.

À la limite, le résultat devient `VerificationCode::AttemptsExhausted`. Par défaut, **la troisième erreur** termine le CAPTCHA. Les nouvelles tentatives avec cet identifiant sont refusées et la durée n’est pas prolongée.

Pour continuer, créez un nouveau CAPTCHA via [`issue()`](api_fr.md#issue) : un CAPTCHA dont les essais sont épuisés ne peut pas être renouvelé avec `replace()`.

## Bonne réponse

Une bonne réponse renvoie `VerificationCode::Accepted`. Le CAPTCHA ne peut plus être validé. Tant que le résultat est conservé, une nouvelle tentative renvoie `AlreadyConsumed`.

## Renouvellement du CAPTCHA

[`ChallengeService::replace()`](api_fr.md#replace) crée un nouveau CAPTCHA à la place de l’ancien. L’application reçoit un nouvel identifiant et des données d’affichage ; les anciennes réponses et sélections ne sont plus valables.

Un CAPTCHA actif peut être remplacé, ainsi qu’un CAPTCHA expiré tant que son enregistrement existe. Remplacer un CAPTCHA actif n’augmente pas le nombre d’actifs ; pour un expiré, une place libre peut être nécessaire.

Un CAPTCHA déjà réussi, remplacé ou sans essais restants ne peut pas être renouvelé par cette méthode. Le nouveau type est textuel par défaut ; précisez un autre type si nécessaire.

## Expiration et suppression des enregistrements

Un CAPTCHA reste valable tant que l’heure actuelle précède `expiresAt`. Dès cet instant, aucune réponse n’est acceptée.

Les informations d’un CAPTCHA expiré peuvent rester stockées temporairement. Tant qu’elles existent, `verify()` renvoie `Expired` et `replace()` peut en créer un nouveau. Après suppression, l’ancien identifiant renvoie `NotFound`.

Les enregistrements terminés sont conservés pour une durée limitée. Si leur nombre dépasse `maxTombstones`, les plus anciens peuvent être supprimés plus tôt. Un ancien identifiant ne renvoie donc pas toujours `Expired`, mais `NotFound` après suppression.

## Association à un formulaire

`Purpose` est une clé textuelle de formulaire ou d’opération, par exemple `contact.send` pour envoyer un message et `account.register` pour s’inscrire. L’application définit cette clé sur le serveur lors de la création et de la vérification. Une autre clé provoque `PurposeMismatch` et le refus de la réponse. Format décrit dans la [référence API](api_fr.md#types-principaux).

## Résultats des méthodes

| Méthode | En cas de succès | Autres résultats |
| --- | --- |
| [`issue()`](api_fr.md#issue) | `Issued` et nouveau CAPTCHA | `CapacityExceeded` |
| [`replace()`](api_fr.md#replace) | `Refreshed` et nouveau CAPTCHA | `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced` |
| [`verify()`](api_fr.md#verify) | `Accepted` | `Incorrect`, `AttemptsExhausted`, `Expired`, `NotFound`, `PurposeMismatch`, `AlreadyConsumed`, `Replaced` |

Les erreurs de stockage ou de génération d’image déclenchent des exceptions, pas `Incorrect`. Voir la [référence API](api_fr.md#exceptions).

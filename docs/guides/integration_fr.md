# Intégration de Human Gate

Human Gate crée des CAPTCHA et vérifie les réponses sur le serveur. L’application affiche le CAPTCHA dans son formulaire et ne poursuit le traitement qu’après une bonne réponse.

## Fonctionnement de la vérification

L’application appelle [`ChallengeService::issue()`](../reference/api_fr.md#issue) et reçoit l’identifiant et les données d’affichage du CAPTCHA, qu’elle transmet au navigateur.

À l’envoi du formulaire, l’application récupère l’identifiant et la réponse, puis appelle [`ChallengeService::verify()`](../reference/api_fr.md#verify). `VerificationCode::Accepted` indique une vérification réussie. La bonne réponse et les choix corrects restent sur le serveur.

[`ChallengeService::replace()`](../reference/api_fr.md#replace) crée un nouveau CAPTCHA avec un nouvel identifiant.


## Connexion du paquet

`ChallengeService` se construit avec un stockage, les [réglages communs `Policy`](../reference/api_fr.md#policy), la source de temps `SystemClock` et les stratégies choisies. Cet exemple enregistre les trois types avec une session PHP standard. Les catégories servent uniquement d’exemple.

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

Appelez cette fonction après `session_start()`. Les trois stratégies permettent aussi de vérifier les CAPTCHA de types différents déjà créés dans la même session. Voir les guides [`TextImage`](../challenges/text-image_fr.md), [`CategorySelection`](../challenges/category-selection_fr.md) et [`IconSequence`](../challenges/icon-sequence_fr.md).


## Créer un CAPTCHA

Dans le traitement d’une requête, l’application ouvre la session et crée le type voulu. Exemple pour les cartes :

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

Un [`issue()`](../reference/api_fr.md#issue) réussi renvoie `IssueResult` avec `LifecycleCode::Issued` et un `IssuedChallenge` contenant `id`, `kind`, `expiresAt`, `presentation`. À la limite de CAPTCHA actifs, `challenge` vaut `null` et le code indique pourquoi.

`Purpose` est la clé du formulaire ou de l’opération, par exemple `contact.send` pour les messages et `account.register` pour l’inscription. La même clé sert à la création et à la vérification ; elle doit être définie côté serveur, pas reçue du navigateur.

Sans second argument, `issue()` crée un CAPTCHA textuel. Les autres types utilisent `ChallengeKind::CategorySelection` ou `ChallengeKind::IconSequence`.


## Afficher le CAPTCHA

Le paquet fournit les données ; l’application choisit le HTML et le style :

| Type de CAPTCHA | Données d’affichage | Réponse utilisateur |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image_fr.md) | `ImagePresentation` : type, octets et dimensions | Caractères saisis |
| [`CategorySelection`](../challenges/category-selection_fr.md) | `CategorySelectionPresentation` : catégorie et cartes | Jetons séparés par virgules, ordre indifférent |
| [`IconSequence`](../challenges/icon-sequence_fr.md) | `IconSequencePresentation` : image cible et choix | Jetons séparés par virgules dans l’ordre sélectionné |

`ImagePresentation::bytes` contient les octets bruts de l’image. L’application choisit comment les envoyer au navigateur. Les noms de catégories et de cartes sont du texte, pas du HTML.

Les trois types sont vérifiés côté serveur. Le navigateur reçoit seulement les données nécessaires à l’affichage et à la saisie.


## Vérifier la réponse

La réponse arrive dans une requête distincte. Ouvrez **la même session utilisateur**, recréez `ChallengeService` et transmettez l’identifiant et la réponse :

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

`challengeId` et `answer` sont des noms de champs d’exemple. Analysez l’identifiant avec `ChallengeId::fromString()`. Passez la réponse brute à `verify()` sans changer la casse, trier les jetons ou effectuer d’autres transformations : chaque type a ses propres règles.

`VerificationCode::Accepted` signifie que le CAPTCHA a été réussi. Une bonne réponse n’est acceptée qu’une fois ; une seconde vérification du même identifiant ne peut pas réussir.


## Renouveler le CAPTCHA

[`ChallengeService::replace()`](../reference/api_fr.md#replace) reçoit l’ancien identifiant et renvoie un nouveau CAPTCHA. Exemple pour les cartes :

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

Après un renouvellement réussi, affichez les nouvelles données et effacez la saisie et les sélections précédentes. Un CAPTCHA expiré peut être renouvelé tant que son enregistrement existe ; après suppression le résultat est `NotFound`. Voir le [cycle de vie](../reference/lifecycle_fr.md).


## Stockage dans une session PHP

Les exemples utilisent [`NativeSessionChallengeStore`](../reference/api_fr.md#stockage-et-temps). L’application ouvre une session PHP avec `session_start()`, appelle `ActiveNativeSessionScope::capture()` puis transmet ce contexte au stockage. Aucun service de stockage supplémentaire n’est nécessaire.

L’adaptateur prend en charge le gestionnaire de sessions `files` et les formats `php` ou `php_serialize`.

Après une modification réussie, l’adaptateur enregistre les données et ferme la session. Pour modifier ensuite `$_SESSION`, l’application doit la rouvrir. Une opération sans changement peut la laisser ouverte ; l’application gère la suite.

Les opérations modifiant les données doivent se faire avant l’envoi des en-têtes HTTP. Effectuez les opérations externes longues après la fin du travail sur la session. Un échec de stockage lève `NativeSessionStorageException`, ce n’est pas une mauvaise réponse.

Un autre stockage peut être fourni via `ChallengeStore` ; ses exigences figurent dans la [référence API](../reference/api_fr.md#extension-du-paquet).


## Gestion des résultats

| Code | Signification |
| --- | --- |
| `Issued`, `Refreshed` | CAPTCHA créé ou renouvelé ; nouvelles données reçues |
| `CapacityExceeded` | Limite de CAPTCHA actifs atteinte |
| `Accepted` | Bonne réponse acceptée ; CAPTCHA non réutilisable |
| `Incorrect` | Mauvaise réponse, essais restants |
| `AttemptsExhausted` | Limite de mauvaises réponses atteinte |
| `Expired` | CAPTCHA expiré |
| `AlreadyConsumed`, `Replaced` | Identifiant inutilisable pour une nouvelle vérification |
| `NotFound` | CAPTCHA introuvable ou enregistrement supprimé |
| `PurposeMismatch` | CAPTCHA d’un autre formulaire ou opération (`Purpose`) |

`issue()` et `replace()` renvoient `IssueResult`, tandis que `verify()` renvoie `VerificationCode`. Certains noms se ressemblent, mais le type dépend de la méthode. Voir la [référence API](../reference/api_fr.md).


## Erreurs courantes

- Appeler `ActiveNativeSessionScope::capture()` avant `session_start()`.
- Modifier `$_SESSION` après fermeture sans rouvrir la session.
- Omettre une stratégie pour un type de CAPTCHA encore présent dans la session.
- Oublier `ChallengeKind` pour les cartes ou les icônes.
- Recevoir `Purpose` du formulaire au lieu de le définir côté serveur.
- Envoyer des libellés de cartes au lieu des jetons, ou trier les jetons `IconSequence`.
- Poursuivre le formulaire après un problème de stockage ou un résultat autre que `Accepted`.

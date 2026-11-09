# Référence API

Le point d’entrée principal est `ChallengeService` : création et renouvellement des CAPTCHA, vérification des réponses et résultats typés. Cette page décrit les signatures et options ; le [guide d’intégration](../guides/integration_fr.md) montre leur utilisation.

Tous les types cités appartiennent à `Yaleksandr\HumanGate\`. Les sous-espaces indiqués dans les tableaux sont relatifs à ce préfixe.

## ChallengeService

Le constructeur reçoit un stockage, des paramètres communs, une source de temps et les stratégies :

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

Une stratégie par type suffit. Si différents types de CAPTCHA restent actifs dans une session, leurs stratégies doivent être disponibles pour la vérification.

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Crée un CAPTCHA. En cas de succès, `IssueResult` contient `LifecycleCode::Issued` et `IssuedChallenge`. Si la limite des CAPTCHA actifs est atteinte, le code est `CapacityExceeded` et `challenge === null`.

Le type par défaut est [`TextImage`](../challenges/text-image_fr.md). Pour les autres, utilisez `ChallengeKind::CategorySelection` ou `ChallengeKind::IconSequence`.

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Crée un nouveau CAPTCHA à la place de l’ancien. En cas de succès, renvoie `LifecycleCode::Refreshed` et un nouvel `IssuedChallenge`. L’ancien identifiant n’est plus valable.

Le renouvellement fonctionne sur un CAPTCHA actif ou expiré si son enregistrement est encore conservé. Le nouveau type est `TextImage` par défaut ; précisez `kind` si nécessaire. Voir le [cycle de vie](lifecycle_fr.md).

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Vérifie la réponse et renvoie `VerificationCode`. `Accepted` signifie qu’elle est correcte et que le même CAPTCHA ne peut plus être utilisé.

| Valeur de `VerificationCode` | Signification |
| --- | --- |
| `Accepted` | Bonne réponse, CAPTCHA réussi |
| `Incorrect` | Mauvaise réponse, essais restants |
| `AttemptsExhausted` | Limite de mauvaises réponses atteinte |
| `Expired` | CAPTCHA expiré |
| `NotFound` | CAPTCHA introuvable ou ancien enregistrement supprimé |
| `PurposeMismatch` | CAPTCHA d’un autre formulaire ou opération (`Purpose` différent) |
| `AlreadyConsumed` | Bonne réponse déjà acceptée pour ce CAPTCHA |
| `Replaced` | CAPTCHA déjà remplacé |

L’application définit le statut HTTP et le message utilisateur. Voir l’[exemple de `verify()`](../guides/integration_fr.md).

## Policy

`Challenge\Policy` règle la validité, les mauvaises réponses et les limites de stockage. Valeurs par défaut :

```php
<?php

use Yaleksandr\HumanGate\Challenge\Policy;

$policy = new Policy(
    ttlSeconds: 180,
    maxWrongAttempts: 3,
    maxActive: 12,
    maxActivePerPurpose: 4,
    maxTombstones: 32,
    terminalRetentionSeconds: 180,
);
```

| Paramètre | Par défaut | Rôle |
| --- | --- | --- |
| `ttlSeconds` | `180` | Durée de validité du CAPTCHA en secondes |
| `maxWrongAttempts` | `3` | Nombre de mauvaises réponses autorisées |
| `maxActive` | `12` | Nombre maximal de CAPTCHA actifs dans un stockage |
| `maxActivePerPurpose` | `4` | Nombre maximal d’actifs par formulaire ou opération (`Purpose`) |
| `maxTombstones` | `32` | Nombre maximal d’enregistrements conservés pour les CAPTCHA terminés |
| `terminalRetentionSeconds` | `180` | Durée de conservation en secondes, sauf suppression anticipée par la limite de nombre |

Avec [`NativeSessionChallengeStore`](#stockage-et-temps), les limites s’appliquent **par session PHP**, pas à tout le site. `contact.send` et `account.register` peuvent chacun avoir quatre CAPTCHA actifs, sans dépasser douze au total dans la session.

Après réussite, renouvellement, expiration ou épuisement des essais, le paquet conserve temporairement la raison de fin afin de distinguer `AlreadyConsumed` de `Expired`. `maxTombstones` et `terminalRetentionSeconds` bornent le nombre et la durée de ces enregistrements. Après suppression, un ancien identifiant peut renvoyer `NotFound`.

Les valeurs doivent être positives et `maxActivePerPurpose` ne peut pas dépasser `maxActive`. Pour cinq minutes : `new Policy(ttlSeconds: 300)`. Ces limites **ne régulent pas la fréquence des requêtes HTTP**. Voir le [cycle de vie](lifecycle_fr.md).

## Types principaux

| Type | Rôle |
| --- | --- |
| `Challenge\ChallengeId` | Identifiant du CAPTCHA : `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Clé du formulaire ou de l’opération : `__construct(string $value)`, `value(): string` |
| `Challenge\ChallengeKind` | Type de CAPTCHA : `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Code `code` et `challenge` ou `null` |
| `Challenge\IssuedChallenge` | Nouveau CAPTCHA : `id`, `kind`, `expiresAt`, `presentation` |

`ChallengeId` contient exactement 64 caractères hexadécimaux minuscules. `Purpose` contient 1–64 octets ASCII : premier caractère = lettre latine minuscule ou chiffre, puis ces caractères, point, underscore ou tiret.

L’application définit `Purpose` sur le serveur : par exemple `contact.send` pour les messages et `account.register` pour l’inscription. La même clé sert à la création et à la vérification, jamais une valeur reçue du navigateur. `IssuedChallenge::expiresAt` est l’instant d’expiration exprimé en secondes Unix.

## TextImage

Classes principales de `TextImage\` :

```php
TextImageStrategy::__construct(
    TextImageRenderer $renderer,
    TextImageAnswerOptions $answerOptions = new TextImageAnswerOptions(),
)

TextImageAnswerOptions::__construct(bool $caseSensitive = false)

GdTextImageRenderer::__construct(
    TextImageRenderOptions $options = new TextImageRenderOptions(),
)

TextImageRenderOptions::__construct(
    int $scalePercent = 100,
    TextImageBlur $blur = TextImageBlur::Standard,
    int $lightNoisePercent = 100,
    int $maxRotationDegrees = 28,
)
```

`TextImageBlur` accepte `None`, `Light`, `Standard`, `Strong`. Échelle : `100`, `105`, `110`, `115`, `120` ; bruit : `0`–`150` ; rotation : `0`–`28` degrés. La casse se règle via `caseSensitive`. La réponse compte toujours six caractères. Voir le [guide du CAPTCHA textuel](../challenges/text-image_fr.md).

## CategorySelection

Classes principales de `CategorySelection\` :

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

De 2 à 16 catégories avec 1 à 32 éléments chacune. `cardCount` va de 3 à 12 ; `targetCount` vaut au moins 1, mais moins que `cardCount`. Les libellés sont uniques, en UTF-8, de 1 à 80 octets, sans caractères de contrôle ni espaces aux extrémités.

La réponse consiste en jetons de cartes séparés par des virgules ; l’ordre est indifférent. Voir le [guide de sélection de cartes](../challenges/category-selection_fr.md).

## IconSequence

Classes principales de `IconSequence\` :

```php
IconSequenceStrategy::__construct(
    IconSequenceOptions $options = new IconSequenceOptions(),
    IconSequenceRenderOptions $renderOptions = new IconSequenceRenderOptions(),
)

IconSequenceOptions::__construct(int $sequenceLength = 4, int $choiceCount = 8)

IconSequenceRenderOptions::__construct(
    int $minIconSize = 72,
    int $maxIconSize = 80,
    IconSequenceBlur $blur = IconSequenceBlur::Strong,
    int $noisePercent = 150,
    int $maxRotationDegrees = 90,
)
```

Séquence : 3–6 icônes. Choix : au moins deux de plus que la séquence, maximum 12 (par exemple 6–12 choix pour quatre icônes). Taille : 72–96 pixels ; bruit : 0–150 ; rotation : 0–90 degrés. `IconSequenceBlur` accepte `None`, `Light`, `Standard`, `Strong`.

Les jetons sont transmis dans l’ordre de sélection. Voir le [guide des séquences d’icônes](../challenges/icon-sequence_fr.md).

## Données d’affichage

Tous les types sont dans `Presentation\` :

| Type | Champs |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` contient des octets d’image. Ce type accepte `image/png`, `image/jpeg`, `image/webp`, des dimensions de 1 à 4096 pixels de chaque côté et jusqu’à 131072 octets de données. Les générateurs intégrés produisent du PNG.

La sélection de cartes fournit la catégorie et les libellés ; la séquence d’icônes fournit l’image cible et des choix comportant jeton et image. La bonne réponse n’est pas incluse.

## Stockage et temps

| Type | Rôle |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Capture le contexte d’une session PHP déjà ouverte |
| `Session\NativeSessionChallengeStore` | Stocke les données du CAPTCHA en session PHP |
| `Clock\SystemClock` | Source de temps : `now(): int` renvoie des secondes Unix |

`NativeSessionChallengeStore` prend `ActiveNativeSessionScope`. Il prend en charge `files` et la sérialisation `php` ou `php_serialize`. L’application appelle d’abord `session_start()` ; l’adaptateur ferme la session après modification. Voir le [guide d’intégration](../guides/integration_fr.md).

## Exceptions

Les mauvaises réponses sont représentées par `VerificationCode`. Les erreurs de configuration ou d’exécution lèvent des exceptions :

| Exception | Cause |
| --- | --- |
| `InvalidArgumentException` | Arguments ou options invalides |
| `Exception\UnsupportedChallengeKindException` | Aucune stratégie pour le type demandé |
| `Exception\RenderingException` | Échec de génération d’une image |
| `Random\RandomException` | Échec du générateur aléatoire |
| `Session\NativeSessionStorageException` | Échec de lecture, d’écriture ou de vérification des données en session PHP |

`NativeSessionStorageException::reason` contient `NativeSessionStorageFailureReason` : `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. Ne transmettez pas directement les détails techniques à l’utilisateur.

## Extension du paquet

Du stockage, des sources de temps ou des stratégies personnalisés peuvent être fournis via `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy` et `Port\TextImageRenderer`.

`ChallengeStore::atomic()` doit appliquer les modifications sans conflit entre requêtes concurrentes et confirmer leur enregistrement avant de renvoyer. Un échec de stockage ne doit jamais valider un CAPTCHA.

`Clock::now()` donne l’heure Unix actuelle en secondes pour calculer la durée de validité : c’est une source de temps, pas un compte à rebours. `ChallengeStrategy::kind()`, `prepare()` et `verify()` définissent la stratégie ; `TextImageRenderer::render()` renvoie un `ImagePresentation`. Pour l’usage courant, préférez les implémentations du [guide d’intégration](../guides/integration_fr.md).

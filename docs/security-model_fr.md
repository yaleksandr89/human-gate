# Modèle de sécurité

Human Gate génère des CAPTCHA et vérifie les réponses sur le serveur. Il limite leur durée de validité et le nombre de mauvaises tentatives, et empêche la réutilisation d’un CAPTCHA réussi.

## Vérification côté serveur

Lors de la création, l’application fournit une clé serveur [`Purpose`](reference/api_fr.md#types-principaux) indiquant le formulaire ou l’opération. Par exemple, `contact.send` peut désigner le formulaire de contact et `account.register` l’inscription. Un CAPTCHA créé pour l’un ne peut pas valider l’autre. La bonne réponse et les données de vérification restent sur le serveur ; le navigateur ne reçoit que l’identifiant et les données d’affichage.

À l’envoi du formulaire, l’application transmet l’identifiant et la réponse à [`ChallengeService::verify()`](reference/api_fr.md#verify). Le serveur vérifie la réponse : l’identifiant seul ne prouve pas que le CAPTCHA a été réussi.

Le paquet ne conserve pas la bonne réponse en clair. Pour [`CategorySelection`](challenges/category-selection_fr.md) et [`IconSequence`](challenges/icon-sequence_fr.md), le navigateur reçoit des jetons de choix aléatoires, sans indication des bons choix. L’ordre des cartes est indifférent, celui des icônes est important.

## Expiration et réutilisation

La durée de validité et la limite de mauvaises réponses se règlent avec [`Policy`](reference/api_fr.md#policy). Par défaut, la durée est de 180 secondes ; la troisième erreur termine la vérification.

Une bonne réponse renvoie `VerificationCode::Accepted`, puis le CAPTCHA ne peut plus être réutilisé. Tant que l’enregistrement est conservé, une nouvelle tentative renvoie `AlreadyConsumed` ; après sa suppression, `NotFound`. Aucun des deux résultats ne permet une nouvelle validation.

Les CAPTCHA expirés sont également refusés. Voir les [règles de renouvellement et de conservation](reference/lifecycle_fr.md).

## Stockage des données

Le stockage intégré [`NativeSessionChallengeStore`](reference/api_fr.md#stockage-et-temps) fonctionne avec une session PHP ouverte par l’application et le gestionnaire `files`. Après une modification, le paquet enregistre les données et vérifie l’écriture. Un échec de stockage déclenche une exception, jamais une validation réussie.

Le [guide d’intégration](guides/integration_fr.md) présente l’utilisation du stockage et des sessions PHP.

## Limites des CAPTCHA actifs

[`Policy::maxActive` et `Policy::maxActivePerPurpose`](reference/api_fr.md#policy) limitent le nombre de CAPTCHA actifs conservés simultanément. Ces valeurs ne comptent pas les requêtes HTTP pendant une période.

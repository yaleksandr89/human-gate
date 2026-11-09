# Contribuer

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_zh.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_de.md) |

Merci de contribuer à Human Gate. Ce paquet Composer crée et vérifie trois types de CAPTCHA en PHP : `TextImage`, `CategorySelection` et `IconSequence`. Les corrections et améliorations sont bienvenues si elles rendent l’API publique plus claire ou la vérification plus fiable.

## Avant de commencer

- Signalez les bugs reproductibles dans [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues/new/choose).
- Pour proposer une fonctionnalité, utilisez le formulaire dédié. Décrivez le problème et le comportement souhaité avant de discuter de l’implémentation.
- Signalez les vulnérabilités potentielles selon la [politique de sécurité](https://github.com/yaleksandr89/human-gate/security/policy) sans publier de méthode de contournement.
- Discutez d’abord dans une Issue des changements majeurs d’API publique, d’expiration, de vérification ou de format de réponse.

## Comportement à préserver

- Le paquet prend en charge `PHP ^8.4`, requiert `ext-gd` et n’a aucune dépendance Composer tierce à l’exécution.
- La vérification se fait sur le serveur ; chaque CAPTCHA est lié à une clé de formulaire ou d’opération (`Purpose`).
- Un CAPTCHA réussi ne peut pas être réutilisé. `Policy` limite sa durée de validité et les mauvaises réponses.
- [`TextImage`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/text-image_fr.md) vérifie les caractères d’une image ; [`CategorySelection`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/category-selection_fr.md) accepte des cartes indépendamment de leur ordre ; [`IconSequence`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/icon-sequence_fr.md) vérifie l’ordre des icônes.
- Le paquet fournit les données d’affichage et vérifie les réponses ; l’application réalise l’interface HTML.
- Les modifications respectent l’architecture existante et évitent les dépendances ou fonctions publiques inutiles.

Les détails et signatures figurent dans la [référence API](https://github.com/yaleksandr89/human-gate/blob/master/docs/reference/api_fr.md).

## Branches et commits

Créez une branche distincte dont le nom décrit l’objectif, par exemple :

```text
fix/icon-sequence-verification
feat/text-image-rendering
docs/integration-example
```

Utilisez Conventional Commits avec une description en russe après le préfixe :

```text
fix: исправить повторную проверку капчи
feat: добавить настройку отображения
test: добавить проверку обновления капчи
docs: уточнить интеграцию с сессией PHP
```

Chaque commit doit porter sur une modification cohérente, sans refactorisation sans rapport. Décrivez dans la PR la raison du changement, son effet sur l’API et les contrôles effectivement exécutés.

## Vérifications locales

Installez les dépendances puis lancez l’ensemble des contrôles :

```bash
composer install
composer check
```

Vous pouvez aussi lancer les contrôles séparément :

```bash
composer test
composer analyse
composer cs:check
```

Pour une modification Markdown uniquement, vérifiez les liens et lancez `git diff --check`. Pour une modification PHP, exécutez les tests pertinents, le contrôle de style et l’analyse statique. La couverture est un outil de diagnostic, pas un pourcentage cible.

## Tests et exemples

- Testez les succès et les échecs, notamment l’expiration, les limites d’essais, la réutilisation, le renouvellement et les erreurs de session.
- Utilisez des réponses, catégories, identifiants et données de session fictifs. Ne publiez pas de données personnelles réelles.
- Les exemples doivent correspondre à l’API publique actuelle et fonctionner sans configuration non documentée.
- Si le comportement change, actualisez ensemble la documentation russe et les traductions concernées.

## Pull Request

Avant de soumettre une PR, vérifiez que les changements répondent à l’objectif annoncé, que les tests couvrent les modifications et que les documents correspondent au code. Utilisez le modèle de PR ; le responsable peut demander des précisions ou des contrôles supplémentaires.

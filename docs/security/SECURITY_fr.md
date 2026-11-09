# Politique de sécurité

## Choisir une langue

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/security/policy) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_zh.md) | **Sélectionné** | [Deutsch](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_de.md) |

## Versions prises en charge

Les correctifs de sécurité concernent la version stable actuelle de la branche `1.x` de Human Gate.

| Version | Prise en charge |
| --- | --- |
| `1.x` | Oui |

## Ce qui constitue une vulnérabilité

Les problèmes de sécurité possibles dans Human Gate comprennent notamment :

- l’acceptation d’une mauvaise réponse ou le contournement de la vérification du CAPTCHA ;
- la réutilisation acceptée d’un CAPTCHA déjà réussi ;
- le contournement de l’expiration ou de la limite de mauvaises réponses ;
- l’utilisation d’un CAPTCHA créé pour un formulaire afin de valider un autre formulaire avec un `Purpose` différent ;
- la divulgation au navigateur de la bonne réponse, des cartes correctes ou de l’ordre des icônes ;
- l’altération des données du CAPTCHA lors de la lecture ou de l’écriture d’une session PHP prise en charge ;
- le non-respect des limites de traitement des entrées non fiables ou de génération d’images entraînant un risque de sécurité ;
- la compromission du code source, des dépendances, de la CI ou des artefacts de publication.

## Modèle de sécurité

Human Gate crée et vérifie les CAPTCHA sur le serveur. Chaque CAPTCHA est lié à une clé de formulaire ou d’opération (`Purpose`), a une durée de validité et un nombre limité de mauvaises réponses, et ne peut pas être réutilisé après une validation réussie.

Les données de vérification restent sur le serveur. Le stockage fourni utilise les sessions PHP. Voir le [modèle de sécurité du paquet](https://github.com/yaleksandr89/human-gate/blob/master/docs/security-model_fr.md).

## Signaler une vulnérabilité

Utilisez de préférence **GitHub Private Vulnerability Reporting** si cette fonction est disponible dans le dépôt :

1. Ouvrez l’onglet **Security**.
2. Accédez à **Advisories**.
3. Sélectionnez **Report a vulnerability**.
4. Soumettez votre rapport en privé.

Si le signalement privé est indisponible, ouvrez une [Issue](https://github.com/yaleksandr89/human-gate/issues/new/choose) demandant un canal privé, **sans inclure les détails de la vulnérabilité**. Ne publiez pas de méthode de contournement, d’exploit ni d’informations sensibles.

Avant une divulgation coordonnée, ne publiez pas :

- de méthode fonctionnelle permettant de contourner le CAPTCHA ni de code d’exploitation ;
- d’identifiants ou de contenu de sessions réelles, de données de formulaires ou de données personnelles ;
- de secrets, d’identifiants de connexion, de journaux privés ni de données de production.

## Informations à joindre

Dans la mesure du possible, indiquez :

- la version de Human Gate ou le SHA du commit ;
- la version de PHP, la disponibilité de `ext-gd` et les détails utiles de l’environnement ;
- le type de CAPTCHA : `TextImage`, `CategorySelection` ou `IconSequence` ;
- l’opération concernée : `issue()`, `replace()`, `verify()`, génération d’image ou stockage en session ;
- les étapes minimales de reproduction avec des données fictives ;
- le comportement attendu et celui observé ;
- les conséquences possibles et une suggestion de correction, si vous en avez une.

Utilisez des données fictives. Ne joignez jamais de contenu de sessions réelles.

## Traitement des signalements

Le projet est maintenu par une seule personne. Les rapports sont examinés selon les disponibilités, sans délai de réponse garanti. Les problèmes confirmés sont corrigés avec des vérifications de non-régression. Merci de coordonner la divulgation des détails techniques avec le responsable du projet.

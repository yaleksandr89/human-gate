# Sicherheitsrichtlinie

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/security/policy) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/security/SECURITY_fr.md) | **Ausgewählt** |

## Unterstützte Versionen

Sicherheitskorrekturen werden für die aktuelle stabile Version der `1.x`-Reihe von Human Gate veröffentlicht.

| Version | Unterstützt |
| --- | --- |
| `1.x` | Ja |

## Was als Sicherheitslücke gilt

Zu möglichen Sicherheitsproblemen von Human Gate gehören insbesondere:

- das Akzeptieren falscher Antworten oder die Umgehung der CAPTCHA-Prüfung;
- das erneute Akzeptieren eines bereits bestandenen CAPTCHAs;
- die Umgehung der Gültigkeitsdauer oder der Begrenzung falscher Antworten;
- die Verwendung eines CAPTCHAs für ein anderes Formular mit abweichendem `Purpose`;
- die Weitergabe der richtigen Antwort, der korrekten Kategorie-Karten oder der Symbolreihenfolge an den Browser;
- die Beschädigung von CAPTCHA-Daten beim Lesen oder Schreiben einer unterstützten PHP-Session;
- Sicherheitslücken beim Umgang mit nicht vertrauenswürdigen Eingaben oder bei der Bilderzeugung;
- die Kompromittierung von Quellcode, Abhängigkeiten, CI oder Release-Artefakten.

## Sicherheitsmodell

Human Gate erstellt und prüft CAPTCHAs serverseitig. Jedes CAPTCHA ist an einen Formular- oder Vorgangsschlüssel (`Purpose`) gebunden, hat eine begrenzte Gültigkeit und Fehlversuchszahl und kann nach erfolgreicher Prüfung nicht wiederverwendet werden.

Die für die Prüfung nötigen Daten verbleiben auf dem Server. Der mitgelieferte Speicher nutzt PHP-Sessions. Weitere Informationen stehen im [Sicherheitsmodell des Pakets](https://github.com/yaleksandr89/human-gate/blob/master/docs/security-model_de.md).

## Eine Schwachstelle melden

Nutzen Sie vorzugsweise **GitHub Private Vulnerability Reporting**, sofern es für dieses Repository verfügbar ist:

1. Öffnen Sie den Reiter **Security**.
2. Wechseln Sie zu **Advisories**.
3. Wählen Sie **Report a vulnerability**.
4. Senden Sie den Bericht privat.

Falls private Meldungen nicht verfügbar sind, erstellen Sie ein [Issue](https://github.com/yaleksandr89/human-gate/issues/new/choose), in dem Sie **nur um einen privaten Kontaktkanal bitten, ohne Details zur Schwachstelle**. Veröffentlichen Sie keine Umgehungsmethoden, Exploits oder vertraulichen Daten.

Veröffentlichen Sie bis zur abgestimmten Offenlegung nicht:

- funktionierende Anleitungen zur CAPTCHA-Umgehung oder Exploit-Code;
- Kennungen und Inhalte echter Benutzersitzungen, Formulardaten oder personenbezogene Informationen;
- Geheimnisse, Zugangsdaten, private Logs oder Daten aus Produktivsystemen.

## Angaben zum Bericht

Geben Sie möglichst Folgendes an:

- Human-Gate-Version oder Commit-SHA;
- PHP-Version, Verfügbarkeit von `ext-gd` und relevante Umgebungsdetails;
- CAPTCHA-Art: `TextImage`, `CategorySelection` oder `IconSequence`;
- betroffene Operation: `issue()`, `replace()`, `verify()`, Bilderzeugung oder Session-Speicherung;
- minimale Schritte zum Nachstellen mit erfundenen Daten;
- erwartetes und tatsächliches Verhalten;
- mögliche Auswirkungen und einen Lösungsvorschlag, falls bekannt.

Verwenden Sie erfundene Daten und fügen Sie keine Inhalte echter Sessions bei.

## Bearbeitung von Meldungen

Das Projekt wird von einer Person betreut. Meldungen werden nach Möglichkeit geprüft; feste Antwortzeiten werden nicht garantiert. Bestätigte Probleme werden behoben und mit Regressionstests überprüft. Bitte stimmen Sie die Veröffentlichung technischer Details mit dem Maintainer ab.

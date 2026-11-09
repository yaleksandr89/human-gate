# Mitwirken

## Sprache auswählen

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](https://github.com/yaleksandr89/human-gate/blob/master/.github/CONTRIBUTING.md) | [English](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_en.md) | [Español](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_es.md) | [中文](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_zh.md) | [Français](https://github.com/yaleksandr89/human-gate/blob/master/docs/contributing/CONTRIBUTING_fr.md) | **Ausgewählt** |

Vielen Dank für deinen Beitrag zu Human Gate. Dieses Composer-Paket erstellt und prüft drei CAPTCHA-Arten in PHP: `TextImage`, `CategorySelection` und `IconSequence`. Fehlerbehebungen und Verbesserungen sind willkommen, wenn sie die öffentliche API verständlicher oder die Prüfung zuverlässiger machen.

## Vor dem Einstieg

- Melde reproduzierbare Fehler über [GitHub Issues](https://github.com/yaleksandr89/human-gate/issues/new/choose).
- Verwende das Formular für Funktionsvorschläge. Beschreibe zuerst das Problem und das gewünschte Verhalten, bevor du die Implementierung diskutierst.
- Melde mögliche Schwachstellen gemäß der [Sicherheitsrichtlinie](https://github.com/yaleksandr89/human-gate/security/policy), ohne Umgehungsmethoden öffentlich zu posten.
- Besprich größere Änderungen an API, Ablaufzeit, Prüfregeln oder Antwortformat zunächst in einem Issue.

## Zu bewahrendes Paketverhalten

- Das Paket unterstützt `PHP ^8.4`, benötigt `ext-gd` und hat keine Composer-Laufzeitabhängigkeiten von Drittanbietern.
- Antworten werden serverseitig geprüft. Jedes CAPTCHA ist an einen Formular- oder Vorgangsschlüssel (`Purpose`) gebunden.
- Ein erfolgreiches CAPTCHA kann nicht erneut verwendet werden. `Policy` begrenzt die Gültigkeit und die Zahl falscher Antworten.
- [`TextImage`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/text-image_de.md) prüft Bildzeichen; [`CategorySelection`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/category-selection_de.md) akzeptiert eine Kartenauswahl unabhängig von ihrer Reihenfolge; [`IconSequence`](https://github.com/yaleksandr89/human-gate/blob/master/docs/challenges/icon-sequence_de.md) prüft die Symbolreihenfolge.
- Das Paket liefert Darstellungsdaten und prüft Antworten; die HTML-Oberfläche erstellt die Anwendung.
- Änderungen sollen die Architektur respektieren und unnötige Abhängigkeiten oder neue öffentliche Funktionen vermeiden.

Weitere Informationen und Signaturen enthält die [API-Referenz](https://github.com/yaleksandr89/human-gate/blob/master/docs/reference/api_de.md).

## Branches und Commits

Erstelle einen eigenen Branch mit einem passenden Namen, zum Beispiel:

```text
fix/icon-sequence-verification
feat/text-image-rendering
docs/integration-example
```

Verwende Conventional Commits mit einer russischen Beschreibung nach dem Präfix:

```text
fix: исправить повторную проверку капчи
feat: добавить настройку отображения
test: добавить проверку обновления капчи
docs: уточнить интеграцию с сессией PHP
```

Ein Commit soll eine zusammengehörige Änderung ohne fremdes Refactoring enthalten. Erkläre im PR den Grund, die API-Auswirkungen und die tatsächlich ausgeführten Prüfungen.

## Lokale Prüfungen

Installiere die Abhängigkeiten und führe alle Prüfungen aus:

```bash
composer install
composer check
```

Einzelne Prüfungen sind ebenfalls verfügbar:

```bash
composer test
composer analyse
composer cs:check
```

Bei reinen Markdown-Änderungen Links und `git diff --check` prüfen. Bei PHP-Änderungen relevante Tests, Stilprüfung und statische Analyse ausführen. Testabdeckung ist ein Diagnosewerkzeug, keine Zielquote.

## Tests und Beispiele

- Prüfe Erfolgs- und Fehlerfälle, insbesondere Ablauf, Fehlversuche, erneute Prüfung, Erneuerung und Session-Fehler.
- Verwende erfundene Antworten, Kategorien, Kennungen und Session-Daten. Keine echten Benutzerdaten committen.
- Beispiele müssen zur aktuellen öffentlichen API passen und ohne undokumentierte Einrichtung funktionieren.
- Bei Verhaltensänderungen russische Dokumentation und Übersetzungen gemeinsam aktualisieren.

## Pull Request

Prüfe vor dem PR, ob die Änderungen zum angegebenen Ziel gehören, Tests das neue Verhalten abdecken und die Dokumentation zum Code passt. Nutze die PR-Vorlage; der Maintainer kann Rückfragen stellen oder zusätzliche Prüfungen verlangen.

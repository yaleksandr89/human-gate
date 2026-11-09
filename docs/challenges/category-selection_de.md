# CategorySelection — Karten nach Kategorie auswählen

Der Benutzer sieht eine Kategorie und wählt alle passenden Karten aus, etwa nur Früchte aus Früchten und Werkzeugen. **Die Auswahlreihenfolge ist egal.**

## Einrichtung und Kategorien

Die Anwendung liefert Kategorien und Kartenbeschriftungen. `CategorySelectionStrategy` wählt Karten zufällig aus und prüft die ausgewählte Menge.

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

Registriere die Strategie bei `ChallengeService` und übergib `ChallengeKind::CategorySelection` an [`ChallengeService::issue()`](../reference/api_de.md#issue). Die [Integrationsanleitung](../guides/integration_de.md) zeigt den vollständigen Aufbau und Sessions.

Ein Katalog darf 2–16 Kategorien mit je 1–32 Einträgen enthalten. Es müssen genügend passende Karten und Ablenkungsoptionen vorhanden sein. Kategorienamen und Beschriftungen müssen eindeutig sein; inhaltliche Überschneidungen sollten vermieden werden.

Beschriftungen müssen gültiges UTF-8 mit 1–80 **Bytes** (nicht Zeichen) ohne Steuerzeichen oder Rand-Leerraum sein. Das Paket liefert Klartext und kein fertiges HTML.

## Kartenanzahl

`CategorySelectionOptions` bestimmt die Gesamtzahl und die richtige Auswahlmenge:

| Parameter | Standard | Erlaubte Werte |
| --- | --- | --- |
| `cardCount` | `6` | Von `3` bis `12` Karten |
| `targetCount` | `2` | Mindestens `1`, aber weniger als `cardCount` |

Die übrigen Karten stammen aus anderen Kategorien. Ablaufzeit und Versuche werden über [`Policy`](../reference/api_de.md#policy) gesteuert, nicht `CategorySelectionOptions`.

## Darstellungsdaten

[`CategorySelectionPresentation`](../reference/api_de.md) enthält `category` und `cards`. Jede `CategorySelectionCard` hat die sichtbare Beschriftung `label` und ein zufälliges `token`.

Der Browser erhält Karten und Token, aber keine Information darüber, welche Karten richtig sind. Human Gate prüft anhand der gespeicherten Serverdaten.

## Antwortformat

Die Anwendung verbindet die Token ausgewählter Karten mit Kommas und übergibt sie an [`ChallengeService::verify()`](../reference/api_de.md#verify). **Die Reihenfolge ist egal**; das Paket vergleicht die Menge.

Jedes Token hat 32 kleine Hexadezimalzeichen (`0–9`, `a–f`). Duplikate sind verboten, die gesamte Antwort darf höchstens 512 Bytes lang sein. Leerzeichen und Änderungen der Schreibweise sind unzulässig. Nicht selbst sortieren oder normalisieren.

Nach erfolgreichem [`ChallengeService::replace()`](../reference/api_de.md#replace) neue Karten anzeigen und die vorherige Auswahl löschen. Alte Token sind ungültig. Siehe [Lebenszyklus](../reference/lifecycle_de.md).

## Fehler

Ungültige Kataloge oder Einstellungen führen zu `InvalidArgumentException` beim Erstellen der Strategie. Falsche Antworten liefern `VerificationCode::Incorrect` oder `AttemptsExhausted`; Speicherfehler werden getrennt behandelt. Siehe [Sicherheitsmodell](../security-model_de.md) und [API-Ausnahmen](../reference/api_de.md#ausnahmen).

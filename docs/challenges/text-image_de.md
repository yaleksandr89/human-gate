# TextImage — Text-CAPTCHA

Der Benutzer sieht ein Bild mit sechs Zeichen und gibt sie in ein Formular ein. Human Gate erzeugt das Bild und prüft die Antwort auf dem Server. Groß-/Kleinschreibung wird standardmäßig ignoriert.

## Einrichtung

Für Text-CAPTCHAs werden `TextImageStrategy` und `GdTextImageRenderer` verwendet. Beispiel mit Bild- und Groß-/Kleinschreibungs-Einstellungen:

```php
<?php

use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageAnswerOptions;
use Yaleksandr\HumanGate\TextImage\TextImageBlur;
use Yaleksandr\HumanGate\TextImage\TextImageRenderOptions;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

$strategy = new TextImageStrategy(
    new GdTextImageRenderer(new TextImageRenderOptions(
        scalePercent: 110,
        blur: TextImageBlur::Standard,
        lightNoisePercent: 100,
        maxRotationDegrees: 28,
    )),
    new TextImageAnswerOptions(caseSensitive: false),
);
```

Übergib die Strategie an `ChallengeService`. [`ChallengeService::issue()`](../reference/api_de.md#issue) erstellt standardmäßig ein Text-CAPTCHA, ohne einen Typ anzugeben. Die [Integrationsanleitung](../guides/integration_de.md) enthält ein vollständiges Session- und Prüfbeispiel.

## Antwort-Einstellungen

`TextImageAnswerOptions::caseSensitive` steuert die Groß-/Kleinschreibung:

| Wert | Prüfverhalten |
| --- | --- |
| `false` | Lateinische Groß- und Kleinbuchstaben gelten als gleich |
| `true` | Die Schreibweise muss mit dem Bild übereinstimmen |

**Die Antwort enthält immer sechs Zeichen**, ihre Länge ist nicht einstellbar. Die Auswahl vermeidet einige leicht verwechselbare Zeichen. Der case-sensitive Modus verwendet zusätzlich bestimmte Kleinbuchstaben.

Die Benutzereingabe unverändert an [`ChallengeService::verify()`](../reference/api_de.md#verify) übergeben. Das Paket übernimmt die erlaubte Umwandlung. Nur Leerzeichen, Tabs und `CR`/`LF`-Zeilenumbrüche am Rand werden entfernt. Interne Leerzeichen und beliebige Unicode-Normalisierung sind nicht erlaubt. Maximale Eingabegröße: 64 Bytes.

## Bild-Einstellungen

`TextImageRenderOptions` steuert die Bilderzeugung:

| Parameter | Standard | Erlaubte Werte |
| --- | --- | --- |
| `scalePercent` | `100` | `100`, `105`, `110`, `115`, `120` |
| `blur` | `TextImageBlur::Standard` | `None`, `Light`, `Standard`, `Strong` |
| `lightNoisePercent` | `100` | Von `0` bis `150` |
| `maxRotationDegrees` | `28` | Von `0` bis `28` Grad |

Die Skalierung ändert die Größe bei gleichem Seitenverhältnis. Unschärfe, Rauschen und Drehung verändern das Bild, aber nicht die richtige Antwort.

Die gemeinsame Gültigkeit wird über [`Policy::ttlSeconds`](../reference/api_de.md#policy) gesetzt, **nicht** über Bildoptionen. Standard sind 180 Sekunden; `new Policy(ttlSeconds: 300)` ergibt fünf Minuten.

## Darstellungsdaten

Nach der Ausgabe erhält die Anwendung [`ImagePresentation`](../reference/api_de.md) mit `mimeType`, `bytes`, `width`, `height`. Der Typ ist `image/png`; `bytes` enthält rohe PNG-Bytes, keine base64-kodierte Zeichenkette.

Bei Skalierung `100` beträgt die Bildgröße 240×80 Pixel. Die Anwendung bestimmt die Übertragung an den Browser und das Eingabefeld.

Die Bilderzeugung benötigt `ext-gd` mit FreeType. Noto Sans Regular ist enthalten; [Quelle](../../resources/fonts/SOURCE.txt) und [Lizenz](../../resources/fonts/OFL.txt) stehen in den Ressourcen.

## Fehler

Ungültige Einstellungen führen zu `InvalidArgumentException`, Fehler bei der Bilderzeugung zu `RenderingException`. Falsche Antworten liefern `VerificationCode::Incorrect` oder bei ausgeschöpften Versuchen `AttemptsExhausted`. Das sind keine Bildfehler.

Der Browser erhält nur das Zeichenbild, nicht die richtige Antwort. Weitere Informationen zur [serverseitigen Prüfung](../security-model_de.md).

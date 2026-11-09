# IconSequence — Symbolfolge

Der Benutzer sieht Symbole in einem Bild und wählt dieselben Symbole in der gezeigten Reihenfolge. **Die Reihenfolge ist entscheidend.**

## Einrichtung

Dieses CAPTCHA verwendet `IconSequenceStrategy`. Mit Standardeinstellungen genügt:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy();
```

Weitere Einstellungen können explizit übergeben werden:

```php
<?php

use Yaleksandr\HumanGate\IconSequence\IconSequenceBlur;
use Yaleksandr\HumanGate\IconSequence\IconSequenceOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceRenderOptions;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;

$strategy = new IconSequenceStrategy(
    new IconSequenceOptions(sequenceLength: 4, choiceCount: 8),
    new IconSequenceRenderOptions(
        minIconSize: 72,
        maxIconSize: 80,
        blur: IconSequenceBlur::Strong,
        noisePercent: 150,
        maxRotationDegrees: 90,
    ),
);
```

Registriere `IconSequenceStrategy` in `ChallengeService` und setze `ChallengeKind::IconSequence` bei [`ChallengeService::issue()`](../reference/api_de.md#issue). Siehe [Integrationsanleitung](../guides/integration_de.md).

## Anzahl und Reihenfolge der Symbole

| `IconSequenceOptions`-Parameter | Standard | Erlaubte Werte |
| --- | --- | --- |
| `sequenceLength` | `4` | Von `3` bis `6` Symbolen |
| `choiceCount` | `8` | Mindestens zwei Optionen mehr als die Folgenlänge, höchstens `12` |

Zur richtigen Folge kommen mindestens zwei Ablenkungsoptionen hinzu. Bei vier Zielsymbolen sind 6–12 Auswahlmöglichkeiten zulässig (standardmäßig acht). Der Benutzer wählt die vier richtigen Symbole in der gezeigten Reihenfolge.

## Bild-Einstellungen

| `IconSequenceRenderOptions`-Parameter | Standard | Erlaubte Werte |
| --- | --- | --- |
| `minIconSize` | `72` | Von `72` bis `96` Pixel |
| `maxIconSize` | `80` | Von `72` bis `96`, mindestens `minIconSize` |
| `blur` | `IconSequenceBlur::Strong` | `None`, `Light`, `Standard`, `Strong` |
| `noisePercent` | `150` | Von `0` bis `150` |
| `maxRotationDegrees` | `90` | Von `0` bis `90` Grad |

Unschärfe, Rauschen und Drehung betreffen das Zielbild; die Auswahlbilder bleiben scharf. Symbolgröße und Winkel müssen so kombiniert werden, dass gedrehte Symbole in die Bildhöhe von 128 Pixel passen.

Die Gültigkeit wird über das gemeinsame [`Policy::ttlSeconds`](../reference/api_de.md#policy) eingestellt, nicht über Bildoptionen. Standard: 180 Sekunden.

## Darstellungsdaten

[`IconSequencePresentation`](../reference/api_de.md) enthält:

| Feld | Daten für die Anwendung |
| --- | --- |
| `target` | Bild mit der Ziel-Symbolfolge |
| `requiredSelections` | Anzahl der auszuwählenden Symbole |
| `choices` | `IconSequenceChoice`-Optionen mit `token` und `image` |

Bilder sind `ImagePresentation`-Objekte mit PNG-Bytes. Jedes Auswahlbild misst 96×96 Pixel; das Zielbild ist 128 Pixel hoch und je nach Folgenlänge unterschiedlich breit.

`IconSequenceChoice` enthält nur Token und Bild. Symbolnamen und richtige Reihenfolge werden nicht an den Browser gesendet.

## Antwortformat

Die Anwendung verbindet die ausgewählten Symbol-Token mit Kommas **in exakter Auswahlreihenfolge** und übergibt sie an [`ChallengeService::verify()`](../reference/api_de.md#verify).

Jedes Token besteht aus 32 kleinen Hexadezimalzeichen. Eine Antwort enthält 3–6 unterschiedliche Token und höchstens 197 Bytes. Leerzeichen, Duplikate und Vertauschen sind ungültig. Das Paket sortiert und normalisiert die Antwort nicht.

Nach erfolgreichem [`ChallengeService::replace()`](../reference/api_de.md#replace) die alte Auswahl löschen und neue Bilder anzeigen. Siehe [CAPTCHA-Lebenszyklus](../reference/lifecycle_de.md).

## Prüfung und Fehler

Die Reihenfolge wird auf dem Server geprüft. Der Browser erhält weder Symbolnamen noch versteckte Informationen zur richtigen Reihenfolge. Siehe [Sicherheitsmodell](../security-model_de.md).

Das Paket enthält 32 Symbole aus Tabler Icons `v3.48.0` unter MIT-Lizenz. Ihre [Quelle](../../resources/icons/tabler/SOURCE.txt) und [Lizenz](../../resources/icons/tabler/LICENSE.txt) liegen in den Ressourcen.

Ungültige Einstellungen führen zu `InvalidArgumentException`, Fehler bei der Bilderzeugung zu `RenderingException`, falsche Antworten zu `VerificationCode::Incorrect` bzw. `AttemptsExhausted` bei ausgeschöpften Versuchen.

# API-Referenz

Der zentrale Einstieg ist `ChallengeService`. Er erstellt und erneuert CAPTCHAs, prüft Antworten und gibt typisierte Ergebnisse zurück. Diese Seite dokumentiert Signaturen und Optionen; Beispiele stehen in der [Integrationsanleitung](../guides/integration_de.md).

Alle genannten Typen liegen im Namensraum `Yaleksandr\HumanGate\`. Unternamensräume werden relativ dazu angegeben.

## ChallengeService

Der Konstruktor erhält Speicher, gemeinsame Einstellungen, eine Zeitquelle und CAPTCHA-Strategien:

```php
public function __construct(
    ChallengeStore $store,
    Policy $policy,
    Clock $clock,
    ChallengeStrategy ...$strategies,
)
```

Eine Strategie pro CAPTCHA-Art reicht aus. Solange in einer Session unterschiedliche Arten aktiv sind, müssen deren Strategien zur Prüfung vorhanden sein.

### issue()

```php
public function issue(
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Erstellt ein neues CAPTCHA. Bei Erfolg enthält `IssueResult` `LifecycleCode::Issued` und `IssuedChallenge`. Bei erreichter Aktiv-Grenze folgt `CapacityExceeded` und `challenge === null`.

Standardmäßig wird [`TextImage`](../challenges/text-image_de.md) erstellt. Für andere Arten `ChallengeKind::CategorySelection` oder `ChallengeKind::IconSequence` verwenden.

### replace()

```php
public function replace(
    ChallengeId $oldId,
    Purpose $purpose,
    ChallengeKind $kind = ChallengeKind::TextImage,
): IssueResult
```

Erstellt ein neues CAPTCHA statt des angegebenen. Bei Erfolg liefert es `LifecycleCode::Refreshed` und ein neues `IssuedChallenge`. Die alte Kennung kann nicht mehr geprüft werden.

Erneuerung ist bei aktiven und noch gespeicherten abgelaufenen CAPTCHAs möglich. Standardmäßig ist der neue Typ `TextImage`, für andere `kind` angeben. Siehe [Lebenszyklus](lifecycle_de.md).

### verify()

```php
public function verify(
    ChallengeId $id,
    Purpose $purpose,
    string $submittedAnswer,
): VerificationCode
```

Prüft die Antwort und liefert `VerificationCode`. `Accepted` bedeutet: richtige Antwort, CAPTCHA kann nicht erneut genutzt werden.

| `VerificationCode`-Wert | Bedeutung |
| --- | --- |
| `Accepted` | Richtige Antwort, CAPTCHA bestanden |
| `Incorrect` | Falsche Antwort; Versuche übrig |
| `AttemptsExhausted` | Fehlversuche erschöpft |
| `Expired` | CAPTCHA abgelaufen |
| `NotFound` | CAPTCHA nicht gefunden oder alter Eintrag gelöscht |
| `PurposeMismatch` | CAPTCHA gehört zu anderem Formular/Vorgang (`Purpose` abweichend) |
| `AlreadyConsumed` | Richtige Antwort wurde für dieses CAPTCHA bereits akzeptiert |
| `Replaced` | CAPTCHA wurde bereits ersetzt |

HTTP-Status und Benutzermeldung bestimmt die Anwendung. Beispiel für [`verify()`](../guides/integration_de.md).

## Policy

`Challenge\Policy` bestimmt Gültigkeit, Fehlversuche und Speichergrenzen. Standardeinstellungen:

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

| Parameter | Standard | Bedeutung |
| --- | --- | --- |
| `ttlSeconds` | `180` | CAPTCHA-Gültigkeit in Sekunden |
| `maxWrongAttempts` | `3` | Erlaubte Anzahl falscher Antworten |
| `maxActive` | `12` | Maximal aktive CAPTCHAs in einem Speicher |
| `maxActivePerPurpose` | `4` | Maximal aktive CAPTCHAs pro Formular/Vorgang (`Purpose`) |
| `maxTombstones` | `32` | Maximal aufbewahrte Einträge abgeschlossener CAPTCHAs |
| `terminalRetentionSeconds` | `180` | Aufbewahrungsdauer in Sekunden, sofern die Anzahlgrenze nicht früher löscht |

Mit [`NativeSessionChallengeStore`](#speicherung-und-zeit) gelten Grenzen **pro PHP-Session**, nicht für die ganze Website. `contact.send` und `account.register` dürfen jeweils bis zu vier aktive CAPTCHAs haben, aber die Session maximal zwölf insgesamt.

Nach Erfolg, Erneuerung, Ablauf oder ausgeschöpften Versuchen speichert das Paket den Endgrund kurzzeitig, um etwa `AlreadyConsumed` von `Expired` zu unterscheiden. `maxTombstones` und `terminalRetentionSeconds` begrenzen Zahl und Dauer. Nach dem Löschen kann `NotFound` folgen.

Alle Werte müssen positiv sein; `maxActivePerPurpose` darf `maxActive` nicht übersteigen. Für fünf Minuten: `new Policy(ttlSeconds: 300)`. Diese Grenzen **beschränken keine HTTP-Anfragehäufigkeit**. Siehe [Lebenszyklus](lifecycle_de.md).

## Grundlegende Typen

| Typ | Zweck |
| --- | --- |
| `Challenge\ChallengeId` | CAPTCHA-Kennung: `generate()`, `fromString(string $value)`, `value(): string` |
| `Challenge\Purpose` | Formular-/Vorgangsschlüssel: `__construct(string $value)`, `value(): string` |
| `Challenge\ChallengeKind` | CAPTCHA-Art: `TextImage`, `CategorySelection`, `IconSequence` |
| `Challenge\IssueResult` | Ergebnisfeld `code` und `challenge` oder `null` |
| `Challenge\IssuedChallenge` | Neues CAPTCHA: `id`, `kind`, `expiresAt`, `presentation` |

`ChallengeId` besteht genau aus 64 kleinen Hexadezimalzeichen. `Purpose` enthält 1–64 ASCII-Bytes: zuerst ein lateinischer Kleinbuchstabe oder eine Ziffer, danach dieselben Zeichen sowie Punkt, Unterstrich oder Bindestrich.

Die Anwendung setzt `Purpose` serverseitig, zum Beispiel `contact.send` für Nachrichten und `account.register` für Registrierung. Beim Erstellen und Prüfen gilt derselbe Schlüssel, nicht ein Browser-Feld. `IssuedChallenge::expiresAt` ist der Ablaufzeitpunkt in Unix-Sekunden.

## TextImage

Wichtige Klassen im Namensraum `TextImage\`:

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

`TextImageBlur` unterstützt `None`, `Light`, `Standard`, `Strong`. Skalierung: `100`, `105`, `110`, `115`, `120`; Rauschen: `0`–`150`; Drehung: `0`–`28` Grad. `caseSensitive` steuert die Groß-/Kleinschreibung. Die Antwort hat immer sechs Zeichen. Siehe [Text-CAPTCHA-Anleitung](../challenges/text-image_de.md).

## CategorySelection

Wichtige Klassen im Namensraum `CategorySelection\`:

```php
CategorySelectionCategory::__construct(string $label, array $items)
CategorySelectionOptions::__construct(int $cardCount = 6, int $targetCount = 2)
CategorySelectionStrategy::__construct(
    array $categories,
    CategorySelectionOptions $options = new CategorySelectionOptions(),
)
```

2–16 Kategorien mit je 1–32 Einträgen. `cardCount` umfasst 3–12, `targetCount` mindestens 1 und weniger als `cardCount`. Beschriftungen sind eindeutig, gültiges UTF-8 von 1–80 Bytes ohne Steuerzeichen oder Rand-Leerraum.

Die Antwort enthält kommagetrennte Karten-Token; die Reihenfolge ist egal. Siehe [Kartenauswahl-Anleitung](../challenges/category-selection_de.md).

## IconSequence

Wichtige Klassen im Namensraum `IconSequence\`:

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

Folgenlänge: 3–6 Symbole. Auswahlmöglichkeiten: mindestens zwei mehr als die Länge, höchstens 12 (bei vier Zielsymbolen 6–12 Optionen). Symbolgröße: 72–96 Pixel; Rauschen: 0–150; Drehung: 0–90 Grad. `IconSequenceBlur` unterstützt `None`, `Light`, `Standard`, `Strong`.

Token werden in Auswahlreihenfolge übergeben. Siehe [Symbolfolge-Anleitung](../challenges/icon-sequence_de.md).

## Darstellungsdaten

Alle Typen liegen in `Presentation\`:

| Typ | Felder |
| --- | --- |
| `ImagePresentation` | `mimeType`, `bytes`, `width`, `height` |
| `CategorySelectionPresentation` | `category`, `cards` |
| `CategorySelectionCard` | `token`, `label` |
| `IconSequencePresentation` | `target`, `requiredSelections`, `choices` |
| `IconSequenceChoice` | `token`, `image` |

`ImagePresentation` enthält rohe Bild-Bytes. Unterstützt werden `image/png`, `image/jpeg`, `image/webp`, 1–4096 Pixel je Seite und bis zu 131072 Daten-Bytes. Die eingebauten Text- und Symbol-Renderer erzeugen PNG.

Kartenauswahl liefert Kategorie und Kartentext. Die Symbolfolge liefert das Zielbild und Optionen mit Token und Bild. Die richtige Antwort ist nicht enthalten.

## Speicherung und Zeit

| Typ | Zweck |
| --- | --- |
| `Session\ActiveNativeSessionScope::capture()` | Erfasst den Kontext einer bereits geöffneten PHP-Session |
| `Session\NativeSessionChallengeStore` | Speichert CAPTCHA-Daten in einer PHP-Session |
| `Clock\SystemClock` | Zeitquelle: `now(): int` liefert Unix-Sekunden |

`NativeSessionChallengeStore` erhält `ActiveNativeSessionScope`. Unterstützt werden der Handler `files` sowie Serialisierung `php` und `php_serialize`. Zuerst ruft die Anwendung `session_start()` auf; nach Datenänderungen schließt der Adapter die Session. Siehe [Integrationsanleitung](../guides/integration_de.md).

## Ausnahmen

Falsche Antworten werden durch `VerificationCode` angezeigt. Konfigurations- und Laufzeitfehler lösen Ausnahmen aus:

| Ausnahme | Grund |
| --- | --- |
| `InvalidArgumentException` | Ungültige Argumente oder Einstellungen |
| `Exception\UnsupportedChallengeKindException` | Keine Strategie für gewünschte CAPTCHA-Art |
| `Exception\RenderingException` | Fehler bei der Bilderzeugung |
| `Random\RandomException` | Zufallszahlengenerator fehlgeschlagen |
| `Session\NativeSessionStorageException` | Fehler beim Lesen, Schreiben oder Prüfen der CAPTCHA-Daten in der PHP-Session |

`NativeSessionStorageException::reason` enthält `NativeSessionStorageFailureReason`: `UnsupportedSession`, `SessionIdentity`, `HeadersSent`, `MalformedState`, `SizeLimit`, `Randomness`, `Commit`, `Verification`. Interne Fehlerdetails nicht direkt an Benutzer ausgeben.

## Paket erweitern

Eigene Speicher, Zeitquellen oder CAPTCHA-Strategien lassen sich über `Port\ChallengeStore`, `Port\Clock`, `Port\ChallengeStrategy` und `Port\TextImageRenderer` bereitstellen.

`ChallengeStore::atomic()` muss Änderungen bei parallelen Anfragen konfliktfrei übernehmen und die Speicherung vor der Rückgabe bestätigen. Speicherfehler dürfen niemals eine erfolgreiche CAPTCHA-Prüfung erzeugen.

`Clock::now()` liefert die aktuelle Unix-Zeit in Sekunden zur Berechnung des Ablaufs, ist aber kein Countdown-Timer. `ChallengeStrategy::kind()`, `prepare()` und `verify()` definieren eine Strategie; `TextImageRenderer::render()` liefert `ImagePresentation`. Für die übliche Nutzung empfehlen sich die fertigen Implementierungen aus der [Integrationsanleitung](../guides/integration_de.md).

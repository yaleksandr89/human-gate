# Human Gate integrieren

Human Gate erstellt CAPTCHAs und prüft Antworten auf dem Server. Die Anwendung zeigt das CAPTCHA im Formular und verarbeitet es erst nach einer richtigen Antwort weiter.

## So funktioniert die Prüfung

Die Anwendung ruft [`ChallengeService::issue()`](../reference/api_de.md#issue) auf und erhält CAPTCHA-Kennung und Darstellungsdaten. Diese Daten werden an den Browser gesendet.

Beim Absenden bekommt die Anwendung Kennung und Antwort und ruft [`ChallengeService::verify()`](../reference/api_de.md#verify) auf. `VerificationCode::Accepted` bedeutet erfolgreich. Die richtige Antwort und die Informationen über korrekte Optionen bleiben auf dem Server.

[`ChallengeService::replace()`](../reference/api_de.md#replace) erstellt ein Ersatz-CAPTCHA mit neuer Kennung.


## Paket einbinden

`ChallengeService` besteht aus Speicher, gemeinsamen [`Policy`-Einstellungen](../reference/api_de.md#policy), `SystemClock` als Zeitquelle und Strategien für die CAPTCHA-Arten. Das Beispiel registriert alle drei Arten mit einer PHP-Session. Kategorien dienen nur der Veranschaulichung.

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\CategorySelection\CategorySelectionCategory;
use Yaleksandr\HumanGate\CategorySelection\CategorySelectionStrategy;
use Yaleksandr\HumanGate\Challenge\Policy;
use Yaleksandr\HumanGate\ChallengeService;
use Yaleksandr\HumanGate\Clock\SystemClock;
use Yaleksandr\HumanGate\IconSequence\IconSequenceStrategy;
use Yaleksandr\HumanGate\Session\ActiveNativeSessionScope;
use Yaleksandr\HumanGate\Session\NativeSessionChallengeStore;
use Yaleksandr\HumanGate\TextImage\GdTextImageRenderer;
use Yaleksandr\HumanGate\TextImage\TextImageStrategy;

require 'vendor/autoload.php';

function createChallengeServiceForActiveSession(): ChallengeService
{
    return new ChallengeService(
        new NativeSessionChallengeStore(ActiveNativeSessionScope::capture()),
        new Policy(),
        new SystemClock(),
        new TextImageStrategy(new GdTextImageRenderer()),
        new CategorySelectionStrategy([
            new CategorySelectionCategory('Фрукты', ['Яблоко', 'Груша', 'Слива', 'Персик']),
            new CategorySelectionCategory('Инструменты', ['Молоток', 'Пила', 'Отвёртка', 'Клещи']),
        ]),
        new IconSequenceStrategy(),
    );
}
```

Rufe diese Funktion nach `session_start()` auf. Sind alle drei Strategien registriert, können auch zuvor erzeugte CAPTCHAs unterschiedlicher Arten aus derselben Session geprüft werden. Weitere Optionen stehen in den Anleitungen zu [`TextImage`](../challenges/text-image_de.md), [`CategorySelection`](../challenges/category-selection_de.md) und [`IconSequence`](../challenges/icon-sequence_de.md).


## CAPTCHA erstellen

Im Request-Handler öffnet die Anwendung die Session und erstellt den gewünschten Typ. Beispiel für Kartenauswahl:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\Purpose;

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->issue(new Purpose('contact.send'), ChallengeKind::CategorySelection);

if ($result->challenge === null) {
    throw new RuntimeException('Не удалось создать капчу.');
}

$id = $result->challenge->id->value();
$presentation = $result->challenge->presentation;
```

Bei Erfolg liefert [`issue()`](../reference/api_de.md#issue) ein `IssueResult` mit `LifecycleCode::Issued` und `IssuedChallenge` mit `id`, `kind`, `expiresAt`, `presentation`. Ist die Grenze aktiver CAPTCHAs erreicht, ist `challenge` gleich `null`; der Code erklärt den Grund.

`Purpose` ist ein Schlüssel für Formular oder Vorgang, z. B. `contact.send` für Nachrichten und `account.register` für die Registrierung. Beim Erstellen und Prüfen muss er gleich sein. Der Schlüssel wird serverseitig festgelegt, nicht aus dem Browser übernommen.

Ohne zweiten Parameter erzeugt `issue()` ein Text-CAPTCHA. Für andere Arten `ChallengeKind::CategorySelection` oder `ChallengeKind::IconSequence` übergeben.


## CAPTCHA anzeigen

Das Paket liefert Darstellungsdaten; die Anwendung erstellt HTML und Gestaltung:

| CAPTCHA-Art | Darstellungsdaten | Benutzerantwort |
| --- | --- | --- |
| [`TextImage`](../challenges/text-image_de.md) | `ImagePresentation`: Bildtyp, Bytes und Größe | Eingegebene Zeichen |
| [`CategorySelection`](../challenges/category-selection_de.md) | `CategorySelectionPresentation`: Kategorie und Karten | Kommagetrennte Token, Reihenfolge egal |
| [`IconSequence`](../challenges/icon-sequence_de.md) | `IconSequencePresentation`: Zielbild und Symbole | Kommagetrennte Token in Auswahlreihenfolge |

`ImagePresentation::bytes` enthält rohe Bilddaten. Die Anwendung bestimmt die Übertragung an den Browser. Kategorienamen und Kartenbeschriftungen sind Klartext, kein fertiges HTML.

Alle drei Arten werden serverseitig geprüft. Der Browser bekommt nur die zur Anzeige und Auswahl nötigen Daten.


## Antwort prüfen

Die Antwort trifft in einem separaten Request ein. Öffne **dieselbe Benutzersession** erneut, erstelle `ChallengeService` neu und übergib Kennung und Antwort:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\Purpose;

$rawId = $_POST['challengeId'] ?? null;
$rawAnswer = $_POST['answer'] ?? null;
if (!is_string($rawId) || !is_string($rawAnswer)) {
    throw new InvalidArgumentException('Ожидаются строковые идентификатор и ответ.');
}

$id = ChallengeId::fromString($rawId);

if (!session_start()) {
    throw new RuntimeException('Не удалось открыть сессию PHP.');
}

$service = createChallengeServiceForActiveSession();
$result = $service->verify($id, new Purpose('contact.send'), $rawAnswer);
```

`challengeId` und `answer` sind Beispiel-Feldnamen. Kennung mit `ChallengeId::fromString()` einlesen. Die rohe Antwort ohne Umwandlung der Schreibweise, Sortieren der Token oder andere Änderungen an `verify()` übergeben; die Regeln hängen von der Art ab.

`VerificationCode::Accepted` bedeutet, dass das CAPTCHA bestanden wurde. Eine richtige Antwort wird nur einmal akzeptiert; eine zweite Prüfung derselben Kennung kann nicht erfolgreich sein.


## CAPTCHA erneuern

[`ChallengeService::replace()`](../reference/api_de.md#replace) erhält die alte Kennung und gibt ein neues CAPTCHA zurück. Beispiel für Kartenauswahl:

```php
<?php

declare(strict_types=1);

use Yaleksandr\HumanGate\Challenge\ChallengeId;
use Yaleksandr\HumanGate\Challenge\ChallengeKind;
use Yaleksandr\HumanGate\Challenge\IssueResult;
use Yaleksandr\HumanGate\Challenge\Purpose;

function replaceCategoryChallenge(ChallengeId $oldId): IssueResult
{
    if (!session_start()) {
        throw new RuntimeException('Не удалось открыть сессию PHP.');
    }

    return createChallengeServiceForActiveSession()->replace(
        $oldId,
        new Purpose('contact.send'),
        ChallengeKind::CategorySelection,
    );
}
```

Nach erfolgreicher Erneuerung neue Daten anzeigen und frühere Eingaben und Auswahlen löschen. Ein abgelaufenes CAPTCHA kann erneuert werden, solange sein Eintrag erhalten ist; nach dem Löschen folgt `NotFound`. Siehe [Lebenszyklus](../reference/lifecycle_de.md).


## Speicherung in der PHP-Session

Die Beispiele verwenden [`NativeSessionChallengeStore`](../reference/api_de.md#speicherung-und-zeit). Die Anwendung öffnet eine PHP-Session mit `session_start()`, ruft dann `ActiveNativeSessionScope::capture()` auf und übergibt den Kontext an den Speicher. Ein zusätzlicher Speicherdienst ist nicht nötig.

Der Adapter unterstützt den Standard-Handler `files` sowie die Serialisierungsformate `php` und `php_serialize`.

Nach erfolgreicher Änderung der CAPTCHA-Daten speichert der Adapter und schließt die Session. Soll danach `$_SESSION` geändert werden, muss die Session erneut geöffnet werden. Bei unveränderten Daten kann sie geöffnet bleiben; die Anwendung steuert weitere Session-Arbeit.

Datenändernde Operationen müssen vor dem Senden der HTTP-Header erfolgen. Längere externe Operationen besser nach der Session-Arbeit ausführen. Ein Speicherfehler führt zu `NativeSessionStorageException`, nicht zu einer falschen Antwort.

Andere Speicher können durch `ChallengeStore` eingebunden werden. Anforderungen stehen in der [API-Referenz](../reference/api_de.md#paket-erweitern).


## Ergebnisse behandeln

| Code | Bedeutung |
| --- | --- |
| `Issued`, `Refreshed` | CAPTCHA erstellt oder erneuert; neue Daten zurückgegeben |
| `CapacityExceeded` | Grenze aktiver CAPTCHAs erreicht |
| `Accepted` | Richtige Antwort akzeptiert; CAPTCHA nicht wiederverwendbar |
| `Incorrect` | Falsche Antwort, Versuche übrig |
| `AttemptsExhausted` | Fehlversuchslimit erreicht |
| `Expired` | CAPTCHA abgelaufen |
| `AlreadyConsumed`, `Replaced` | Kennung kann nicht erneut zur Prüfung genutzt werden |
| `NotFound` | CAPTCHA fehlt oder Eintrag gelöscht |
| `PurposeMismatch` | CAPTCHA gehört zu anderem Formular oder Vorgang (`Purpose`) |

`issue()` und `replace()` geben `IssueResult` zurück; `verify()` liefert `VerificationCode`. Einige Codes haben ähnliche Namen, der Typ hängt aber vom aufgerufenen Verfahren ab. Siehe [API-Referenz](../reference/api_de.md).


## Häufige Fehler

- `ActiveNativeSessionScope::capture()` vor `session_start()` aufrufen.
- Nach dem Schließen ohne erneutes Öffnen in `$_SESSION` schreiben.
- Strategie für eine möglicherweise noch aktive CAPTCHA-Art nicht registrieren.
- `ChallengeKind` für Karten oder Symbole weglassen.
- `Purpose` aus dem Formular übernehmen, statt serverseitig festzulegen.
- Kartenbeschriftungen statt Token senden oder `IconSequence`-Token sortieren.
- Formular trotz Speicherfehler oder Ergebnis ungleich `Accepted` weiterverarbeiten.

# CAPTCHA-Lebenszyklus

Human Gate verfolgt jedes erstellte CAPTCHA, seinen Ablauf und Fehlversuche. Eine richtige Antwort kann nicht wiederverwendet werden. Gemeinsame Grenzen werden über [`Policy`](api_de.md#policy) festgelegt.

## Gültigkeit und Grenzen

| Parameter | Standard | Bedeutung |
| --- | --- |
| `ttlSeconds` | `180` | Wie viele Sekunden ein CAPTCHA gültig ist |
| `maxWrongAttempts` | `3` | Erlaubte Anzahl falscher Antworten |
| `maxActive` | `12` | Maximal aktive CAPTCHAs in einem Speicher |
| `maxActivePerPurpose` | `4` | Maximal aktive CAPTCHAs pro Formular oder Vorgang (`Purpose`) |
| `maxTombstones` | `32` | Maximal aufbewahrte Einträge abgeschlossener CAPTCHAs |
| `terminalRetentionSeconds` | `180` | Aufbewahrungsdauer abgeschlossener Einträge, sofern die Anzahlgrenze sie nicht früher entfernt |

Bei der standardmäßigen PHP-Session-Speicherung gelten die Grenzen pro Session. Beispielsweise können bis zu vier CAPTCHAs für `contact.send` und vier für `account.register` aktiv sein, aber höchstens zwölf insgesamt. Grenzen für Zahl und Dauer abgeschlossener Einträge verhindern unbegrenztes Wachstum.

Alle Werte müssen positiv sein; `maxActivePerPurpose` darf `maxActive` nicht überschreiten. Fünf Minuten werden mit `new Policy(ttlSeconds: 300)` eingestellt. Diese Grenzen beschränken nicht die HTTP-Anfragehäufigkeit.

## CAPTCHA erstellen

[`ChallengeService::issue()`](api_de.md#issue) erstellt ein CAPTCHA des gewählten Typs. Bei Erfolg erhält die Anwendung Kennung, Ablaufzeit und Darstellungsdaten.

Ist die Grenze aktiver CAPTCHAs erreicht, folgt `LifecycleCode::CapacityExceeded` und kein neues CAPTCHA. Standard ist das [Text-CAPTCHA](../challenges/text-image_de.md); andere Arten werden über `ChallengeKind` gewählt.

## Antwort prüfen

[`ChallengeService::verify()`](api_de.md#verify) erhält die CAPTCHA-Kennung, den serverseitigen Formularschlüssel (`Purpose`) und die Benutzerantwort. Der Schlüssel muss dem bei der Erstellung entsprechen. Der Typ stammt aus gespeicherten Daten, nicht vom Browser.

Jede Art hat eigene Antwortregeln. Bei [`CategorySelection`](../challenges/category-selection_de.md) ist die Token-Reihenfolge egal, bei [`IconSequence`](../challenges/icon-sequence_de.md) entscheidend. Ein ungültiges Antwortformat zählt als Fehlversuch.

## Falsche Antworten

Jede falsche Antwort erhöht den Versuchszähler. Solange Versuche übrig sind, gibt der Dienst `VerificationCode::Incorrect` zurück; dasselbe CAPTCHA kann erneut versucht werden.

Beim Erreichen der Grenze folgt `VerificationCode::AttemptsExhausted`. Standardmäßig beendet **die dritte falsche Antwort** das CAPTCHA. Weitere Prüfungen derselben Kennung werden abgewiesen; die Gültigkeit wird nicht verlängert.

Für weitere Versuche über [`issue()`](api_de.md#issue) ein neues CAPTCHA erstellen: Bei ausgeschöpften Versuchen funktioniert `replace()` nicht.

## Richtige Antwort

Eine richtige Antwort gibt `VerificationCode::Accepted` zurück. Danach kann das CAPTCHA nicht erneut bestanden werden. Solange der Erfolgseintrag gespeichert ist, folgt bei Wiederholung `AlreadyConsumed`.

## CAPTCHA erneuern

[`ChallengeService::replace()`](api_de.md#replace) erstellt ein neues CAPTCHA anstelle des bisherigen. Bei Erfolg erhält die Anwendung eine neue Kennung und Darstellungsdaten; alte Antworten und Auswahlen sind ungültig.

Aktive CAPTCHAs können ersetzt werden, ebenso abgelaufene, solange ihr Eintrag erhalten ist. Ein aktives CAPTCHA zu ersetzen erhöht die Anzahl nicht; bei abgelaufenen kann freie Kapazität nötig sein.

Bereits bestandene, ersetzte oder wegen zu vieler Fehler beendete CAPTCHAs sind mit dieser Methode nicht erneuerbar. Der neue Typ ist standardmäßig Text; andere Typen müssen angegeben werden.

## Ablauf und Löschen von Einträgen

Ein CAPTCHA ist gültig, solange die aktuelle Zeit vor `expiresAt` liegt. Ab diesem Zeitpunkt wird keine Antwort mehr akzeptiert.

Informationen über abgelaufene CAPTCHAs bleiben möglicherweise vorübergehend gespeichert. Solange sie vorliegen, liefert `verify()` `Expired` und `replace()` kann ein neues CAPTCHA erstellen. Nach dem Löschen folgt `NotFound`.

Abgeschlossene Einträge bleiben nur begrenzt erhalten. Überschreitet ihre Anzahl `maxTombstones`, können ältere früher gelöscht werden. Deshalb liefert eine alte Kennung nicht dauerhaft `Expired`, sondern nach dem Löschen `NotFound`.

## Bindung an ein Formular

`Purpose` ist ein Textschlüssel für ein Formular oder einen Vorgang, z. B. `contact.send` für Nachrichten und `account.register` für die Registrierung. Die Anwendung setzt ihn serverseitig beim Erstellen und Prüfen. Ein anderer Schlüssel führt zu `PurposeMismatch` und einer abgewiesenen Antwort. Format in der [API-Referenz](api_de.md#grundlegende-typen).

## Methodenergebnisse

| Methode | Bei Erfolg | Weitere Ergebnisse |
| --- | --- |
| [`issue()`](api_de.md#issue) | `Issued` und neues CAPTCHA | `CapacityExceeded` |
| [`replace()`](api_de.md#replace) | `Refreshed` und neues CAPTCHA | `CapacityExceeded`, `NotFound`, `PurposeMismatch`, `AttemptsExhausted`, `AlreadyConsumed`, `Replaced` |
| [`verify()`](api_de.md#verify) | `Accepted` | `Incorrect`, `AttemptsExhausted`, `Expired`, `NotFound`, `PurposeMismatch`, `AlreadyConsumed`, `Replaced` |

Speicher- oder Bilderzeugungsfehler lösen Ausnahmen aus, statt `Incorrect` zurückzugeben. Siehe die [API-Referenz](api_de.md#ausnahmen).

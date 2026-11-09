# Sicherheitsmodell

Human Gate erstellt CAPTCHAs und prüft Antworten auf dem Server. Das Paket begrenzt Gültigkeitsdauer und Fehlversuche und verhindert die Wiederverwendung bereits bestandener CAPTCHAs.

## Serverseitige Prüfung

Beim Erstellen eines CAPTCHAs gibt die Anwendung den serverseitigen Schlüssel [`Purpose`](reference/api_de.md#grundlegende-typen) für ein Formular oder einen Vorgang an. Beispielsweise steht `contact.send` für das Kontaktformular und `account.register` für die Registrierung. Ein CAPTCHA für das eine Formular kann nicht das andere bestätigen. Richtige Antwort und Prüfdaten bleiben auf dem Server; der Browser erhält nur Kennung und Darstellungsdaten.

Beim Absenden übergibt die Anwendung Kennung und Antwort an [`ChallengeService::verify()`](reference/api_de.md#verify). Die Korrektheit wird serverseitig geprüft: Eine Kennung allein beweist kein bestandenes CAPTCHA.

Die richtige Antwort wird nicht im Klartext gespeichert. Bei [`CategorySelection`](challenges/category-selection_de.md) und [`IconSequence`](challenges/icon-sequence_de.md) erhält der Browser zufällige Auswahltoken, aber keine Information über die richtigen Optionen. Bei Karten ist die Reihenfolge egal, bei Symbolen entscheidend.

## Ablauf und Wiederverwendung

Ablaufzeit und Fehlversuche werden über [`Policy`](reference/api_de.md#policy) eingestellt. Standardmäßig gilt ein CAPTCHA 180 Sekunden; der dritte Fehler beendet die Prüfung.

Eine richtige Antwort führt zu `VerificationCode::Accepted`; danach ist das CAPTCHA nicht wiederverwendbar. Solange der Erfolg gespeichert ist, folgt bei Wiederholung `AlreadyConsumed`, nach Löschung des Eintrags `NotFound`. Keine der beiden Antworten erlaubt eine erneute erfolgreiche Prüfung.

Abgelaufene CAPTCHAs werden ebenfalls abgewiesen. Siehe die [Regeln für Erneuerung und Aufbewahrung](reference/lifecycle_de.md).

## Datenspeicherung

Der mitgelieferte [`NativeSessionChallengeStore`](reference/api_de.md#speicherung-und-zeit) arbeitet mit einer von der Anwendung geöffneten PHP-Session und dem Handler `files`. Nach Änderungen speichert das Paket die Daten und prüft die Speicherung. Ein Speicherfehler löst eine Ausnahme aus und gilt nicht als erfolgreiche Verifizierung.

Die [Integrationsanleitung](guides/integration_de.md) beschreibt den Speicheranschluss und den Umgang mit PHP-Sessions.

## Begrenzung aktiver CAPTCHAs

[`Policy::maxActive` und `Policy::maxActivePerPurpose`](reference/api_de.md#policy) begrenzen gleichzeitig gespeicherte aktive CAPTCHAs. Sie messen keine HTTP-Anfragen innerhalb eines Zeitraums.

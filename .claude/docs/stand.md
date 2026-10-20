# Stand

Branchlage und offene Punkte. Erledigtes wird hier gestrichen, nicht abgehakt.

## Branches auf GitHub

| Branch | Letzter Commit | `library.json` |
| --- | --- | --- |
| `main` | 30.09.2025 | 2.4.4 |
| `beta` | 04.10.2025, Farbverlauf der Kachel | 2.4.7, Build 2 |
| `Testing` | 23.04.2026, „Errorhändling verbessert“ | 2.5.3, Build 6 |

`beta` und `Testing` haben sich nach `440e356` (17.09.2025) getrennt. `Testing` ist der neueste Stand, aber weder in `beta` noch in `main` zusammengeführt. Welcher Branch im Module Store ausgeliefert wird, ist am Code nicht prüfbar.

Was `Testing` gegenüber `beta` bringt (Code, `libs/functions.php` und beide Module):

- Ohne Token keine API-Anfrage.
- Rate-Limit: HTTP 429/428 oder „Too many requests“ setzen ein Sperrfenster aus `Retry-After` (mindestens 60 s, sonst 300 s), Attribut `ApiRetryAfter`, Status 205; währenddessen keine Anfragen.
- HTTP 401/403 → Status 210, abgelehnte Query → neuer Status 206 (in beiden Formularen übersetzt).
- Preisabruf: kein zweiter Versuch mit Stundenauflösung mehr, wenn die Viertelstunden-Abfrage scheitert; stattdessen Wiederholung über den Preis-Zeitgeber (`PriceRetryCount`).
- Realtime: Neuanmeldung mit exponentiellem Backoff (30 s, verdoppelt bis höchstens 600 s) und Versuchszähler.
- README: Danksagung an die ursprünglichen Entwickler statt des alten Vorworts.

## Review 20.10.2026 (Tibber_Query)

Behoben: TypeError bei leerem Preisarray (`Statistics(null)` nach Status 203), Status blieb nach 203 auf 104, Token im Debug-Header, veralteter 15-Minuten-Cache leerte die Kachel (plus „Undefined array key 0“ bei `price_cur`), Stunde aus Listenindex statt `startsAt` (Zeitumstellung). Prüfstand `tests/query_test.php` (lokal) deckt die Fälle ab.

Noch offen aus dem Review:

- `ApplyChanges` macht bis zu drei API-Aufrufe und bis zu 240 × `IPS_Sleep`, ohne `KR_READY`-Prüfung und auch bei `InstanceActive = false`.
- `Price_Variables_15m` ohne `Enable_15m` legt 192 Variablen an, die nie befüllt werden; das Formular zeigt die Abhängigkeit nicht.
- `Price_log` aus lässt `Ahead_Price`, Archiv-Logging und Chart stehen; Statistik-Logging bleibt ebenfalls aktiv.
- Toter Code: `ProcessConsumptionData` (switch ohne break), `CalcNewDay`, `CalcNewHour`, `hoursUntilTomorrowMidnight`, `GetFullUpdateMessageMANU` (verpackt JSON in ein Array).
- `Update_Ahead_Price_Data` läuft je Preisabruf bis zu dreimal und schreibt jedes Mal Attribute (settings.json); `SetPriceVariables`/`RegisterVariables` schlafen je Variable.
- Kachel-Startzustand ohne `JSON_HEX_TAG` (derzeit nur Zahlen/Enums).

## Offen

- Arbeitsstand liegt seit 08.10.2026 in `symcon-beta` (= `Testing` + Projektgedächtnis, ohne die alte `.bak`-Datei); `beta` (2.4.7) und `main` (2.4.4) hängen zurück. Branchrolle von `Testing` festlegen.
- Platzhalter-Preise (0 ct, leeres `level`) in der Stunden-Variable für Optimierer, siehe [preisdaten-fuer-optimierer](entscheidungen/preisdaten-fuer-optimierer.md); entweder weglassen oder als Vertrag beibehalten und in SmartStart behandeln.
- Altbestand: `extends IPSModule`, Variablenprofile (`Tibber.price.cent`, `Tibber.price.level`, `~TextBox` …) für eigene Variablen, untypisierte Signaturen. Ein Umbau auf `IPSModuleStrict` mit Darstellungen ändert sichtbare Variablen bestehender Installationen und braucht deshalb einen Migrationsplan.
- `docs/preview.png` wird von keiner README eingebunden.

Stand: geprüft gegen den Code am 20.10.2026

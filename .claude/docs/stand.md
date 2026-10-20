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

Ebenfalls behoben (zweiter Durchgang): `ApplyChanges` wartet auf `KR_READY` (`IPS_KERNELSTARTED`), macht bei `InstanceActive = false` keine API-Aufrufe und überschreibt keinen Fehlerstatus; `Price_Variables_15m` wirkt nur mit `Enable_15m` (Formularfeld sonst unsichtbar); `Price_log` aus beendet nur das Archiv-Logging der Hilfsvariable (Variable und Chart bleiben); toter Code entfernt (`ProcessConsumptionData`, `CalcNewDay`, `CalcNewHour`, `hoursUntilTomorrowMidnight`, `GetFullUpdateMessageMANU`); keine `IPS_Sleep` je Variable mehr; Kachel-Startzustand mit `JSON_HEX_*`.

Preislog (Forumsbericht 20.10.2026, Stundenlücke im Day-Ahead-Chart um Mitternacht): `LogAheadPrices` schrieb jeden Tag eine Null auf 00:00:01 des Folgetags – auf dieselbe Sekunde wie den echten 0-Uhr-Preis, weil `count($result_array) <= 48` durch die Nullplatzhalter immer wahr war. Jetzt werden nur echte Preise geloggt, und die Null kommt nur ohne Morgen-Preise als einzelner Endmarker.

Bewusst gelassen: `Update_Ahead_Price_Data` läuft je Abruf zweimal (Preisabruf und aktueller Preis) und hält Kachel- und Vorschaudaten in Attributen – sie überleben so den Neustart und werden von `PriceArray()`/SmartStart gelesen.

Mit dem öffentlichen Demo-Token von Tibber (pyTibber `DEMO_TOKEN`) gegen die echte API geprüft: 96 Viertelstundenpreise heute und morgen, alle Optionen, Status 102, Kachel gerendert.

## Offen

- Arbeitsstand liegt seit 08.10.2026 in `symcon-beta` (= `Testing` + Projektgedächtnis, ohne die alte `.bak`-Datei); `beta` (2.4.7) und `main` (2.4.4) hängen zurück. Branchrolle von `Testing` festlegen.
- Platzhalter-Preise (0 ct, leeres `level`) in der Stunden-Variable für Optimierer, siehe [preisdaten-fuer-optimierer](entscheidungen/preisdaten-fuer-optimierer.md); entweder weglassen oder als Vertrag beibehalten und in SmartStart behandeln.
- Altbestand: `extends IPSModule`, Variablenprofile (`Tibber.price.cent`, `Tibber.price.level`, `~TextBox` …) für eigene Variablen, untypisierte Signaturen. Ein Umbau auf `IPSModuleStrict` mit Darstellungen ändert sichtbare Variablen bestehender Installationen und braucht deshalb einen Migrationsplan.
- `docs/preview.png` wird von keiner README eingebunden.

Stand: geprüft gegen den Code am 20.10.2026

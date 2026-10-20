# Tibber V.2

Symcon-Bibliothek für die Tibber-API: Strompreise (Stunden- und 15-Minuten-Preise, Kachel, Preisvorschau für Energie-Optimierer) und Realtime-Daten des Tibber Pulse. Ursprünglich von Philipp Hirzel und Kristian Lorbetzki entwickelt, übernommen und gepflegt von Stephan Sprick. Öffentliches Repo `da8ter/TibberV2`, Arbeitszweig `symcon-beta`; daneben `main`, `beta`, `Testing` (Lage in `.claude/docs/stand.md`).

Projektwissen: **`.claude/docs/README.md`**. Offenes und Branchlage: `.claude/docs/stand.md`. Der lokale Stand der Arbeitskopie steht in `CLAUDE.local.md` (nicht eingecheckt) – vor jedem Commit lesen.

## Aufbau

- **`Tibber_Query/`** (Klasse `Tibber`, Präfix `TIBV2`, Typ 3): GraphQL-Abfrage der Preise und Verbräuche, Preisvariablen, Kachel `module.html` (`GetVisualizationTile`), Preisvorschau-Variablen `Ahead_Price_Data_60m`/`_15m`, öffentliche `PriceArray()`/`PriceArray15m()`.
- **`Tibber_Realtime/`** (Präfix `TIBRTV2`, Typ 3): Realtime-Stream über einen WebSocket-Client als Elterninstanz.
- **`libs/functions.php`**: Trait `TibberHelper` (API-Aufruf, Fehler- und Rate-Limit-Behandlung, Statuscodes 201–210).
- Altbestand: `extends IPSModule` mit `declare(strict_types=1)`, Variablenprofile `Tibber.price.*` für eigene Variablen, untypisierte Overrides. Bei `IPSModule` keine Parametertypen in Overrides nachrüsten (LSP-Fehler); Umbau auf Darstellungen nur mit Migrationsplan.
- **Stundenpreise tragen die Stunde aus `startsAt`** im Ident (`PT60M_T0_<Stunde>`), nicht den Listenindex: an Zeitumstellungstagen hat der Tag 23 oder 25 Einträge (im Oktober zweimal `_2`). `SetActualPrice` und `Statistics` gehen über `start`/`end`, nicht über den Ident (Review 20.10.2026).
- `ProcessPriceData()` setzt den Status selbst: 203 ohne heutige Preise, sonst 102/104 je `InstanceActive`; `GetPriceData()` kümmert sich nur um den Zeitgeber. Der 15-Minuten-Cache `Price_Array_15m` wird nur genutzt, wenn er die aktuelle Stunde noch abdeckt.
- `CallTibber()` ist `protected`, damit der Prüfstand ihn durch eine feste Antwort ersetzen kann; der Authorization-Header wird im Debug maskiert.
- `ApplyChanges` wartet auf `KR_READY`, macht bei inaktiver Instanz keine API-Aufrufe und lässt einen Fehlerstatus (≥ 200) aus dem Abruf stehen. `Price_Variables_15m` gilt nur zusammen mit `Enable_15m`. `Price_log` aus beendet nur das Logging; Variable `Ahead_Price` und Chart bleiben (kein Datenverlust, Chart-Verweis bleibt gültig).
- **Echte API ohne eigenes Konto:** Tibber veröffentlicht einen Demo-Token (pyTibber `tibber/const.py`, `DEMO_TOKEN`; Demo-Konto mit Viertelstundenpreisen und Realtime). Nie ins Repo, nur im Docker (siehe `CLAUDE.local.md`).

## Prüfen

`tests/` ist nur lokal (`.git/info/exclude`): SDK-Attrappe für `IPSModule` in `tests/bootstrap.php`, `TibberProbe` liefert feste API-Antworten.

```bash
php tests/query_test.php
for f in */module.php libs/functions.php; do php -l "$f"; done
for f in */*.json library.json; do python3 -m json.tool "$f" > /dev/null; done
```

Gegenprobe gegen einen alten Stand: `TIBBER_MODULE=<Pfad zu module.php> php tests/query_test.php` (daneben muss `../libs/functions.php` liegen).

## Regeln

- **Commits:** ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Prüfungen vorher. Die Historie besteht weitgehend aus Web-Uploads; neue Commits lokal und sprechend.
- **Nie** `git checkout`/`git restore` auf Dateien und keinen Branchwechsel mit geänderter Arbeitskopie: dort liegt nicht committete Arbeit.
- **Push und Release nur auf Zuruf.** Release: `version`, `build` und `date` in `library.json` hochsetzen.
- **Öffentliches Repo:** keine Tibber-Token, Home-IDs, Instanz-IDs, Adressen, Pfade unter `/Users/`, keine Preise oder Verbräuche des eigenen Haushalts – auch nicht in Debug-Beispielen oder Commit-Botschaften.
- **Preisvorschau ist eine Schnittstelle:** Änderungen am Format der Variablen für Energie-Optimierer mit `.claude/docs/entscheidungen/preisdaten-fuer-optimierer.md` und dem Leser SmartStart abstimmen.
- **Doku nachziehen:** Ändert ein Commit etwas aus `docs/`, im selben Commit anpassen und das „Stand“-Datum erneuern.

## Plattformwissen

Gemessenes Symcon-Verhalten für alle Module: https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/.claude/docs/plattform (lokal `../List/.claude/docs/plattform/`). Für diese Bibliothek besonders `module-strict-und-php.md`, `kachel-visu.md` und `timer.md`.

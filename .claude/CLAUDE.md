# Tibber V.2

Symcon-Bibliothek für die Tibber-API: Strompreise (Stunden- und 15-Minuten-Preise, Kachel, Preisvorschau für Energie-Optimierer) und Realtime-Daten des Tibber Pulse. Ursprünglich von Philipp Hirzel und Kristian Lorbetzki entwickelt, übernommen und gepflegt von Stephan Sprick. Öffentliches Repo `da8ter/TibberV2`, Arbeitszweig `symcon-beta`; daneben `main`, `beta`, `Testing` (Lage in `.claude/docs/stand.md`).

Projektwissen: **`.claude/docs/README.md`**. Offenes und Branchlage: `.claude/docs/stand.md`. Der lokale Stand der Arbeitskopie steht in `CLAUDE.local.md` (nicht eingecheckt) – vor jedem Commit lesen.

## Aufbau

- **`Tibber_Query/`** (Klasse `Tibber`, Präfix `TIBV2`, Typ 3): GraphQL-Abfrage der Preise und Verbräuche, Preisvariablen, Kachel `module.html` (`GetVisualizationTile`), Preisvorschau-Variablen `Ahead_Price_Data_60m`/`_15m`, öffentliche `PriceArray()`/`PriceArray15m()`.
- **`Tibber_Realtime/`** (Präfix `TIBRTV2`, Typ 3): Realtime-Stream über einen WebSocket-Client als Elterninstanz.
- **`libs/functions.php`**: Trait `TibberHelper` (API-Aufruf, Fehler- und Rate-Limit-Behandlung, Statuscodes 201–210).
- Altbestand: `extends IPSModule` mit `declare(strict_types=1)`, Variablenprofile `Tibber.price.*` für eigene Variablen, untypisierte Overrides. Bei `IPSModule` keine Parametertypen in Overrides nachrüsten (LSP-Fehler); Umbau auf Darstellungen nur mit Migrationsplan.

## Prüfen

Kein Prüfstand vorhanden.

```bash
for f in */module.php libs/functions.php; do php -l "$f"; done
for f in */*.json library.json; do python3 -m json.tool "$f" > /dev/null; done
```

## Regeln

- **Commits:** ein Thema je Commit, deutsche Botschaft, **ohne** Co-Authored-By-Zeile. Prüfungen vorher. Die Historie besteht weitgehend aus Web-Uploads; neue Commits lokal und sprechend.
- **Nie** `git checkout`/`git restore` auf Dateien und keinen Branchwechsel mit geänderter Arbeitskopie: dort liegt nicht committete Arbeit.
- **Push und Release nur auf Zuruf.** Release: `version`, `build` und `date` in `library.json` hochsetzen.
- **Öffentliches Repo:** keine Tibber-Token, Home-IDs, Instanz-IDs, Adressen, Pfade unter `/Users/`, keine Preise oder Verbräuche des eigenen Haushalts – auch nicht in Debug-Beispielen oder Commit-Botschaften.
- **Preisvorschau ist eine Schnittstelle:** Änderungen am Format der Variablen für Energie-Optimierer mit `.claude/docs/entscheidungen/preisdaten-fuer-optimierer.md` und dem Leser SmartStart abstimmen.
- **Doku nachziehen:** Ändert ein Commit etwas aus `docs/`, im selben Commit anpassen und das „Stand“-Datum erneuern.

## Plattformwissen

Gemessenes Symcon-Verhalten für alle Module: https://github.com/da8ter/SymDo-Family-Organizer/tree/SymDo-Beta/.claude/docs/plattform (lokal `../List/.claude/docs/plattform/`). Für diese Bibliothek besonders `module-strict-und-php.md`, `kachel-visu.md` und `timer.md`.

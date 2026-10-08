# Preisdaten für Energie-Optimierer

Vertrag der Variablen „Preisvorschaudaten für Energie Optimierer“ des Moduls Tibber V.2 (`Tibber_Query`), wie andere Module sie lesen, z. B. SmartStart (`da8ter/TibberSmartStart`). Beschrieben ist der Stand der Branches `beta` und `Testing` (`Update_Ahead_Price_Data()` ist in beiden gleich); `main` baut die Daten noch anders und ohne Platzhalter.

## Vertrag

- **Zwei String-Variablen**, angelegt mit der Einstellung `Ahead_Price_Data_bool`: `Ahead_Price_Data_60m` (Stundenpreise) und `Ahead_Price_Data_15m` (15-Minuten-Preise).
- **Inhalt:** JSON-Liste, je Abschnitt `{"start": <Unix-Sekunden>, "end": <Unix-Sekunden>, "price": <ct/kWh, 2 Nachkommastellen>, "level": "VERY_CHEAP" … "VERY_EXPENSIVE"}`, aufsteigend nach `start`. Preise sind Brutto-Endpreise der Tibber-API (`total`) in Cent.
- **Zeitraum:** ab der aktuellen vollen Stunde, so viele Stunden wie die Kachel zeigt (`HTML_Default_HourAhead`), mindestens 12, höchstens 48. Vergangene Abschnitte der laufenden Stunde sind enthalten.
- **15-Minuten-Variable** ist nur gefüllt, wenn „15-Minuten-Preise aktivieren“ (`Enable_15m`) an ist und die API Viertelstunden liefert; sonst leerer String.
- **Stunden-Variable** ist bei 15-Minuten-Daten der Mittelwert der vier Viertelstunden einer Stunde, `level` vom ersten Viertel.

## Fallen

- **Platzhalter im Stundenmodus:** Ohne 15-Minuten-Daten entsteht für jede Stunde des Zeitraums ein Eintrag; fehlt der Preis (typisch: Folgetag vor der Veröffentlichung), steht dort `"price": 0.0, "level": ""`. Leser müssen Einträge mit leerem `level` als „kein Preis“ behandeln, sonst gilt die Stunde als die günstigste. SmartStart tut das bisher nicht.
- **Im 15-Minuten-Modus gibt es keine Platzhalter,** sondern Lücken: fehlende Viertelstunden fehlen in der Liste.
- **Demo-Modus weicht ab:** Mit `DemoMode` und `Enable_15m` landet in der Stunden-Variable die Viertelstunden-Liste, obwohl der Kommentar „immer Stundenpreise“ sagt.
- **Zeitzone:** Die Funktion setzt `date_default_timezone_set('Europe/Berlin')` für den ganzen PHP-Lauf.

Stand: geprüft gegen den Code am 08.10.2026

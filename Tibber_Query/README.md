# Tibber
Mit diesem Modul können die Informationen abgerufen werden welche von der Tibber Query API bereitgestellt werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
7. [Symcon Kachel](#7-symcon-kachel)

### 1. Funktionsumfang

* Auslesen aktueller Preis
* Auslesen aktueller Preis Level (sehr günstig, günstig, normal, teuer, sehr teuer)
* Preisvorschau als Chart
* Variablen pro Stunden anlegen für den heutigen und morgigen Tag
* Array zur Verwendung in eigene Anwendungen und Scripten.
 
### 2. Voraussetzungen

- Symcon ab Version 7.1
- Tibber Account UND Vertrag zum Heim. Ohne Vertrag bekommen wir keine Preisdaten!
- Tibber Api Token -> [Tibber Developer](https://developer.tibber.com/) -> dort auf Sign-in, meldet euch mit eurem Tibber Account an und erstellt dort den Access-Token.

### 3. Software-Installation

* Über den Module Store das 'Tibber V.2'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/lorbetzki/net.lorbetzki.tibber.v2.git

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann die 'Tibber V.2'-Instanz mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name          				     | Beschreibung
-------------------------------- | -------------------------------------------------------
aktiviere Instanz | aktivieren der Instanz
Benutzer Token | Access-Token aus der Tibber API eintragen
Heim auswählen | Nachdem der Token eingetragen und die Änderung übernommen wurde, werden hier die im Account gespeicherten Heime aufgeführt. Wählt das, welches Ihr abfragen möchtet
Preisdatenvariablen loggen | Diese Checkbox muss aktiviert werden, wenn die Day Ahead Preise im Archiv gespeichert sowie der Multi-Chart erzeugt werden sollen. [1] 
Preisvariablen pro Stunde anlegen | Wird diese Checkbox aktivert, werden 48 Variablen ( 24 für den aktuellen Tag und 24 für den Folgetag) für jede Stunde angelegt, welche beim Abruf der Day Ahead Preise aktualisiert werden.
einige Statistiken | erstellt Variablen mit ein paar statistischen werten.
erstelle variable für Energie optimierer | Variable um in einer zukünftiger Version des Symcon Energie Optimierers weiter zu verwenden
Schriftfarbe Balken | ändert die Schriftfarbe innerhalb der Balken
Schriftfarbe der aktuellen Stunde | ändert die Schriftfarbe der Stunden
Hintergrundfarbe der aktuellen Stunde | ändert die Hintergrundfarbe der aktuellen Stunde
Legen Sie die Skala des Balkens fest, Werte zwischen 1-10 sind zulässig. | passt die höhe der Balken an, um differnzen größer wirken zu lassen
Beginn Farbverlauf | damit lässt sich der untere Teil der Balken farblich den eigenen wünschen anpassen
Ende Farbverlauf | damit lässt sich der obere Teil der Balken farblich den eigenen wünschen anpassen
zeige Tibber Preislevel Indikator | zeigt eine Markierung am unteren Ende der Balken mit dem aktuellen Tibber Preislevel
dicke der Markierung in px | hiermit kann man die Markierung in deren Dicke anpassen
Farbe für Level sehr günstig, günstig, normal, teuer, sehr teuer | anpassen der Farbe für die jeweiligen Level nach eigenen Bedürfniss
setze Balkenradius | passt den Radius der Balken an
anzeige der Nachkommastelle | zeigt im Balken den Preis mit 1, 2 oder 0 Nachommastellen an
Zeige cent als Suffix | zeigt im Balken ct ans suffix an oder nicht

Schriftgröße Balken, Stunde, Preise min, standard, max | hier kann man die mindest und maximalst größe in Abhängigkeit der Viewport-Breite anpassen. 1VW (Viewport-Width) entspricht dabei 1% der Breite des Viewports.


[1] Es werden Day Ahead Preise für den aktuellen und (wenn schon publiziert) für den Folgetag abgerufen und gespeichert. Dies passiert rückwirkend, da Symcon keine zukünftigen Werte im Archiv erlaubt, in der "Day Ahead Preis Hilfsvariable" Variablen. Dabei wird der aktuelle Tag mit T -2 und der morgige Tag mit T -1 ins Archiv gespeichert.
Zusätzlich wird automatisch ein Multi-Chart angelegt, welcher diese beiden Tage im stündlichen Vergleich über die beiden Tagen darstellt.


### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name						 | Typ     | Beschreibung
-----------------------------| ------- | ------------
Aktueller Preis | FLOAT | Gibt den aktuellen Strompreis von Tibber wieder
Aktueller Preis Level | INT | Preisniveau auf der Grundlage des nachlaufenden Preisdurchschnitts 
Day Ahead Preis Hilfsvariable | FLOAT | Wird nur gebraucht um einen Chart über zukünftige Preise erstellen zu können
Preis Array | STRING | Tibber Abfrage wird in ein Array geschrieben um eigene Anwendungen zu ermöglichen. WIRD ERSETZT DURCH DIE FUNKTION TIBV2_PriceArray();
Realtime Verfügbar | BOOL | Gibt an, ob ein Pulse oder anderer Smartmeter in Tibber erkannt wurde und Echtzeitabfragen des Verbauchs zu ermöglichen. Dazu das Modul TIBV2_Realtime benutzen
Day Ahead Chart | CHART | Multichart mit Anzeige vergangener und zukünftigen Preise
Heute 0 bis 1 Uhr | FLOAT | Preisvariable pro Stunde 
Heute 1 bis 2 Uhr | FLOAT | Preisvariable pro Stunde 
... | ... | ...
Heute 23 bis 24 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 0 bis 1 Uhr | FLOAT | Preisvariable pro Stunde 
Morgen 1 bis 2 Uhr | FLOAT | Preisvariable pro Stunde 
... | ... | ...
Morgen 23 bis 24 Uhr | FLOAT | Preisvariable pro Stunde 
min/max Preisspanne für heute | FLOAT | Preisspanne, also die differenz zwischen min und max Wert
max. Preis für heute  | FLOAT | der höchste Tagespreis 
min. Preis für heute | FLOAT | der niedriegste Tagespreis
niedrigster Preis am diesem Zeitpunkt  für heute | INT | der niedriegste war zu diesem Zeitpunkt
höchster Preis am diesem Zeitpunkt für heute | INT | der höchste Preis war zu diesem Zeitpunkt
min/max Preisspanne für morgen | FLOAT | Preisspanne, also die differenz zwischen min und max Wert
max. Preis für morgen | FLOAT | der höchste Tagespreis 
min. Preis für morgen | FLOAT | der niedriegste Tagespreis
niedrigster Preis am diesem Zeitpunkt für morgen | INT | der niedriegste war zu diesem Zeitpunkt
höchster Preis am diesem Zeitpunkt für morgen | INT | der höchste Preis war zu diesem Zeitpunkt
Anzahl sehr günstiger Preis | INT | Anzahl des Preises
Anzahl günstiger Preis | INT | Anzahl des Preises
Anzahl normaler Preis | INT | Anzahl des Preises
Anzahl teurer Preis | INT | Anzahl des Preises
Anzahl sehr teurer Preis | INT | Anzahl des Preises
Preisvorschaudaten für Energie Optimierer | String | erstellt eine Variable die in den Symcon Energie Optimierer eingebunden werden kann.

#### Profile

Name                    | Typ
------------------------| -------
Tibber.price.cent | INT | Eurocent zweistellig
Tibber.price.level | INT | ermittelter Preislevel von Tibber 1 = sehr günstig, 2 = günstig, 3 = normal, 4 = teuer, 5 = sehr teuer, 0 = -
Tibber.price.hour | INT | zeigt nur den Suffix "Uhr" an. 

### .6 PHP-Befehlsreferenz
`TIBV2_GetPriceData(integer $InstanzID);`

Holt sich neue Preisdaten von Tibber ab und schreibt es in eine interne Variable. Bei aktiviertem "Preis - Array anlegen -> Zur Nutzung in Scripten und anderen Modulen" aktiviert ist, wird auch diese Variable geschrieben.

Beispiel:
`TIBV2_GetPriceData(12345);`


`TIBV2_SetActualPrice(integer $InstanzID);`

Holt sich neue Preisdaten von Tibber ab und schreibt diese in alle aktivierten Variablen.

Beispiel:
`TIBV2_SetActualPrice(12345);`	



`TIBV2_GetConsumptionHourlyLast(integer $InstanzID, integer $count);`,

`TIBV2_GetConsumptionDailyLast(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionWeeklyLast(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionMonthlyLast(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionYearlyLast(integer $InstanzID, integer $count);`

Holt sich die letzen Verbrauchsdaten für den angegebenen ($count) Zeitraum ab. $count gibt die Anzahl der Datensätze an. Als Ergebnis kommt ein JSON Codierter Datensatz mit dem man weiterarbeiten kann.

Beispiel starte ich um 21:00 diese Abfrage werden die letzten 24 Stunden ab 21:00 des vortages abgeholt:

`TIBV2_GetConsumptionHourlyLast(12345,24);`

Beispiel holt den letzten Monat ab:

`TIBV2_GetConsumptionMonthlyLast(12345,1);`



`TIBV2_GetConsumptionHourlyFirst(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionDailyFirst(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionWeeklyFirst(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionMonthlyFirst(integer $InstanzID, integer $count);`, 

`TIBV2_GetConsumptionYearlyFirst(integer $InstanzID, integer $count);`

Holt sich die Verbrauchsdaten für den angegebenen ($count) Zeitraum ab. $count gibt die Anzahl der Datensätze an. Als Ergebnis kommt ein JSON Codierter Datensatz mit dem man weiterarbeiten kann.

Beispiel holt die letzten 24 Stunden ab Anfang des Monats ab:

`TIBV2_GetConsumptionHourlyFirst(12345,24);`

Beispiel holt die Daten des aktuellen Monats ab:

`TIBV2_GetConsumptionMonthlyFirst(12345,1);`


`TIBV2_PriceArray(integer $InstanzID);`
kann für externe Scripte eingesetzt werden. Gibt das abgeholte Preis Array raus

Beispiel:
`TIBV2_PriceArray(12345);`

### 7. Symcon Kachel
Mit der Version 2 von diesem Modul gibt es auch eine Preisvorschau Kachel. Um diese in der Visu anzeigen zu können wird nur ein Link der Tibber Query Instanz in der Visu Kategorie benötigt. Meinen Dank geht an [Da8ter](https://github.com/da8ter) Kachelsammlung.
![grafik](../docs/preview.png?raw=true)


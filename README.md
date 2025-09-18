### IP-Symcon Modul Library: Tibber V.2 
 
Diese Modul wurde von Philipp Hirzel entwickelt aufgrund von Zeitmangel aber leider nicht veröffentlicht. Dankenswerterweise durfte ich das Modul übernehmen und der Symcon Community zur Verfügung stellen. Bei Fragen oder Wünschem gerne Meldung an mich.
  
Die Nutzung des Moduls geschieht auf eigene Gefahr ohne Gewähr. Es handelt sich hierbei um einen frühen Entwicklungsstand.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Module](#4-module)
5. [15-Minuten-Werte](#5-15-minuten-werte)

## 1. Funktionsumfang

Die Tibber Library stellt 2 Module zur verfügung mit denen die "Tibber Query API" und die "Tibber Realtime API" abgefragt werden können.

Einen genauen Funktionsumfang des jeweiligen Moduls wird in der Modul Readme detailiert beschrieben

## 2. Systemanforderungen
- Symcon ab Version 7.1
- Tibber Account
- Tibber Api Token -> [Tibber Developer](https://developer.tibber.com/) -> dort auf Sign-in, meldet euch mit eurem Tibber Account an und erstellt dort den Access-Token.
- optional: Tibber Pulse für Realtime Daten

## 3. Installation

* Über den Module Store das 'Tibber'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen https://github.com/da8ter/TibberV2.git


## 4. Module

### 4.1. Tibber V.2

Mit dem Tibber Modul kann die normale Tibber API abgefragt werden.
Genaue Infos sind in der Modul Readme vorhanden

### 4.2. Tibber Realtime V.2

Mit dem Tibber Realtime Modul kann optional der Realtime-Stream von Tibber abgefragt werde, sofern ein Tibber Pulse Zähler mit dem Tibber-Account verknüpft ist.
Genaue Infos sind in der Modul Readme vorhanden

## 5. Tibber Query V.2

Abfrage und Visualisierung der Tibber Preise.
Dieses Modul unterstützt die Anzeige und Weitergabe von 15-Minuten-Preisen (4 Balken pro Stunde) zusätzlich zu den Stundenpreisen.

- **Visualisierung (Kachel)**
  - 24-Stunden-Fenster, beginnend ab der aktuellen vollen Stunde.
  - Bei 15-Minuten-Daten werden vier Balken pro Stunde dargestellt.
  - Das Label zeigt den Durchschnittspreis der Stunde an.

- **Preisvorschaudaten für Energie Optimierer**
  - Einstellung: „erstelle Variablen für Energie Optimierer mit Stundenpreisen und 15 Minuten Preisen“. Legt zwei Text-Variablen an:
    - „Preisvorschaudaten für Energie Optimierer (Stundenpreise)“: Stundenpreise
    - „Preisvorschaudaten für Energie Optimierer (15-Minuten-Preise)“: 15-Minuten-Segmente. Bleibt leer, wenn 15m nicht aktiv oder nicht von der API geliefert.

- **Aktueller Preis und Level**
  - Variablen „Aktueller Preis“ und „Aktueller Preis Level“ verwenden bei aktivierter Einstellung „15-Minuten-Preise aktivieren“ den Preis/Level des aktuell laufenden 15-Minuten-Segments.
  - Der Aktualisierungstimer für den aktuellen Preis läuft bei aktivem 15m-Modus exakt zur nächsten Viertelstunde (00/15/30/45, mit kleinem Puffer). Im Stundenmodus bleibt die Aktualisierung stündlich.

- **Optionale Preis-Variablen**
  - „Preis - Variablen pro Stunde anlegen (24 für aktuellen Tag & 24 für die Preisvorschau)“
  - „Preis - Variablen je 15 Minuten anlegen (96 für aktuellen Tag & 96 für die Preisvorschau)“

  
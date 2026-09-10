# Changelog

Alle nennenswerten Änderungen an diesem Projekt stehen hier. Format nach
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach
[SemVer](https://semver.org/lang/de/).

## [Unreleased]

### Geplant

- **Logging in der Statamic-Hülle.** Der Kern bleibt I/O-frei und wirft; die
  Hülle muss fangen und mit Kontext protokollieren, statt einen 500 zu
  produzieren. Mit unterschiedlichen Stufen (zu großer Logokasten ist
  `warning`, unerwarteter Fehlschlag `error`), mit Symbolgröße, Version, Stufe
  und Kastenmaßen als Kontext, **ohne die Nutzlast**, und ohne dass ein
  einzelner Fehlschlag einen Stapellauf abbricht
- Code- und Token-Erzeugung, `CodeRepository`-Interface
- Statamic-Hülle: ServiceProvider, Artisan-Command, Auflösungs-Route,
  Download-Seite, Flat-File-Repository
- `statamic/cms` und `extra.laravel.providers` in der `composer.json`, sobald
  der ServiceProvider existiert
- Raster-Logo als Data-URI, falls kein SVG geliefert wird
- PNG-Ausgabe ohne `gd`, über `zlib` und `crc32()`

## [0.5.0] - 2026-09-10

Textsammlung und festgelegte Werte.

### Hinzugefügt

- **`resources/lang/de.php` und `en.php`** — alle Oberflächentexte an einer
  Stelle. Deutsch ist die Standardsprache, Englisch existiert als Katalog, wird
  aber von nichts angeboten: es ist eine Frage danach (`?lang=en`), keine Datei,
  die noch zu schreiben wäre. Format ist **Laravels** — `return`-Array,
  punktgetrennte Schlüssel, `:name`-Platzhalter — damit Laravels eigener
  Übersetzer dieselben Dateien später in der Statamic-Hülle lädt, ohne dass sie
  angefasst werden
- **`Redcodede\QrGen\I18n\Translator`**, bewusst **außerhalb** von `src/Qr/`.
  Der Kern braucht keine Übersetzungen und darf keine Dateien lesen; die
  Meldungen seiner Ausnahmen sind Entwicklerdiagnostik und bleiben englisch.
  Ein fehlender Schlüssel kommt als er selbst zurück — lauter als ein leerer
  String, leiser als eine Ausnahme
- **`Redcodede\QrGen\Qr\Preset`** — die entschiedene Konfiguration an einer
  Stelle: Logokasten **11**, Rand **1**, Modulgröße **13**, Ruhezone **2**.
  Die Fehlerkorrekturstufe fehlt absichtlich; sie folgt aus Nutzlast und Kasten
  und wird von `LogoFit` ausgerechnet
- 32 Tests dazu. Drei davon halten die Kataloge zusammen: gleiche Schlüssel,
  gleiche Platzhalter je Schlüssel, kein leerer Text. Eine Übersetzung, die
  still auseinanderläuft, ist schlimmer als eine fehlende

### Geändert

- **Die Demo-Seite hat nur noch zwei Eingaben: URL und Bildmarke.**
  Fehlerkorrektur, Kasten, Rand, Modulgröße, Ruhezone, Transparenz und die
  Ausrichtungsmuster-Erlaubnis sind keine Felder mehr. Damit gibt es nichts, was
  in einen schlechten Zustand geraten kann, und die Demo übt die Form, die das
  Plugin bekommt — eine Ziel-URL und ein Asset-Pfad
- Kein Text steht mehr in `demo/index.php`; alles kommt aus dem Katalog. Zahlen
  werden mit deutschem Dezimalkomma formatiert

### Beachten

- **Die Ruhezone von 2 Modulen ist eine bewusste Abweichung** von ISO/IEC
  18004, die 4 verlangt. Sie trägt nur, wenn das Layout drumherum die fehlenden
  zwei Module an Weißraum beisteuert; grenzt der Code direkt an Grafik, wird er
  unzuverlässig. Die Seite weist darauf hin, solange die Abweichung besteht
- **Der Standardwert der Bibliothek bleibt bei 4.** `SvgOptions::default()`
  hält sich an die Norm, die Abweichung gehört dem Projekt. Ein Test hält beides
  auseinander und schlägt an, wenn die Abweichung verschwindet

## [0.4.2] - 2026-09-10

Formularzustand. Alle drei Punkte betreffen nur die Demo-Seite, nicht das Paket.

### Behoben

- **Das Formular hat `autocomplete="off"`.** Chrome stellt Feldwerte bei einem
  weichen Neuladen wieder her, also überlebte ein Wert, der einmal in einem
  Feld stand, jedes Refresh — URL und Standardwerte sagten das eine, das
  Formular zeigte das andere. Von außen sieht das aus wie „die Seite hat nicht
  vernünftig nachgeladen", und nur ein frischer Aufruf des Links räumte es weg.
  Das war die Ursache eines gemeldeten Fehlerbildes (`37x37`-Kasten auf einem
  `25x25`-Symbol), nicht eine fehlerhafte Eingabe
- **Das Mausrad verstellt die Zahlenfelder nicht mehr.** Ein fokussiertes
  `input[type="number"]` behandelt das Rad als Drehregler, also verändert
  Scrollen mit dem Zeiger darüber den Wert stillschweigend. Bei `step="2"` am
  Logokasten sind das von 9 auf 37 in vierzehn Rasten, und der Wert sieht
  danach aus wie etwas, das jemand absichtlich eingetippt hat. Das Feld gibt
  jetzt den Fokus ab, statt zu zählen, und die Seite scrollt

### Hinzugefügt

- **Reset-Knopf** neben „Generate". Ein Klick zurück auf die Standardwerte,
  ohne die URL von Hand zu putzen

## [0.4.1] - 2026-09-10

### Behoben

- **Das Logo wurde ohne Kantenglättung gezeichnet.** Das Wurzelelement trägt
  `shape-rendering="crispEdges"`, was für Module richtig ist — achsparallele
  Quadrate, die ein Scanner hart will — und für Zeichnungen falsch. Geerbt von
  gekrümmten Pfaden in diesem Maßstab (500 Einheiten in neun Module) fallen
  feine Formen weg und Kurven zacken, was aussieht wie „das Logo ist nicht
  gerendert worden". Die Logo-Gruppe setzt jetzt
  `shape-rendering="geometricPrecision"` für ihren Teilbaum
- Die Demo-Seite schickt `Cache-Control: no-store`. Eine
  zwischengespeicherte Kopie, die einen Fehler von gestern zeigt, ist
  schlimmer als ein etwas langsamerer Neuaufbau

### Geändert

- **Standardkonfiguration, die sichtbar funktioniert.** Kasten **9** statt 11,
  Modulgröße **10** statt 8. Auf der Demo-URL waren elf Module 38 % der Breite
  und 14,4 % der Module — innerhalb der Reserve, aber mit 4 % Rest. Neun sind
  31 % der Breite und 9,6 % der Module, lassen 23 % übrig und liegen in dem
  Bereich, den die Praxis nutzt (10 bis 20 % freigeräumt). Ein Standard sollte
  die Konfiguration sein, die man ausliefern würde, nicht die größte, die noch
  durchgeht
- `demo/logos/contrast-check.svg` neu und als Standardlogo vorausgewählt: eine
  kontraststarke Referenzmarke. Ist sie sichtbar, funktioniert die Einbettung —
  und ein blasses Logo daneben ist blass, nicht kaputt

## [0.4.0] - 2026-09-10

Die Stufe wird nicht mehr geraten, sondern ausgerechnet.

### Hinzugefügt

- **`Qr\Logo\LogoFit`** — findet die **niedrigste** Fehlerkorrekturstufe, bei
  der ein Logokasten überlebt, und gibt das schon kodierte Symbol mit zurück.
  Die niedrigste, nicht die höchste: eine höhere Stufe hilft doppelt (mehr
  Wiederherstellung und ein größeres Symbol, in dem derselbe Kasten weniger
  Anteil hat), kostet aber Dichte
- `Qr\Logo\LogoFitResult` mit `clearedShare()`, `budget()`, `headroom()` und
  `compromisesAlignment()` — die Zahlen hinter der Entscheidung
- `Qr\Exception\NoFittingLevel` nennt **den Grund je Stufe** und den größten
  Kasten, der bei H noch ginge. „Passt nicht" allein ist nutzlos: ob man den
  Kasten verkleinert, die Nutzlast kürzt oder einen Kompromiss eingeht, hängt
  davon ab, an welcher Wand man bei welcher Stufe steht
- `LogoFit::largestFittingAt()` — größter Kasten bei gegebener Stufe, das
  Seitenverhältnis behaltend
- `ErrorCorrection::recoveryRate()` — 7 / 15 / 25 / 30 %
- **Sicherheitsfaktor**, Standard `0.5`: höchstens die Hälfte der
  Wiederherstellungsrate darf das Logo kosten. Die andere Hälfte zahlt für
  Farbzuwachs, Kratzer, schlechtes Licht und ein schräg gehaltenes Telefon.
  Konstruktorargument, weil jemand mit einem Andruck in der Hand es besser weiß
- `LogoBox::allowingAlignmentPatterns()` und
  `LogoPlacement::compromisesAlignment()` / `coveredAlignmentModules()`
- Demo-Seite: Stufenauswahl kennt **`auto`**, zeigt die gewählte Stufe, die
  Ausnutzung der Reserve und ob ein Ausrichtungsmuster aufgegeben wurde.
  Dazu ein Schalter für die Erlaubnis

### Geändert

- **Ausrichtungsmuster werden von den übrigen Funktionsmustern
  unterschieden.** Ein Such-, Takt- oder Formatmuster zu verdecken nimmt einem
  Scanner die Geometrie und wird immer abgewiesen. Ein Ausrichtungsmuster
  dient der Verzerrungskorrektur; eines von mehreren zu verlieren ist ein
  Kompromiss und auf Wunsch erlaubt
- `ModuleMatrix` nimmt eine dritte, optionale Maske für die
  Ausrichtungsmuster; `BaconQrEncoder` füllt sie aus
  `Version::getAlignmentPatternCenters()` und lässt die drei Kombinationen aus,
  die auf einer Sucheck liegen und deshalb nicht als Ausrichtungsmuster
  gezeichnet werden

### Korrigiert

- **Die Aussage „ab Version 7 sitzt ein Ausrichtungsmuster in der Mitte" war
  falsch.** Gegen die Versionstabelle gemessen gilt das für die Versionen
  **7 bis 13** sowie **21, 23, 25 und 27**; 1–6, 14–20, 22, 24, 26 und ab 28
  haben die Mitte frei. Die Verallgemeinerung stand in Kommentaren, in einer
  Ausnahmemeldung und in der Doku und ist überall berichtigt. Aufgefallen ist
  es, weil ein Test darauf gebaut hatte und fehlschlug

## [0.3.1] - 2026-09-10

### Geändert

- **Die beiden Logo-Ablehnungen nennen jetzt Zahlen.** „The logo box reaches
  into the corner zone of a finder pattern. Use a smaller box." schickte
  jemanden zur Logodatei, obwohl die Ursache die Fehlerkorrekturstufe war: eine
  niedrigere Stufe ergibt ein kleineres Symbol, und ein Kasten, der bei H passt,
  passt bei M nicht mehr. Die Meldung nennt nun Kastenmaß, Symbolgröße, den
  größten hier möglichen Kasten und den Hinweis, dass die Symbolgröße aus
  Nutzlast und Stufe folgt und keine Einstellung ist
- `LogoBox::largestSideFor()` neu: der größte zentrierte Kasten, der in einem
  Symbol dieser Größe die Suchmuster freilässt. Damit lässt sich vorher fragen,
  statt hinterher zu scheitern. Die Demo-Seite zeigt den Wert an

### Hinzugefügt

- Sieben Tests dazu, darunter der Fall, der tatsächlich aufgetreten ist: ein
  Kasten, der bei Stufe H passt und bei M nicht mehr

## [0.3.0] - 2026-09-10

Ein Logo in der Mitte, mit Ruhezone darum.

### Hinzugefügt

- `Qr\Contract\Logo` — Markup plus Eigengröße. Ein Logo liefert **Markup,
  keinen Pfad**: das Lesen der Datei bleibt beim Aufrufer, was den Kern frei
  von Datei-I/O hält
- `Qr\Logo\SvgLogo` — Sanitizer für fremdes SVG. **Baut das Markup aus
  geparsten Tokens neu auf**, statt es zu filtern, damit nichts Unverstandenes
  im Ergebnis auftauchen kann. Whitelist aus acht Elementen und den
  Geometrie-, Fill-, Stroke- und Transform-Attributen; ein `<style>`-Block mit
  einfachen Klassenselektoren wird in Präsentationsattribute aufgelöst (das ist
  Illustrators Standardexport), `id`-Attribute werden entfernt statt umbenannt.
  Abgewiesen statt bereinigt werden `<script>`, `on…`-Handler, `<image>`,
  `<text>`, `<use>`, `<a>`, `<foreignObject>`, Animationen, nicht leere
  `<defs>`, `url(…)`, `xlink:href`, `data:`, At-Rules, DOCTYPE mit interner
  Teilmenge, loser Text und unbalancierte Tags
- `Qr\Logo\LogoBox` und `Qr\Logo\LogoPlacement` — der freigeräumte Kasten in
  Modulen, Logo **plus** Rand. Beide Kantenlängen müssen ungerade sein, weil
  ein QR-Symbol immer ungerade ist und eine gerade Kante einen halben Modul
  neben dem Raster läge. Jedes Seitenverhältnis, auch aus dem Logo abgeleitet
  über `forAspectRatio()`
- `Qr\Exception\LogoRejected` mit einer Meldung je Ablehnungsgrund, die benennt,
  was im Export zu beheben ist
- `SvgOptions::withLogo()` / `withoutLogo()`
- Demo-Seite zeigt **beide Varianten nebeneinander**, mit Kennzahlen zum
  freigeräumten Anteil und je einem Download. `demo/logos/` nimmt Testlogos auf
- 74 weitere Tests: 36 für den Sanitizer, dazu Kasten, Platzierung, Freiräumen,
  Transform und ein Lauf über die echten Dateien in `demo/logos`

### Geändert

- `ModuleMatrix` nimmt optional eine **Funktionsmuster-Maske** und beantwortet
  `isReserved()` und `hasReservedInfo()`. Such-, Trenn-, Takt- und
  Ausrichtungsmuster sowie Format- und Versionsinformation sind **nicht**
  fehlerkorrigiert, also muss alles, was Module entfernt, wissen wo sie liegen —
  und nur der Encoder weiß das. Der Parameter ist optional, bestehender Code
  bleibt unverändert lauffähig
- `BaconQrEncoder` füllt die Maske aus `Version::buildFunctionPattern()`, statt
  Positionen aus Tabellen zu schätzen. Ein Logokasten über einem
  Ausrichtungsmuster wird damit **abgewiesen**, nicht gerendert — bei manchen
  Versionen sitzt eines dicht an der Mitte
- `SvgRenderer` räumt die Module unter dem Kasten **frei**, statt sie zu
  zeichnen und zu überdecken. Das Logo sitzt dann auf dem Hintergrund und der
  Pfad bleibt so klein wie möglich
- `ModuleMatrix::version()` neu, leitet die Version aus der Seitenlänge ab

### Abgewiesen wird jetzt auch

- ein Logo bei transparentem Hintergrund. Die freigeräumte Fläche muss hell
  lesen, sonst scheint der Untergrund durch und ein Scanner sieht dort weder
  hell noch dunkel

### Befunde am echten Material

- **`gvoe-logo-cmyk.svg` ist trotz des Dateinamens brauchbar.** SVG kennt kein
  CMYK; die Datei trägt Hex-Farben und ist bis auf den `<style>`-Block ideale
  Eingabe. Der offene Punkt „RGB-Logo fehlt" war ein Missverständnis
- Für `https://gvoe.de/return/7K4M2` bei Stufe H (Version 4, 33 × 33) räumt ein
  Kasten von 11 × 11 **11,1 %** der Module frei und trifft kein
  Funktionsmuster. Bei Stufe Q (Version 3) trifft 13 × 13 zwei Module des
  Ausrichtungsmusters

## [0.2.0] - 2026-09-10

Stufe 1: eine URL wird zu einem SVG, das als QR-Code funktioniert.

### Hinzugefügt

- `Qr\Contract\QrEncoder` und `Qr\Encoder\BaconQrEncoder` — Zeichenkette zu
  Modul-Matrix, Schritte 1 bis 6 der ISO/IEC 18004
- `Qr\Contract\QrRenderer` und `Qr\Render\SvgRenderer` — Matrix zu SVG,
  Schritt 7, selbst geschrieben. Ein einziger `<path>` mit
  zusammengefassten Modulläufen, `viewBox` in Modul-Einheiten
- `Qr\Render\SvgOptions` — unveränderliche Einstellungen für Modulgröße,
  Ruhezone, Farben, Titel und XML-Deklaration. Farben werden validiert,
  nicht escaped
- `Qr\ModuleMatrix` — die Grenze zwischen Kodieren und Zeichnen, ohne
  Ruhezone
- `Qr\ErrorCorrection` — die vier Stufen der Norm, auch aus einer
  Zeichenkette
- `Qr\Exception\QrGenException` als gemeinsame Oberfläche, dazu
  `InvalidArgument` und `EncodingFailed`
- Demo-Seite unter `demo/` mit Vorschau, Kennzahlen und Download.
  Erzeugt je Anfrage neu, speichert nichts, `Cache-Control: no-store`
- 77 Tests, 4636 Assertions. Darunter die Struktursicherungen aus der Norm
  (Suchmuster, Taktmuster, immer dunkles Modul, Seitenlänge), ein
  Rundlauf-Test, der das SVG zurück in die Matrix liest, und ein Test, der
  die Framework-Freiheit von `src/Qr` erzwingt

### Geändert

- `bacon/bacon-qr-code` von `^2.0` auf **`^2.0 || ^3.0`**. Der benutzte Teil
  der API ist in beiden Linien identisch, und Composer nimmt ab PHP 8.1 die
  3er. Das ist kein Kosmetikpunkt: 2.0.8 deklariert Parameter implizit
  nullable und erzeugt auf PHP 8.4 **zwei Deprecation-Meldungen pro
  Erzeugung**, 3.1.1 nicht
- `statamic/cms` aus `require` **entfernt**. Noch fasst keine Zeile Statamic
  an, und der Eintrag zog rund 140 unbenutzte Pakete nach. Er kommt mit dem
  ServiceProvider zurück
- DDEV-Docroot auf `demo/`, damit die Demo-Seite unter
  <https://qr-gen.ddev.site> erreichbar ist
- `phpunit.xml`: die leere `Statamic`-Testsuite entfernt, sie hätte den Lauf
  unter `failOnWarning` scheitern lassen
- `demo/` per `export-ignore` aus dem Composer-Dist genommen

### Nicht enthalten

Logo-Einbettung, PNG und die Statamic-Anbindung. Ein Logo im Code wird erst
nach einem Andruck in Originalgröße zugesagt, und dafür fehlen Druckgröße,
Material und ein RGB-Logo.

## [0.1.0] - 2026-09-10

### Hinzugefügt

- Projektgerüst: `composer.json`, README, Changelog, AGPL-3.0-Lizenz
- DDEV-Konfiguration auf PHP 8.4 zum Testen, passend zum DDEV der GVÖ-Seite
- PHPUnit-Konfiguration
- Festlegung: Fachlogik in `src/Qr/` ohne Laravel- und Statamic-Bezug,
  Statamic-Anbindung in `src/Statamic/`

[Unreleased]: https://github.com/redcodede/qr-gen/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/redcodede/qr-gen/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/redcodede/qr-gen/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/redcodede/qr-gen/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/redcodede/qr-gen/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/redcodede/qr-gen/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/redcodede/qr-gen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/redcodede/qr-gen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/redcodede/qr-gen/releases/tag/v0.1.0

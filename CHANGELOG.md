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
- Statamic-Hülle: Einstellungen im Control Panel, Fieldset und Tag für die
  Frontend-Komponente, Bild-Route, Auflösungs-Route, Flat-File-Repository.
  Das Gerüst steht seit `0.9.0`
- Bildmarke als **Raster** annehmen, falls sie nur als PNG geliefert wird.
  Braucht einen PNG-Dekoder im Paket; die Vektor-Bildmarke ist erledigt
- Elliptische Bögen (`A`) und Konturen im Rasterisierer, falls eine Zeichnung
  sie je braucht. Bisher hat keine
- Interlacing (Adam7) im PNG-Dekoder, falls je eine so gespeicherte Datei
  ankommt

## [0.9.0] - 2026-09-14

Das Gerüst der Statamic-Hülle. Noch nichts davon ist sichtbar; es ist das, woran
die Einstellungen und die Frontend-Komponente hängen werden.

### Hinzugefügt

- **`Qr\Statamic\ServiceProvider`** — hält nur Verdrahtung: Fieldset-Namensraum
  `qr-gen`, View-Namensraum `qr-gen`, Konfiguration, Übersetzungen. Keine
  Fachlogik, und das soll so bleiben
- **`config/qr-gen.php`** mit den Rückfallwerten: welche Codes erzeugt werden
  (ohne und mit Bildmarke), welche Formate zum Herunterladen angeboten werden,
  Default-Bildmarke und Default-URL. **Die Druckwerte stehen absichtlich nicht
  darin** — Druckgröße, Auflösung, Logokasten und Ruhezone liegen in
  `Qr\Preset` und sind entschieden, nicht eingestellt. Ein freigegebener Andruck
  gilt für genau diese Werte
- **Testharness auf `orchestra/testbench`.** Statamic 3.4 bringt keine Testhilfe
  für Erweiterungen mit, `src/Testing` kam erst mit Statamic 4. Der Harness
  reicht drei Dinge von Hand nach: das Addon-Manifest, Statamics eigene
  Konfiguration und ein temporäres Dateiwurzelverzeichnis, das nach jedem Test
  verschwindet
- Sechs Tests, die das Gerüst absichern: dass die Anwendung mit der Erweiterung
  bootet, dass Statamic sie im Manifest findet, dass das abgeleitete Verzeichnis
  auf das Paket zeigt, dass die Konfiguration unter `qr-gen` liegt, dass die
  Druckwerte **keine** Konfiguration sind, und dass der Kern innerhalb einer
  laufenden Laravel-Anwendung genau dasselbe rendert wie ohne

### Geändert

- **`statamic/cms ^3.4` ist jetzt eine Laufzeit-Abhängigkeit.** Bis hierher
  stand in der README, der Eintrag komme mit dem ServiceProvider; das ist jetzt
  so. Er zieht rund 140 Pakete nach, und ab jetzt ist das berechtigt
- **`laravel/framework ^8.83` in `require-dev`, nicht zum Benutzen, sondern zum
  Festnageln.** Statamic 3.4 erlaubt Laravel 8 oder 9, die GVÖ-Seite fährt 8.
  Ohne den Eintrag löst Composer hier 9 auf, und eine API, die es nur in 9 gibt,
  fiele erst auf dem Server auf. Die Auflösung zieht dadurch `league/flysystem`
  auf 1.1 und `league/glide` auf 1.7, also genau die Kombination der Zielseite
- **`autoload.psr-4` bekommt einen zweiten, spezifischeren Eintrag** für
  `Redcodede\QrGen\Statamic\`. Der sieht überflüssig aus und ist es nicht:
  Statamics `Manifest::formatPackage()` leitet das Verzeichnis der Erweiterung
  aus `autoload.psr-4[Namensraum des Providers]` ab, und ohne den Eintrag gibt
  es diesen Schlüssel nicht
- **`.ddev/php/error-reporting.ini`**: Laravel 8 ist auf PHP 8.4 nicht
  deprecation-frei, und ohne Gegenmaßnahme erzeugt allein das Autoloading 528
  Meldungen, bevor der erste Test läuft. Gesetzt werden `error_reporting`,
  `display_errors` und `log_errors` auf die Werte, die auf dem Zielserver
  ohnehin gelten. `composer test:deprecations` zeigt trotzdem, was das eigene
  Paket meldet

## [0.8.0] - 2026-09-14

Die Bildmarke darf jetzt auch ein PNG sein.

### Hinzugefügt

- **`Qr\Logo\PngLogo`** — das Gegenstück zu `SvgLogo`, mit demselben Vertrag,
  also nehmen beide Renderer es ohne Unterschied. Im SVG landet die Datei als
  `<image>` mit Data-URI, im PNG wird sie umgerechnet und einkomponiert
- **`Qr\Raster\PngDecoder`** — ein PNG-Leser nach der Spezifikation, ohne
  Bildextension: alle fünf Farbtypen, Bittiefen von 1 bis 16, alle fünf
  Zeilenfilter, `tRNS` in allen drei Formen. Sechzehn Bit werden auf acht
  gebracht, weil nichts dahinter mehr tragen kann. Ausgabe ist RGBA als
  **Binärstring**, vier Bytes je Pixel: dieselben Pixel als PHP-Array kosteten
  Dutzende Megabyte
- **`Qr\Raster\RasterScaler`** — verkleinert über den Flächenmittelwert,
  vergrößert bilinear. **Alpha wird vormultipliziert und danach wieder
  herausgerechnet**; ohne das mischt sich die Farbe unter durchsichtigen Pixeln
  in jede Kante und die Marke bekommt einen dunklen Saum
- **`Qr\Contract\RasterArtwork`** — daran erkennen die Renderer, dass eine
  Bildmarke schon Pixel ist und nicht gezeichnet werden muss
- **`PngRenderer::artworkPixels()`**, dazu `PngLogo::isSharpEnoughFor()` und
  `recommendedPixels()`. Bei den Werten des Projekts sind das **288 × 288 px**;
  darunter wird hochskaliert und weich. Die Demo-Seite zeigt Soll und Ist
  nebeneinander
- Demo-Seite: PNG-Dateien in `demo/logos` stehen zur Auswahl
- 51 Tests. Die Prüffälle für den Dekoder werden Byte für Byte im Test gebaut,
  damit neben den erwarteten Pixeln steht, welche Bytes sie erzeugt haben —
  und damit Kombinationen abgedeckt sind, die hier bisher keine Datei benutzt:
  Vier-Bit-Paletten, Sechzehn-Bit-Kanäle, Grau mit transparentem Wert

### Geändert

- **Metadaten werden aus eingebetteten PNGs entfernt.** `tEXt`, `iTXt`, EXIF
  und Farbprofile reisen sonst mit; der Name einer Grafikerin oder die
  Koordinaten einer Kamera haben in einem Symbol auf einer Verpackung nichts
  verloren. Die Datei wird mit den Bildchunks neu geschrieben, sonst nichts
- Der SVG-Renderer deklariert `xmlns:xlink`, **aber nur wenn eine Rastermarke
  im Spiel ist**. Vektorausgaben bleiben Byte für Byte, wie sie waren

### Nicht enthalten, mit Absicht

- **Interlacing wird abgelehnt.** Adam7 legt das Bild in sieben ineinander
  verschränkten Durchgängen ab, jeder mit eigener Geometrie. Es ist eine Option
  für den Bildaufbau über eine langsame Leitung und nützt Druckvorlagen nichts.
  Die Ablehnung nennt das Häkchen, das umzulegen ist
- **Die Referenz im `<image>` steht nur als `xlink:href`.** Beide Schreibweisen
  trügen die Datei zweimal, und bei fünfzig Kilobyte je Kopie ist das kein
  Rundungsfehler. Die alte Schreibweise ist die, die überall funktioniert —
  SVG 2 hat sie für veraltet erklärt und verlangt trotzdem von jedem Renderer,
  sie zu verstehen

## [0.7.0] - 2026-09-14

Die Bildmarke auch im PNG. Beide Codes gibt es jetzt in beiden Formaten.

### Hinzugefügt

- **`Qr\Raster`** — ein eigener Rasterisierer, weiterhin ohne Bildextension.
  Ein SVG reicht die Marke an den Betrachter weiter und lässt ihn zeichnen; ein
  PNG muss selbst zeichnen. Sechs Klassen: `Transform` (affine 2 × 3-Matrix und
  `transform`-Listen), `PathFlattener` (`d`-Attribut zu Streckenzügen),
  `ShapeFlattener` (rect, circle, ellipse, polygon, polyline über die
  Pfadgrammatik), `ScanlineFiller` (Nonzero und Even-Odd, 4 × 4
  überabgetastet), `LogoRaster` (Markup durchlaufen, Farbe vererben,
  komponieren) und `Palette`
- **`PngOptions::withLogo()`**, mit derselben `LogoBox` wie der SVG-Renderer.
  Daran hängt, dass die zwei Dateien dasselbe Bild zeigen und die Marke nicht
  im Vektor an einer und im Raster an einer anderen Stelle sitzt
- **`LogoRaster::rejectionFor()`** — beantwortet vorab, ob eine Zeichnung sich
  rastern lässt, damit eine Oberfläche keinen Download anbietet, der scheitert
- Demo-Seite: **PNG-Download auch für den Code mit Bildmarke**, und wo das
  nicht geht, der Grund an der Stelle des Knopfs
- 84 Tests. Der Füller wird als **Bild** geprüft — kleine Formen, Deckung
  Zeichen für Zeichen gegen eine erwartete Zeichnung, weil ein Windungsfehler,
  eine Halbpixelverschiebung und eine ausgelaufene Spanne so in derselben
  Zusicherung auffallen. Dazu ein Rundlauf, der die fertige Datei wieder in
  Chunks zerlegt, inflatet und **jedes Modul außerhalb des Logokastens** mit
  der Matrix vergleicht, aus der sie entstand

### Geändert

- **Das PNG mit Bildmarke ist 8 Bit indiziert** statt 1 Bit. Die Marke bringt
  eigene Farben und gebogene Kanten mit, die bei dieser Größe Kantenglättung
  brauchen; beides passt nicht in ein Bit. **Das PNG ohne Bildmarke bleibt
  unverändert bei 1 Bit** und zwei Palettenfarben. Palette bleibt es in beiden
  Fällen: flache Zeichnungen ergeben wenige Farben, die GVÖ-Marke landet bei 34
  von 256
- `pngRenderer()`, `rendererFor()` und `pngAvailable()` in der Demo nehmen jetzt
  die Bildmarke entgegen. `cheaperFormat()` verglich für die Logo-Variante
  fälschlich gegen das PNG **ohne** Marke

### Nicht enthalten, mit Absicht

- **Elliptische Bögen, Konturen und Gruppendeckkraft** werden **beim Namen
  genannt und abgelehnt**, nicht genähert. Ein Raster, das still vom Vektor
  derselben Marke abweicht, ist der Fehler, den vor der Auflage niemand
  bemerkt. Eine Ablehnung kostet das PNG dieser einen Zeichnung und sonst
  nichts: der SVG-Renderer nimmt dieselbe Datei anstandslos. Keine der
  vorliegenden Zeichnungen benutzt eines der drei — geprüft, nicht vermutet

## [0.6.0] - 2026-09-10

Ein druckfertiges PNG, ohne Bildextension.

### Hinzugefügt

- **`Qr\Render\PngRenderer`** — von Hand geschrieben, ohne `gd` und ohne
  `imagick`. Ein zweifarbiges PNG ist `IHDR`, `PLTE`, `pHYs`, `IDAT` und
  `IEND`, jedes mit CRC32; `zlib` ist in jedem Standard-PHP-Build und `crc32()`
  ist Sprachkern. **1 Bit je Pixel, zwei Palettenfarben** — genau das, was ein
  RIP für Strichzeichnungen will: keine Kantenglättung, die eine Modulkante
  aufweicht, und eine Datei von rund einem Kilobyte
- **`Qr\Render\PngOptions`** — angegeben werden **physische Größe und
  Auflösung**, die Pixelmaße fallen daraus. Jedes Modul bekommt eine ganze Zahl
  an Pixeln, und gerundet wird nach oben, damit die Datei nie kleiner ist als
  bestellt. Für die Briefing-URL: 37 Module × 32 px = **1184 px = 50,12 mm bei
  600 dpi**, 1.136 Bytes
- Der **`pHYs`-Chunk**, der den Unterschied zwischen einem großen Bild und
  einem druckfertigen macht. Ohne ihn platziert ein Layoutprogramm die Datei
  mit seiner eigenen Annahme, meist 72 dpi
- `Preset::pngOptions()`, `Preset::PRINT_DPI` (600), `Preset::PRINT_SIZE_MM`
  (50), `Preset::DARK_COLOR` und `LIGHT_COLOR`
- Demo-Seite: **PNG-Download** für den schlichten Code, und die Vorschau zeigt
  das kleinere der beiden Formate — bei einem QR-Code ist das das PNG, was der
  Intuition widerspricht, die ein Vektorformat weckt
- Ein Hinweis für die Druckerei: **100 % K, kein Rich Black.** Ein aus vier
  Farben gemischtes Schwarz braucht vier passgenaue Platten, und wo sie nicht
  passen, weicht eine Modulkante zu einem farbigen Saum auf. Weder PNG noch SVG
  können CMYK überhaupt tragen; die Umwandlung passiert im Umbruch
- 31 Tests, die die Bytes auseinandernehmen: Signatur, Chunk-Reihenfolge, jede
  CRC, `pHYs`-Wert, Palette, Bittiefe über `getimagesize()` — und ein
  Rundlauf, der den Raster zurück in die Matrix entpackt und mit der Eingabe
  vergleicht

### Geändert

- `demo/svg.php` heißt jetzt `demo/image.php` und nimmt `?format=png`. Eine
  Datei, die PNG ausliefert, sollte nicht `svg.php` heißen

### Nicht enthalten

- **Ein PNG mit Bildmarke.** Dafür müssten Vektorpfade gerastert werden —
  Bézierkurven, Bögen, Füllregeln — und das ist ein 2D-Rasterisierer, nicht
  hundert Zeilen Chunk-Schreiben. Es ist auch die falsche Frage: für den Druck
  ist das SVG das Lieferformat, und wer das Layout macht, exportiert daraus ein
  Raster in jeder Größe. Eine Anfrage nach `format=png` mit Bildmarke kommt
  deshalb als SVG zurück, nicht als Symbol mit einem Loch darin. Der Weg dorthin
  wäre eine Raster-Bildmarke plus ein PNG-Dekoder im Paket, und der kostet die
  1-Bit-Schärfe

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

[Unreleased]: https://github.com/redcodede/qr-gen/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/redcodede/qr-gen/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/redcodede/qr-gen/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/redcodede/qr-gen/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/redcodede/qr-gen/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/redcodede/qr-gen/compare/v0.3.1...v0.4.0
[0.3.1]: https://github.com/redcodede/qr-gen/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/redcodede/qr-gen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/redcodede/qr-gen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/redcodede/qr-gen/releases/tag/v0.1.0

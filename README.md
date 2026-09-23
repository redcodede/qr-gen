# qr-gen

> Erzeugt aus einer URL einen QR-Code als SVG und als druckfertiges PNG. Zwei
> Laufzeit-Abhängigkeiten, keine Bildextension, kein Framework im Kern.

**Status: `1.0.0`, die erste Freigabe.** URL rein, zwei Codes raus — einer
ohne, einer mit Bildmarke in der Mitte, **beide als SVG und als druckfertiges
PNG**. Dazu die Statamic-Anbindung: ein Tag für die Seite, eine signierte
Bild-Route, eine Einstellungsseite im Control Panel und ein Fieldset für
Blueprints. Was hier unter „geplant" steht, existiert nicht.

Geprüft am 23.09.2026 auf PHP 8.4: **497 Tests, 31697 Assertions, grün.**

**`1.0` heißt: der Funktionsumfang der Erstfreigabe steht und die öffentliche
API ist ab hier stabil.** Es heißt nicht, dass ein Andruck abgenommen wäre —
siehe [Was dieses Paket nicht entscheidet](#was-dieses-paket-nicht-entscheidet).
Wer das Paket einsetzt, bekommt dieselben Dateien, die auch in den Andruck
gehen; ob sie auf dem echten Material gelesen werden, entscheidet der Andruck
und nicht dieses Repository.

---

## Warum es das gibt

Ab dem **12.02.2027** greifen Kennzeichnungspflichten aus **VerpackDG** und
**PPWR** (Verordnung (EU) 2025/40). Wer Verpackungen in Verkehr bringt, braucht
einen gedruckten QR-Code, der auf eine Rücknahmeseite zeigt.

Daraus kommt der Zuschnitt dieses Pakets: **der Code geht in den Druck.** Er
wird einmal gedruckt und danach jahrelang gescannt, auf kleinen Etiketten, auf
gewölbten Gebinden, im Zweifel bei schlechtem Licht. Ein Generator, der nur am
Bildschirm gut aussieht, reicht dafür nicht.

**Ein gedruckter QR-Code muss dauerhaft nur eines leisten: seine URL muss
gültig bleiben.** Was hinter der URL steht, darf sich ändern. Daraus folgt der
Zuschnitt in zwei Stufen.

## Was drin ist

### Fertig

- [x] URL → QR-Code-Matrix, Fehlerkorrekturstufe wählbar
- [x] Matrix → SVG, ein einziger `<path>`, verlustfrei skalierbar
- [x] **Das Etikett**: Code, Bildmarke daneben und zwei Zeilen Text in einem
      Bild, als SVG und als PNG. Siehe [Das Etikett](#das-etikett)
- [x] **Schrift als Umrisse**, aus einer mitgelieferten SVG-Schriftdatei. Kein
      `<text>`, keine Schriftinstallation beim Empfänger, kein TrueType-Parser
- [x] **Logo in der Mitte**, mit Ruhezone darum, jedes Seitenverhältnis
- [x] **Sanitizer für fremde SVGs**, Whitelist statt Filter
- [x] **Bildmarke auch als PNG**, mit eigenem PNG-Dekoder: alle fünf Farbtypen,
      alle Bittiefen, alle fünf Zeilenfilter, Transparenz. Metadaten werden
      abgeschnitten
- [x] **Funktionsmuster-Prüfung:** ein Logokasten über Such-, Takt- oder
      Formatmustern wird abgewiesen, nicht gerendert. Ausrichtungsmuster
      werden davon unterschieden, weil sie ein Kompromiss und kein Fehler sind
- [x] **`LogoFit`: die niedrigste Stufe finden, bei der ein Kasten überlebt** —
      statt jemanden rätseln zu lassen, warum ein Kasten bei H passt und bei M
      nicht
- [x] **Druckfertiges PNG ohne `gd` und ohne `imagick`**, mit `pHYs`-Auflösung
      und aus der physischen Größe berechnet. 1 Bit und zwei Farben ohne
      Bildmarke, 8 Bit indiziert mit
- [x] **Eigener Rasterisierer für die Bildmarke im PNG** — Pfade flachlegen,
      Scanline-Füllung mit Nonzero und Even-Odd, 4 × 4 überabgetastet. Was er
      nicht zeichnet — Bögen, Konturen, Gruppendeckkraft — **lehnt er beim Namen
      ab, statt es zu nähern**
- [x] Textsammlung DE/EN, Deutsch als Standard
- [x] Demo-Seite mit beiden Varianten, Kennzahlen und Download
- [x] **Gerüst der Statamic-Hülle**: ServiceProvider mit Fieldset- und
      View-Namensraum, `config/qr-gen.php`, Testharness auf `orchestra/testbench`
- [x] **Konfigurationsmodell mit einer Ebene**: alles steht global im Control
      Panel und gilt überall gleich. Je Stelle kommt genau eine Angabe dazu, die
      Ziel-URL. Bis `1.0.1` gab es daneben eine zweite Ebene je Seite, die
      globale Werte überschreiben durfte; sie ist zurückgebaut
- [x] **Frontend-Komponente**: der Tag `{{ qr_gen }}` gibt beide Varianten mit
      Vorschau und Download aus, die Bild-Route liefert die einzelne Datei
      signiert aus. Siehe [In Statamic](#in-statamic)
- [x] **Ausnahmen werden in der Hülle gefangen und protokolliert**, mit
      Kontext statt Prosa und ohne die verarbeitete Adresse. Siehe
      [Logging](#logging)
- [x] **Einstellungsseite im Control Panel**, unter „Werkzeuge", mit eigener
      Berechtigung. Kein eigenes JavaScript: sie rendert Statamics eigene
      Publish-Form. Siehe [Im Control Panel](#im-control-panel)
- [x] **Fieldset für den Blueprint einer Seite**, `import: qr-gen::qr_code`
- [x] **Die Hülle benutzt den Textkatalog**, DE und EN, über Laravels
      Übersetzer
- [x] Test, der die Framework-Freiheit des Kerns erzwingt
- [x] Rundlauf-Test, der das SVG zurück in eine Matrix liest

### Offen

- [ ] Code- und Token-Erzeugung, `CodeRepository`-Interface
- [ ] **Ein eigener Fieldtype für die Varianten.** Heute lässt sich eine Seite
      auf „mit Bildmarke" stellen, während der Typ global abgeschaltet ist; es
      kommt dann nichts, ohne dass im Formular stünde warum
- [ ] Elliptische Bögen (`A`) und Konturen im Rasterisierer. Bisher nicht
      gebraucht: keine der vorliegenden Zeichnungen benutzt beides
- [ ] Interlacing (Adam7) im PNG-Dekoder, falls je eine so gespeicherte Datei
      ankommt. Bis dahin nennt die Ablehnung das Häkchen, das umzulegen ist

### Stufe 2 — später, im Website-Relaunch

Control-Panel-Actions und Fieldtype, herstellerspezifische Inhalte, Tracking.
Auf dem dann aktuellen Statamic-Stand, nicht auf Statamic 3.

## Die festgelegten Werte

`Qr\Preset` hält die entschiedene Konfiguration an einer Stelle. Das sind
Entscheidungen, keine Stellschrauben:

| | |
|---|---|
| Logokasten | **11** Module |
| Rand im Kasten | **1** Modul |
| Modulgröße | **13** px |
| Ruhezone | **2** Module |
| Druckauflösung | **600** dpi |
| Zielgröße im Druck | **50** mm |
| Farben | `#000000` auf `#ffffff` |
| Fehlerkorrekturstufe | **wird ausgerechnet**, siehe `LogoFit` |

```php
use Redcodede\QrGen\Qr\Preset;

$svgOptions = Preset::svgOptions();   // Modulgröße, Ruhezone, Farben
$pngOptions = Preset::pngOptions();   // Auflösung, Druckgröße, Farben
$box = Preset::logoBox();             // Kasten und Rand
```

Die Stufe fehlt absichtlich. Sie folgt aus Nutzlast und Logokasten, und sie
vorab festzunageln hieße, das falsche Ende festzunageln.

Was daraus für die beiden echten Nutzlasten folgt:

| Nutzlast | Stufe | Symbol | freigeräumt | Reserve |
|---|---|---|---|---|
| `example.org/qr/7K4M2` (28 B) | H | Version 4, 33 × 33 | 11,1 % | **26 %** |
| `www.redcode.de/` (23 B) | H | Version 3, 29 × 29 | 14,4 % | **4 %** |

Der zweite Fall ist knapp — die kürzere URL ergibt ein kleineres Symbol, in dem
derselbe Kasten mehr Anteil hat. Für die Produktionsurl ist es entspannt.

### Ruhezone 2: eine bewusste Abweichung

ISO/IEC 18004 verlangt **4** Module. Zwei tragen nur unter einer Bedingung:
**das Layout drumherum muss die fehlenden zwei Module an Weißraum
beisteuern.** Ein Symbol, das mit zwei eigenen Modulen direkt an Grafik grenzt,
hat halb so viel hellen Rand, wie ein Scanner erwartet, und wird genau dort
unzuverlässig, wo es zählt — auf einem kleinen Etikett, schräg gehalten, bei
schlechtem Licht.

Wer das Symbol platziert, verantwortet die Bedingung. Das Paket kann sie nicht
prüfen, und entschieden wird sie vom Andruck, nicht von dieser Zeile.

**Der Standardwert der Bibliothek bleibt bei 4.** `SvgOptions::default()` hält
sich an die Norm; die Abweichung gehört dem Projekt, nicht dem Paket. Ein
Paket, das eine normwidrige Ruhezone als eigenen Standard ausliefert, würde
jeden anlügen, der es installiert. Ein Test hält die beiden auseinander.

## Beide Formate, und welches wofür

```php
use Redcodede\QrGen\Qr\Render\PngRenderer;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

$svg = (new SvgRenderer(Preset::svgOptions()))->render($matrix);
$png = (new PngRenderer(Preset::pngOptions()))->render($matrix);

// Mit Bildmarke, in beiden Formaten. Derselbe LogoBox in beiden Aufrufen —
// daran hängt, dass die zwei Dateien dasselbe Bild zeigen.
$logo = SvgLogo::fromMarkup(file_get_contents('logo.svg'));
$box = Preset::logoBox();

$svgMitMarke = (new SvgRenderer(Preset::svgOptions()->withLogo($logo, $box)))->render($matrix);
$pngMitMarke = (new PngRenderer(Preset::pngOptions()->withLogo($logo, $box)))->render($matrix);
```

Ob eine Bildmarke als PNG geht, lässt sich **vorher** fragen, statt einen
Download anzubieten, der scheitert:

```php
use Redcodede\QrGen\Qr\Raster\LogoRaster;

$grund = LogoRaster::rejectionFor($logo);   // null heißt: geht

if ($grund !== null) {
    // Der Text nennt das Konstrukt und was dagegen zu tun ist.
}
```

| | SVG | PNG |
|---|---|---|
| Skalierbar | beliebig | nein |
| Bildmarke | **ja** | **ja**, siehe unten |
| Für die Druckerei | **das richtige Format** | Beilage |
| Briefing-URL, ohne Marke | 3.952 B | **1.184 × 1.184 px, 1 Bit, 1.136 B** |
| Briefing-URL, mit Marke | 5.129 B | 1.184 × 1.184 px, 8 Bit, 10.018 B |

### Das PNG ist aus der Druckgröße gerechnet, nicht aus einer Pixelzahl

Die Pixelmaße sind keine Einstellung. Angegeben werden **physische Größe und
Auflösung**, und daraus fällt die Pixelzahl:

| | |
|---|---|
| Auflösung | **600 dpi** |
| Zielgröße | **50 mm** |
| Briefing-URL | 37 Module × **32 px** = 1184 px = **50,12 mm** |
| Demo-URL | 33 Module × **36 px** = 1188 px = 50,29 mm |

Zwei Dinge daran sind Absicht. **Jedes Modul bekommt eine ganze Zahl an
Pixeln** — eine Modulgrenze, die zwischen zwei Pixel fällt, ist eine Kante, die
der Raster verschmieren muss, und eine verschmierte Kante liest ein Scanner
falsch. Und **gerundet wird nach oben**, also ist die Datei nie kleiner als
bestellt: auf 50 mm gedruckt wird um Bruchteile eines Prozents verkleinert,
nie hochskaliert.

600 dpi statt 300, weil 300 die Zahl für Fotografien ist, wo das Auge in einem
Halbton nicht mehr auflöst. Ein QR-Code ist harte Kante, und eine Kante
profitiert von jedem Punkt, den die Maschine setzen kann.

**`pHYs` ist der Unterschied zwischen einem großen Bild und einem
druckfertigen.** Ohne diesen Chunk platziert ein Layoutprogramm die Datei mit
seiner eigenen Annahme — meist 72 dpi — und der Code landet achtmal zu groß,
woraufhin ihn jemand nach Augenmaß verkleinert.

**Ohne Bildmarke: 1 Bit, zwei Palettenfarben.** Genau das ist ein QR-Code und
genau das will ein RIP für Strichzeichnungen: keine Kantenglättung, die eine
Modulkante aufweicht, kein Graustufenwert, den eine Maschine rastern muss, und
eine Datei von einem Kilobyte statt von einem Megabyte.

**Mit Bildmarke: 8 Bit, weiterhin Palette.** Die Marke bringt eigene Farben und
gebogene Kanten mit, die bei dieser Größe Kantenglättung brauchen; beides passt
nicht in ein Bit. Palette bleibt es trotzdem, weil flache Zeichnungen wenige
Farben ergeben — die Marke im Test landet bei 34 von 256 möglichen. Ein Byte je
Pixel ist ein Drittel von RGB, und die Module kosten weiter zwei
Paletteneinträge ohne jede Glättung in ihrer Nähe.

### Farbmodus: was mitzugeben ist

Schwarz ist `#000000`, Weiß `#ffffff`. **Weder PNG noch SVG können CMYK
überhaupt tragen** — PNG hat den Farbraum nicht, SVG 1.1 auch nicht. Die
Umwandlung passiert im Umbruch, und die Anweisung dazu lautet: **100 % K, kein
Rich Black.** Ein aus vier Farben gemischtes Schwarz braucht vier passgenaue
Platten, und wo sie nicht passen, weicht eine Modulkante zu einem farbigen Saum
auf — genau die Kante, die ein Scanner vermisst.

### Wie die Bildmarke ins PNG kommt

Ein SVG reicht die Marke an den Betrachter weiter und lässt ihn zeichnen. Ein
PNG muss selbst zeichnen, und dafür liegt in `Qr\Raster` ein eigener
Rasterisierer — vier Klassen, keine Bildextension:

| | |
|---|---|
| `Transform` | affine 2 × 3-Matrix, `transform`-Listen, Verschachtelung |
| `PathFlattener` | `d` → Streckenzüge in **Gerätepixeln**, Kurven adaptiv unterteilt |
| `ShapeFlattener` | rect, circle, ellipse, polygon, polyline → Pfadgrammatik |
| `ScanlineFiller` | Scanline-Füllung, Nonzero und Even-Odd, 4 × 4 überabgetastet |
| `LogoRaster` | läuft durch das Markup, vererbt Farbe, komponiert in Dokumentreihenfolge |
| `Palette` | Farben → Indizes, mit Reduktion als Auffanglinie |

Drei Entscheidungen darin sind erklärungsbedürftig.

**Die Toleranz wird in Pixeln gemessen, nicht in Kurvenparametern.** Deshalb
wird die Transformationsmatrix schon beim Flachlegen angewandt und nicht danach:
eine Kurve wird so lange geteilt, bis die Sehne **auf der Seite** nicht mehr von
ihr abweicht. Eine Marke in neun Modulen und dieselbe Marke auf einem Plakat
bekommen dann jede die Anzahl Segmente, die sie braucht.

**Überabgetastet statt analytisch.** Sechzehn Proben je Pixel ergeben siebzehn
Deckungsstufen. Das Auge zählt auf einer gebogenen Kante bei etwa acht auf. Was
es bringt: ein dünnes Detail — der Querstrich eines Buchstabens, die Lücke in
einem Ring — wird grau, statt herauszufallen oder auf volle Deckung zu springen.
Herausfallen ist das, was eine kleine Marke kaputt aussehen lässt.

Für eine Bildmarke, die schon Pixel ist, entfällt das alles: `PngDecoder`
liest sie, `RasterScaler` bringt sie auf die Zielgröße, fertig. Beim Skalieren
wird **Alpha vormultipliziert und danach wieder herausgerechnet** — ohne das
mischt sich die Farbe unter vollständig durchsichtigen Pixeln in jede Kante,
und die Marke bekommt einen dunklen Saum, den niemand gezeichnet hat. Verkleinert
wird über die Fläche gemittelt, vergrößert bilinear interpoliert.

**Was er nicht kann, lehnt er ab.** Elliptische Bögen, Konturen und
Gruppendeckkraft werden **beim Namen genannt und verweigert**, nicht genähert.
Ein Raster, das still von dem Vektor derselben Marke abweicht, ist der Fehler,
den vor der Auflage niemand bemerkt. Eine Ablehnung kostet das PNG dieser einen
Marke und sonst nichts: der SVG-Renderer nimmt dieselbe Datei anstandslos, und
die Demo bietet dann eben nur den Vektor an.

Keine der vorliegenden Zeichnungen benutzt eines der drei — geprüft, nicht
vermutet. `LogoRaster::rejectionFor()` beantwortet die Frage vorab.

**Für den Druck bleibt das SVG das Lieferformat.** Eine Druckerei nimmt Vektor.
Das PNG ist die Beilage für Bildschirm, Office und E-Mail, wo ein SVG Ärger
macht — jetzt eben mit Bildmarke statt ohne.

#### Wie geprüft wurde, dass da das Richtige steht

Ein QR-Code, der falsch ist, sieht nicht falsch aus, und für eine gerasterte
Bildmarke gilt dasselbe. Drei Schichten:

- **Der Füller wird als Bild geprüft.** `ScanlineFillerTest` zeichnet kleine
  Formen und vergleicht die Deckung Zeichen für Zeichen mit einer erwarteten
  Zeichnung. Ein Windungsfehler, eine Halbpixelverschiebung und eine
  ausgelaufene Spanne fallen damit in derselben Zusicherung auf
- **Die Module werden zurückgelesen.** `PngRendererLogoTest` zerlegt die
  fertige Datei wieder in Chunks, inflatet sie und vergleicht **jedes Modul
  außerhalb des Logokastens** mit der Matrix, aus der sie entstand
- **Eine unabhängige Instanz.** Dasselbe Symbol einmal durch diesen
  Rasterisierer und einmal durch `imagick` — nicht im Test, weil das Paket
  imagick nicht verlangt, aber bei der Entwicklung gemessen. Ergebnis im
  Logokasten: 3,2 % der Pixel weichen überhaupt ab, praktisch alle davon am
  Rand einer Fläche, und **16 von 173.056 abseits jeder Kante**. Das ist
  Kantenglättung, keine Geometrie

## Texte und Sprachen

Alle Oberflächentexte liegen in `resources/lang/{sprache}/texts.php`.
**Deutsch ist die Standardsprache**, Englisch steht daneben und wird von der
Statamic-Hülle mitbenutzt: sie fragt Laravels Übersetzer, der die Sprache der
Seite kennt.

```php
use Redcodede\QrGen\I18n\Translator;

$texts = Translator::forLocale();               // de
$texts = Translator::forLocale('en');           // auf Abruf
$texts = Translator::forLocaleOrDefault($any);  // fällt zurück statt zu werfen

$texts->get('facts.payload.value', ['bytes' => 28]);   // "28 Bytes"
```

In der Statamic-Hülle dieselben Dateien, über Laravels Übersetzer:

```php
__('qr-gen::texts.panel.plain');            // in PHP und Blade
```

```antlers
{{ trans:qr-gen::texts.panel.plain }}       {{# in Antlers #}}
```

**Der Zuschnitt zählt so viel wie das Format.** Laravels `FileLoader` sucht
unter `{pfad}/{sprache}/{gruppe}.php`. Ein flaches `resources/lang/de.php` hat
denselben Inhalt und ist für `loadTranslationsFrom()` unsichtbar — der Aufruf
kommt dann als `qr-gen::texts.panel.plain` zurück, also als der Schlüssel
selbst, und zwar ohne Fehler. Deshalb der Unterordner.

Drei Eigenschaften, die von Tests gehalten werden:

- **Beide Kataloge haben genau dieselben Schlüssel.** Eine Übersetzung, die
  still auseinanderläuft, ist schlimmer als eine fehlende — die fehlende sieht
  man
- **Beide benutzen je Schlüssel dieselben Platzhalter**, sonst rendert eine
  Sprache ein übriggebliebenes `:headroom`, wo die andere eine Zahl zeigt
- **Ein fehlender Schlüssel kommt als er selbst zurück.** Lauter als ein leerer
  String und leiser als eine Ausnahme: die Seite rendert weiter, und ein
  `facts.allowance` in einer Tabelle ist unmissverständlich

Was **nicht** in den Katalogen steht, sind die Meldungen der Ausnahmen aus
`src/Qr/`. Die sind Entwicklerdiagnostik — sie landen in Logs, nennen
Klassennamen und Modulzahlen und richten sich an jemanden, der den Code liest.
Wer sie einem Endnutzer zeigt, bildet sie auf einen eigenen Text ab, statt sie
zu übersetzen. Deshalb liegt `src/I18n/` auch außerhalb von `src/Qr/`: der Kern
braucht keine Übersetzungen und darf keine Dateien lesen.

## Abhängigkeiten und was sie tun

Ein QR-Code entsteht in sieben Schritten. **Sechs davon kommen aus einem
Fremdpaket, der siebte ist unser.**

| Schritt | | Woher |
|---|---|---|
| 1 Modus wählen (numerisch, alphanumerisch, Byte) | Fremdcode | `BaconQrCode\Encoder\Encoder` |
| 2 Version und Fehlerkorrekturstufe | Fremdcode | `BaconQrCode\Common\Version`, `EcBlocks` |
| 3 Bitstrom bauen | Fremdcode | `BaconQrCode\Common\BitArray` |
| 4 Reed-Solomon rechnen | Fremdcode | `BaconQrCode\Common\ReedSolomonCodec` |
| 5 Module setzen | Fremdcode | `BaconQrCode\Encoder\MatrixUtil` |
| 6 Maskieren | Fremdcode | `BaconQrCode\Encoder\MaskUtil` |
| **7 Zeichnen** | **selbst** | `Redcodede\QrGen\Qr\Render\SvgRenderer` |

### Laufzeit

| Paket | Version | Job | Größe |
|---|---|---|---|
| `bacon/bacon-qr-code` | `^2.0 \|\| ^3.0` | Schritte 1–6: Zeichenkette → Modul-Matrix. **Nur `Encoder::encode()`**, keiner ihrer Renderer | 60 Dateien, ~7900 Zeilen |
| `dasprid/enum` | `^1.0.3` | Enum-Polyfill, den Bacon für `ErrorCorrectionLevel` benutzt. Kommt als transitive Abhängigkeit mit | 10 Dateien, ~790 Zeilen |
| `ext-iconv` | — | Zeichensatzumwandlung im Encoder. In jeder Standard-PHP vorhanden | — |
| `statamic/cms` | `^3.4` | Die Hülle: ServiceProvider, Control Panel, Blueprints, Antlers. **Der Kern fasst davon nichts an**, ein Test erzwingt das | rund 140 Pakete |

**Das ist alles.** Kein `ext-gd`, kein `ext-imagick`, kein `ext-dom`, kein
`ext-simplexml`, kein `ext-mbstring`, kein Laravel, kein Statamic.

### Mitgeliefert, aber kein Paket

| Datei | Job |
|---|---|
| `resources/fonts/pt-sans-v18-latin/…-regular.svg` | **Die Schrift, aus der das Etikett gesetzt wird.** Eine SVG-Schriftdatei trägt jede Glyphe als Pfad und ihre Vorschubweite als Zahl. Das ist genau das, was ein TrueType-Parser ausrechnen müsste, nur schon hingeschrieben, und die Pfade versteht `PathFlattener` bereits. Deshalb liest `Qr\Text\SvgFont` diese Fassung und nicht die `ttf` |
| `…-regular.ttf` | Die Quelle, aus der die SVG-Fassung erzeugt wurde. Vom Code nicht angefasst, liegt bei, damit sie sich neu erzeugen lässt |
| `OFL.txt` | Der Lizenztext, siehe [Lizenz](#lizenz) |

Die Webformate (`eot`, `woff`, `woff2`) liegen im Repository, sind aber per
`export-ignore` aus dem Composer-Dist genommen: im Paket haben sie keine
Aufgabe.

**Die Schrift ist austauschbar.** `SvgFont::fromMarkup()` nimmt jede
SVG-Schriftdatei. Zwei Bedingungen: die gebrauchten Zeichen müssen im Subset
liegen (die mitgelieferte deckt Latein ab, 202 Glyphen, inklusive Umlauten),
und die Umrisse dürfen keine elliptischen Bögen benutzen, die der Rasterisierer
ablehnt. Schriften benutzen praktisch nie welche.

Zu `bacon/bacon-qr-code` drei Dinge, die man wissen sollte:

- **Beide Linien werden unterstützt**, weil der benutzte Teil ihrer API
  identisch ist. Composer nimmt **3.x ab PHP 8.1** und 2.x darunter. Das ist
  kein Detail: **2.x deklariert Parameter implizit nullable und erzeugt auf
  PHP 8.4 zwei Deprecation-Meldungen pro Erzeugung.** 3.x nicht
- **Ihre Renderer bleiben unangetastet.** Nur zwei von ihnen brauchen
  überhaupt Extensions (`ImagickImageBackEnd`, `SvgImageBackEnd`) — und weil
  wir keinen davon benutzen, bleibt es bei `ext-iconv`
- **Sie mischt keine Modi.** Eine Zeichenkette geht in einem einzigen Modus
  raus. Bei einer URL mit kleingeschriebenem Host heißt das Byte-Modus, auch
  wenn ein großgeschriebener Code darin alphanumerisch passen würde. Bei
  fünf Zeichen ist der Unterschied ein Bruchteil einer Version

Austauschbar: der Encoder liegt hinter `Qr\Contract\QrEncoder`. Ein eigener
Encoder wäre eine Klasse und ein Minor-Release; Renderer, Statamic-Hülle und
die Tests in `tests/Qr/Encoder/` merken den Wechsel nicht.

### Entwicklung

| Paket | Version | Job |
|---|---|---|
| `phpunit/phpunit` | `^9.6` | Testlauf |
| `orchestra/testbench` | `^6.18` | Fährt eine Laravel-Anwendung für die Tests der Hülle hoch. Statamic 3.4 bringt keine eigene Testhilfe mit, `src/Testing` gibt es dort noch nicht |
| `laravel/framework` | `^8.83` | **Nicht zum Benutzen, zum Festnageln.** Statamic 3.4 erlaubt Laravel 8 oder 9; die erste Zielinstallation fährt 8. Ohne diesen Eintrag löst Composer hier 9 auf, und eine API, die es nur in 9 gibt, fiele erst auf dem Server auf |

Die Auflösung auf Laravel 8 zieht `league/flysystem` auf 1.1 und `league/glide`
auf 1.7 herunter. Das ist kein Zufall und kein Problem, sondern genau die
Kombination, die auf der Zielseite läuft.

### Deprecations sind im Container aus

Laravel 8 ist auf PHP 8.4 nicht deprecation-frei. Ohne Gegenmaßnahme erzeugt
allein das Autoloading 528 Meldungen, bevor der erste Test läuft, und unter
`beStrictAboutOutputDuringTests` wird dadurch jeder Test „risky".

`.ddev/php/error-reporting.ini` setzt deshalb `error_reporting`,
`display_errors` und `log_errors` auf die Werte, **die auf dem Zielserver ohnehin
gelten** (geprüft am 14.09.2026 im phpinfo). Es wird hier also nichts
stillgelegt, was dort meldet.

Zwei Stellschrauben sind nötig, weil eine nicht reicht: Laravel setzt beim
Booten selbst `error_reporting(-1)`, und sein Fehlerbehandler tut unter Tests
mit Deprecations bewusst nichts und gibt `null` zurück, woraufhin PHP sie
selbst druckt.

Was das eigene Paket meldet, zeigt `composer test:deprecations`.

### Warum keine Bildextension

Ein SVG ist Zeichenkettenbau. In der PHP-CLI der WSL fehlen `gd`, `imagick`,
`dom`, `simplexml` und `mbstring` — für den Renderer ist das gleichgültig.

Zur Ehrlichkeit gehört: **auf der ersten Zielinstallation ist `gd` vorhanden**
(2.3.3, geprüft am 14.09.2026), `imagick` nicht. Dort wäre die Freiheit von
Bildextensionen also nicht nötig gewesen. Sie bleibt trotzdem richtig, aber als
Versicherung für einen Serverumzug und für die spätere Statamic-6-Fassung, nicht
als Voraussetzung für heute.

**Und PNG braucht auch keines.** Ein zweifarbiges PNG ist `IHDR`, `PLTE`,
`pHYs`, `IDAT` und `IEND`, jedes mit CRC32: `zlib` ist in jedem
Standard-PHP-Build und `crc32()` ist Sprachkern. Das kostet rund hundert Zeilen
und spart eine Abhängigkeit samt Installationsschritt auf dem Server. Was eine
Bildbibliothek hier zusätzlich brächte, wäre ein 8- oder 24-Bit-Puffer, wo ein
Bit je Pixel genau richtig ist.

## Einrichten

```bash
git clone git@github.com:redcodede/qr-gen.git
cd qr-gen
ddev start
ddev composer install
```

Danach:

| | |
|---|---|
| Demo-Seite | <https://qr-gen.ddev.site> |
| Tests | `ddev exec vendor/bin/phpunit` |
| Tests, lesbar | `ddev exec vendor/bin/phpunit --testdox` |
| Composer | `ddev composer …` |

Die DDEV-Konfiguration liegt im Repo: reiner PHP-Container ohne
Datenbank, **PHP 8.4**, Docroot `demo/`. Passend zur ersten
Zielinstallation, die ebenfalls auf 8.4 steht.

**Warum nicht die PHP-CLI der WSL:** dort fehlen `dom`, `mbstring` und `xml`,
die PHPUnit selbst braucht. Für die Demo-Seite allein genügt sie
(`php -S localhost:8080 -t demo`), weil der Renderer keine Extension braucht
und Bacon nur `iconv`.

## Benutzen

Zwei Objekte, drei Zeilen. Der Encoder liefert die Matrix, der Renderer die
Datei.

```php
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

$matrix = (new BaconQrEncoder())->encode(
    'https://www.redcode.de/',
    ErrorCorrection::medium()
);

$svg = (new SvgRenderer())->render($matrix);
```

### Fehlerkorrektur

`ErrorCorrection::low()`, `medium()`, `quartile()`, `high()` — oder
`ErrorCorrection::fromString('h')` für einen Wert aus einer Config oder einem
Formular, groß oder klein geschrieben.

| Stufe | Wiederherstellung | wofür |
|---|---|---|
| `low()` | ~7 % | kleinster Code, kaum Reserve |
| `medium()` | ~15 % | Standard ohne Logo |
| `quartile()` | ~25 % | |
| `high()` | ~30 % | **nötig, sobald ein Logo Module verdeckt** |

Eine höhere Stufe macht den Code nie kleiner, meist größer.

### Darstellung

`SvgOptions` ist unveränderlich, jeder `with…`-Aufruf gibt eine neue Instanz
zurück.

```php
$options = SvgOptions::default()
    ->withModuleSize(8)          // px je Modul, nur width/height
    ->withQuietZone(4)           // Module Rand, 4 verlangt die Norm
    ->withColors('#000', 'none') // 'none' = transparent
    ->withTitle('Rücknahme')     // barrierefreier Name, wird escaped
    ->withXmlDeclaration();      // für eine .svg-Datei; für Inline weglassen

$svg = (new SvgRenderer($options))->render($matrix);
```

Zwei Entscheidungen im Renderer, die Absicht sind:

- **Ein einziger `<path>`, kein `<rect>` je Modul.** Aneinanderliegende
  Rechtecke zeigen in manchen Druck-RIPs und in Illustrator Haarlinien an den
  Kanten, weil jedes einzeln gerastert wird. Eine Füllfläche kann das nicht.
  Nebenbei wird die Datei ein Vielfaches kleiner
- **`viewBox` in Modul-Einheiten, `width`/`height` in Pixel.** Dieselbe Datei
  skaliert vom Etikett bis zum Plakat, ohne neu erzeugt zu werden

Farben werden geprüft, nicht escaped: erlaubt sind Hex-Notation mit 3, 4, 6
oder 8 Stellen und das Schlüsselwort `none`. Eine beliebige Zeichenkette
könnte das Attribut schließen und eigenes Markup schreiben.

### Logo in der Mitte

```php
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\SvgLogo;

$logo = SvgLogo::fromMarkup(file_get_contents('/pfad/zum/logo.svg'));

$options = SvgOptions::default()
    ->withLogo($logo, LogoBox::square(11, 1));   // 11 Module Kasten, 1 Modul Rand

$svg = (new SvgRenderer($options))->render($matrix);
```

**Der Kasten ist Logo plus Rand**, nicht das Logo allein. Bei `square(11, 1)`
werden 11 × 11 Module freigeräumt und das Logo in die inneren 9 × 9 gesetzt.
Der Rand trennt nicht nur optisch — er verhindert auch, dass eine Logokante als
Modulkante gelesen wird.

Das Logo darf jedes Seitenverhältnis haben. Der Kasten muss es nicht
nachbilden, aber es hilft:

```php
LogoBox::of(15, 9, 1);                                   // breit, von Hand
LogoBox::forAspectRatio($logo->width() / $logo->height(), 11);   // aus dem Logo
```

#### Wenn die Bildmarke ein PNG ist

Kommt sie als Raster, ist der Aufruf derselbe — nur die Klasse wechselt:

```php
use Redcodede\QrGen\Qr\Logo\PngLogo;

$logo = PngLogo::fromBinary(file_get_contents('/pfad/zum/logo.png'));

$options = PngOptions::default()->withLogo($logo, LogoBox::square(11, 1));
```

`PngLogo` erfüllt `Logo` wie `SvgLogo`, also nehmen beide Renderer sie ohne
Unterschied. Im SVG landet die Datei als `<image>` mit Data-URI, im PNG wird
sie auf die Zielgröße umgerechnet und einkomponiert.

**Ein Raster ist schlechter als ein Vektor, und daran kann das Paket nichts
ändern.** Was es kann, ist es nicht schlimmer zu machen, als die Datei erlaubt,
und vorher zu sagen, wie viele Pixel die Druckgröße verlangt:

```php
[$breite, $hoehe] = (new PngRenderer($options))->artworkPixels($matrix);
// 288 x 288 bei 50 mm und 600 dpi

$logo->isSharpEnoughFor($breite, $hoehe);      // false heißt: wird vergrößert
$logo->recommendedPixels($breite, $hoehe);     // was anzufordern wäre
```

Bei den festgelegten Werten des Projekts — 50 mm, 600 dpi, Kasten 11, Rand 1 —
sind das **288 × 288 px**. Darunter wird hochskaliert und das Ergebnis weich;
darüber wird verkleinert, und da verhält sich ein Raster gut. Die Demo-Seite
zeigt beide Zahlen nebeneinander und sagt, welcher Fall vorliegt.

Zwei Dinge macht `PngLogo` ungefragt:

- **Metadaten fallen weg.** Ein PNG kann kein Skript tragen, aber `tEXt`,
  `iTXt`, EXIF und Farbprofile — und die reisen mit. Der Name einer
  Grafikerin oder die GPS-Koordinaten einer Kamera hätten in einem Symbol
  nichts verloren, das auf eine Verpackung gedruckt wird. Die Datei wird mit
  den Bildchunks neu geschrieben, sonst nichts
- **Eingebettet, nie verlinkt.** Im SVG steht die ganze Datei als Data-URI.
  Ein `<image href="https://…">` würde den Browser des Betrachters die Datei
  nachholen lassen — der einzige echte Abfluss im ganzen Entwurf — und in der
  Druckerei als leerer Kasten ankommen

Beide Kantenlängen müssen **ungerade** sein. Ein QR-Symbol ist immer ungerade
(17 + 4 × Version), eine gerade Kantenlänge läge einen halben Modul neben dem
Raster und räumte Teile von Modulen frei statt ganze.

#### Die Stufe nicht raten, ausrechnen lassen

Die Symbolgröße ist **keine Einstellung**. Sie folgt aus Nutzlast und
Fehlerkorrekturstufe, weshalb ein Kasten, der bei H passt, bei M nicht mehr
passt — das Symbol ist kleiner geworden, nicht das Logo. `LogoFit` nimmt einem
das Rätsel ab:

```php
use Redcodede\QrGen\Qr\Logo\LogoFit;

$fit = new LogoFit(new BaconQrEncoder());
$result = $fit->lowestLevelFor('https://example.org/qr/7K4M2', LogoBox::square(11, 1));

$result->level();        // ErrorCorrection, hier H
$result->matrix();       // das Symbol, schon kodiert
$result->placement();    // wo das Logo hinkommt
$result->clearedShare(); // 0.111 — Anteil freigeräumter Module
$result->budget();       // 0.15  — was die Stufe erlaubt
$result->headroom();     // 0.26  — was davon übrig ist
```

**Die niedrigste Stufe, die überlebt, nicht die höchste verfügbare.** Eine
höhere Stufe hilft doppelt (mehr Wiederherstellung *und* ein größeres Symbol,
in dem derselbe Kasten weniger Anteil hat), kostet aber Dichte: mehr Module auf
derselben Druckbreite heißt kleinere Module. Die niedrigste ausreichende Stufe
hält die Module so groß wie möglich.

Passt nichts, sagt `NoFittingLevel`, **was an jeder der vier Stufen scheiterte**
und welcher Kasten bei H noch ginge:

```
No error correction level lets a 17x17 logo box survive on this payload.
  L: 25x25 symbol — … largest centred box here is 9 modules …
  M: 25x25 symbol — … largest centred box here is 9 modules …
  Q: 29x29 symbol — … largest centred box here is 13 modules …
  H: 29x29 symbol — … largest centred box here is 13 modules …
At level H the largest box that survives is 11x11 modules.
```

Der Sicherheitsfaktor ist der Punkt, an dem das verteidigungsfähig wird: die
freigeräumte Fläche ist ein Anteil an **Modulen**, die Wiederherstellungsrate
ein Anteil an **Codewörtern**. Standard ist, höchstens die **Hälfte** der Rate
für das Logo auszugeben — die andere Hälfte zahlt für Farbzuwachs, Kratzer,
Fingerabdruck, schlechtes Licht und ein schräg gehaltenes Telefon. Ein Logo,
das die ganze Reserve frisst, ergibt einen Code, der am Bildschirm scannt und
im Regal versagt. Der Faktor ist ein Konstruktorargument, weil jemand mit einem
Andruck in der Hand es besser weiß als diese Klasse.

#### Ausrichtungsmuster: Kompromiss, kein Fehler

Nicht jedes Funktionsmuster ist gleich. Ein Such-, Takt- oder Formatmuster zu
verdecken nimmt einem Scanner die Geometrie, mit der er das Symbol überhaupt
findet — das wird **immer** abgewiesen. Ein Ausrichtungsmuster dient der
Perspektiv- und Verzerrungskorrektur; eines von mehreren zu verlieren kostet
Toleranz auf gewölbtem oder schräg gehaltenem Material, die übrigen finden das
Raster weiter.

Das ist keine Feinheit, sondern notwendig: **auf vielen Versionen sitzt ein
Ausrichtungsmuster genau in der Mitte**, dort kann ein zentriertes Logo es nicht
umgehen, wie klein es auch ist. Gemessen an der Versionstabelle:

| Versionen | Mitte |
|---|---|
| 1–6 | frei |
| **7–13** | **belegt** |
| 14–20 | frei |
| **21, 23, 25, 27** | **belegt** |
| 22, 24, 26, 28+ | frei |

Ein pauschales Verbot würde diese Versionen logofeindlich machen. Deshalb:

```php
LogoBox::square(11, 1)->allowingAlignmentPatterns();
```

Standard ist **aus**. Die Platzierung merkt sich den Kompromiss, statt ihn zu
verschlucken:

```php
$placement->compromisesAlignment();       // true
$placement->coveredAlignmentModules();    // 25, ein ganzes 5x5-Muster
```

Auf einem gedruckten Etikett ist das die Sorte Entscheidung, die ein Andruck
klärt, nicht ein Standardwert.

#### Wie groß der Kasten sein darf

Für `https://example.org/qr/7K4M2` (28 Bytes), Funktionsmuster exakt geprüft:

| Kasten | bei Q (29 × 29) | bei H (33 × 33) |
|---|---|---|
| 9 × 9 | 9,6 %, frei | 7,4 %, frei |
| 11 × 11 | 14,4 %, frei | **11,1 %, frei** |
| 13 × 13 | 20,1 %, **2 Module Ausrichtungsmuster** | 15,5 %, frei |

Verdeckte Module gegen Wiederherstellungsrate ist eine **Faustregel** — Module
und Codewörter sind nicht dieselbe Einheit. Rund ein Drittel der Breite bei
Stufe H ist bequem. Keine Faustregel sind die Funktionsmuster: Such-, Takt- und
Ausrichtungsmuster tragen **keine** Fehlerkorrektur, und ein Kasten darüber
wird abgewiesen.

Das Logo kostet zweimal: einmal in verdeckter Fläche, einmal in der Version.
Ohne Logo genügt Stufe M, mit Logo braucht es H — und H hebt dasselbe Nutzdatum
von 29 × 29 auf 33 × 33. Bei 20 mm Codebreite sind das **0,54 mm je Modul ohne
gegen 0,49 mm mit Logo**.

Und: **ein Logo braucht einen deckenden Hintergrund.** Mit `'none'` als heller
Farbe wird die Kombination abgewiesen, weil sonst der Untergrund durch die
freigeräumte Fläche scheint und ein Scanner dort weder hell noch dunkel sieht.

#### Was das Logo mitbringen muss

Ein SVG ist ein Dokument, kein Bild: es kann Skript, Event-Handler, externe
Verweise und Entity-Deklarationen tragen. Ein Logo kommt fast immer von außen,
also wird es als **nicht vertrauenswürdig** behandelt, auch wenn die Person
vertrauenswürdig ist, die es geliefert hat.

`SvgLogo` **baut das Markup aus geparsten Tokens neu auf**, statt es zu
filtern. Was der Sanitizer nicht verstanden hat, kann im Ergebnis nicht
auftauchen — eine Lücke in der Musterliste lässt eine Datei also scheitern,
statt sie mit Unerwartetem durchzulassen.

| | |
|---|---|
| **erlaubt** | `g`, `path`, `rect`, `circle`, `ellipse`, `line`, `polygon`, `polyline`; Geometrie-, Fill-, Stroke- und Transform-Attribute |
| **wird aufgelöst** | ein `<style>`-Block mit Klassenselektoren wird in Präsentationsattribute inlined, das leere `<defs>` danach entfernt. Das ist Illustrators Standardexport, einzeln (`.a{…}`) wie gruppiert (`.a,.b{…}`) |
| **wird entfernt** | `id`-Attribute, Kommentare, `<title>`, `<desc>`, `<metadata>` |
| **wird abgewiesen** | `<script>`, `on…`-Handler, `<image>`, `<text>`, `<use>`, `<a>`, `<foreignObject>`, Animationen, nicht leere `<defs>` (Gradienten, Masken, Clip-Paths), `url(…)`, `xlink:href`, `data:`, At-Rules, DOCTYPE mit interner Teilmenge, loser Text, unbalancierte Tags |

**Abgewiesen statt bereinigt.** Stilles Entfernen würde entweder die Zeichnung
ändern, ohne es zu sagen, oder einen Rest übriglassen, den niemand bedacht hat.
Eine Ablehnung, die das störende Konstrukt benennt, lässt die Datei dort
reparieren, wo sie richtig zu reparieren ist.

Was daraus als Zulieferbedingung folgt: **`viewBox` vorhanden, Schrift in Pfade
umgewandelt, Gradienten und Masken aufgelöst, keine eingebetteten Bilder,
keine externen Verweise.** Farben sind ohnehin RGB — SVG kennt kein CMYK.

`id`-Attribute werden entfernt statt umbenannt. Ohne Bezeichner gibt es nichts,
was kollidieren kann, wenn zwei Codes auf derselben Seite stehen.

Aus demselben Grund scheitert eine Zeichnung mit `clip-path:url(#…)`: der
Verweis hätte nach dem Entfernen der Bezeichner kein Ziel mehr. Das ist keine
Schikane, sondern der Preis dafür, dass zwei Codes nebeneinander stehen dürfen.

**Ein gruppierter Selektor sagt nichts, was eine wiederholte Regel nicht auch
sagt** — `.a,.b{fill:#000}` wird deshalb verstanden, obwohl es kein einzelner
Selektor ist. Taucht eine Klasse zweimal auf, einmal in einer Gruppe und einmal
allein, gewinnt je Eigenschaft die spätere Deklaration. Das ist, was ein
Browser mit derselben Datei täte.

### Das Etikett

Nicht der Code allein, sondern der Code als Teil eines Bildes: Rahmen,
Codefläche, Bildmarke daneben und darunter der Text.

```php
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\Render\LabelOptions;
use Redcodede\QrGen\Qr\Render\LabelPngRenderer;
use Redcodede\QrGen\Qr\Render\LabelSvgRenderer;
use Redcodede\QrGen\Qr\Text\SvgFont;

// Der Kern öffnet keine Dateien. Wer die Schrift hat, liest sie.
$font = SvgFont::fromMarkup(file_get_contents(
    __DIR__ . '/resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg'
));

$renderer = new LabelSvgRenderer(LabelLayout::standard(), $font);
$svg = $renderer->render($matrix, 'Rückgabe über das GVÖ-SYSTEM', $logo);

// Dasselbe Bild als PNG, in Druckauflösung.
$png = (new LabelPngRenderer(LabelLayout::standard(), $font))
    ->render($matrix, 'Rückgabe über das GVÖ-SYSTEM', $logo);
```

Die Codefarbe ist der einzige Wert, in dem sich die beiden ausgelieferten
Fassungen unterscheiden:

```php
$options = LabelOptions::default()->withCodeColor('#009a7c');
```

**Der Kasten bleibt, die Schrift gibt nach.** Der Text wird umgebrochen und
verkleinert, bis er in seinen Kasten passt. Passt er auch beim kleinsten
erlaubten Grad nicht, wird er abgewiesen statt unleserlich gesetzt. Die Regel
liegt in `Qr\Layout\LabelText` und **wird von beiden Renderern benutzt**: zwei
Formate desselben Etiketts sollen dasselbe Bild zeigen, und das ist nur dann
zugesichert, wenn der Umbruch einmal gerechnet wird.

**Die Maße stehen in `LabelLayout` und sind Entscheidungen**, keine
Standardwerte zum Drehen, genau wie die Werte in `Qr\Preset`. Ein freigegebener
Andruck gilt für diese Zahlen.

Zwei Eigenheiten des PNG, die zu kennen sind:

- **Es ist Truecolor**, nicht indiziert wie das PNG des Symbols allein. Ein
  Etikett trägt beliebige Bildmarken, eine frei gesetzte Farbe und
  kantengeglättete Schrift; das zu quantisieren hieße, einen Quantisierer
  mitzuliefern, um Bytes in einer ohnehin komprimierten Datei zu sparen
- **Die Pixelgröße folgt dem Modul, nicht der Wunschauflösung.** Ein Modul
  bekommt ganzzahlig viele Pixel, damit seine Kanten hart bleiben; die
  Auflösung landet dadurch etwas neben den bestellten 600 dpi, und der
  `pHYs`-Block trägt den tatsächlichen Wert. Die Datei druckt damit weiterhin
  in der Größe, die `LabelLayout` nennt

### Ausliefern

Der Renderer gibt eine Zeichenkette zurück und schreibt nichts. Was daraus
wird, entscheidet der Aufrufer:

```php
$renderer = new SvgRenderer(SvgOptions::default()->withXmlDeclaration());

header('Content-Type: ' . $renderer->mimeType());   // image/svg+xml
header('Content-Disposition: attachment; filename="qr-code.'
    . $renderer->fileExtension() . '"');            // svg
echo $renderer->render($matrix);
```

### Fehler behandeln

Alles, was dieses Paket wirft, erfüllt `Qr\Exception\QrGenException`.

```php
use Redcodede\QrGen\Qr\Exception\EncodingFailed;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\QrGenException;

try {
    $svg = (new SvgRenderer())->render($encoder->encode($url, $level));
} catch (InvalidArgument $e) {
    // leere Eingabe, unbekannte Stufe, unmögliche Farbe oder Größe
} catch (EncodingFailed $e) {
    // passt in kein QR-Symbol, auch nicht in Version 40
} catch (QrGenException $e) {
    // alles aus diesem Paket
}
```

`EncodingFailed` nennt **die Länge der Nutzlast, nicht die Nutzlast.** Was in
diese Meldung kommt, landet in jedem Log, das die Ausnahme fängt — und ein
Paket, das zusagt, verarbeitete URLs nicht zu speichern, kann sie nicht in
einen Stacktrace schreiben.

### Logging

**Der Kern protokolliert nichts** und wird es nicht tun — er hat kein I/O, und
das ist Absicht: eine Bibliothek, die selbst ins Log schreibt, schreibt in ein
Log, das sie nicht kennt. Er wirft, der Aufrufer entscheidet.

Die andere Hälfte liegt in der Hülle, und die ist gebaut. Zwei Stellen fangen
`QrGenException` und schreiben eine Warnung, statt einen 500er durchschlagen zu
lassen:

| | |
|---|---|
| `Artwork::load()` | `qr-gen: Bildmarke abgelehnt`, mit Asset-Pfad und Grund. Ergebnis: der Code ohne Bildmarke |
| `ImageController` | `qr-gen: Symbol nicht erzeugt`, mit Host, Länge, Variante, Format und Grund. Ergebnis: 422 mit der Begründung |

**Die verarbeitete Adresse steht in keinem der beiden.** Aus demselben Grund,
aus dem `EncodingFailed` nur die Länge der Nutzlast nennt: ein Paket, das
zusagt, verarbeitete Adressen nicht zu speichern, kann sie nicht in ein Log
schreiben. Zum Nachstellen genügen Host, Länge, Variante und Format.

Offen bleibt davon:

- Logger per Konstruktor injiziert (PSR-3) statt per `logger()`-Helfer. Heute
  steht der Framework-Bezug in der Hülle, wo er hin darf, aber nicht als
  auswechselbare Abhängigkeit
- **Stufen unterscheiden:** ein zu großer Logokasten ist eine
  Konfigurationssache und gehört auf `warning`, ein unerwarteter Fehlschlag auf
  `error`. Heute nicht unterscheidbar, weil beides dieselbe Ausnahme ist
- Ein Fehlschlag beim Erzeugen eines Codes darf einen Stapelverarbeitungslauf
  nicht abbrechen — protokollieren, weitermachen, am Ende zusammenfassen

### In Statamic

Der Tag baut beide Varianten, zeigt sie als Vorschau und verlinkt die
Downloads:

```antlers
{{ qr_gen url="https://example.org/qr/7K4M2" }}
```

| Parameter | |
|---|---|
| `url` | die Adresse, die im Code steht. Ohne sie gibt der Tag nichts aus |
| `logo` | Asset-Pfad der Bildmarke, mit oder ohne Container (`assets::pfad`) |
| `variants` | `plain`, `logo` oder `plain\|logo`. Ohne Angabe beides, soweit global erlaubt |
| `heading` | Ebene der Überschrift: `h1` bis `h5`, voreingestellt `h1`. Die Beschriftung der Codes rückt mit |
| `button_class` | Klasse der Download-Knöpfe, voreingestellt `qr-gen-button` |
| `button_class_secondary` | Klasse für „direkt öffnen" |

Die beiden Klassenparameter sind da, damit eine Seite ihre eigenen Knöpfe
einsetzen kann, ohne die Vorlage zu kopieren:

```antlers
{{ qr_gen :url="ziel_url" button_class="button" button_class_secondary="button outline" }}
```

Aus einer Seite heraus mit Werten aus dem Eintrag:

```antlers
{{ qr_gen :url="ziel_url" :logo="logo_pfad" }}
```

Die **Vorschau steht als SVG direkt im Markup** und kostet keine zweite
Anfrage. Nur Download und „direkt öffnen" laufen über die Bild-Route
`/!/qr-gen/image`. Deren Adressen sind **signiert**: ohne Signatur wäre der
Endpunkt ein kostenloser QR-Generator auf fremder Domain, mit dem sich Codes
für beliebige Links erzeugen ließen. Der Routenname steht in
`ImageController::ROUTE`; Statamic stellt allen Action-Routen `statamic.`
voran, weil es die ganze Gruppe so benennt.

Ausgeliefert wird mit `Cache-Control: no-store` — erzeugt wird bei jeder
Anfrage neu, es gibt also keine gespeicherte Kopie, und eine
zwischengespeicherte wäre die einzige.

Die Ausgabe ist die View `qr-gen::panels` mit eigenen Klassen und ohne
mitgeliefertes Aussehen. Wer mehr ändern will als die Knopfklassen,
veröffentlicht sie nach `resources/views/vendor/qr-gen/` und passt sie dort an,
statt das Paket anzufassen.

**Der Aufbau ist die eine Zusicherung, die die Vorlage macht:**

```
.qr-gen
  .qr-gen-header        Überschrift und Einleitung
  .qr-gen-panels        die Codes, hier wird nebeneinander gestellt
    .qr-gen-panel
      .qr-gen-title     welcher Code das ist
      .qr-gen-preview
      .qr-gen-actions
        .qr-gen-downloads   die Formate, gehören zusammen
        der Knopf zum Ansehen, eine Stufe darunter
```

Zwei Aussagen über die Ordnung stecken darin, beides keine Geschmacksfragen:

**Der Kopf steht über den Codes und ist kein Element von `.qr-gen-panels`.**
Wer die Codes nebeneinander stellt, tut das an dieser einen Stelle, und Text
kann dabei nicht in eine Spalte neben einen Code rutschen.

**Die beiden Formate stehen in einem eigenen Kasten**, der Knopf zum Ansehen
daneben und nicht darin. Dieselbe Sache gehört zusammen, das Nebenher eine
Stufe tiefer.

Die Beschriftung eines Codes steht eine Überschriftenstufe unter der der
Ausgabe: bei `heading="h2"` also `h3`. Sie ist der Überschrift untergeordnet,
und das ergibt sich aus dem Aufbau, nicht aus dem Aussehen.

Überschrift und Einleitung kommen aus den globalen Einstellungen, je
Sprachfassung, mit den mitgelieferten Texten als Rückfall. In der Einleitung
wird `{url}` durch die Adresse ersetzt und dabei verlinkt. Die Einleitung
kommt als fertiges Markup in die Vorlage, weil Antlers nicht von sich aus
maskiert — so ist an genau einer Stelle maskiert, und eine Vorlage, die sie
ausgibt, kann nichts falsch machen.

`Statamic\Artwork::load()` löst den Logo-Pfad zu einem Asset auf und
entscheidet an der Dateiendung zwischen `PngLogo` und `SvgLogo`. Es ist die
einzige Stelle im Paket, die ein Dateisystem anfasst, und sie liegt bewusst in
der Hülle. Fehlt das Asset oder lehnt der Sanitizer es ab, gibt es **kein
500er, sondern eine Warnung im Log und den Code ohne Bildmarke.**

### Im Control Panel

Unter **Werkzeuge → QR-Codes** stehen die globalen Einstellungen: welche der
vier Typen angeboten werden, welche Formate zum Herunterladen, Bildmarke und
Default-URL, Text und Farbe des Etiketts, dazu Überschrift und Einleitung der
Seite — **ein Block je Sprachfassung**, die Fassungen kommen aus Statamic und
nicht aus einer Liste im Paket.

**Zwei Typen hängen an einem Wert und entfallen sonst lautlos.** „Code mit
Bildmarke" gibt es nur, wenn eine Bildmarke hinterlegt ist, und „Etikett,
farbiger Code" nur, wenn eine Farbe gesetzt ist. Beides ist keine
Fehlkonfiguration, sondern die Antwort auf „was soll ich sonst zeigen": zwei
Etiketten in derselben Farbe wären keine zwei.

Ein leeres Textfeld heißt „nimm den mitgelieferten Text" und nicht „zeig
nichts". Deshalb steht der mitgelieferte Text auch nicht vorausgefüllt im
Formular: wer ihn einmal speichert, hat ihn von da an als eigenen und bekommt
eine spätere Verbesserung des Pakets nicht mehr mit.

Die Beschriftung der vier Codes bleibt im Textkatalog und ist keine
Einstellung: sie benennt, was das Paket erzeugt, und ändert sich mit ihm. Der
**Text auf dem Etikett** ist etwas anderes und deshalb ein Feld: er steht im
erzeugten Bild und geht auf eine Verpackung.

Die Seite rendert Statamics eigene `publish-form`-Komponente. Kein eigenes
Vue, kein Build im Paket: Speichern, Validierung, Toast und Strg+S kommen mit,
weil es Statamics eigene Bausteine sind. Denselben Weg geht Statamic für seine
Globals.

| | |
|---|---|
| Ablage | `content/qr-gen/settings.yaml`, Pfad über `qr-gen.settings_path` |
| Form der Datei | dieselbe wie `config/qr-gen.php` |
| Rückfall | was dort fehlt, kommt aus `config/qr-gen.php` |
| Berechtigung | `configure qr-gen`, eigene Gruppe in den Rollen |

**Unter `content/` und nicht unter `storage/`**, weil die Datei versioniert und
mitgesichert gehört: sie ist Konfiguration, nicht Zwischenstand.

**Kein Global Set.** Ein Global Set stünde in der Redakteursnavigation zwischen
den Inhalten und wäre versehentlich änderbar. Eine abgeschaltete Variante nimmt
einer ganzen Seite ihre Codes, und das soll niemand im Vorbeigehen können —
daher auch die eigene Berechtigung.

Die Druckwerte stehen auf der Seite, aber als Text und nicht als Feld: sie sind
entschieden, nicht eingestellt, und ein Feld sähe aus, als ginge es doch. Zu
ändern sind sie in `Qr\Preset`.

### Auf einer Seite

Der Blueprint einer Seite importiert das Fieldset der Erweiterung:

```yaml
-
  import: qr-gen::qr_code
```

Es bringt genau ein Feld mit, `qr_url`. Leer heißt: die Default-URL aus den
globalen Einstellungen gilt. Die Seite reicht den Wert an den Tag weiter:

```antlers
{{ qr_gen :url="qr_url" }}
```

**Mehr ist je Stelle nicht einzustellen, und das ist Absicht.** Bildmarke,
Varianten und Formate stehen im Control Panel und gelten überall gleich. Die
Adresse muss verschieden sein, weil jeder Hersteller seine eigene hat; alles
andere wäre eine zweite Stelle, an der dieselbe Frage beantwortet wird.

Das Fieldset ist ein Angebot, keine Vorschrift. Wer die Adresse anders
herleitet, und die erste Installation setzt sie aus einem Herstellercode
zusammen, gibt sie einfach direkt als Tag-Parameter mit.

### Beim Suchen

`ModuleMatrix::toAsciiArt()` zeichnet die Matrix als Text. In einer
fehlgeschlagenen Assertion ist das lesbarer als 625 Booleans.

```php
echo $matrix->toAsciiArt();
```

## Demo-Seite

`demo/` ist der Docroot des DDEV-Containers und läuft auf denselben Klassen
wie die Statamic-Hülle: Encoder, `Qr\Settings`, `Qr\Preset`, beide Renderer.
Wer daran etwas ändert und die Demo aufruft, sieht es sofort, ohne Statamic
hochzufahren.

**Was die Demo nicht teilt, ist die Ausgabe.** `demo/index.php` bringt eigenes
Markup mit; die View `qr-gen::panels`, die auf einer Seite erscheint, kommt
dort nicht vor. Wer an der Darstellung arbeitet, tut das in der Hülle und
nicht hier — die Demo prüft den Kern, nicht die Seite.

**Zwei Eingaben: die URL und welche Bildmarke.** Alles andere kommt aus
`Qr\Preset`, weil alles andere entschieden ist. Dieselben zwei Eingaben nimmt
der Tag, also übt die Demo die Form, die das Plugin hat, und nicht eine
größere.

| Datei | |
|---|---|
| `demo/index.php` | Formular, **beide Varianten nebeneinander**, Kennzahlen, Download-Knöpfe. Kein Text im Code, alles aus dem Katalog |
| `demo/image.php` | liefert ein Bild allein; `?format=png`, `?variant=logo`, `?download=1` |
| `demo/bootstrap.php` | Autoload, Eingabeprüfung, Objektaufbau |
| `demo/logos/*.svg`, `*.png` | Testlogos, Vektor und Raster. Jedes SVG hier wird von `RealWorldLogoTest` durch die ganze Kette geschickt |

`?lang=en` schaltet auf Englisch. Das Formular bietet es nicht an — genau das
ist mit „auf Abruf" gemeint.

**Es wird nichts gespeichert.** Jede Anfrage erzeugt und rendert von neuem,
das SVG lebt nur in der Antwort. Kein Cache, kein Ausgabeordner, deshalb
`Cache-Control: no-store` — eine zwischengespeicherte Kopie wäre die einzige.
Der Download erzeugt neu, statt eine Datei zu holen.

Der Ordner ist per `export-ignore` aus dem Composer-Dist ausgenommen; er ist
Entwicklungswerkzeug, kein Lieferbestandteil.

## Aufbau

```
src/
  Qr/                      Fachlogik. Kein Laravel, kein Statamic, keine Datei-I/O
    Contract/
      QrEncoder.php        Zeichenkette → Matrix (Schritte 1–6)
      QrRenderer.php       Matrix → Datei-Bytes (Schritt 7)
      Logo.php             Markup plus Eigengröße
      RasterArtwork.php    Bildmarke, die schon Pixel ist
    Encoder/
      BaconQrEncoder.php   Adapter auf bacon/bacon-qr-code
    Logo/
      SvgLogo.php          Sanitizer: fremdes SVG → einbettbares Markup
      PngLogo.php          fremdes PNG → Data-URI plus Pixel, ohne Metadaten
      LogoBox.php          gewünschter Kasten in Modulen, prüft die Platzierung
      LogoPlacement.php    wo der Kasten dann liegt
    Settings/
      GlobalSettings.php   was angeboten wird, und die Rückfallwerte
      EffectiveSettings.php was mit der Adresse einer Stelle daraus gilt
      Variant.php          die beiden Code-Arten, mit festen Namen
    Text/                  Schrift, ohne Schriftparser
      SvgFont.php          SVG-Schriftdatei → Glyphen mit Umriss und Vorschub
      Glyph.php            ein Zeichen: Vorschubweite und `d`
      TextLine.php         setzt und misst eine Zeile, verkleinert sie
      PlacedGlyph.php      eine gesetzte Glyphe mit ihrem Abstand
    Layout/                das Etikett, unabhängig vom Ausgabeformat
      LabelLayout.php      die Maße: Fläche, Rahmen, Codeplatz, Logo, Textkasten
      LabelText.php        Umbruch, Grad und Grundlinien, für beide Formate
    Render/
      SvgRenderer.php      Matrix → SVG, räumt den Logokasten frei
      SvgOptions.php       unveränderliche Darstellungseinstellungen
      PngRenderer.php      Matrix → PNG, von Hand, ohne Bildextension
      PngOptions.php       Auflösung und Druckgröße statt Pixelmaße
      LabelSvgRenderer.php das ganze Etikett als SVG
      LabelPngRenderer.php dasselbe Etikett als Truecolor-PNG
      LabelOptions.php     die Farben des Etiketts
    Raster/                zeichnet die Bildmarke ins PNG
      Transform.php        affine Matrix, transform-Listen
      PathFlattener.php    d-Attribut → Streckenzüge in Gerätepixeln
      ShapeFlattener.php   rect, circle, ellipse, polygon → Pfadgrammatik
      ScanlineFiller.php   Scanline-Füllung, überabgetastet
      LogoRaster.php       Markup durchlaufen, Farbe vererben, komponieren
      Palette.php          Farben → Palettenindizes
      PngDecoder.php       PNG → RGBA, ohne Bildextension
      RasterScaler.php     umrechnen, mit vormultipliziertem Alpha
    Exception/             QrGenException, InvalidArgument, EncodingFailed, LogoRejected, TextRejected
    ErrorCorrection.php    die vier Stufen der Norm
    ModuleMatrix.php       die Grenze zwischen Kodieren und Zeichnen
    Preset.php             die festgelegten Werte des Projekts
  I18n/
    Translator.php         Oberflächentexte, außerhalb des Kerns
  Statamic/                dünne Hülle, alles Framework-Nahe liegt hier
    ServiceProvider.php    nur Verdrahtung, keine Fachlogik
    Artwork.php            Asset-Pfad → Logo; die einzige Datei-I/O des Pakets
    Symbols.php            Encoder, LogoFit und Renderer zusammengesteckt
    Tags/QrGen.php         {{ qr_gen }}, die Frontend-Komponente
    Settings/
      SettingsStore.php    die YAML, mit der Config als Rückfall darunter
      SettingsBlueprint.php das Formular der CP-Seite, in PHP wegen der Texte
    Http/Controllers/
      ImageController.php  die einzelne Datei, signiert und ohne Zwischenspeicher
      CP/SettingsController.php  die Seite unter Werkzeuge
routes/
  actions.php              die Bild-Route, landet unter /!/qr-gen/image
  cp.php                   die Einstellungsseite, landet unter /cp/qr-gen
config/
  qr-gen.php               Rückfallwerte; das CP überschreibt sie, die Seite die wiederum
resources/views/
  panels.antlers.html      die Ausgabe des Tags, eigene Klassen, kein Aussehen
  cp/settings.blade.php    die CP-Seite, rendert Statamics publish-form
resources/fieldsets/
  qr_code.yaml             die Felder für den Blueprint einer Seite
resources/lang/            {sprache}/texts.php, von Laravels Übersetzer ladbar
demo/                      Demo-Seite und Testlogos, nicht im Dist
tests/                     Qr/ und I18n/
```

`ModuleMatrix` ist die ganze Grenze zwischen Kodieren und Zeichnen. Alles, was
ein Encoder weiß, endet dort, und alles, was ein Renderer braucht, beginnt
dort. Deshalb ist ein Encoder-Wechsel eine Klasse und kein Umbau. Die Matrix
trägt **keine Ruhezone** — die gehört zum Zeichnen, weil ihre Größe davon
abhängt, wie der Code platziert wird, nicht davon, wie er kodiert wurde.

Sie trägt aber optional, **welche Module Funktionsmuster sind** — Such-,
Trenn-, Takt- und Ausrichtungsmuster, Format- und Versionsinformation. Die sind
nicht fehlerkorrigiert, also muss alles, was Module entfernt, wissen wo sie
liegen. Nur der Encoder weiß das, deshalb reist die Maske mit der Matrix.
`hasReservedInfo()` unterscheidet „kein Funktionsmuster" von „niemand hat es
gesagt" — für die Prüfung eines Logokastens ist das der Unterschied zwischen
geprüft und ungeprüft.

Ein Logo liefert **Markup, keinen Pfad.** Das Lesen einer Datei ist Sache des
Aufrufers, was den Kern frei von Datei-I/O hält und dasselbe Logo aus einem
Statamic-Asset, einem Paket oder einer Testdatei kommen lässt, ohne dass dieses
Paket den Unterschied kennt.

Für `src/Qr/` gilt: **kein `use Statamic\…`, kein `use Illuminate\…`, kein
Datei- oder Netzzugriff.** Statamic 3 und 6 lassen sich nicht von einem
Paketstand aus bedienen — 3.4 will Laravel 8 und Vue 2, 6 will PHP 8.2,
Laravel 11 und Vue 3. Bleibt der Kern framework-frei, ist die zweite Umsetzung
Hüllenarbeit statt Wiederholung.

Das prüft `tests/Qr/CoreIsFrameworkFreeTest.php`, nicht die Absprache.

## Was die Tests zusichern

Ein falscher QR-Code sieht nicht falsch aus. Deshalb prüfen die Tests die
Strukturen, die die Norm festlegt, und nicht nur die Form der Ausgabe:

- **Die drei Suchmuster** stehen an ihren Plätzen, und in der vierten Ecke
  steht keins — daran erkennt ein Scanner die Drehung
- **Die Taktmuster** in Reihe und Spalte 6 wechseln, dunkel auf geraden
  Koordinaten. Daran misst ein Scanner die Modulbreite
- **Das immer dunkle Modul** bei (8, 4 × Version + 9) ist dunkel
- **Die Seitenlänge** ist 17 + 4 × Version, also stets eins mehr als ein
  Vielfaches von vier
- **Eine höhere Fehlerkorrekturstufe** macht das Symbol nie kleiner
- **Das gerenderte SVG lässt sich zurück in die Matrix lesen** und ergibt
  dieselbe. Das ist die Zusicherung für Schritt 7: die Zusammenfassung
  benachbarter Module zu einem Pfad verliert und erfindet nichts
- **Die Ausgabe verweist auf nichts Externes** — kein `<image>`, kein
  `xlink:href`, kein `@import`, kein `<script>`
- **Der Logokasten ist wirklich leer.** Auf einer vollflächig dunklen Matrix
  wird gezählt: gezeichnet werden genau `Module − Kasten`, und kein einziger
  Lauf reicht hinein
- **Der Sanitizer weist ab, was er nicht kennt** — 19 Fälle von `<script>` über
  `url(…)` bis zur DOCTYPE-Teilmenge, jeder mit der Meldung, die im Export zu
  beheben ist
- **Echte Exporte gehen durch die ganze Kette.** Jede Datei in `demo/logos`
  wird von `RealWorldLogoTest` sanitisiert, in das Briefing-Symbol gerendert und
  daraufhin geprüft, dass weder `<style>` noch `class=` noch `id=` noch `url(`
  überlebt hat

**Was die Tests nicht können:** sagen, ob der Code vom Etikett gelesen wird.
Dafür braucht es einen Andruck in Originalgröße auf dem echten Material,
geprüft mit mehreren Telefonen. Ein unabhängiger Decoder über die Ausgabe
(`zbarimg`) ist der nächste Prüfschritt und hier noch nicht installiert; der
kürzeste Weg bis dahin ist, die Demo-Seite mit dem Telefon zu scannen.

## Versionierung

**SemVer.** Bis `1.0.0` gilt der Stand als in Entwicklung, Breaking Changes
sind bis dahin in Minor-Schritten erlaubt.

| | |
|---|---|
| `0.1.0` | Projektgerüst |
| `0.2.0` | URL → SVG, Demo-Seite, Tests |
| `0.3.0` | Logo in der Mitte, SVG-Sanitizer, Funktionsmuster-Prüfung |
| `0.3.1` | Logo-Ablehnungen nennen Zahlen und die tatsächliche Ursache |
| `0.4.0` | `LogoFit` findet die Stufe; Ausrichtungsmuster als Kompromiss |
| `0.4.1` | Logo-Darstellung ohne Kantenglättung behoben, brauchbare Standardwerte |
| `0.4.2` | Formularzustand der Demo: kein Autofill, kein Mausrad, Reset-Knopf |
| `0.5.0` | Textsammlung DE/EN, `Qr\Preset` mit den festgelegten Werten |
| `0.6.0` | Druckfertiges PNG ohne Bildextension, Download für beide Formate |
| `0.7.0` | Eigener Rasterisierer: Bildmarke auch im PNG, beide Codes in beiden Formaten |
| `0.8.0` | Bildmarke darf ein PNG sein: eigener Dekoder, Skalierer, Größenempfehlung |
| `0.9.0` | Gerüst der Statamic-Hülle: ServiceProvider, Konfiguration, Testharness |
| `0.10.0` | Konfigurationsmodell mit zwei Ebenen, Demo-Seite nach Bereichen getrennt |
| `0.11.0` | Frontend-Komponente: Tag, Bild-Route, Logging, in einer Seite lauffähig |
| `0.12.0` | Einstellungen im Control Panel, Fieldset, Texte in der Hülle |
| `0.13.0` | Seitentexte je Sprachfassung, Aufbau und Knöpfe der Ausgabe |
| `0.13.1` | Knöpfe nach Zusammengehörigkeit, Beschriftung eine Stufe tiefer |
| `1.0.0` | **Erstfreigabe.** Funktionsumfang steht, öffentliche API ab hier stabil |
| `1.0.1` | Veröffentlicht: Projektbezug raus, Installation über Packagist |
| `2.0.0` | **Etikett mit Schrift und Bildmarke, Rückbau auf eine Konfigurationsebene.** Breaking: `PageSettings`, `qr_logo`, `qr_variants` und die Tag-Parameter `logo` und `variants` sind weg |
| `2.1.0` | Das Etikett in der Statamic-Hülle: zwei neue Typen, Text und Farbe im Control Panel, Tag und Bild-Route |
| `2.1.1` | Ein Etikett mit einer Bildmarke aus Pixeln war kein gültiges XML |
| `2.2.0` | geplant: Code- und Token-Erzeugung |

Commits folgen [Conventional Commits](https://www.conventionalcommits.org/de/v1.0.0/):
`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `build:`. Ein `!`
oder eine `BREAKING CHANGE:`-Fußzeile markiert einen Bruch. Jede Version
bekommt einen Tag `vX.Y.Z` und ein GitHub-Release, die Änderungen stehen in
[CHANGELOG.md](CHANGELOG.md).

## Einbauen

```bash
composer require redcodede/qr-gen
```

Mehr nicht. Das Paket liegt auf Packagist, es braucht keinen
`repositories`-Eintrag und keine Zugangsdaten. Statamic findet die Erweiterung
danach von selbst; ein `php please addons:discover` ist nur nötig, wenn ein
Deploy den Autoloader zwischendurch eingefroren hat.

**`composer audit` wird danach 35 Hinweise mehr melden als vorher, und keiner
davon kommt aus diesem Paket.** Sie stammen aus `statamic/cms ^3.4` und dessen
Abhängigkeiten. 3.4.17 ist das Ende der 3.4-Linie, 8.83 das Ende der
Laravel-8-Linie; die Hinweise lassen sich innerhalb dieser Vorgaben nicht
schließen, sondern nur durch einen Versionssprung. Wer sie dieser Erweiterung
zuschreibt, sucht an der falschen Stelle: ihre eigenen Laufzeit-Abhängigkeiten
sind `bacon/bacon-qr-code` und `statamic/cms`, sonst nichts.

### Am Paket selbst arbeiten

Wer gleichzeitig an diesem Paket und an der einbindenden Seite arbeitet, legt
ein Path-Repository auf das Verzeichnis mit diesem Repo:

```json
{
    "repositories": [
        { "type": "path", "url": "../qr-gen", "options": { "symlink": true } }
    ]
}
```

**Läuft die Seite in einem Container, muss das Verzeichnis dort auch
existieren.** Ein Path-Repository zeigt auf einen Pfad neben dem Projekt; im
Container gibt es den nicht, und Composer legt einen Symlink an, der ins Leere
zeigt. Unter DDEV genügt ein `docker-compose.*.yaml` im Ordner `.ddev`, das
`../../qr-gen` hineinhängt.

**Vor einem Deploy muss das wieder weg.** Ein Path-Repository funktioniert auf
keinem anderen Rechner und auf keinem Server.

`extra.laravel.providers` zeigt auf `Redcodede\QrGen\Statamic\ServiceProvider`,
und die `autoload.psr-4` führt neben `Redcodede\QrGen\` einen zweiten,
spezifischeren Eintrag für `Redcodede\QrGen\Statamic\`. Der sieht überflüssig
aus und ist es nicht: Statamics `Manifest::formatPackage()` leitet das
Verzeichnis der Erweiterung aus `autoload.psr-4[Namensraum des Providers]` ab.
Ohne den Eintrag gibt es diesen Schlüssel nicht, und die Erweiterung fände ihre
eigene Konfiguration, ihre Views und ihre Fieldsets nicht.

## Was dieses Paket nicht entscheidet

Drei Dinge liegen außerhalb und lassen sich hier nicht beantworten. Sie stehen
trotzdem hier, damit niemand sie ratend beantwortet.

**Ob der gedruckte Code gelesen wird, entscheidet ein Andruck.** In
Originalgröße, auf dem echten Material. Die Werte in `Qr\Preset` sind auf
Druck ausgelegt und begründet, aber eine Begründung ist kein Andruck. Solange
keiner vorliegt, ist die Variante mit Bildmarke technisch fertig und nicht
freigegeben — das ist ein Unterschied.

**Welche Zeichnung als Bildmarke taugt, ist eine Gestaltungsfrage.** Eine Marke
mit Wortzusatz sieht am Bildschirm gut aus und verschwindet im Druck: bei 20 mm
Codebreite steht ein Logokasten von 9 Modulen rund 5 mm hoch, und was darin an
Schrift steckt, misst Bruchteile eines Millimeters. Das Paket nimmt jede
Zeichnung an, die der Sanitizer versteht, und urteilt nicht über sie. Eine
eigene Fassung für kleine Größen ist fast immer die bessere Zulieferung.

**Welche Adresse auf der Verpackung steht, entscheidet die einbindende Seite.**
Mit oder ohne `www`, mit oder ohne Sprachpräfix: verarbeitet wird, was
eingegeben wird, ohne Ergänzen und ohne Umschreiben. Auf Papier lässt sich das
nicht mehr ändern, deshalb gehört die Entscheidung vor den Andruck und nicht
danach.

### Eine Auflage, die aus der Bauweise folgt

Wird der Code ohne zusätzliches Token unter einer erratbaren Adresse angeboten
— und das ist der übliche Fall, weil die Adresse auf der Verpackung steht —,
**darf auf dieser Seite nie etwas Nichtöffentliches erscheinen.** Kein
Kundenname, keine Ansprechpartner, keine internen Notizen. Wer das braucht,
braucht ein Token davor, und dann ist die Adresse nicht mehr erratbar.

Der Tag gibt von sich aus nur aus, was er bekommt: eine Adresse und eine
Bildmarke. Was die Seite drumherum stellt, ist ihre Verantwortung.

## Wiederverwendbarkeit

Nichts Projektspezifisches gehört in dieses Paket. Collection- und Feld-Handles,
URL-Präfixe, der Logo-Pfad (das Logo wird nicht mitgeliefert) und das Verhalten
bei unbekanntem oder deaktiviertem Code kommen aus der Config. Übersetzungen
als Language-Files, DE und EN.

## Lizenz

[AGPL-3.0-or-later](LICENSE).

**Die mitgelieferte Schrift steht unter einer eigenen Lizenz.** PT Sans Regular
in `resources/fonts/pt-sans-v18-latin/` ist Copyright 2010 ParaType Ltd. und
steht unter der [SIL Open Font License 1.1](resources/fonts/pt-sans-v18-latin/OFL.txt),
mit den geschützten Namen „PT Sans" und „ParaType". Die OFL verlangt, dass ihr
Wortlaut mitverteilt wird; deshalb liegt er im selben Ordner. Wer die Schrift
gegen eine andere tauscht, tauscht auch diese Datei.

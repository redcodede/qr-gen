# qr-gen

> Erzeugt aus einer URL einen QR-Code als SVG und als druckfertiges PNG. Zwei
> Laufzeit-Abhängigkeiten, keine Bildextension, kein Framework im Kern.

**Status: URL rein, zwei Codes raus** — einer ohne, einer mit Bildmarke in der
Mitte, **beide als SVG und als druckfertiges PNG**. Mit Demo-Seite und
Downloads. Noch **nicht** dabei: die Statamic-Anbindung. Was hier unter
„geplant" steht, existiert nicht.

Geprüft am 14.09.2026 auf PHP 8.4: **335 Tests, 24788 Assertions, grün.**

---

## Warum es das gibt

Ab dem **12.02.2027** greifen für die GVÖ Kennzeichnungspflichten aus
**VerpackDG** und **PPWR** (Verordnung (EU) 2025/40). Hersteller brauchen einen
gedruckten QR-Code, der auf eine Rücknahme-Seite der GVÖ zeigt.

Zugehörige Aufgabe:
[`#10249150900` QR-Code-Generator programmieren](https://3.basecamp.com/3143253/buckets/48323376/todos/10249150900),
fällig **22.09.2026**. Fachlicher Rahmen, Aufwände und offene Entscheidungen
liegen im Kunden-Vault `redvault-gvoe` unter `02 Projekte/`.

**Ein gedruckter QR-Code muss dauerhaft nur eines leisten: seine URL muss
gültig bleiben.** Was hinter der URL steht, darf sich ändern. Daraus folgt der
Zuschnitt in zwei Stufen.

## Was drin ist

### Fertig

- [x] URL → QR-Code-Matrix, Fehlerkorrekturstufe wählbar
- [x] Matrix → SVG, ein einziger `<path>`, verlustfrei skalierbar
- [x] **Logo in der Mitte**, mit Ruhezone darum, jedes Seitenverhältnis
- [x] **Sanitizer für fremde SVGs**, Whitelist statt Filter
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
- [x] Test, der die Framework-Freiheit des Kerns erzwingt
- [x] Rundlauf-Test, der das SVG zurück in eine Matrix liest

### Offen

- [ ] **Logging in der Statamic-Hülle.** Die Ausnahmen dieses Pakets müssen dort
      gefangen und mit Kontext ins Log geschrieben werden, statt als 500
      durchzuschlagen. Siehe [Logging](#logging)
- [ ] Code- und Token-Erzeugung, `CodeRepository`-Interface
- [ ] Statamic-Hülle: ServiceProvider, Artisan-Command, Auflösungs-Route,
      Download-Seite
- [ ] Bildmarke als **Raster** annehmen, falls sie nur als PNG geliefert wird.
      Braucht einen PNG-**Dekoder**; die Vektor-Bildmarke ist erledigt
- [ ] Elliptische Bögen (`A`) und Konturen im Rasterisierer. Bisher nicht
      gebraucht: keine der vorliegenden Zeichnungen benutzt beides

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
| `gvoe.de/return/7K4M2` (28 B) | H | Version 4, 33 × 33 | 11,1 % | **26 %** |
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
Farben ergeben — die GVÖ-Marke landet bei 34 von 256 möglichen. Ein Byte je
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

Alle Oberflächentexte liegen in `resources/lang/`. **Deutsch ist die
Standardsprache**, Englisch existiert als Katalog, wird aber von nichts
angeboten — es ist eine Frage danach, keine Datei, die noch zu schreiben wäre.

```php
use Redcodede\QrGen\I18n\Translator;

$texts = Translator::forLocale();               // de
$texts = Translator::forLocale('en');           // auf Abruf
$texts = Translator::forLocaleOrDefault($any);  // fällt zurück statt zu werfen

$texts->get('facts.payload.value', ['bytes' => 28]);   // "28 Bytes"
```

Das Format ist **Laravels**: ein `return`-Array mit punktgetrennten Schlüsseln
und `:name` als Platzhalter. Damit lädt Laravels eigener Übersetzer dieselben
Dateien später in der Statamic-Hülle, ohne dass sie angefasst werden — eine
Textsammlung, nicht zwei.

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

**Das ist alles.** Kein `ext-gd`, kein `ext-imagick`, kein `ext-dom`, kein
`ext-simplexml`, kein `ext-mbstring`, kein Laravel, kein Statamic.

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

| Paket | Job |
|---|---|
| `phpunit/phpunit` | `^9.6`, Testlauf |

`statamic/cms` steht **absichtlich in keiner der beiden Listen.** Noch fasst
keine Zeile Statamic an, und ein `require` darauf zöge rund 140 Pakete nach,
von denen nichts benutzt wird. Der Eintrag kommt mit dem ServiceProvider.
`statamic/cms` 3.4.17 installiert nachweislich auf PHP 8.4 (geprüft
10.09.2026).

### Warum keine Bildextension

Ein SVG ist Zeichenkettenbau. In der PHP-CLI der WSL fehlen `gd`, `imagick`,
`dom`, `simplexml` und `mbstring` — für den Renderer ist das gleichgültig.

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
Datenbank, **PHP 8.4**, Docroot `demo/`. Passend zum DDEV der GVÖ-Seite, die
ebenfalls auf 8.4 steht.

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
    ->withTitle('Rücknahme GVÖ') // barrierefreier Name, wird escaped
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
$result = $fit->lowestLevelFor('https://gvoe.de/return/7K4M2', LogoBox::square(11, 1));

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

Für `https://gvoe.de/return/7K4M2` (28 Bytes), Funktionsmuster exakt geprüft:

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
| **wird aufgelöst** | ein `<style>`-Block mit einfachen Klassenselektoren wird in Präsentationsattribute inlined, das leere `<defs>` danach entfernt. Das ist Illustrators Standardexport |
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

**Noch nicht gebaut.** Der Kern protokolliert nichts und wird es nicht tun — er
hat kein I/O, und das ist Absicht: eine Bibliothek, die selbst ins Log schreibt,
schreibt in ein Log, das sie nicht kennt. Er wirft und der Aufrufer entscheidet.

Was fehlt, ist die andere Hälfte davon: **die Statamic-Hülle muss die Ausnahmen
fangen und mit Kontext protokollieren**, statt sie als 500 durchschlagen zu
lassen. Anforderungen, wenn das gebaut wird:

- Logger per Konstruktor injiziert (PSR-3), nicht per Facade geholt — sonst
  wandert der Framework-Bezug in Code, der ihn nicht haben darf
- **Stufen unterscheiden:** ein zu großer Logokasten ist eine
  Konfigurationssache und gehört auf `warning`, ein unerwarteter Fehlschlag auf
  `error`. Beides heute nicht unterscheidbar, weil beides dieselbe Ausnahme ist
- **Kontext statt Prosa:** Symbolgröße, Version, Fehlerkorrekturstufe,
  Kastenmaße, betroffener Eintrag. Das ist, was eine Meldung nachvollziehbar
  macht
- **Die Nutzlast nicht mitloggen.** Aus demselben Grund, aus dem
  `EncodingFailed` nur die Länge nennt: ein Paket, das zusagt, verarbeitete URLs
  nicht zu speichern, kann sie nicht in ein Log schreiben
- Ein Fehlschlag beim Erzeugen eines Codes darf einen Stapelverarbeitungslauf
  nicht abbrechen — protokollieren, weitermachen, am Ende zusammenfassen

### Beim Suchen

`ModuleMatrix::toAsciiArt()` zeichnet die Matrix als Text. In einer
fehlgeschlagenen Assertion ist das lesbarer als 625 Booleans.

```php
echo $matrix->toAsciiArt();
```

## Demo-Seite

`demo/` ist der Docroot des DDEV-Containers und läuft auf denselben Klassen,
die später die Statamic-Hülle aufruft. Wenn es dort geht, geht es dort auch —
und wenn es aufhört zu gehen, liegt es am Paket und nicht am Klebstoff.

**Zwei Eingaben: die URL und welche Bildmarke.** Alles andere kommt aus
`Qr\Preset`, weil alles andere entschieden ist. Die Statamic-Hülle wird
dieselben zwei Eingaben haben — eine Ziel-URL und ein Asset-Pfad — also übt die
Demo die Form, die das Plugin bekommt, und nicht eine größere.

| Datei | |
|---|---|
| `demo/index.php` | Formular, **beide Varianten nebeneinander**, Kennzahlen, Download-Knöpfe. Kein Text im Code, alles aus dem Katalog |
| `demo/image.php` | liefert ein Bild allein; `?format=png`, `?variant=logo`, `?download=1` |
| `demo/bootstrap.php` | Autoload, Eingabeprüfung, Objektaufbau |
| `demo/logos/*.svg` | Testlogos. Jede Datei hier wird von `RealWorldLogoTest` durch die ganze Kette geschickt |

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
    Encoder/
      BaconQrEncoder.php   Adapter auf bacon/bacon-qr-code
    Logo/
      SvgLogo.php          Sanitizer: fremdes SVG → einbettbares Markup
      LogoBox.php          gewünschter Kasten in Modulen, prüft die Platzierung
      LogoPlacement.php    wo der Kasten dann liegt
    Render/
      SvgRenderer.php      Matrix → SVG, räumt den Logokasten frei
      SvgOptions.php       unveränderliche Darstellungseinstellungen
      PngRenderer.php      Matrix → PNG, von Hand, ohne Bildextension
      PngOptions.php       Auflösung und Druckgröße statt Pixelmaße
    Raster/                zeichnet die Bildmarke ins PNG
      Transform.php        affine Matrix, transform-Listen
      PathFlattener.php    d-Attribut → Streckenzüge in Gerätepixeln
      ShapeFlattener.php   rect, circle, ellipse, polygon → Pfadgrammatik
      ScanlineFiller.php   Scanline-Füllung, überabgetastet
      LogoRaster.php       Markup durchlaufen, Farbe vererben, komponieren
      Palette.php          Farben → Palettenindizes
    Exception/             QrGenException, InvalidArgument, EncodingFailed, LogoRejected
    ErrorCorrection.php    die vier Stufen der Norm
    ModuleMatrix.php       die Grenze zwischen Kodieren und Zeichnen
    Preset.php             die festgelegten Werte des Projekts
  I18n/
    Translator.php         Oberflächentexte, außerhalb des Kerns
  Statamic/                (leer) dünne Hülle: Provider, Tag, Command, Controller
resources/lang/            de.php und en.php, im Laravel-Format, werden mitgeliefert
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
| `0.8.0` | geplant: Code- und Token-Erzeugung |
| `0.9.0` | geplant: Statamic-Hülle, in der GVÖ-Seite lauffähig |
| `1.0.0` | in Produktion abgenommen, öffentliche API stabil |

Commits folgen [Conventional Commits](https://www.conventionalcommits.org/de/v1.0.0/):
`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `build:`. Ein `!`
oder eine `BREAKING CHANGE:`-Fußzeile markiert einen Bruch. Jede Version
bekommt einen Tag `vX.Y.Z` und ein GitHub-Release, die Änderungen stehen in
[CHANGELOG.md](CHANGELOG.md).

## Einbauen

Das Paket liegt nicht auf Packagist. Im Zielprojekt braucht `composer.json`
einen `repositories`-Eintrag auf dieses Repo:

```json
{
    "repositories": [
        { "type": "vcs", "url": "git@github.com:redcodede/qr-gen.git" }
    ]
}
```

```bash
composer require redcodede/qr-gen
```

Für die lokale Entwicklung stattdessen ein Path-Repository auf `addons/*`. Im
GVÖ-Projekt fehlt der Eintrag noch (geprüft 10.09.2026).

`extra.laravel.providers` ist in der `composer.json` noch nicht gesetzt. Der
Eintrag kommt zusammen mit dem ServiceProvider — vorher zeigte er auf eine
nicht existierende Klasse und die Installation bräche ab.

## Offene Punkte

Diese Fragen sind nicht offen, weil sie keiner gestellt hat, sondern weil sie
außerhalb dieses Repos entschieden werden. Sie stehen hier, damit niemand sie
ratend beantwortet.

1. **Welche PHP-Version bedient die Produktion der GVÖ-Seite?** Lokal steht
   DDEV auf 8.4, die `composer.json` der Seite erlaubt noch 7.4. Dieses Paket
   verlangt `^8.0`. Ab 8.1 zieht Composer Bacon 3.x und die
   PHP-8.4-Deprecations verschwinden
2. **Ist „Hersteller" die bestehende Collection `partner` oder eine neue?**
   `partner` hat 262 DE-Einträge, aber nur `title`, `ort`, `slug`. Hersteller
   im Sinne des VerpackDG sind eine andere Rolle als Lizenzpartner
3. **Druckgröße und Material.** Ohne das kann der Andruck nicht anlaufen, und
   ohne Andruck wird ein Logo im Code nicht zugesagt. Das ist jetzt der einzige
   Punkt, der die Logo-Variante noch aufhält — technisch läuft sie
4. **Welche Logo-Zeichnung?** Die GVÖ-Seite trägt zwei verschiedene: ein SVG
   mit 1,20 : 1 in zwei Farben und ein PNG mit 1,65 : 1, einfarbig, mit der
   Wortmarke. Bei 20 mm Codebreite stehen die Buchstaben rund 2,3 mm hoch und
   die Umlautpunkte messen etwa 0,36 mm, also weniger als ein Codemodul.
   Druckbar, aber eine Gestaltungsfrage — eine Fassung für kleine Größen wäre
   besser
5. **Sprachlogik der Auflösungs-Route.** Weiterleitung auf `/en/…` oder eine
   URL für beide Sprachen? Betrifft Caching und Suchmaschinen

Erledigt: **„Logo als RGB-SVG fehlt" war ein Missverständnis.** SVG kennt kein
CMYK; `gvoe-logo-cmyk.svg` trägt bereits Hex-Farben (`#009879`, `#9D9D9C`) und
ist bis auf den `<style>`-Block ideale Eingabe — und den löst der Sanitizer auf.

Aus dem Briefing bereits festgelegt: fünfstelliger Code aus
`ABCDEFGHJKMNPQRSTUVWXYZ23456789` (ohne `0 O 1 I L`), Ziel-URL
`https://gvoe.de/return/{code}`, Download unter `/qr/{code}` ohne
zusätzlichen Token, Erzeugung on-the-fly ohne Dateibestand.

Daraus folgt eine dauerhafte Auflage: **`/qr/{code}` ist erratbar**, weil der
Code auf der Verpackung steht. Dort darf nie etwas Nichtöffentliches
erscheinen — kein Herstellername, keine Ansprechpartner, keine internen
Notizen. Sobald das gewünscht wird, kommt der Token zurück.

## Wiederverwendbarkeit

Nichts GVÖ-Spezifisches gehört in dieses Paket. Collection- und Feld-Handles,
URL-Präfixe, der Logo-Pfad (das Logo wird nicht mitgeliefert) und das Verhalten
bei unbekanntem oder deaktiviertem Code kommen aus der Config. Übersetzungen
als Language-Files, DE und EN.

## Lizenz

[AGPL-3.0-or-later](LICENSE).

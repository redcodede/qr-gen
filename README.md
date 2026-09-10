# qr-gen

> Erzeugt aus einer URL einen QR-Code als SVG. Zwei Laufzeit-Abhängigkeiten,
> keine Bildextension, kein Framework im Kern.

**Status: Stufe 1 läuft.** URL rein, SVG raus, mit Demo-Seite und Download.
Noch **nicht** dabei: Logo-Einbettung, PNG und die Statamic-Anbindung. Was hier
unter „geplant" steht, existiert nicht.

Geprüft am 10.09.2026 auf PHP 8.4: **77 Tests, 4636 Assertions, grün.**

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

### Stufe 1 — fertig

- [x] URL → QR-Code-Matrix, Fehlerkorrekturstufe wählbar
- [x] Matrix → SVG, ein einziger `<path>`, verlustfrei skalierbar
- [x] Demo-Seite mit Vorschau, Kennzahlen und Download
- [x] Test, der die Framework-Freiheit des Kerns erzwingt
- [x] Rundlauf-Test, der das SVG zurück in eine Matrix liest

### Stufe 1 — offen

- [ ] Logo-Einbettung ins SVG (braucht Druckgröße und ein RGB-Logo)
- [ ] Code- und Token-Erzeugung, `CodeRepository`-Interface
- [ ] Statamic-Hülle: ServiceProvider, Artisan-Command, Auflösungs-Route,
      Download-Seite
- [ ] PNG, ohne `gd` (siehe [Warum keine Bildextension](#warum-keine-bildextension))

### Stufe 2 — später, im Website-Relaunch

Control-Panel-Actions und Fieldtype, herstellerspezifische Inhalte, Tracking.
Auf dem dann aktuellen Statamic-Stand, nicht auf Statamic 3.

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

Auch **PNG braucht später kein `gd`**: ein zweifarbiges PNG ist `IHDR`, `IDAT`
und `IEND` mit CRC32, `zlib` ist vorhanden und `crc32()` ist Sprachkern. Rund
80 Zeilen.

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

| Datei | |
|---|---|
| `demo/index.php` | Formular, Vorschau, Kennzahlen, Download-Knopf |
| `demo/svg.php` | liefert das SVG allein, mit `?download=1` als Datei |
| `demo/bootstrap.php` | Autoload, Eingabeprüfung, Objektaufbau |

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
    Encoder/
      BaconQrEncoder.php   Adapter auf bacon/bacon-qr-code
    Render/
      SvgRenderer.php      Matrix → SVG
      SvgOptions.php       unveränderliche Darstellungseinstellungen
    Exception/             QrGenException, InvalidArgument, EncodingFailed
    ErrorCorrection.php    die vier Stufen der Norm
    ModuleMatrix.php       die Grenze zwischen Kodieren und Zeichnen
  Statamic/                (leer) dünne Hülle: Provider, Tag, Command, Controller
demo/                      Demo-Seite, nicht im Dist
tests/Qr/                  Tests des Kerns
```

`ModuleMatrix` ist die ganze Grenze zwischen Kodieren und Zeichnen. Alles, was
ein Encoder weiß, endet dort, und alles, was ein Renderer braucht, beginnt
dort. Deshalb ist ein Encoder-Wechsel eine Klasse und kein Umbau. Die Matrix
trägt **keine Ruhezone** — die gehört zum Zeichnen, weil ihre Größe davon
abhängt, wie der Code platziert wird, nicht davon, wie er kodiert wurde.

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
| `0.2.0` | Stufe 1: URL → SVG, Demo-Seite, Tests |
| `0.3.0` | geplant: Logo-Einbettung |
| `0.4.0` | geplant: Statamic-Hülle, in der GVÖ-Seite lauffähig |
| `1.0.0` | Stufe 1 in Produktion abgenommen, öffentliche API stabil |

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
   ohne Andruck wird ein Logo im Code nicht zugesagt
4. **Logo als RGB-SVG**, mit `viewBox`, ohne `<style>`-Block. Vorhanden ist nur
   CMYK
5. **Sprachlogik der Auflösungs-Route.** Weiterleitung auf `/en/…` oder eine
   URL für beide Sprachen? Betrifft Caching und Suchmaschinen

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

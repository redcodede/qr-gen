# Changelog

Alle nennenswerten Änderungen an diesem Projekt stehen hier. Format nach
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach
[SemVer](https://semver.org/lang/de/).

## [Unreleased]

### Geplant

- Logo-Einbettung ins SVG, string-basiert mit `id`-Präfixen, abschaltbar
- Code- und Token-Erzeugung, `CodeRepository`-Interface
- Statamic-Hülle: ServiceProvider, Artisan-Command, Auflösungs-Route,
  Download-Seite, Flat-File-Repository
- `statamic/cms` und `extra.laravel.providers` in der `composer.json`, sobald
  der ServiceProvider existiert
- PNG-Renderer ohne `gd`, über `zlib` und `crc32()`

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

[Unreleased]: https://github.com/redcodede/qr-gen/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/redcodede/qr-gen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/redcodede/qr-gen/releases/tag/v0.1.0

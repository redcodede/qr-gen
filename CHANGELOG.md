# Changelog

Alle nennenswerten Änderungen an diesem Projekt stehen hier. Format nach
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach
[SemVer](https://semver.org/lang/de/).

## [Unreleased]

### Geplant

- Eigener QR-Encoder (ISO/IEC 18004): Segment-Kodierung, Reed-Solomon,
  Maskenwahl, Versions- und Kapazitätstabellen
- SVG-Renderer
- Logo-Einbettung ins SVG, abschaltbar
- Code- und Token-Erzeugung
- Statamic-Hülle: ServiceProvider, Artisan-Command, Auflösungs-Route,
  Download-Seite, Flat-File-Repository
- `extra.laravel.providers` in der `composer.json`, sobald der ServiceProvider
  existiert

## [0.1.0] - 2026-09-10

### Hinzugefügt

- Projektgerüst: `composer.json`, README, Changelog, AGPL-3.0-Lizenz
- DDEV-Konfiguration auf PHP 8.4 zum Testen, passend zum DDEV der GVÖ-Seite
- PHPUnit-Konfiguration
- Festlegung: keine Laufzeit-Abhängigkeiten. `bacon/bacon-qr-code` nur als
  Prüfinstanz in `require-dev`
- Festlegung: Fachlogik in `src/Qr/` ohne Laravel- und Statamic-Bezug,
  Statamic-Anbindung in `src/Statamic/`

[Unreleased]: https://github.com/redcodede/qr-gen/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/redcodede/qr-gen/releases/tag/v0.1.0

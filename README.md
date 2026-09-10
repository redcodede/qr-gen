# qr-gen

> Statamic-Addon, das aus einer URL einen Satz QR-Codes erzeugt. Ausgabe als SVG,
> ohne Laufzeit-Abhängigkeiten.

**Status: Gerüst. Es ist noch kein Code implementiert.** Dieses README beschreibt,
was gebaut wird und in welcher Reihenfolge. Was hier unter „geplant" steht, existiert
noch nicht.

---

## Warum es das gibt

Ab dem **12.02.2027** greifen für die GVÖ Kennzeichnungspflichten aus **VerpackDG**
und **PPWR** (Verordnung (EU) 2025/40). Hersteller brauchen einen gedruckten QR-Code,
der auf eine Rücknahme-Seite der GVÖ zeigt. Das Addon erzeugt diese Codes.

Zugehörige Aufgabe:
[`#10249150900` QR-Code-Generator programmieren](https://3.basecamp.com/3143253/buckets/48323376/todos/10249150900),
fällig **22.09.2026**.

Der fachliche Rahmen, die Aufwandsschätzung und die offenen Entscheidungen liegen im
Kunden-Vault `redvault-gvoe` unter `02 Projekte/gvoe QR-Code-Generator (Aufwandsschätzung).md`.

**Ein gedruckter QR-Code muss dauerhaft nur eines leisten: seine URL muss gültig
bleiben.** Was hinter der URL steht, darf sich ändern. Daraus folgt der Zuschnitt in
zwei Stufen.

## Funktionsumfang

### Stufe 1 (dieses Repo, Ziel bis 22.09.2026)

- [ ] URL → QR-Code-Matrix (eigener Encoder, ISO/IEC 18004)
- [ ] Matrix → SVG
- [ ] Logo-Einbettung ins SVG, abschaltbar
- [ ] Codeerzeugung (Alphabet und Länge als Parameter) und Token-Erzeugung
- [ ] Artisan-Command, das die SVG-Varianten in einen Ordner schreibt
- [ ] Route zur Auflösung eines Codes
- [ ] Schlichte, nicht erratbare Download-Seite

Absichtlich **nicht** in Stufe 1: eigener Fieldtype, Control-Panel-Komfort,
Selbstbedienung für Hersteller.

### Stufe 2 (später, im Website-Relaunch)

Control-Panel-Actions und Fieldtype, herstellerspezifische Inhalte, Tracking.
Kommt auf dem dann aktuellen Statamic-Stand, nicht auf Statamic 3.

## Zielumgebung

Maßgeblich ist die aktuelle GVÖ-Seite. Sie ist die untere Grenze der
Kompatibilität. Stand geprüft am 10.09.2026:

| Was | Stand |
|---|---|
| Statamic | 3.4 (`3.4.*`) |
| Laravel | `^8.83` |
| PHP laut `composer.json` der Seite | `^7.4 \|\| ^8.0` |
| PHP im lokalen DDEV der Seite | **8.4** |
| PHP der WSL-CLI | 8.1.2 |
| Content | Flat File, kein `eloquent-driver`, kein Runway |
| Mehrsprachigkeit | vorhanden: `default` (de_DE, `/`) und `en` (en_US, `/en/`) |

Dieses Paket verlangt `php: ^8.0`. Das deckt das DDEV der Seite ab und schließt
PHP 7.4 aus, das seit 2022 ohne Sicherheitspflege ist. Sollte die Produktion
tatsächlich 7.4 ausliefern, ist das vor dem ersten Code zu klären — es betrifft den
Sprachstand des ganzen Kerns. Siehe [Offene Punkte](#offene-punkte).

## Grundsatz: keine Laufzeit-Abhängigkeiten

`require` enthält nur `php` und `statamic/cms`. Kein QR-Paket, keine Bildbibliothek,
keine PHP-Extension über den Standard hinaus.

Das ist keine Ideologie, sondern folgt aus der Umgebung. In der PHP-CLI der WSL
fehlen `gd`, `imagick`, `dom`, `simplexml` und `mbstring`. Ein SVG ist
Zeichenkettenbau und braucht nichts davon. Fremdpakete brächten genau diese
Extension-Anforderungen zurück und verstecken die Modul-Matrix hinter einer
Renderer-Schicht, die für die Logo-Einbettung im Weg steht.

Als **Entwicklungs**-Abhängigkeit ist `bacon/bacon-qr-code` vorgesehen, allein als
Prüfinstanz in den Tests: dieselbe Eingabe durch beide Encoder, Matrizen vergleichen.
Sie landet nie in einer Installation.

## Aufbau (geplant)

```
src/
  Qr/          Fachlogik. Kein Laravel, kein Statamic, keine Datei-I/O.
               Gibt Zeichenketten und Matrizen zurück.
  Statamic/    Dünne Hülle: ServiceProvider, Tag, Command, Controller, Repository.
```

Die Trennung ist der einzige Architekturpunkt, der später Geld spart. Statamic 3 und
Statamic 6 lassen sich nicht von einem Paketstand aus bedienen — Statamic 3.4 will
Laravel 8/9 und Vue 2, Statamic 6 will PHP 8.2+, Laravel 11/12 und Vue 3. Wenn `Qr/`
frei von Framework-Bezügen bleibt, ist die zweite Umsetzung Hüllenarbeit statt
Wiederholung, und der Kern lässt sich bei Bedarf als eigenes Paket herauslösen, ohne
ihn anzufassen.

Deshalb gilt für `src/Qr/`: **kein `use Statamic\...`, kein `use Illuminate\...`.**
Das wird per Test geprüft, nicht per Zuruf.

## Installation

Noch nichts zu installieren. Sobald es einen Codestand gibt:

```bash
composer require redcodede/qr-gen
```

Das Paket liegt nicht auf Packagist. Im Zielprojekt braucht `composer.json` einen
`repositories`-Eintrag auf dieses Repo; für die lokale Entwicklung ein
Path-Repository auf `addons/*`. Im GVÖ-Projekt fehlt dieser Eintrag noch
(geprüft 10.09.2026).

`extra.laravel.providers` ist in der `composer.json` noch nicht gesetzt. Der Eintrag
kommt zusammen mit dem ServiceProvider — vorher würde Laravels Package Discovery
auf eine nicht existierende Klasse zeigen und die Installation abbrechen.

## Entwicklung

```bash
ddev start
ddev composer install
ddev composer test
```

Die mitgelieferte DDEV-Konfiguration ist ein reiner PHP-Container ohne Webserver-Rolle,
auf **PHP 8.4**, passend zum DDEV der GVÖ-Seite. Die PHP-CLI der WSL taugt nicht zum
Testen: dort fehlen `dom`, `mbstring` und `xml`, die PHPUnit selbst braucht.

## Versionierung

**SemVer.** Bis `1.0.0` gilt der Stand als in Entwicklung, Breaking Changes sind bis
dahin in Minor-Schritten erlaubt.

- `0.1.0` Projektgerüst
- `0.2.0` Stufe 1 vollständig, in der GVÖ-Seite lauffähig
- `1.0.0` Stufe 1 in Produktion abgenommen, öffentliche API stabil

Commits folgen [Conventional Commits](https://www.conventionalcommits.org/de/v1.0.0/):
`feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `build:`. Ein `!` oder ein
`BREAKING CHANGE:`-Fußzeile markiert einen Bruch. Jede Version bekommt einen Tag
`vX.Y.Z` und ein GitHub-Release; die Änderungen stehen in
[CHANGELOG.md](CHANGELOG.md).

## Offene Punkte

Diese Fragen sind nicht offen, weil sie noch keiner gestellt hat, sondern weil sie
außerhalb dieses Repos entschieden werden. Sie stehen hier, damit niemand sie
ratend beantwortet.

1. **Welche PHP-Version bedient die Produktion der GVÖ-Seite?** Lokal steht DDEV auf
   8.4, die `composer.json` erlaubt noch 7.4. Entscheidet den Sprachstand von `src/Qr/`.
2. **Ist „Hersteller" die bestehende Collection `partner` oder eine neue?** `partner`
   hat 262 DE-Einträge, aber nur `title`, `ort`, `slug`. Hersteller im Sinne des
   VerpackDG sind eine andere Rolle als Lizenzpartner. Wer liefert die belastbare Liste?
3. **Druckprüfung.** Wer druckt, in welcher Größe, auf welchem Material? Ein Logo im
   QR-Code wird erst nach einem Andruck in Originalgröße zugesagt, geprüft mit
   mehreren Telefonen — nicht nach einem Blick in den Browser.
4. **Sprachlogik der Auflösungs-Route.** Weiterleitung auf `/en/...` oder eine URL,
   die beide Sprachen ausliefert? Betrifft Caching und Suchmaschinen.
5. **Logo als RGB/Web-SVG.** Vorhanden ist nur CMYK
   (`public/assets/unternehmen/gvoe-logo-cmyk.svg` in der GVÖ-Seite).

Die Pakete „Datenmodell", „Auflösungs-Route" und „Control-Panel" hängen an den
Konzeptaufgaben derselben Basecamp-Liste. Der Encoder und der SVG-Renderer hängen an
keiner davon — deshalb fangen sie an.

## Wiederverwendbarkeit

Nichts GVÖ-Spezifisches gehört in dieses Paket. Collection- und Feld-Handles,
URL-Präfixe, der Logo-Pfad (das Logo wird nicht mitgeliefert) und das Verhalten bei
unbekanntem oder deaktiviertem Code kommen aus der Config. Übersetzungen als
Language-Files, DE und EN.

## Lizenz

[AGPL-3.0-or-later](LICENSE), wie die übrigen Redcode-Addons.

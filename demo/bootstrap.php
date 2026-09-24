<?php

/**
 * Shared setup for the two demo entry points.
 *
 * The demo exists to see rendered symbols in a browser and to download them. It
 * writes nothing: every request encodes and renders from scratch, and the SVG
 * lives only in the response. There is no cache and no output directory, so
 * there is nothing to clean up and nothing to leak.
 *
 * Two things are input: the URL and which artwork to use. Everything else comes
 * from Qr\Preset, because everything else is decided. The eventual Statamic
 * shell has the same two inputs — a target URL and an asset path — so the demo
 * exercises the shape the plugin will have rather than a superset of it.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\I18n\Translator;
use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Contract\QrRenderer;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\LogoFit;
use Redcodede\QrGen\Qr\Logo\LogoFitResult;
use Redcodede\QrGen\Qr\Logo\PngLogo;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\LabelOptions;
use Redcodede\QrGen\Qr\Render\LabelPngRenderer;
use Redcodede\QrGen\Qr\Render\LabelSvgRenderer;
use Redcodede\QrGen\Qr\Render\PngRenderer;
use Redcodede\QrGen\Qr\Render\SvgRenderer;
use Redcodede\QrGen\Qr\Text\SvgFont;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Redcodede\QrGen\Qr\Settings\Variant;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dependencies are not installed. Run:\n\n    ddev composer install\n";
    exit(1);
}

require $autoload;

const DEFAULT_URL = 'https://www.redcode.de/';

/**
 * Preferred when present: a high-contrast reference mark. If it shows up, the
 * embedding works — and a pale logo next to it is pale, not broken.
 */
const DEFAULT_LOGO = 'contrast-check.svg';

const LOGO_DIR = __DIR__ . '/logos';

const MAX_URL_LENGTH = 2000;

/** Der Satz aus der gelieferten Vorlage, als Startwert der Demo. */
const LABEL_TEXT = 'Rückgabe über das GVÖ-SYSTEM';

/** GVÖ-Grün. In der Demo ein Startwert, im Plugin ein Feld. */
const LABEL_COLOR = '#009a7c';

/** Vereinbart am 23.09.2026. */
const LABEL_TEXT_LIMIT = 72;

/**
 * Die Namen der beiden Etikett-Varianten in der Adresszeile.
 *
 * In der Demo sind das Zeichenketten. Im Plugin werden daraus Konstanten in
 * `Variant`, und dann stehen sie zusätzlich in der YAML und im Blueprint.
 */
const LABEL_VARIANT_DARK = 'label';

const LABEL_VARIANT_COLOR = 'label-color';

/**
 * Liest die Adresszeile und löst daraus auf, was gilt.
 *
 * Die Demo bildet ab, was das Plugin hat: **globale Einstellungen**, die im
 * Control Panel gepflegt werden, und daneben **eine einzige Angabe je Stelle**,
 * die Ziel-URL aus dem Blueprint. Hier kommt beides aus der Adresszeile, weil
 * diese Seite nichts speichert; die Objekte dahinter sind dieselben, die die
 * Statamic-Hülle benutzt.
 *
 * Zurück kommen zusätzlich `url` und `logo` als **aufgelöste** Werte. Alles
 * Nachgelagerte auf der Seite rechnet damit weiter.
 *
 * @param array<string, mixed> $query
 *
 * @return array{
 *     url: string, logo: string, errors: list<string>,
 *     global: GlobalSettings, pageUrl: string|null, effective: EffectiveSettings
 * }
 */
function readInput(array $query, Translator $texts): array
{
    $errors = [];
    $logos = availableLogos();

    // Ein nicht angehaktes Kästchen schickt gar nichts. Ohne diese Marke liesse
    // sich "abgewählt" nicht von "Seite zum ersten Mal geöffnet" unterscheiden,
    // und nichts liesse sich je abschalten.
    $submitted = isset($query['configured']);

    // `url` und `logo` ohne Ebene sind die Kurzform, die `image.php` in seinem
    // Kopfkommentar anbietet und die die Knöpfe der Seite benutzen. Ohne diesen
    // Rückgriff fiel jeder Download auf DEFAULT_URL zurück und lieferte den
    // Code einer ganz anderen Adresse, ohne dass irgendwo etwas fehlschlug.
    $global = GlobalSettings::default()
        ->withDefaultUrl(field($query, ['g', 'url'], field($query, ['url'], $submitted ? null : DEFAULT_URL)))
        ->withDefaultLogo(field($query, ['g', 'logo'], field($query, ['logo'], $submitted ? null : defaultLogo($logos))));

    if ($submitted) {
        $global = $global
            ->withVariants(
                checked($query, ['g', 'variants', Variant::PLAIN]),
                checked($query, ['g', 'variants', Variant::LOGO])
            )
            ->withDownloads(
                checked($query, ['g', 'downloads', 'svg']),
                checked($query, ['g', 'downloads', 'png'])
            )
            ->withReturnInfo(checked($query, ['g', 'variants', Variant::RETURN_INFO]));
    }

    // Das Einzige, was je Stelle verschieden sein darf. Seit dem 23.09.2026
    // gibt es daneben keine zweite Einstellungsebene mehr.
    $pageUrl = field($query, ['p', 'url'], null);

    // Eine Bildmarke, die es nicht gibt, gilt als keine. Dann entfällt die
    // Variante mit Bildmarke von selbst, statt ein Panel zu versprechen, das
    // nur eine Fehlermeldung enthalten kann.
    $global = $global->withDefaultLogo(knownLogo($global->defaultLogo(), $logos));

    $effective = EffectiveSettings::from($global, $pageUrl);
    $url = (string) $effective->url();

    if ($url === '') {
        $errors[] = $texts->get('error.url.missing');
    } elseif (strlen($url) > MAX_URL_LENGTH) {
        $errors[] = $texts->get('error.url.tooLong', [
            'length' => strlen($url),
            'max' => MAX_URL_LENGTH,
        ]);
        $url = substr($url, 0, MAX_URL_LENGTH);
    } elseif (!isHttpUrl($url)) {
        $errors[] = $texts->get('error.url.notHttp');
    }

    return [
        'url' => $url,
        'logo' => (string) $effective->logo(),
        'errors' => $errors,
        'global' => $global,
        'pageUrl' => $pageUrl,
        'effective' => $effective,
    ];
}

/**
 * Ein Textfeld aus der verschachtelten Adresszeile, oder der Rückfallwert.
 *
 * @param array<string, mixed> $query
 * @param list<string>         $path
 */
function field(array $query, array $path, ?string $fallback): ?string
{
    $found = $query;

    foreach ($path as $segment) {
        if (!is_array($found) || !array_key_exists($segment, $found)) {
            return $fallback;
        }

        $found = $found[$segment];
    }

    return is_string($found) ? $found : $fallback;
}

/**
 * @param array<string, mixed> $query
 * @param list<string>         $path
 */
function checked(array $query, array $path): bool
{
    $found = $query;

    foreach ($path as $segment) {
        if (!is_array($found) || !array_key_exists($segment, $found)) {
            return false;
        }

        $found = $found[$segment];
    }

    return (bool) $found;
}

/**
 * @param list<string> $logos
 */
function defaultLogo(array $logos): ?string
{
    if (in_array(DEFAULT_LOGO, $logos, true)) {
        return DEFAULT_LOGO;
    }

    return $logos === [] ? null : $logos[0];
}

/**
 * @param list<string> $logos
 */
function knownLogo(?string $logo, array $logos): ?string
{
    if ($logo === null) {
        return null;
    }

    $name = basename($logo);

    return in_array($name, $logos, true) ? $name : null;
}

/**
 * German unless a locale is asked for. Nothing in the interface offers the
 * switch; `?lang=en` is the asking.
 *
 * @param array<string, mixed> $query
 */
function texts(array $query): Translator
{
    $locale = isset($query['lang']) && is_string($query['lang']) ? $query['lang'] : null;

    return Translator::forLocaleOrDefault($locale);
}

/**
 * The artwork sitting in demo/logos, by filename.
 *
 * Both kinds are offered. A vector original is the better delivery every time,
 * but a PNG is what often arrives, and the package takes it.
 *
 * @return list<string>
 */
function availableLogos(): array
{
    $found = array_merge(glob(LOGO_DIR . '/*.svg') ?: [], glob(LOGO_DIR . '/*.png') ?: []);
    sort($found);

    return array_values(array_map('basename', $found));
}

function isHttpUrl(string $candidate): bool
{
    if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));

    return $scheme === 'http' || $scheme === 'https';
}

function box(): LogoBox
{
    return Preset::logoBox();
}

/**
 * Resolves which error correction level survives the preset box, memoised so a
 * page that asks several times encodes once.
 *
 * @return array{0: LogoFitResult|null, 1: string|null} The fit, or why there is none
 */
function fit(string $url): array
{
    static $cache = [];

    if (array_key_exists($url, $cache)) {
        return $cache[$url];
    }

    try {
        $result = [(new LogoFit(new BaconQrEncoder()))->lowestLevelFor($url, box()), null];
    } catch (QrGenException $exception) {
        $result = [null, $exception->getMessage()];
    }

    return $cache[$url] = $result;
}

/**
 * Whatever LogoFit settled on; if nothing survives, H, so the page still shows
 * a plain symbol and the reason next to the empty artwork panel.
 */
function effectiveLevel(string $url): ErrorCorrection
{
    [$result] = fit($url);

    return $result === null ? ErrorCorrection::high() : $result->level();
}

function encode(string $url): ModuleMatrix
{
    return (new BaconQrEncoder())->encode($url, effectiveLevel($url));
}

/**
 * Reads the chosen artwork and hands the bytes to whichever class understands
 * them.
 *
 * Reading the file happens here, in the demo, not in the package: the core
 * takes bytes rather than a path, so the same artwork can come from a Statamic
 * asset, a fixture or a delivery folder without the package caring which. The
 * extension picks the class, and both classes refuse rather than repair, so a
 * file that is not what its name claims is rejected by the reader that opens
 * it.
 *
 * @throws \Redcodede\QrGen\Qr\Exception\QrGenException if the file cannot be used
 */
function logo(string $filename): ?Logo
{
    if ($filename === '') {
        return null;
    }

    $path = LOGO_DIR . '/' . $filename;

    if (!is_file($path)) {
        return null;
    }

    $bytes = (string) file_get_contents($path);

    return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'png'
        ? PngLogo::fromBinary($bytes)
        : SvgLogo::fromMarkup($bytes);
}

function svgRenderer(string $logoFile, bool $standalone, bool $withLogo): SvgRenderer
{
    $options = Preset::svgOptions()->withXmlDeclaration($standalone);

    if ($withLogo) {
        $artwork = logo($logoFile);

        if ($artwork !== null) {
            $options = $options->withLogo($artwork, box());
        }
    }

    return new SvgRenderer($options);
}

function pngRenderer(string $logoFile = '', bool $withLogo = false): PngRenderer
{
    $options = Preset::pngOptions();

    if ($withLogo) {
        $artwork = logo($logoFile);

        if ($artwork !== null) {
            $options = $options->withLogo($artwork, box());
        }
    }

    return new PngRenderer($options);
}

/**
 * The renderer for a requested format. Both formats now carry artwork, and both
 * place it with the same LogoBox, so the two files are the same picture.
 */
function rendererFor(string $format, string $logoFile, bool $standalone, bool $withLogo): QrRenderer
{
    return $format === 'png'
        ? pngRenderer($logoFile, $withLogo)
        : svgRenderer($logoFile, $standalone, $withLogo);
}

/**
 * Why this artwork cannot go into a PNG, or null if it can.
 *
 * The rasteriser draws a narrower subset than the SVG renderer will embed:
 * elliptical arcs, strokes and group opacity are refused by name rather than
 * approximated. Asking in advance means the page can leave the download out and
 * say why, instead of offering a button that fails.
 *
 * Memoised because the page asks twice — once to decide on the button, once for
 * the figures beside it — and the answer cannot change within a request.
 */
function pngRejection(string $logoFile, bool $withLogo): ?string
{
    static $cache = [];

    if (!$withLogo) {
        return null;
    }

    if (array_key_exists($logoFile, $cache)) {
        return $cache[$logoFile];
    }

    try {
        $artwork = logo($logoFile);
    } catch (QrGenException $exception) {
        // A file that cannot be read at all has no PNG either, and the reason
        // is the same one the panel will show for the SVG.
        return $cache[$logoFile] = $exception->getMessage();
    }

    return $cache[$logoFile] = $artwork === null ? null : LogoRaster::rejectionFor($artwork);
}

function pngAvailable(string $logoFile, bool $withLogo): bool
{
    return pngRejection($logoFile, $withLogo) === null;
}

/**
 * @return array{0: string|null, 1: string|null} The rendered bytes, or null and a message
 */
function tryRender(
    string $url,
    string $logoFile,
    bool $standalone,
    bool $withLogo,
    string $format = 'svg'
): array {
    try {
        return [rendererFor($format, $logoFile, $standalone, $withLogo)->render(encode($url)), null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

/**
 * Which format the preview should use: whichever is fewer bytes.
 *
 * Counter to the intuition that a vector file is always the lean one — a
 * two-colour PNG of a QR code is long runs of identical bytes, and deflate
 * eats those, so 1184 pixels square lands around a kilobyte while the SVG
 * spends three on path data.
 *
 * @return array{0: string, 1: int, 2: int} Format, PNG bytes, SVG bytes
 */
function cheaperFormat(string $url, string $logoFile, bool $withLogo): array
{
    [$svg] = tryRender($url, $logoFile, false, $withLogo);
    $svgBytes = strlen((string) $svg);

    if (!pngAvailable($logoFile, $withLogo)) {
        return ['svg', 0, $svgBytes];
    }

    [$png] = tryRender($url, $logoFile, false, $withLogo, 'png');
    $pngBytes = strlen((string) $png);

    return [$pngBytes > 0 && $pngBytes < $svgBytes ? 'png' : 'svg', $pngBytes, $svgBytes];
}

/**
 * Kürzt auf eine Zahl von Zeichen, nicht von Bytes.
 *
 * `substr` zählt Bytes. Bei „Rückgabe über" liegt die 72. Byte-Grenze mitten in
 * einem Umlaut, und heraus kommt keine gültige UTF-8-Zeichenkette mehr. Die
 * Grenze ist mit 72 Zeichen vereinbart, also wird in Zeichen gezählt.
 */
function clampCharacters(string $text, int $limit): string
{
    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    if ($characters === false) {
        return '';
    }

    return implode('', array_slice($characters, 0, $limit));
}

/**
 * The shipped font, read once per request.
 *
 * The core does not open files, so this is where the file is opened. Reading it
 * twice per page would parse 202 glyphs twice for nothing.
 */
function labelFont(): SvgFont
{
    static $font = null;

    if ($font === null) {
        $path = dirname(__DIR__) . '/resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';
        $font = SvgFont::fromMarkup((string) file_get_contents($path));
    }

    return $font;
}

/**
 * Die beiden Eingaben des Etiketts aus der Adresszeile.
 *
 * Steht hier und nicht in der Seite, weil die Seite und der Bild-Endpunkt
 * dieselben Werte lesen müssen. Läsen sie verschieden, zeigte die Vorschau ein
 * anderes Etikett als der Download daneben.
 *
 * @param array<string, mixed> $query
 *
 * @return array{text: string, color: string}
 */
function labelInput(array $query): array
{
    $color = field($query, ['label', 'color'], LABEL_COLOR);

    return [
        'text' => clampCharacters((string) field($query, ['label', 'text'], LABEL_TEXT), LABEL_TEXT_LIMIT),
        'color' => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : LABEL_COLOR,
    ];
}

/**
 * Welche Codefarbe eine der beiden Etikett-Varianten benutzt.
 */
function labelCodeColor(string $variant, string $chosen): string
{
    return $variant === LABEL_VARIANT_COLOR ? $chosen : LabelOptions::DEFAULT_INK;
}

function isLabelVariant(string $variant): bool
{
    return $variant === LABEL_VARIANT_DARK || $variant === LABEL_VARIANT_COLOR;
}

/**
 * Der Renderer für ein Etikett, in einem der beiden Formate.
 *
 * `$standalone` heißt: die Datei geht als Datei heraus und nicht in eine Seite
 * hinein. Dann bekommt das SVG seine XML-Deklaration, wie beim Symbol auch.
 *
 * @return LabelSvgRenderer|LabelPngRenderer
 */
function labelRendererFor(string $format, string $codeColor, bool $standalone)
{
    $layout = LabelLayout::standard();
    $options = LabelOptions::default()->withCodeColor($codeColor);

    if ($format === 'png') {
        return new LabelPngRenderer($layout, labelFont(), $options);
    }

    return new LabelSvgRenderer($layout, labelFont(), $options->withXmlDeclaration($standalone));
}

/**
 * Piktogramm und Text des Etiketts „Informationen zur Rückgabe", einmal je
 * Anfrage gelesen.
 *
 * Dieselbe Datei, die `Statamic\Artwork::returnInfo()` öffnet. Die Demo liest
 * sie selbst, weil der Kern keine Dateien anfasst und die Hülle hier nicht
 * geladen ist.
 *
 * @throws QrGenException
 */
function returnInfoArtwork(): Logo
{
    static $artwork = null;

    if ($artwork === null) {
        $path = dirname(__DIR__) . '/resources/artwork/rueckgabe-information.svg';
        $artwork = SvgLogo::fromMarkup((string) file_get_contents($path));
    }

    return $artwork;
}

/**
 * Der Renderer des Etiketts „Informationen zur Rückgabe".
 *
 * Dieselben Werte wie `Statamic\Symbols::returnInfoRenderer()`: die feste
 * Geometrie, reines Schwarz, und nichts aus der Adresszeile außer der Adresse.
 *
 * @return LabelSvgRenderer|LabelPngRenderer
 */
function returnInfoRendererFor(string $format, bool $standalone)
{
    $layout = LabelLayout::returnInfo();
    $options = LabelOptions::default()
        ->withCodeColor(Preset::DARK_COLOR)
        ->withInkColor(Preset::DARK_COLOR)
        ->withLightColor(Preset::LIGHT_COLOR);

    if ($format === 'png') {
        return new LabelPngRenderer($layout, labelFont(), $options);
    }

    return new LabelSvgRenderer($layout, labelFont(), $options->withXmlDeclaration($standalone));
}

/**
 * Das Etikett „Informationen zur Rückgabe" für eine Adresse.
 *
 * **Fehlerkorrektur H wie im Plugin**, nicht die Stufe, die `encode()` für den
 * Logokasten ausrechnet. Im Etikett liegt nichts im Symbol, und die Vorschau
 * soll genau das Bild zeigen, das die Statamic-Installation liefert.
 *
 * @return array{0: string|null, 1: string|null} Das Bild, oder warum es keines gibt
 */
function tryReturnInfo(string $url, bool $standalone = false, string $format = 'svg'): array
{
    try {
        $matrix = (new BaconQrEncoder())->encode($url, ErrorCorrection::high());

        return [returnInfoRendererFor($format, $standalone)->render($matrix, '', returnInfoArtwork()), null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

/**
 * One label, in the colour given.
 *
 * The two label variants differ in exactly this argument, which is the whole
 * point: whether the manufacturers print in colour is still open, so the
 * answer must not sit in the code.
 *
 * @return array{0: string|null, 1: string|null} Das Bild, oder warum es keines gibt
 */
function tryLabel(
    string $url,
    string $logoFile,
    string $text,
    string $codeColor,
    bool $standalone = false,
    string $format = 'svg'
): array {
    try {
        $image = labelRendererFor($format, $codeColor, $standalone)
            ->render(encode($url), $text, logo($logoFile));

        return [$image, null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

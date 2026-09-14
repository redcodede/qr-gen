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
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\PngRenderer;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

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

/**
 * Reads and validates the query string.
 *
 * Only `url`, `logo` and `lang` are read. The rest of the configuration is not
 * a query parameter any more, so there is nothing to get into a bad state and
 * no field for a stray mouse wheel to change.
 *
 * @param array<string, mixed> $query
 *
 * @return array{url: string, logo: string, errors: list<string>}
 */
function readInput(array $query, Translator $texts): array
{
    $errors = [];

    $url = isset($query['url']) && is_string($query['url']) ? trim($query['url']) : DEFAULT_URL;

    if ($url === '') {
        $url = DEFAULT_URL;
    }

    if (strlen($url) > MAX_URL_LENGTH) {
        $errors[] = $texts->get('error.url.tooLong', [
            'length' => strlen($url),
            'max' => MAX_URL_LENGTH,
        ]);
        $url = substr($url, 0, MAX_URL_LENGTH);
    } elseif (!isHttpUrl($url)) {
        $errors[] = $texts->get('error.url.notHttp');
    }

    $logos = availableLogos();
    $logo = isset($query['logo']) && is_string($query['logo']) ? basename($query['logo']) : '';

    if (!in_array($logo, $logos, true)) {
        if (in_array(DEFAULT_LOGO, $logos, true)) {
            $logo = DEFAULT_LOGO;
        } else {
            $logo = $logos === [] ? '' : $logos[0];
        }
    }

    return ['url' => $url, 'logo' => $logo, 'errors' => $errors];
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

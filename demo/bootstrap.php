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
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\LogoFit;
use Redcodede\QrGen\Qr\Logo\LogoFitResult;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
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
 * The SVG files sitting in demo/logos, by filename.
 *
 * @return list<string>
 */
function availableLogos(): array
{
    $found = glob(LOGO_DIR . '/*.svg') ?: [];

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
 * Reads the chosen artwork and hands its markup to the sanitiser.
 *
 * Reading the file happens here, in the demo, not in the package: the core
 * takes markup rather than a path, so the same artwork can come from a Statamic
 * asset, a fixture or a delivery folder without the package caring which.
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

    return SvgLogo::fromMarkup((string) file_get_contents($path));
}

function renderer(string $logoFile, bool $standalone, bool $withLogo): SvgRenderer
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

/**
 * @return array{0: string|null, 1: string|null} The rendered SVG, or null and a message
 */
function tryRender(string $url, string $logoFile, bool $standalone, bool $withLogo): array
{
    try {
        return [renderer($logoFile, $standalone, $withLogo)->render(encode($url)), null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

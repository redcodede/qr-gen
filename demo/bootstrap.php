<?php

/**
 * Shared setup for the two demo entry points.
 *
 * The demo exists to see rendered symbols in a browser and to download them. It
 * writes nothing: every request encodes and renders from scratch, and the SVG
 * lives only in the response. There is no cache and no output directory, so
 * there is nothing to clean up and nothing to leak.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\LogoFit;
use Redcodede\QrGen\Qr\Logo\LogoFitResult;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\SvgOptions;
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
 * H by default, because that is what a logo needs: it clears modules, and the
 * ~30% recovery is what pays for them. It costs a version step — the plain
 * symbol on this page is denser than it would have to be, which is exactly the
 * trade-off worth seeing side by side.
 */
const DEFAULT_LEVEL = ErrorCorrection::HIGH;

/**
 * Pseudo-level for the select: let LogoFit work out which level survives.
 */
const LEVEL_AUTO = 'AUTO';

const DEFAULT_LOGO_MODULES = 11;
const DEFAULT_LOGO_MARGIN = 1;

const LOGO_DIR = __DIR__ . '/logos';

const MAX_URL_LENGTH = 2000;
const MIN_MODULE_SIZE = 1;
const MAX_MODULE_SIZE = 64;
const MAX_QUIET_ZONE = 16;
const MAX_LOGO_MODULES = 61;
const MAX_LOGO_MARGIN = 6;

/**
 * Reads and validates the query string.
 *
 * Every value is clamped or rejected here so that neither entry point has to
 * think about it again. Errors are collected rather than thrown: the page stays
 * usable and tells the visitor what is wrong with their input.
 *
 * @param array<string, mixed> $query
 *
 * @return array{url: string, level: string, moduleSize: int, quietZone: int, transparent: bool, logo: string, logoModules: int, logoMargin: int, errors: list<string>}
 */
function readInput(array $query): array
{
    $errors = [];

    $url = isset($query['url']) && is_string($query['url']) ? trim($query['url']) : DEFAULT_URL;

    if ($url === '') {
        $url = DEFAULT_URL;
    }

    if (strlen($url) > MAX_URL_LENGTH) {
        $errors[] = sprintf(
            'The URL is %d bytes long. This demo accepts at most %d.',
            strlen($url),
            MAX_URL_LENGTH
        );
        $url = substr($url, 0, MAX_URL_LENGTH);
    } elseif (!isHttpUrl($url)) {
        $errors[] = 'That is not an http or https URL. '
            . 'The encoder itself takes any string, but stage one is about URLs, so the demo insists on one.';
    }

    $level = isset($query['level']) && is_string($query['level'])
        ? strtoupper(trim($query['level']))
        : LEVEL_AUTO;

    if ($level !== LEVEL_AUTO && !in_array($level, ErrorCorrection::all(), true)) {
        $errors[] = sprintf('Unknown error correction level, falling back to %s.', DEFAULT_LEVEL);
        $level = DEFAULT_LEVEL;
    }

    $logos = availableLogos();
    $logo = isset($query['logo']) && is_string($query['logo']) ? basename($query['logo']) : '';

    if (!in_array($logo, $logos, true)) {
        $logo = $logos === [] ? '' : $logos[0];
    }

    return [
        'url' => $url,
        'level' => $level,
        'moduleSize' => clamp($query['moduleSize'] ?? 8, MIN_MODULE_SIZE, MAX_MODULE_SIZE, 8),
        'quietZone' => clamp($query['quietZone'] ?? SvgOptions::SPEC_QUIET_ZONE, 0, MAX_QUIET_ZONE, SvgOptions::SPEC_QUIET_ZONE),
        'transparent' => !empty($query['transparent']),
        'logo' => $logo,
        'logoModules' => clamp($query['logoModules'] ?? DEFAULT_LOGO_MODULES, 3, MAX_LOGO_MODULES, DEFAULT_LOGO_MODULES),
        'logoMargin' => clamp($query['logoMargin'] ?? DEFAULT_LOGO_MARGIN, 0, MAX_LOGO_MARGIN, DEFAULT_LOGO_MARGIN),
        'allowAlignment' => !empty($query['allowAlignment']),
        'errors' => $errors,
    ];
}

/**
 * The box as configured, including the alignment-pattern permission.
 *
 * @param array<string, mixed> $input
 */
function box(array $input): LogoBox
{
    $box = LogoBox::square($input['logoModules'], $input['logoMargin']);

    return $input['allowAlignment'] ? $box->allowingAlignmentPatterns() : $box;
}

/**
 * Resolves the level when the form says "auto", memoised so a page that asks
 * several times encodes once.
 *
 * @param array<string, mixed> $input
 *
 * @return array{0: LogoFitResult|null, 1: string|null} The fit, or why there is none
 */
function fit(array $input): array
{
    static $cache = [];

    $key = md5(serialize([
        $input['url'],
        $input['logoModules'],
        $input['logoMargin'],
        $input['allowAlignment'],
    ]));

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $result = [(new LogoFit(new BaconQrEncoder()))->lowestLevelFor($input['url'], box($input)), null];
    } catch (QrGenException $exception) {
        $result = [null, $exception->getMessage()];
    }

    return $cache[$key] = $result;
}

/**
 * The level actually used. On "auto" that is whatever LogoFit settled on; if
 * nothing survives, H, so the page still shows a plain symbol and the reason
 * next to the empty logo panel.
 *
 * @param array<string, mixed> $input
 */
function effectiveLevel(array $input): ErrorCorrection
{
    if ($input['level'] !== LEVEL_AUTO) {
        return ErrorCorrection::fromString($input['level']);
    }

    [$result] = fit($input);

    return $result === null ? ErrorCorrection::high() : $result->level();
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

/**
 * @param mixed $value
 */
function clamp($value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    return max($min, min($max, (int) $value));
}

/**
 * @param array<string, mixed> $input
 */
function encode(array $input): ModuleMatrix
{
    return (new BaconQrEncoder())->encode($input['url'], effectiveLevel($input));
}

/**
 * Reads the chosen logo file and hands its markup to the sanitiser.
 *
 * Reading the file happens here, in the demo, not in the package: the core takes
 * markup rather than a path, so the same logo can come from a Statamic asset, a
 * fixture or a delivery folder without the package caring which.
 *
 * @param array<string, mixed> $input
 */
function logo(array $input): ?Logo
{
    if ($input['logo'] === '') {
        return null;
    }

    $path = LOGO_DIR . '/' . $input['logo'];

    if (!is_file($path)) {
        return null;
    }

    return SvgLogo::fromMarkup((string) file_get_contents($path));
}

/**
 * @param array<string, mixed> $input
 */
function renderer(array $input, bool $standalone, bool $withLogo): SvgRenderer
{
    $options = SvgOptions::default()
        ->withModuleSize($input['moduleSize'])
        ->withQuietZone($input['quietZone'])
        ->withColors('#000000', $input['transparent'] && !$withLogo ? 'none' : '#ffffff')
        ->withXmlDeclaration($standalone);

    if ($withLogo) {
        $artwork = logo($input);

        if ($artwork !== null) {
            $options = $options->withLogo($artwork, box($input));
        }
    }

    return new SvgRenderer($options);
}

/**
 * Recovery rate of an error correction level, for the form and the fact table.
 */
function levelHint(string $level): string
{
    switch ($level) {
        case ErrorCorrection::LOW:
            return ' — ~7% recovery';

        case ErrorCorrection::MEDIUM:
            return ' — ~15% recovery';

        case ErrorCorrection::QUARTILE:
            return ' — ~25% recovery';

        default:
            return ' — ~30% recovery, needed for a logo';
    }
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{0: string|null, 1: string|null} The rendered SVG, or null and a message
 */
function tryRender(array $input, bool $standalone, bool $withLogo): array
{
    try {
        return [renderer($input, $standalone, $withLogo)->render(encode($input)), null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

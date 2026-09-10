<?php

/**
 * Shared setup for the two demo entry points.
 *
 * The demo exists to see a rendered symbol in a browser and to download it. It
 * writes nothing: every request encodes and renders from scratch, and the SVG
 * lives only in the response. There is no cache and no output directory, so
 * there is nothing to clean up and nothing to leak.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\QrGenException;
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

const MAX_URL_LENGTH = 2000;
const MIN_MODULE_SIZE = 1;
const MAX_MODULE_SIZE = 64;
const MAX_QUIET_ZONE = 16;

/**
 * Reads and validates the query string.
 *
 * Every value is clamped or rejected here so that neither entry point has to
 * think about it again. Errors are collected rather than thrown: the page stays
 * usable and tells the visitor what is wrong with their input.
 *
 * @param array<string, mixed> $query
 *
 * @return array{url: string, level: string, moduleSize: int, quietZone: int, transparent: bool, errors: list<string>}
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
        : ErrorCorrection::MEDIUM;

    if (!in_array($level, ErrorCorrection::all(), true)) {
        $errors[] = sprintf('Unknown error correction level, falling back to %s.', ErrorCorrection::MEDIUM);
        $level = ErrorCorrection::MEDIUM;
    }

    return [
        'url' => $url,
        'level' => $level,
        'moduleSize' => clamp($query['moduleSize'] ?? 8, MIN_MODULE_SIZE, MAX_MODULE_SIZE, 8),
        'quietZone' => clamp($query['quietZone'] ?? SvgOptions::SPEC_QUIET_ZONE, 0, MAX_QUIET_ZONE, SvgOptions::SPEC_QUIET_ZONE),
        'transparent' => !empty($query['transparent']),
        'errors' => $errors,
    ];
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
 * @param array{url: string, level: string, moduleSize: int, quietZone: int, transparent: bool, errors: list<string>} $input
 */
function encode(array $input): ModuleMatrix
{
    return (new BaconQrEncoder())->encode($input['url'], ErrorCorrection::fromString($input['level']));
}

/**
 * @param array{url: string, level: string, moduleSize: int, quietZone: int, transparent: bool, errors: list<string>} $input
 */
function renderer(array $input, bool $standalone): SvgRenderer
{
    $options = SvgOptions::default()
        ->withModuleSize($input['moduleSize'])
        ->withQuietZone($input['quietZone'])
        ->withColors('#000000', $input['transparent'] ? 'none' : '#ffffff')
        ->withXmlDeclaration($standalone);

    return new SvgRenderer($options);
}

/**
 * The QR version, derived from the module count. Handy for judging print size:
 * a lower version means fewer and therefore larger modules at the same physical
 * width, which is what a scanner has an easier time with.
 */
function versionOf(ModuleMatrix $matrix): int
{
    return intdiv($matrix->size() - 17, 4);
}

/**
 * Builds a download filename from the URL's host.
 *
 * Reduced to an ASCII slug rather than escaped, because this value goes into a
 * Content-Disposition header where a stray newline or quote would let a caller
 * write headers of their own.
 */
function downloadFilename(string $url, string $extension): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $host) ?? '');
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'code';
    }

    return 'qr-' . substr($slug, 0, 60) . '.' . $extension;
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
 * @return array{0: string|null, 1: string|null} The rendered SVG, or null and a message
 */
function tryRender(array $input, bool $standalone): array
{
    try {
        return [renderer($input, $standalone)->render(encode($input)), null];
    } catch (QrGenException $exception) {
        return [null, $exception->getMessage()];
    }
}

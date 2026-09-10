<?php

/**
 * Serves one SVG on its own, for download or for embedding.
 *
 * Generated per request and streamed straight out. Nothing is written to disk,
 * which is why the response says no-store: there is no stored copy anywhere, and
 * a cached one would be the only one.
 *
 *   svg.php?url=https://www.redcode.de/                        plain, inline
 *   svg.php?url=…&variant=logo                                 with the logo
 *   svg.php?url=…&variant=logo&download=1                      as a file
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

require __DIR__ . '/bootstrap.php';

$input = readInput($_GET);

if ($input['errors'] !== []) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $input['errors']) . "\n";
    exit;
}

$withLogo = isset($_GET['variant']) && $_GET['variant'] === 'logo';

[$svg, $failure] = tryRender($input, true, $withLogo);

if ($svg === null) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $failure . "\n";
    exit;
}

$renderer = renderer($input, true, $withLogo);
$filename = downloadFilename($input['url'], $withLogo, $renderer->fileExtension());

$disposition = empty($_GET['download'])
    ? 'inline'
    : sprintf('attachment; filename="%s"', $filename);

header('Content-Type: ' . $renderer->mimeType() . '; charset=utf-8');
header('Content-Disposition: ' . $disposition);
header('Content-Length: ' . strlen($svg));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo $svg;

/**
 * Builds a download filename from the URL's host.
 *
 * Reduced to an ASCII slug rather than escaped, because this value goes into a
 * Content-Disposition header where a stray newline or quote would let a caller
 * write headers of their own.
 */
function downloadFilename(string $url, bool $withLogo, string $extension): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $host) ?? '');
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'code';
    }

    return 'qr-' . substr($slug, 0, 60) . ($withLogo ? '-logo' : '') . '.' . $extension;
}

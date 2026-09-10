<?php

/**
 * Serves the SVG on its own, for download or for embedding.
 *
 * Generated per request and streamed straight out. Nothing is written to disk,
 * which is why the response says no-store: there is no stored copy anywhere, and
 * a cached one would be the only one.
 *
 *   svg.php?url=https://www.redcode.de/                 inline
 *   svg.php?url=https://www.redcode.de/&download=1      as a file
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

[$svg, $failure] = tryRender($input, true);

if ($svg === null) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $failure . "\n";
    exit;
}

$renderer = renderer($input, true);
$disposition = empty($_GET['download'])
    ? 'inline'
    : sprintf('attachment; filename="%s"', downloadFilename($input['url'], $renderer->fileExtension()));

header('Content-Type: ' . $renderer->mimeType() . '; charset=utf-8');
header('Content-Disposition: ' . $disposition);
header('Content-Length: ' . strlen($svg));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo $svg;

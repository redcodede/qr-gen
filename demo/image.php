<?php

/**
 * Serves one image on its own, for download or for embedding.
 *
 * Generated per request and streamed straight out. Nothing is written to disk,
 * which is why the response says no-store: there is no stored copy anywhere, and
 * a cached one would be the only one.
 *
 *   image.php?url=…                              SVG, plain, inline
 *   image.php?url=…&format=png                   PNG, print-ready
 *   image.php?url=…&variant=logo                 SVG with artwork
 *   image.php?url=…&variant=label                the label, dark symbol
 *   image.php?url=…&variant=label-color          the label, symbol in label[color]
 *   image.php?url=…&variant=return_info          das Etikett „Informationen zur Rückgabe"
 *   image.php?url=…&format=png&download=1        as a file
 *
 * Both formats carry artwork. Where the rasteriser cannot draw a particular
 * logo — see pngAvailable() — a request for its PNG comes back as SVG rather
 * than as a symbol with a hole in it.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\Settings\Variant;

require __DIR__ . '/bootstrap.php';

$texts = texts($_GET);
$input = readInput($_GET, $texts);

if ($input['errors'] !== []) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $input['errors']) . "\n";
    exit;
}

$variant = isset($_GET['variant']) && is_string($_GET['variant']) ? $_GET['variant'] : '';
$withLogo = $variant === 'logo';
$wantsPng = isset($_GET['format']) && $_GET['format'] === 'png';

if ($variant === Variant::RETURN_INFO) {
    // Fest nach Vorlage: keine Bildmarke, kein Text, keine Farbe aus der
    // Adresszeile. Die Grafik ist ein Vektor aus Flächen, ein PNG gibt es
    // deshalb immer.
    $format = $wantsPng ? 'png' : 'svg';

    [$image, $failure] = tryReturnInfo($input['url'], true, $format);
    $renderer = returnInfoRendererFor($format, true);
    $suffix = '-rueckgabeinformation';
} elseif (isLabelVariant($variant)) {
    // Das Etikett trägt die Bildmarke immer, also entscheidet dieselbe Prüfung
    // wie bei der Variante mit Bildmarke, ob es davon ein PNG geben kann.
    $label = labelInput($_GET);
    $codeColor = labelCodeColor($variant, $label['color']);
    $format = $wantsPng && pngAvailable($input['logo'], true) ? 'png' : 'svg';

    [$image, $failure] = tryLabel($input['url'], $input['logo'], $label['text'], $codeColor, true, $format);
    $renderer = labelRendererFor($format, $codeColor, true);
    $suffix = $variant === LABEL_VARIANT_COLOR ? '-etikett-farbig' : '-etikett';
} else {
    $format = $wantsPng && pngAvailable($input['logo'], $withLogo) ? 'png' : 'svg';

    [$image, $failure] = tryRender($input['url'], $input['logo'], true, $withLogo, $format);
    $renderer = rendererFor($format, $input['logo'], true, $withLogo);
    $suffix = $withLogo ? '-logo' : '';
}

if ($image === null) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $failure . "\n";
    exit;
}

$filename = downloadFilename($input['url'], $suffix, $renderer->fileExtension());

$disposition = empty($_GET['download'])
    ? 'inline'
    : sprintf('attachment; filename="%s"', $filename);

header('Content-Type: ' . $renderer->mimeType() . ($format === 'svg' ? '; charset=utf-8' : ''));
header('Content-Disposition: ' . $disposition);
header('Content-Length: ' . strlen($image));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo $image;

/**
 * Builds a download filename from the URL's host.
 *
 * Reduced to an ASCII slug rather than escaped, because this value goes into a
 * Content-Disposition header where a stray newline or quote would let a caller
 * write headers of their own.
 */
function downloadFilename(string $url, string $suffix, string $extension): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $host) ?? '');
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'code';
    }

    return 'qr-' . substr($slug, 0, 60) . $suffix . '.' . $extension;
}

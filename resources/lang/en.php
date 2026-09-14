<?php

/**
 * English interface texts.
 *
 * Present but not offered: German is the default and nothing in the interface
 * switches away from it. This catalogue exists so that English is a matter of
 * asking for a locale rather than of writing a file, which is what "available
 * later on demand" has to mean if it is to be true.
 *
 * Every key in de.php has to exist here and vice versa — there is a test for
 * that, because a translation that drifts is worse than one that is missing:
 * the missing one is visible.
 */

declare(strict_types=1);

return [
    'app.title' => 'qr-gen',
    'app.subtitle' => 'URL in, two codes out — one plain, one with artwork in the middle. As SVG '
        . 'for print, plus a print-ready PNG for the plain one. Nothing is written to disk: every '
        . 'request encodes and renders from scratch, and a download regenerates rather than '
        . 'fetching a stored file.',

    'form.url.label' => 'URL',
    'form.logo.label' => 'Artwork',
    'form.logo.none' => 'none in demo/logos',
    'form.submit' => 'Generate',
    'form.reset' => 'Reset',
    'form.fixed.heading' => 'Fixed settings',
    'form.fixed.note' => 'Logo box :box modules, margin :margin, module size :moduleSize px, '
        . 'quiet zone :quietZone modules. The error correction level is worked out, not chosen.',

    'panel.plain' => 'Without artwork',
    'panel.logo' => 'With artwork',
    'panel.download.svg' => 'Download SVG',
    'panel.download.png' => 'Download PNG',
    'panel.raw' => 'Open raw',
    'panel.nothing' => 'Nothing rendered.',
    'panel.noLogo' => 'No SVG or PNG in demo/logos. Drop one in and reload.',
    'panel.png.whichFormat' => 'Both formats show the same drawing in the same place. The SVG '
        . 'is what goes to the printer: vector, scalable to any size, crisp at every one of them. '
        . 'The PNG is rasterised — 8-bit indexed, anti-aliased, at the ordered print size — which '
        . 'makes it the one for screens, office documents and email, where an SVG is a nuisance.',
    'panel.png.refused' => 'There is no PNG of this artwork. :reason',

    'facts.heading' => 'What came out',
    'facts.payload' => 'Payload',
    'facts.payload.value' => ':bytes bytes',
    'facts.level' => 'Error correction',
    'facts.level.auto' => 'lowest level that survives this box',
    'facts.version' => 'QR version',
    'facts.version.value' => ':version of 40',
    'facts.modules' => 'Modules',
    'facts.modules.value' => ':size × :size = :total',
    'facts.allowance' => 'Allowance',
    'facts.allowance.value' => ':used % used of :budget %, :headroom % headroom',
    'facts.alignment' => 'Alignment pattern',
    'facts.alignment.intact' => 'intact',
    'facts.alignment.given' => ':modules modules given up',
    'facts.box' => 'Logo box',
    'facts.box.value' => ':box × :box modules',
    'facts.cleared' => 'Cleared',
    'facts.cleared.value' => ':modules modules, :share % of the symbol',
    'facts.margin' => 'Margin',
    'facts.margin.value' => ':margin module(s), leaving :drawable × :drawable to draw in',
    'facts.logoWidth' => 'Artwork width',
    'facts.logoWidth.value' => ':percent % of the symbol',
    'facts.largestBox' => 'Largest box that clears the finders',
    'facts.largestBox.value' => ':modules modules',
    'facts.svgSize' => 'SVG size',
    'facts.svgSize.value' => ':plain vs :logo bytes',
    'facts.png' => 'PNG',
    'facts.png.value' => ':pixels × :pixels px, :perModule px per module, 1 bit, :bytes bytes',
    'facts.artworkArea' => 'Area the artwork fills',
    'facts.artworkArea.value' => ':width × :height px in the printed PNG',
    'facts.artworkArea.enough' => 'Supplied :width × :height px — enough, it is being reduced',
    'facts.artworkArea.short' => 'Supplied only :width × :height px — it is being enlarged and '
        . 'will look soft. Ask for a larger file',
    'facts.pngLogo' => 'PNG with artwork',
    'facts.pngLogo.value' => ':bytes bytes, 8-bit indexed',
    'facts.printSize' => 'Printed size',
    'facts.printSize.value' => ':size mm at :dpi dpi — good for :ordered mm with no upscaling',
    'facts.preview' => 'Preview shows',
    'facts.preview.value' => ':format, the smaller of the two',

    'source.summary' => 'SVG source, with artwork',

    'level.L' => 'L — around 7 % recovery',
    'level.M' => 'M — around 15 % recovery',
    'level.Q' => 'Q — around 25 % recovery',
    'level.H' => 'H — around 30 % recovery, needed for artwork',

    'notice.quietZone' => 'The quiet zone is set to :quietZone modules. The specification asks for 4. '
        . 'That only holds if the surrounding layout contributes the missing modules as white space — '
        . 'a code butting straight up against artwork becomes unreliable. A print proof settles it.',

    'notice.print' => 'For the printer: black as :dark, white as :light, and in the CMYK '
        . 'conversion explicitly 100 % K — no rich black. A black mixed from four inks needs four '
        . 'plates in register, and where they are not, a module edge softens into a coloured '
        . 'fringe. That edge is exactly what a scanner measures. Neither PNG nor SVG can carry '
        . 'CMYK at all; the conversion happens in prepress.',

    'error.url.tooLong' => 'The URL is :length bytes long. This page accepts at most :max.',
    'error.url.notHttp' => 'That is not an http or https URL. The encoder itself takes any string, '
        . 'but this is about URLs, so the page insists on one.',

    'footer' => 'The cleared area is weighed against the error correction level, and that is a rule '
        . 'of thumb — modules and codewords are not the same unit. What is not a rule of thumb is '
        . 'the function patterns: finder, timing and format patterns carry no error correction, and '
        . 'a box over one is refused. Whether the printed code scans is settled by a proof at final '
        . 'size on the real material, not by this page.',
];

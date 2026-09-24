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

    'form.logo.none' => 'none in demo/logos',

    'group.global.heading' => 'Global settings',
    'group.global.note' => 'The only level there is to set. In the Control Panel under Tools, '
        . 'QR codes. What stands here applies everywhere alike.',
    'group.page.heading' => 'This one place',
    'group.page.note' => 'The address in the code is the only thing that has to differ from '
        . 'place to place. Later the Blueprint field. Leaving it empty means the default URL '
        . 'applies.',
    'group.output.heading' => 'Output',
    'group.output.note' => 'What actually comes of it.',

    'form.variants.label' => 'These codes are offered',
    'form.downloads.label' => 'These formats are offered',
    'form.defaultUrl.label' => 'Default URL',
    'form.defaultLogo.label' => 'Default artwork',
    'form.pageUrl.label' => 'Target URL of this place',
    'form.inherit.empty' => 'nothing set',
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

    'group.label.heading' => 'Label',
    'group.label.note' => 'Symbol, artwork and type in one image, at the measurements of the '
        . 'delivered file: :width x :height millimetres, symbol :code millimetres.',
    'panel.label' => 'Label, dark symbol',
    'panel.labelColor' => 'Label, coloured symbol',
    'form.labelText.label' => 'Text on the label',
    'form.labelText.hint' => 'At most :max characters. The type wraps and shrinks until it fits '
        . 'the box. If that is not enough it is refused rather than set too small to read.',
    'form.labelColor.label' => 'Colour of the symbol',
    'form.labelColor.hint' => 'Applies to the right-hand label only. The value is RGB; which '
        . 'CMYK it becomes on press is the printer\'s call.',
    'label.facts' => 'Quiet zone :zone modules, ISO/IEC 18004 asks for 4. Module size '
        . ':moduleSize millimetres at :moduleCount modules.',

    'group.returnInfo.heading' => 'Label "Informationen zur Rückgabe"',
    'group.returnInfo.note' => 'A preset after the artwork of 24 September 2026, :width x :height '
        . 'millimetres. Frame, pictogram and type are fixed; the code is the only thing that '
        . 'varies. No mark and no text of your own: taking part in the scheme is stated behind the '
        . 'code, not on the pack.',
    'panel.returnInfo' => 'Label, return information',

    'resolution.url' => 'URL:',
    'resolution.logo' => 'Artwork:',
    'resolution.none' => 'none',
    'resolution.from.page' => 'from the page',
    'resolution.from.global' => 'global',
    'resolution.from.nowhere' => 'set nowhere',

    'output.nothing' => 'No variant selected. There is nothing to show, and that is a valid '
        . 'setting rather than an error.',
    'output.noDownloads' => 'Both formats are switched off globally. The codes appear, but '
        . 'nothing can be downloaded.',

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

    'error.url.missing' => 'There is no URL. Enter one in the page settings, or set a default '
        . 'URL in the global settings.',
    'error.url.tooLong' => 'The URL is :length bytes long. This page accepts at most :max.',
    'error.url.notHttp' => 'That is not an http or https URL. The encoder itself takes any string, '
        . 'but this is about URLs, so the page insists on one.',

    'footer' => 'The cleared area is weighed against the error correction level, and that is a rule '
        . 'of thumb — modules and codewords are not the same unit. What is not a rule of thumb is '
        . 'the function patterns: finder, timing and format patterns carry no error correction, and '
        . 'a box over one is refused. Whether the printed code scans is settled by a proof at final '
        . 'size on the real material, not by this page.',

    // The control panel page.

    'cp.nav' => 'QR codes',
    'cp.title' => 'QR codes',
    'cp.intro' => 'This is what the extension offers. These values apply everywhere alike. The '
        . 'only thing entered per place is the target URL in the Blueprint.',

    'cp.section.variants' => 'What is offered',
    'cp.section.label' => 'The label',
    'cp.section.label.hint' => 'Applies to the two labels with artwork and type, not to the '
        . '"Informationen zur Rückgabe" label. The measurements come from the delivered artwork and '
        . 'are not a setting.',
    'cp.section.texts' => 'Text on the page',
    'cp.section.texts.site' => 'Text on the page (:site)',
    'cp.section.defaults' => 'Fallback values',
    'cp.section.fixed' => 'Settled values',

    'cp.variants.plain' => 'Code without artwork',
    'cp.variants.plain.hint' => 'The plain code. Without it only the artwork variant remains, and '
        . 'that one exists only when artwork is set.',
    'cp.variants.logo' => 'Code with artwork',
    'cp.variants.logo.hint' => 'Appears only when artwork is set below. Without it the variant is '
        . 'silently dropped; that is not a misconfiguration.',
    'cp.variants.label' => 'Label, dark symbol',
    'cp.variants.label.hint' => 'Symbol, artwork beside it and type in one image, at the '
        . 'measurements of the delivered file. The symbol takes the type colour.',
    'cp.variants.labelColor' => 'Label, coloured symbol',
    'cp.variants.labelColor.hint' => 'The same label with the symbol in the colour set below. '
        . 'Without a colour it is silently dropped, like the artwork variant without artwork.',
    'cp.variants.returnInfo' => 'Label "Informationen zur Rückgabe"',
    'cp.variants.returnInfo.hint' => 'Symbol, phone pictogram and the words "Informationen zur '
        . 'Rückgabe" in one image, fixed after the artwork of 24 September 2026. No mark, no text '
        . 'of your own, in black: the code is the only thing that varies. The fields under "The '
        . 'label" and the artwork do not apply to it.',

    'cp.label.text' => 'Text on the label',
    'cp.label.text.hint' => 'Sits to the right of the symbol. At most :max characters. The type '
        . 'wraps and shrinks until it fits its box; if that is not enough the label is refused '
        . 'rather than set too small to read. Leaving it empty means a label without type.',
    'cp.label.color' => 'Colour of the symbol',
    'cp.label.color.hint' => 'For the coloured label only. The value is RGB; which CMYK it '
        . 'becomes on press is the printer\'s call. Without a colour there is no coloured label.',

    'cp.downloads.svg' => 'Download SVG',
    'cp.downloads.svg.hint' => 'The format for the printer. Scales without loss.',
    'cp.downloads.png' => 'Download PNG',
    'cp.downloads.png.hint' => 'The companion for screen, office and email. Computed from the '
        . 'print size rather than from a pixel count.',

    'cp.defaultLogo' => 'Artwork',
    'cp.defaultLogo.hint' => 'Applies everywhere alike, in the code and on the label. SVG is the '
        . 'better delivery, a PNG works too. Leaving it empty is fine: the artwork variant is '
        . 'then dropped and the label stays without a mark.',
    'cp.defaultUrl' => 'Default URL',
    'cp.defaultUrl.hint' => 'Applies where no address is entered in a place. A full address with '
        . 'http or https. What is entered is what is processed — no adding or removing of www.',

    'cp.fixed.hint' => 'These values are settled, not configured. An approved print proof holds '
        . 'for exactly these, and a field someone nudges in passing would invalidate it without '
        . 'anyone noticing. Change them in Qr\\Preset.',
    'cp.fixed.box' => 'Artwork box',
    'cp.fixed.box.value' => ':box modules, :margin of them margin',
    'cp.fixed.moduleSize' => 'Module size',
    'cp.fixed.quietZone' => 'Quiet zone',
    'cp.fixed.modules' => ':count modules',
    'cp.fixed.print' => 'Print size',
    'cp.fixed.print.value' => ':size mm at :dpi dpi',
    'cp.fixed.level' => 'Error correction',
    'cp.fixed.level.value' => 'computed, not chosen',
    'cp.fixed.container' => 'Asset container',
    'cp.fixed.container.hint' => 'Where artwork lives when a path arrives without a container. '
        . 'An installation fact, so it sits in config/qr-gen.php and not here.',

    'cp.texts.title' => 'Heading',
    'cp.texts.title.hint' => 'Leave empty for the text that ships with the package.',
    'cp.texts.lead' => 'Lead',
    'cp.texts.lead.hint' => '{url} is replaced by the address the code points to, and linked. '
        . 'Leave empty for the text that ships with the package.',

    'cp.permission' => 'Change QR code settings',
    'cp.saved' => 'Saved.',
    'cp.nothing' => 'No variant is ticked. No page will show a code.',
    'cp.noDownload' => 'No format is ticked. The codes can then be looked at but not taken away.',

    // The panels on the site.
    //
    // Heading and lead sit here only as a fallback: they are text the site
    // owns, and can be set per language in the control panel. The labels of
    // the two codes stay here, because they name what this package produces
    // and change with it.

    'page.title' => 'QR codes',
    'page.lead' => 'QR codes for {url} to download, for digital and print',

    'panel.failure' => 'This code was not produced: :grund',
    'panel.alt' => 'QR code',
    'panel.alt.logo' => 'QR code with artwork',
    'panel.alt.label' => 'Label with QR code, artwork and type',
    'panel.alt.returnInfo' => 'Label with QR code and the notice "Informationen zur Rückgabe"',
];

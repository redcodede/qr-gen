<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\QrRenderer;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Logo\LogoPlacement;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * Renders a module matrix as SVG. String building, nothing else.
 *
 * Two decisions worth knowing about:
 *
 * The dark modules become a single <path>, not one <rect> per module. Adjacent
 * rectangles meeting edge to edge show hairline seams in some print RIPs and in
 * Illustrator, because each is rasterised on its own. One filled path cannot do
 * that. It also cuts the file to a fraction of the size.
 *
 * The viewBox is in module units and the width and height attributes carry the
 * pixel size. The same file therefore scales from a label to a poster without
 * being regenerated, which is the whole point of shipping vector artwork to a
 * printer.
 *
 * Nothing external is referenced — no font, no stylesheet, no image. The file is
 * self-contained, so opening it never causes a network request.
 */
final class SvgRenderer implements QrRenderer
{
    private const NS = 'http://www.w3.org/2000/svg';

    /** @var SvgOptions */
    private $options;

    public function __construct(?SvgOptions $options = null)
    {
        $this->options = $options ?? SvgOptions::default();
    }

    public function render(ModuleMatrix $matrix): string
    {
        $quietZone = $this->options->quietZone();
        $modules = $matrix->size();
        $extent = $modules + (2 * $quietZone);
        $pixels = $extent * $this->options->moduleSize();
        $placement = $this->placement($matrix);

        $svg = '';

        if ($this->options->hasXmlDeclaration()) {
            $svg .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        }

        $svg .= sprintf(
            '<svg xmlns="%s" width="%d" height="%d" viewBox="0 0 %d %d" '
            . 'shape-rendering="crispEdges" role="img">',
            self::NS,
            $pixels,
            $pixels,
            $extent,
            $extent
        );

        $title = $this->options->title();

        if ($title !== null && $title !== '') {
            $svg .= '<title>' . $this->escape($title) . '</title>';
        }

        if ($this->options->hasBackground()) {
            $svg .= sprintf(
                '<rect width="%d" height="%d" fill="%s"/>',
                $extent,
                $extent,
                $this->options->lightColor()
            );
        }

        $path = $this->buildPath($matrix, $quietZone, $placement);

        if ($path !== '') {
            $svg .= sprintf('<path fill="%s" d="%s"/>', $this->options->darkColor(), $path);
        }

        if ($placement !== null) {
            $svg .= $this->renderLogo($placement, $quietZone);
        }

        return $svg . '</svg>';
    }

    public function mimeType(): string
    {
        return 'image/svg+xml';
    }

    public function fileExtension(): string
    {
        return 'svg';
    }

    private function placement(ModuleMatrix $matrix): ?LogoPlacement
    {
        if (!$this->options->hasLogo()) {
            return null;
        }

        // The cleared area has to read as light. With a transparent background
        // whatever sits behind the symbol shows through it instead, and a
        // scanner then sees neither light nor dark where it needs light.
        if (!$this->options->hasBackground()) {
            throw InvalidArgument::logoNeedsOpaqueBackdrop();
        }

        /** @var \Redcodede\QrGen\Qr\Logo\LogoBox $box */
        $box = $this->options->logoBox();

        return $box->placeIn($matrix);
    }

    /**
     * Walks each row and emits one subpath per uninterrupted run of dark
     * modules, so a row of twenty dark modules costs one subpath rather than
     * twenty.
     *
     * Modules inside a logo box are skipped rather than drawn and covered. The
     * artwork then sits on the background instead of on top of dark modules,
     * and the path stays as small as the symbol allows.
     */
    private function buildPath(ModuleMatrix $matrix, int $quietZone, ?LogoPlacement $placement): string
    {
        $size = $matrix->size();
        $rows = $matrix->rows();
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            $x = 0;

            while ($x < $size) {
                if (!$rows[$y][$x] || ($placement !== null && $placement->covers($x, $y))) {
                    $x++;

                    continue;
                }

                $run = 1;

                while (
                    $x + $run < $size
                    && $rows[$y][$x + $run]
                    && !($placement !== null && $placement->covers($x + $run, $y))
                ) {
                    $run++;
                }

                $path .= sprintf(
                    'M%d %dh%dv1h-%dz',
                    $x + $quietZone,
                    $y + $quietZone,
                    $run,
                    $run
                );

                $x += $run;
            }
        }

        return $path;
    }

    /**
     * Scales the artwork into the drawable part of the box, preserving its
     * aspect ratio and centring what is left over.
     *
     * The transform works in module units, so the scale factor is small — a
     * 500-unit logo into 9 modules is 0.018. Six decimals keep a 177-module
     * symbol accurate to well under a thousandth of a module.
     */
    private function renderLogo(LogoPlacement $placement, int $quietZone): string
    {
        /** @var \Redcodede\QrGen\Qr\Contract\Logo $logo */
        $logo = $this->options->logo();

        $availableWidth = $placement->drawableWidth();
        $availableHeight = $placement->drawableHeight();

        $scale = min($availableWidth / $logo->width(), $availableHeight / $logo->height());
        $drawnWidth = $logo->width() * $scale;
        $drawnHeight = $logo->height() * $scale;

        $x = $quietZone + $placement->drawableX() + (($availableWidth - $drawnWidth) / 2);
        $y = $quietZone + $placement->drawableY() + (($availableHeight - $drawnHeight) / 2);

        // The cleared area is painted in the light colour rather than left to
        // the background rect, so the margin is explicit in the file and a
        // later change to the background cannot swallow it.
        $backdrop = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>',
            $placement->x() + $quietZone,
            $placement->y() + $quietZone,
            $placement->width(),
            $placement->height(),
            $this->options->lightColor()
        );

        // The root element carries shape-rendering="crispEdges", which is right
        // for modules: they are axis-aligned squares and anti-aliasing only
        // softens edges a scanner wants hard. It is wrong for artwork. Curves
        // drawn without anti-aliasing at this scale — a 500-unit logo squeezed
        // into nine modules — come out jagged, and thin features drop out
        // entirely, which looks like the logo failed to render. The group turns
        // it back on for its subtree.
        return $backdrop . sprintf(
            '<g transform="translate(%s %s) scale(%s)" shape-rendering="geometricPrecision">%s</g>',
            $this->number($x),
            $this->number($y),
            $this->number($scale),
            $logo->markup()
        );
    }

    /**
     * Fixed six decimals with trailing zeros removed, so the output does not
     * depend on the locale's decimal separator or on precision settings.
     */
    private function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

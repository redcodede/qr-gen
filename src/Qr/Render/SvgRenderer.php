<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\QrRenderer;
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

        $path = $this->buildPath($matrix, $quietZone);

        if ($path !== '') {
            $svg .= sprintf('<path fill="%s" d="%s"/>', $this->options->darkColor(), $path);
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

    /**
     * Walks each row and emits one subpath per uninterrupted run of dark
     * modules, so a row of twenty dark modules costs one subpath rather than
     * twenty.
     */
    private function buildPath(ModuleMatrix $matrix, int $quietZone): string
    {
        $size = $matrix->size();
        $rows = $matrix->rows();
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            $x = 0;

            while ($x < $size) {
                if (!$rows[$y][$x]) {
                    $x++;

                    continue;
                }

                $run = 1;

                while ($x + $run < $size && $rows[$y][$x + $run]) {
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

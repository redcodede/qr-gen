<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Contract\RasterArtwork;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\Layout\LabelText;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * The whole label as one SVG: frame, symbol, mark and type.
 *
 * {@see SvgRenderer} draws a symbol and nothing else, and it stays that way: it
 * is the package's specification-conforming baseline and a label is a house
 * decision. This renderer is the decision, in the same way
 * {@see \Redcodede\QrGen\Qr\Preset} is for print values.
 *
 * **Type is drawn as outlines, never as `<text>`.** A `<text>` element renders
 * only where the font happens to be installed; on the press it would be set in
 * whatever the RIP falls back to, and nothing in the file would say so. The
 * outlines come from {@see SvgFont}, which is why the package ships a font at
 * all.
 *
 * **The type shrinks, the box does not.** The delivered artwork sets two lines
 * that fill 94 % of their column, and the agreed input allows 72 characters,
 * which is roughly four and a half lines at that size. Something has to give,
 * and it is the size: the text is wrapped and scaled down until it fits the
 * box, down to a floor below which the label is refused outright.
 *
 * The result carries no external reference of any kind, so opening it never
 * causes a network request, and the printer receives one file.
 */
final class LabelSvgRenderer
{
    private const NS = 'http://www.w3.org/2000/svg';

    private const XLINK_NS = 'http://www.w3.org/1999/xlink';

    /** @var LabelLayout */
    private $layout;

    /** @var SvgFont */
    private $font;

    /** @var LabelOptions */
    private $options;

    public function __construct(LabelLayout $layout, SvgFont $font, ?LabelOptions $options = null)
    {
        $this->layout = $layout;
        $this->font = $font;
        $this->options = $options ?? LabelOptions::default();
    }

    /**
     * @throws TextRejected if the text cannot be set in the space available
     */
    public function render(ModuleMatrix $matrix, string $text = '', ?Logo $logo = null): string
    {
        $svg = '';

        if ($this->options->hasXmlDeclaration()) {
            $svg .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        }

        $svg .= sprintf(
            '<svg xmlns="%s"%s width="%smm" height="%smm" viewBox="0 0 %s %s" role="img">',
            self::NS,
            $this->namespaces($logo),
            $this->number($this->layout->width()),
            $this->number($this->layout->height()),
            $this->number($this->layout->width()),
            $this->number($this->layout->height())
        );

        $title = $this->options->title();

        if ($title !== null && $title !== '') {
            $svg .= '<title>' . $this->escape($title) . '</title>';
        }

        $svg .= $this->background();
        $svg .= $this->frame();
        $svg .= $this->code($matrix);

        if ($logo !== null) {
            $svg .= $this->logo($logo);
        }

        $svg .= $this->text($text);

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
     * Der xlink-Namensraum, deklariert nur wenn ihn etwas benutzt.
     *
     * Eine Bildmarke, die schon Pixel ist, kommt als
     * `<image xlink:href="data:…">` herein. Ohne die Deklaration am
     * Wurzelelement ist die Datei **kein gültiges XML**, und das faellt genau
     * dort auf, wo es am spaetesten stoert: im Browser sieht die Vorschau
     * innerhalb einer Seite richtig aus, weil sie der HTML-Parser liest, und
     * dieselbe Datei direkt geoeffnet zeigt einen Parserfehler. Dem SVG-Renderer
     * des Symbols ist das bekannt, diesem hier war es das nicht.
     */
    private function namespaces(?Logo $logo): string
    {
        return $logo instanceof RasterArtwork
            ? ' xmlns:xlink="' . self::XLINK_NS . '"'
            : '';
    }

    private function background(): string
    {
        return sprintf(
            '<rect width="%s" height="%s" fill="%s"/>',
            $this->number($this->layout->width()),
            $this->number($this->layout->height()),
            $this->options->lightColor()
        );
    }

    private function frame(): string
    {
        $inset = $this->layout->frameInset();

        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" fill="none" stroke="%s" stroke-width="%s"/>',
            $this->number($inset),
            $this->number($inset),
            $this->number($this->layout->width() - (2 * $inset)),
            $this->number($this->layout->height() - (2 * $inset)),
            $this->options->inkColor(),
            $this->number($this->layout->frameStroke())
        );
    }

    /**
     * The symbol, drawn in module units and scaled into place.
     *
     * Emitting the path in modules rather than in millimetres keeps every
     * number in it an integer. A module boundary is then exact rather than the
     * result of a rounded multiplication, which is what a scanner is measuring.
     */
    private function code(ModuleMatrix $matrix): string
    {
        $path = $this->modulePath($matrix);

        if ($path === '') {
            return '';
        }

        return sprintf(
            '<g transform="translate(%s %s) scale(%s)" shape-rendering="crispEdges">'
            . '<path fill="%s" d="%s"/></g>',
            $this->number($this->layout->codeX()),
            $this->number($this->layout->codeY()),
            $this->number($this->layout->moduleSizeFor($matrix->size())),
            $this->options->codeColor(),
            $path
        );
    }

    /**
     * Dark modules as horizontal runs.
     *
     * One rectangle per run rather than per module: the file is a third of the
     * size and every edge inside a run disappears, which is one seam fewer for
     * a RIP to put a hairline into.
     */
    private function modulePath(ModuleMatrix $matrix): string
    {
        $size = $matrix->size();
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            $x = 0;

            while ($x < $size) {
                if (!$matrix->isDark($x, $y)) {
                    $x++;

                    continue;
                }

                $run = 0;

                while ($x + $run < $size && $matrix->isDark($x + $run, $y)) {
                    $run++;
                }

                $path .= sprintf('M%d %dh%dv1h-%dz', $x, $y, $run, $run);
                $x += $run;
            }
        }

        return $path;
    }

    /**
     * The mark, fitted into its box and centred there.
     *
     * Anti-aliasing is turned back on for the subtree: it is right for curves
     * and wrong for modules, and the two sit on the same label.
     */
    private function logo(Logo $logo): string
    {
        if ($logo->width() <= 0.0 || $logo->height() <= 0.0) {
            return '';
        }

        $scale = min(
            $this->layout->logoWidth() / $logo->width(),
            $this->layout->logoHeight() / $logo->height()
        );

        $x = $this->layout->logoX() + (($this->layout->logoWidth() - ($logo->width() * $scale)) / 2);
        $y = $this->layout->logoY() + (($this->layout->logoHeight() - ($logo->height() * $scale)) / 2);

        return sprintf(
            '<g transform="translate(%s %s) scale(%s)" shape-rendering="geometricPrecision">%s</g>',
            $this->number($x),
            $this->number($y),
            $this->number($scale),
            $logo->markup()
        );
    }

    /**
     * @throws TextRejected
     */
    private function text(string $text): string
    {
        $set = LabelText::fit($this->layout, $this->font, $text);

        if ($set->isEmpty()) {
            return '';
        }

        $baselines = $set->baselines();
        $paths = '';

        foreach ($set->lines() as $index => $line) {
            $y = $baselines[$index];

            foreach ($line->glyphs() as $placed) {
                $paths .= sprintf(
                    '<path transform="translate(%s %s) scale(%s %s)" d="%s"/>',
                    $this->number($this->layout->textX() + $placed->offset()),
                    $this->number($y),
                    $this->number($line->scale()),
                    $this->number(-$line->scale()),
                    $placed->glyph()->outline()
                );
            }
        }

        if ($paths === '') {
            return '';
        }

        return sprintf(
            '<g fill="%s" shape-rendering="geometricPrecision">%s</g>',
            $this->options->inkColor(),
            $paths
        );
    }

    /**
     * A number for an attribute: fixed point, without a trailing run of zeros.
     *
     * Not `%s` on a float. PHP would write 1.0E-5 for a small scale factor, and
     * an SVG attribute has no exponent notation.
     */
    private function number(float $value): string
    {
        $formatted = number_format($value, 4, '.', '');

        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}

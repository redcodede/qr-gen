<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\Layout\LabelText;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Raster\PathFlattener;
use Redcodede\QrGen\Qr\Raster\ScanlineFiller;
use Redcodede\QrGen\Qr\Raster\Transform;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * The same label as a PNG, for the places an SVG is a nuisance.
 *
 * Written next to {@see LabelSvgRenderer} rather than inside it because the two
 * share a decision and not a technique: the geometry and the typesetting come
 * from {@see LabelLayout} and {@see LabelText}, which both call, so the two
 * files are the same picture by construction rather than by care.
 *
 * **Truecolour, not a palette.** {@see PngRenderer} writes one or eight bits
 * because a symbol is two colours and a logo is a small box that can be
 * quantised. A label is neither: it carries arbitrary artwork, a colour anyone
 * may set, and anti-aliased type over the whole width. Quantising all of that
 * would mean shipping a quantiser, and the thing it would save is bytes in a
 * file that is deflated anyway.
 *
 * **Module edges stay hard.** The pixel size is chosen so that one module is a
 * whole number of pixels, and the label's dimensions follow from that rather
 * than the other way round. The resolution therefore comes out a little under
 * the 600 dpi asked for, and the pHYs chunk carries the real figure, so the
 * file still prints at 90.05 by 36.28 millimetres. The alternative — a round
 * resolution and fractional modules — would put a grey half-pixel along every
 * module edge, which is the one place in the picture where softness costs a
 * scan.
 *
 * Everything that is not a module is drawn through the same rasteriser the
 * logo already used: flatten, fill, composite.
 */
final class LabelPngRenderer
{
    private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

    private const COLOR_TYPE_TRUECOLOR = 2;

    private const MM_PER_INCH = 25.4;

    /** Coverage of a pixel the filler reports as entirely inside an outline. */
    private const FULL = ScanlineFiller::FULL_COVERAGE;

    /** @var LabelLayout */
    private $layout;

    /** @var SvgFont */
    private $font;

    /** @var LabelOptions */
    private $options;

    /** @var int */
    private $dpi;

    /**
     * @throws InvalidArgument
     */
    public function __construct(
        LabelLayout $layout,
        SvgFont $font,
        ?LabelOptions $options = null,
        int $dpi = Preset::PRINT_DPI
    ) {
        if ($dpi < 1 || $dpi > 4800) {
            throw InvalidArgument::outOfRange('The resolution', $dpi, 1, 4800);
        }

        $this->layout = $layout;
        $this->font = $font;
        $this->options = $options ?? LabelOptions::default();
        $this->dpi = $dpi;
    }

    /**
     * @throws TextRejected if the text cannot be set in the space available
     */
    public function render(ModuleMatrix $matrix, string $text = '', ?Logo $logo = null): string
    {
        $modulePixels = $this->pixelsPerModule($matrix);
        $scale = $modulePixels / $this->layout->moduleSizeFor($matrix->size());

        $width = $this->round($this->layout->width() * $scale);
        $height = $this->round($this->layout->height() * $scale);

        $light = $this->color('lightColor', $this->options->lightColor());
        $code = $this->color('codeColor', $this->options->codeColor());
        $ink = $this->color('inkColor', $this->options->inkColor());

        $rows = $this->baseRows($matrix, $modulePixels, $scale, $width, $height, $light, $code);
        $this->drawLogo($rows, $logo, $scale, $width, $height, $light);
        $this->drawInk($rows, $text, $scale, $width, $height, $ink);

        return self::SIGNATURE
            . $this->header($width, $height)
            . $this->physicalResolution($scale)
            . $this->chunk('IDAT', (string) gzcompress($this->filtered($rows), 9))
            . $this->chunk('IEND', '');
    }

    public function mimeType(): string
    {
        return 'image/png';
    }

    public function fileExtension(): string
    {
        return 'png';
    }

    /**
     * Whole pixels per module, which is what the whole raster is built around.
     */
    public function pixelsPerModule(ModuleMatrix $matrix): int
    {
        $millimetres = $this->layout->moduleSizeFor($matrix->size());

        if ($millimetres <= 0.0) {
            return 1;
        }

        return max(1, (int) round($millimetres / self::MM_PER_INCH * $this->dpi));
    }

    /**
     * @return array{0: int, 1: int} Width and height in pixels
     */
    public function pixelSize(ModuleMatrix $matrix): array
    {
        $scale = $this->pixelsPerModule($matrix) / $this->layout->moduleSizeFor($matrix->size());

        return [
            $this->round($this->layout->width() * $scale),
            $this->round($this->layout->height() * $scale),
        ];
    }

    /**
     * The resolution the file actually carries, which is not the one asked for.
     */
    public function effectiveDpi(ModuleMatrix $matrix): float
    {
        $millimetres = $this->layout->moduleSizeFor($matrix->size());

        return $millimetres <= 0.0
            ? (float) $this->dpi
            : $this->pixelsPerModule($matrix) / $millimetres * self::MM_PER_INCH;
    }

    /**
     * Background and modules: the part that is flat colour and needs no filler.
     *
     * Built as strings rather than as pixel arrays because a row of a symbol is
     * a handful of runs, and a run costs one `str_repeat` whatever its length.
     * The rows of one module row are identical, so the string is built once and
     * repeated.
     *
     * @return list<string> One RGB triplet per pixel, $width triplets per row
     */
    private function baseRows(
        ModuleMatrix $matrix,
        int $modulePixels,
        float $scale,
        int $width,
        int $height,
        string $light,
        string $code
    ): array {
        $blank = str_repeat($light, $width);
        $codeX = $this->round($this->layout->codeX() * $scale);
        $codeY = $this->round($this->layout->codeY() * $scale);
        $size = $matrix->size();

        $rows = [];

        for ($y = 0; $y < $height; $y++) {
            $moduleY = intdiv($y - $codeY, $modulePixels);

            if ($y < $codeY || $moduleY >= $size) {
                $rows[] = $blank;

                continue;
            }

            // Every pixel row of a module row is the same string, so it is
            // built on the first one and taken from the array afterwards.
            if (($y - $codeY) % $modulePixels !== 0) {
                $rows[] = $rows[$y - 1];

                continue;
            }

            $row = $blank;
            $x = 0;

            while ($x < $size) {
                if (!$matrix->isDark($x, $moduleY)) {
                    $x++;

                    continue;
                }

                $run = 0;

                while ($x + $run < $size && $matrix->isDark($x + $run, $moduleY)) {
                    $run++;
                }

                $row = substr_replace(
                    $row,
                    str_repeat($code, $run * $modulePixels),
                    ($codeX + ($x * $modulePixels)) * 3,
                    $run * $modulePixels * 3
                );

                $x += $run;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The mark, rasterised into its box and dropped in as whole rows.
     *
     * @param list<string> $rows
     */
    private function drawLogo(array &$rows, ?Logo $logo, float $scale, int $width, int $height, string $light): void
    {
        if ($logo === null || $logo->width() <= 0.0 || $logo->height() <= 0.0) {
            return;
        }

        $boxX = $this->round($this->layout->logoX() * $scale);
        $boxY = $this->round($this->layout->logoY() * $scale);
        $boxWidth = $this->round($this->layout->logoWidth() * $scale);
        $boxHeight = $this->round($this->layout->logoHeight() * $scale);

        if ($boxWidth < 1 || $boxHeight < 1) {
            return;
        }

        // The same arithmetic the SVG does, carried into pixels: fit, keep the
        // aspect ratio, centre the remainder.
        $fit = min($boxWidth / $logo->width(), $boxHeight / $logo->height());
        $offsetX = ($boxWidth - ($logo->width() * $fit)) / 2;
        $offsetY = ($boxHeight - ($logo->height() * $fit)) / 2;

        $pixels = LogoRaster::rasterise(
            $logo,
            $boxWidth,
            $boxHeight,
            Transform::translation($offsetX, $offsetY)->concat(Transform::scaling($fit, $fit)),
            $this->packed($light)
        );

        for ($row = 0; $row < $boxHeight; $row++) {
            $y = $boxY + $row;

            if ($y < 0 || $y >= $height) {
                continue;
            }

            $slice = '';

            foreach (array_slice($pixels, $row * $boxWidth, $boxWidth) as $packed) {
                $slice .= pack('C3', ($packed >> 16) & 0xFF, ($packed >> 8) & 0xFF, $packed & 0xFF);
            }

            $rows[$y] = substr_replace($rows[$y], $slice, $boxX * 3, $boxWidth * 3);
        }
    }

    /**
     * Frame and type, both in the ink colour, composited with their coverage.
     *
     * Only the rows the filler actually touched are unpacked. On this label
     * that is the frame and the text block, a quarter of the height; the rest
     * stays a string and is never looked at pixel by pixel.
     *
     * @param list<string> $rows
     */
    private function drawInk(array &$rows, string $text, float $scale, int $width, int $height, string $ink): void
    {
        $coverage = $this->frameCoverage($scale, $width, $height);

        foreach ($this->textCoverage($text, $scale, $width, $height) as $y => $columns) {
            foreach ($columns as $x => $amount) {
                $coverage[$y][$x] = max($coverage[$y][$x] ?? 0, $amount);
            }
        }

        $inkBytes = [ord($ink[0]), ord($ink[1]), ord($ink[2])];

        foreach ($coverage as $y => $columns) {
            if ($y < 0 || $y >= $height) {
                continue;
            }

            $bytes = unpack('C*', $rows[$y]);

            if ($bytes === false) {
                continue;
            }

            foreach ($columns as $x => $amount) {
                if ($x < 0 || $x >= $width) {
                    continue;
                }

                $offset = ($x * 3) + 1;

                if ($amount >= self::FULL) {
                    $bytes[$offset] = $inkBytes[0];
                    $bytes[$offset + 1] = $inkBytes[1];
                    $bytes[$offset + 2] = $inkBytes[2];

                    continue;
                }

                $alpha = $amount / self::FULL;

                for ($channel = 0; $channel < 3; $channel++) {
                    $under = $bytes[$offset + $channel];
                    $bytes[$offset + $channel] = (int) round(
                        ($inkBytes[$channel] * $alpha) + ($under * (1 - $alpha))
                    );
                }
            }

            $rows[$y] = pack('C*', ...$bytes);
        }
    }

    /**
     * The frame as two rectangles filled even-odd, which leaves the band
     * between them.
     *
     * A stroke is centred on its path, so the band runs half a stroke either
     * side of the inset. That is how the delivered file draws it, including the
     * sliver that falls outside the canvas and is clipped.
     *
     * @return array<int, array<int, int>>
     */
    private function frameCoverage(float $scale, int $width, int $height): array
    {
        $half = $this->layout->frameStroke() / 2;
        $inset = $this->layout->frameInset();

        $outer = $this->rectangle(
            ($inset - $half) * $scale,
            ($inset - $half) * $scale,
            ($this->layout->width() - $inset + $half) * $scale,
            ($this->layout->height() - $inset + $half) * $scale
        );

        $inner = $this->rectangle(
            ($inset + $half) * $scale,
            ($inset + $half) * $scale,
            ($this->layout->width() - $inset - $half) * $scale,
            ($this->layout->height() - $inset - $half) * $scale
        );

        return ScanlineFiller::coverage([$outer, $inner], $width, $height, true);
    }

    /**
     * Every glyph of every line, flattened into pixels.
     *
     * @return array<int, array<int, int>>
     *
     * @throws TextRejected
     */
    private function textCoverage(string $text, float $scale, int $width, int $height): array
    {
        $set = LabelText::fit($this->layout, $this->font, $text);

        if ($set->isEmpty()) {
            return [];
        }

        $baselines = $set->baselines();
        $subpaths = [];

        foreach ($set->lines() as $index => $line) {
            foreach ($line->glyphs() as $placed) {
                // Applied right to left: the glyph's own units are scaled and
                // flipped, moved to the baseline in millimetres, and the whole
                // label is then scaled into pixels.
                $transform = Transform::scaling($scale, $scale)
                    ->concat(Transform::translation(
                        $this->layout->textX() + $placed->offset(),
                        $baselines[$index]
                    ))
                    ->concat(Transform::scaling($line->scale(), -$line->scale()));

                foreach (PathFlattener::flatten($placed->glyph()->outline(), $transform) as $subpath) {
                    $subpaths[] = $subpath;
                }
            }
        }

        // Nonzero, not even-odd: a counter is drawn the other way round, and
        // even-odd would also punch a hole wherever two strokes of one letter
        // cross.
        return ScanlineFiller::coverage($subpaths, $width, $height, false);
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function rectangle(float $left, float $top, float $right, float $bottom): array
    {
        return [[$left, $top], [$right, $top], [$right, $bottom], [$left, $bottom]];
    }

    /**
     * Prepends the filter byte to every scanline.
     *
     * Rows that repeat — and on a symbol most of them do, one per pixel row of
     * a module — are written as filter type 2 with a body of zeros, which says
     * "the same as the row above". Deflate then packs each module row into a
     * few bytes instead of twenty-three copies. The rest is written unfiltered,
     * because working out a filter byte by byte in PHP costs more than the
     * bytes it saves on flat colour.
     *
     * @param list<string> $rows
     */
    private function filtered(array $rows): string
    {
        $raw = '';
        $previous = null;

        foreach ($rows as $row) {
            if ($previous !== null && $row === $previous) {
                $raw .= "\x02" . str_repeat("\x00", strlen($row));
            } else {
                $raw .= "\x00" . $row;
            }

            $previous = $row;
        }

        return $raw;
    }

    private function header(int $width, int $height): string
    {
        return $this->chunk('IHDR', pack(
            'NNCCCCC',
            $width,
            $height,
            8,
            self::COLOR_TYPE_TRUECOLOR,
            0,                          // deflate, the only defined method
            0,                          // adaptive filtering, the only defined method
            0                           // not interlaced
        ));
    }

    private function physicalResolution(float $pixelsPerMm): string
    {
        $perMetre = (int) round($pixelsPerMm * 1000);

        return $this->chunk('pHYs', pack('NNC', $perMetre, $perMetre, 1));
    }

    /**
     * @throws InvalidArgument on anything that is not #rrggbb
     */
    private function color(string $name, string $value): string
    {
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $value, $match) !== 1) {
            throw InvalidArgument::notAPrintColor($name, $value);
        }

        return (string) hex2bin($match[1]);
    }

    private function packed(string $rgb): int
    {
        return (ord($rgb[0]) << 16) | (ord($rgb[1]) << 8) | ord($rgb[2]);
    }

    private function round(float $value): int
    {
        return max(1, (int) round($value));
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}

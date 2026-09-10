<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\QrRenderer;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * Renders a module matrix as a PNG, written by hand rather than by an image
 * library.
 *
 * No `gd`, no `imagick`. A two-colour PNG is four chunks and a compressed
 * bitmap: `zlib` is in every standard PHP build and `crc32()` is part of the
 * language, so the format costs a hundred lines and the package keeps its
 * promise of needing no image extension. What an image library would add here
 * is a dependency, an installation step on the server, and an 8-bit or 24-bit
 * buffer where one bit per pixel is exactly right.
 *
 * **One bit per pixel, two-entry palette.** That is what a QR code is and what
 * a RIP wants for line art: no anti-aliasing to soften a module edge, no
 * greyscale for a press to screen into a halftone, and a file a fraction of the
 * size. Each module is a whole number of pixels, so every edge in the file is
 * an edge in the raster.
 *
 * **It carries no artwork.** Putting a logo in here would mean rasterising
 * vector paths — beziers, arcs, fill rules — which is a 2D rasteriser, not a
 * hundred lines, and it is the reason the logo variant is offered as SVG only.
 * That is also the better answer for print: a print shop takes vector artwork,
 * and anyone laying out the page can export a raster from the SVG at whatever
 * size they need.
 */
final class PngRenderer implements QrRenderer
{
    private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

    /** Colour type 3: each pixel is an index into a palette. */
    private const COLOR_TYPE_PALETTE = 3;

    /** @var PngOptions */
    private $options;

    public function __construct(?PngOptions $options = null)
    {
        $this->options = $options ?? PngOptions::default();
    }

    public function render(ModuleMatrix $matrix): string
    {
        $quietZone = $this->options->quietZone();
        $extent = $matrix->size() + (2 * $quietZone);
        $scale = $this->options->pixelsPerModule($extent);
        $pixels = $extent * $scale;

        return self::SIGNATURE
            . $this->header($pixels)
            . $this->palette()
            . $this->transparency()
            . $this->physicalResolution()
            . $this->imageData($matrix, $quietZone, $extent, $scale, $pixels)
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
     * Pixel edge length of the file this matrix would produce.
     */
    public function pixelWidth(ModuleMatrix $matrix): int
    {
        $extent = $matrix->size() + (2 * $this->options->quietZone());

        return $extent * $this->options->pixelsPerModule($extent);
    }

    public function pixelsPerModule(ModuleMatrix $matrix): int
    {
        return $this->options->pixelsPerModule($matrix->size() + (2 * $this->options->quietZone()));
    }

    /**
     * What the file actually measures when placed at its declared resolution.
     * A shade over what was ordered, because the pixels per module were rounded
     * up.
     */
    public function printedSizeMm(ModuleMatrix $matrix): float
    {
        return $this->pixelWidth($matrix) / $this->options->dpi() * 25.4;
    }

    private function header(int $pixels): string
    {
        return $this->chunk('IHDR', pack(
            'NNCCCCC',
            $pixels,
            $pixels,
            1,                          // one bit per pixel
            self::COLOR_TYPE_PALETTE,
            0,                          // deflate, the only defined method
            0,                          // adaptive filtering, the only defined method
            0                           // not interlaced
        ));
    }

    /**
     * Index 0 is light, index 1 is dark. Chosen so a set bit means a dark
     * module, which is how the matrix reads.
     */
    private function palette(): string
    {
        [$lr, $lg, $lb] = $this->options->lightRgb();
        [$dr, $dg, $db] = $this->options->darkRgb();

        return $this->chunk('PLTE', pack('C6', $lr, $lg, $lb, $dr, $dg, $db));
    }

    private function transparency(): string
    {
        if (!$this->options->hasTransparentBackground()) {
            return '';
        }

        // One alpha byte per palette entry: the light index disappears, the
        // dark one stays opaque.
        return $this->chunk('tRNS', pack('C2', 0, 255));
    }

    private function physicalResolution(): string
    {
        $perMetre = $this->options->pixelsPerMetre();

        return $this->chunk('pHYs', pack('NNC', $perMetre, $perMetre, 1));
    }

    /**
     * Packs the matrix into scanlines, eight pixels to the byte, most
     * significant bit first, each row preceded by its filter byte.
     *
     * Filter 0 — none — on purpose. The filters exist to make photographic data
     * compress better by predicting each byte from its neighbours. Two-colour
     * data in long identical runs already compresses to almost nothing, and a
     * filter would only add a pass over the buffer.
     */
    private function imageData(
        ModuleMatrix $matrix,
        int $quietZone,
        int $extent,
        int $scale,
        int $pixels
    ): string {
        $bytesPerRow = intdiv($pixels + 7, 8);
        $rows = $matrix->rows();
        $size = $matrix->size();
        $raw = '';

        for ($moduleY = 0; $moduleY < $extent; $moduleY++) {
            $y = $moduleY - $quietZone;
            $line = array_fill(0, $bytesPerRow, 0);

            if ($y >= 0 && $y < $size) {
                for ($moduleX = 0; $moduleX < $extent; $moduleX++) {
                    $x = $moduleX - $quietZone;

                    if ($x < 0 || $x >= $size || !$rows[$y][$x]) {
                        continue;
                    }

                    $start = $moduleX * $scale;

                    for ($offset = 0; $offset < $scale; $offset++) {
                        $pixel = $start + $offset;
                        $line[$pixel >> 3] |= 0x80 >> ($pixel & 7);
                    }
                }
            }

            // Every pixel row of a module is the same row of bytes, so the
            // packing happens once per module row and is repeated.
            $packed = "\x00" . pack('C*', ...$line);
            $raw .= str_repeat($packed, $scale);
        }

        // gzcompress is zlib (RFC 1950), which is what IDAT holds — gzdeflate
        // would be raw deflate and gzencode would be gzip. Neither is this.
        return $this->chunk('IDAT', (string) gzcompress($raw, 9));
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}

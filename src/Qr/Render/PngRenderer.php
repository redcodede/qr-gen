<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\QrRenderer;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Logo\LogoPlacement;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Raster\Palette;
use Redcodede\QrGen\Qr\Raster\Transform;

/**
 * Renders a module matrix as a PNG, written by hand rather than by an image
 * library.
 *
 * No `gd`, no `imagick`. A palette PNG is four chunks and a compressed bitmap:
 * `zlib` is in every standard PHP build and `crc32()` is part of the language,
 * so the format costs a couple of hundred lines and the package keeps its
 * promise of needing no image extension. What an image library would add here
 * is a dependency, an installation step on the server, and a buffer wider than
 * the data needs.
 *
 * **Two bit depths, chosen by what is in the picture.**
 *
 * A plain symbol goes out at **one bit per pixel** with a two-entry palette.
 * That is exactly what a QR code is and what a RIP wants for line art: no
 * anti-aliasing to soften a module edge, no greyscale for a press to screen
 * into a halftone, and a file a fraction of the size. Each module is a whole
 * number of pixels, so every edge in the file is an edge in the raster.
 *
 * A symbol with artwork goes out at **eight bits per pixel**, still a palette.
 * The logo has colours of its own and curved edges that need anti-aliasing to
 * survive at this size, and neither fits in one bit. It stays indexed rather
 * than becoming truecolour because flat artwork produces few distinct colours —
 * the GVÖ mark lands around forty of the 256 available — so a byte per pixel is
 * a third of the size of RGB and the modules still cost two palette entries
 * with no anti-aliasing anywhere near them.
 *
 * The artwork itself is drawn by Qr\Raster, which flattens the logo's outlines
 * and fills them. That subset is narrower than what the SVG renderer will
 * embed: arcs, strokes and group opacity are refused by name rather than
 * approximated, because a raster that quietly differs from the vector of the
 * same logo is the failure nobody would catch before the print run.
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
        $placement = $this->placement($matrix);

        if ($placement === null) {
            return self::SIGNATURE
                . $this->header($pixels, 1)
                . $this->palette([$this->packed($this->options->lightRgb()), $this->packed($this->options->darkRgb())])
                . $this->transparency()
                . $this->physicalResolution()
                . $this->chunk('IDAT', (string) gzcompress(
                    $this->monochromeRaster($matrix, $quietZone, $extent, $scale, $pixels),
                    9
                ))
                . $this->chunk('IEND', '');
        }

        [$entries, $raster] = $this->indexedRaster($matrix, $placement, $quietZone, $extent, $scale, $pixels);

        return self::SIGNATURE
            . $this->header($pixels, 8)
            . $this->palette($entries)
            . $this->physicalResolution()
            . $this->chunk('IDAT', (string) gzcompress($raster, 9))
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

    /**
     * Same box, same rules as the SVG renderer — deliberately, so a logo cannot
     * land in one place on the vector and another on the raster.
     */
    private function placement(ModuleMatrix $matrix): ?LogoPlacement
    {
        if (!$this->options->hasLogo()) {
            return null;
        }

        // The cleared area has to read as light. With a transparent background
        // whatever sits behind the symbol shows through it instead, and a
        // scanner then sees neither light nor dark where it needs light.
        if ($this->options->hasTransparentBackground()) {
            throw InvalidArgument::logoNeedsOpaqueBackdrop();
        }

        /** @var \Redcodede\QrGen\Qr\Logo\LogoBox $box */
        $box = $this->options->logoBox();

        return $box->placeIn($matrix);
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
    private function monochromeRaster(
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

        return $raw;
    }

    /**
     * Builds the eight-bit raster: modules everywhere, artwork over the cleared
     * box.
     *
     * Only the box goes through the rasteriser and the palette, which is what
     * keeps this affordable. A 50 mm symbol at 600 dpi is 1.4 million pixels
     * and would be a 1.4-million-entry PHP array; the box is a twentieth of
     * that, and everything outside it is two colours that can be written
     * straight out as bytes.
     *
     * @return array{0: list<int>, 1: string} Palette entries, then the raw scanlines
     */
    private function indexedRaster(
        ModuleMatrix $matrix,
        LogoPlacement $placement,
        int $quietZone,
        int $extent,
        int $scale,
        int $pixels
    ): array {
        $light = $this->packed($this->options->lightRgb());
        $dark = $this->packed($this->options->darkRgb());

        $boxX = ($placement->x() + $quietZone) * $scale;
        $boxY = ($placement->y() + $quietZone) * $scale;
        $boxWidth = $placement->width() * $scale;
        $boxHeight = $placement->height() * $scale;

        /** @var \Redcodede\QrGen\Qr\Contract\Logo $logo */
        $logo = $this->options->logo();

        [$entries, $indices] = Palette::index(
            LogoRaster::rasterise(
                $logo,
                $boxWidth,
                $boxHeight,
                $this->artworkTransform($placement, $scale, $logo->width(), $logo->height()),
                $light
            ),
            [$light, $dark]
        );

        $lightByte = chr((int) array_search($light, $entries, true));
        $darkByte = chr((int) array_search($dark, $entries, true));

        $rows = $matrix->rows();
        $size = $matrix->size();
        $raw = '';

        for ($moduleY = 0; $moduleY < $extent; $moduleY++) {
            $y = $moduleY - $quietZone;
            $line = '';

            for ($moduleX = 0; $moduleX < $extent; $moduleX++) {
                $x = $moduleX - $quietZone;

                // Modules under the box are skipped rather than drawn and
                // covered, exactly as in the SVG. The artwork then sits on the
                // light backdrop and not on top of dark modules, so a rounding
                // difference at the box edge cannot leave a black sliver.
                $isDark = $y >= 0 && $y < $size && $x >= 0 && $x < $size
                    && $rows[$y][$x]
                    && !$placement->covers($x, $y);

                $line .= str_repeat($isDark ? $darkByte : $lightByte, $scale);
            }

            $top = $moduleY * $scale;

            if ($top + $scale <= $boxY || $top >= $boxY + $boxHeight) {
                $raw .= str_repeat("\x00" . $line, $scale);

                continue;
            }

            for ($offset = 0; $offset < $scale; $offset++) {
                $row = $top + $offset;

                if ($row < $boxY || $row >= $boxY + $boxHeight) {
                    $raw .= "\x00" . $line;

                    continue;
                }

                $slice = array_slice($indices, ($row - $boxY) * $boxWidth, $boxWidth);
                $raw .= "\x00" . substr_replace($line, pack('C*', ...$slice), $boxX, $boxWidth);
            }
        }

        return [$entries, $raw];
    }

    /**
     * Maps the logo's own coordinates onto pixels within the cleared box.
     *
     * The same arithmetic the SVG renderer does in module units — fit to the
     * drawable area, keep the aspect ratio, centre the remainder — carried one
     * step further into pixels. Both have to agree, because the whole point of
     * shipping two formats is that they are the same picture.
     */
    private function artworkTransform(
        LogoPlacement $placement,
        int $scale,
        float $logoWidth,
        float $logoHeight
    ): Transform {
        $availableWidth = $placement->drawableWidth();
        $availableHeight = $placement->drawableHeight();

        $fit = min($availableWidth / $logoWidth, $availableHeight / $logoHeight);

        // Relative to the box, not to the symbol: the rasteriser draws into a
        // block that starts at the box's top left corner.
        $x = ($placement->drawableX() - $placement->x()) + (($availableWidth - ($logoWidth * $fit)) / 2);
        $y = ($placement->drawableY() - $placement->y()) + (($availableHeight - ($logoHeight * $fit)) / 2);

        return Transform::translation($x * $scale, $y * $scale)
            ->concat(Transform::scaling($fit * $scale, $fit * $scale));
    }

    private function header(int $pixels, int $bitDepth): string
    {
        return $this->chunk('IHDR', pack(
            'NNCCCCC',
            $pixels,
            $pixels,
            $bitDepth,
            self::COLOR_TYPE_PALETTE,
            0,                          // deflate, the only defined method
            0,                          // adaptive filtering, the only defined method
            0                           // not interlaced
        ));
    }

    /**
     * @param list<int> $entries Packed 0xRRGGBB, in index order
     */
    private function palette(array $entries): string
    {
        $data = '';

        foreach ($entries as $color) {
            $data .= pack('C3', ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
        }

        return $this->chunk('PLTE', $data);
    }

    private function transparency(): string
    {
        if (!$this->options->hasTransparentBackground()) {
            return '';
        }

        // One alpha byte per palette entry: the light index disappears, the
        // dark one stays opaque. Only the plain symbol reaches this — artwork
        // needs an opaque backdrop, and placement() refuses the combination.
        return $this->chunk('tRNS', pack('C2', 0, 255));
    }

    private function physicalResolution(): string
    {
        $perMetre = $this->options->pixelsPerMetre();

        return $this->chunk('pHYs', pack('NNC', $perMetre, $perMetre, 1));
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function packed(array $rgb): int
    {
        return ($rgb[0] << 16) | ($rgb[1] << 8) | $rgb[2];
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}

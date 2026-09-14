<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Contract\RasterArtwork;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Raster\PngDecoder;

/**
 * A logo delivered as a PNG.
 *
 * The counterpart to SvgLogo, and it answers the same question differently. An
 * SVG is a document and has to be rebuilt from tokens before it is safe to
 * embed. A PNG is not a document: it cannot hold a script or a reference to
 * anything outside itself, so there is no markup to sanitise. What it can hold
 * is **text, EXIF and colour profiles**, and those travel — a camera's
 * coordinates or a designer's name inside an artwork file would end up wherever
 * the symbol ends up. So the file is rewritten with only the chunks that carry
 * the picture, which is the same rule by a different route.
 *
 * **It is worse than a vector logo and that is not a bug.** A raster has one
 * resolution; scaled past it, it goes soft, and no amount of care in this class
 * changes that. What this class can do is not make it worse than the file
 * allows, and say plainly how many pixels the print size needs — see
 * recommendedPixels(). Where a vector original exists, it is the better
 * delivery every time.
 *
 * For the SVG output the picture is embedded as an <image> with a data URI,
 * never as a link. A linked image would make the viewer's browser fetch the
 * file from somewhere, which is the one real leak the whole design avoids; and
 * a print shop would receive an SVG that renders as an empty box.
 */
final class PngLogo implements Logo, RasterArtwork
{
    /** @var string The file, stripped to the chunks that carry the picture */
    private $png;

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var string|null Decoded lazily: only the PNG renderer needs the pixels */
    private $rgba;

    private function __construct(string $png, int $width, int $height)
    {
        $this->png = $png;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * @throws LogoRejected if the file is not a PNG this package can read
     */
    public static function fromBinary(string $bytes): self
    {
        // Decoded in full rather than merely inspected, so a file that will
        // fail is refused now — while the caller still has a filename to put
        // in the message — instead of halfway through rendering.
        $decoded = PngDecoder::decode($bytes);

        $logo = new self(
            PngDecoder::stripToPicture($bytes),
            $decoded['width'],
            $decoded['height']
        );

        $logo->rgba = $decoded['rgba'];

        return $logo;
    }

    /**
     * An <image> carrying the whole file, sized in its own pixels.
     *
     * The width and height are the pixel dimensions because that is what the
     * Logo contract asks for — an intrinsic size, whose unit does not matter as
     * long as both share it. The renderer scales the group around this, so the
     * numbers here only set the aspect ratio.
     *
     * The reference is written as `xlink:href`, which SVG 1.1 defined and SVG 2
     * deprecated but still requires every renderer to honour. The modern `href`
     * alone would lose print software older than about 2018; both together
     * would carry the whole file twice, and at fifty kilobytes a copy that is
     * not a rounding error. The old spelling is the one that works everywhere.
     */
    public function markup(): string
    {
        return sprintf(
            '<image width="%d" height="%d" xlink:href="data:image/png;base64,%s"/>',
            $this->width,
            $this->height,
            base64_encode($this->png)
        );
    }

    public function width(): float
    {
        return (float) $this->width;
    }

    public function height(): float
    {
        return (float) $this->height;
    }

    public function pixels(): string
    {
        if ($this->rgba === null) {
            $this->rgba = PngDecoder::decode($this->png)['rgba'];
        }

        return $this->rgba;
    }

    public function pixelWidth(): int
    {
        return $this->width;
    }

    public function pixelHeight(): int
    {
        return $this->height;
    }

    /**
     * The file as it will be embedded — stripped of everything but the picture.
     */
    public function png(): string
    {
        return $this->png;
    }

    /**
     * Whether the artwork has as many pixels as the printed size needs.
     *
     * The comparison is against the longer side of the area the logo will
     * occupy, in output pixels. Below that the file is being enlarged and will
     * look soft; at or above it, it is being reduced, which is where a raster
     * behaves well.
     */
    public function isSharpEnoughFor(int $targetWidth, int $targetHeight): bool
    {
        return $this->width >= $targetWidth && $this->height >= $targetHeight;
    }

    /**
     * How many pixels the artwork would need to fill a given area without being
     * enlarged, keeping its own aspect ratio.
     *
     * @return array{0: int, 1: int} Width and height
     */
    public function recommendedPixels(int $targetWidth, int $targetHeight): array
    {
        $factor = max($targetWidth / $this->width, $targetHeight / $this->height);

        if ($factor <= 1.0) {
            return [$this->width, $this->height];
        }

        return [(int) ceil($this->width * $factor), (int) ceil($this->height * $factor)];
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Logo\LogoBox;

/**
 * Settings for the PNG renderer, expressed the way print work is ordered:
 * how large it goes on paper and at what resolution.
 *
 * The pixel dimensions are not a setting. They are worked out from the physical
 * size, the resolution and the number of modules, and each module gets a whole
 * number of pixels — a module boundary that falls between pixels is a module
 * edge the raster has to fudge, and a fudged edge on a QR code is exactly what
 * a scanner reads wrong. Rounding is always upward, so the file is never
 * smaller than what was ordered and printing it at the ordered size is a
 * fraction of a percent of scaling down, never up.
 */
final class PngOptions
{
    /**
     * Two colours only, so hex with an alpha component is refused rather than
     * silently truncated. A PNG for print has no business carrying
     * transparency in its colours; see withTransparentBackground() for the one
     * case where transparency is expressed, which is a palette entry, not a
     * colour channel.
     */
    private const COLOR_PATTERN = '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i';

    /**
     * 600 dpi, the usual resolution for line art.
     *
     * Photographs go at 300 because the eye cannot resolve more in a
     * continuous tone. A QR code is not continuous tone: it is hard edges, and
     * an edge benefits from every dot the press can place. 1200 is used for
     * fine line work and doubles the file for an edge nobody will see at this
     * module size.
     */
    public const DEFAULT_DPI = 600;

    /** 50 mm — the 5 by 5 centimetres the printed code has to reach. */
    public const DEFAULT_PRINT_SIZE_MM = 50.0;

    private const MM_PER_INCH = 25.4;

    /** @var int */
    private $dpi = self::DEFAULT_DPI;

    /** @var float */
    private $printSizeMm = self::DEFAULT_PRINT_SIZE_MM;

    /** @var int */
    private $quietZone = SvgOptions::SPEC_QUIET_ZONE;

    /** @var string */
    private $darkColor = '#000000';

    /** @var string */
    private $lightColor = '#ffffff';

    /** @var bool */
    private $transparentBackground = false;

    /** @var Logo|null */
    private $logo;

    /** @var LogoBox|null */
    private $logoBox;

    public static function default(): self
    {
        return new self();
    }

    /**
     * Places artwork in the middle of the symbol, as the SVG renderer does.
     *
     * Same box, same placement rules, same refusal over a function pattern —
     * the two renderers share LogoBox precisely so a logo cannot sit in one
     * place on the vector and another on the raster.
     *
     * What differs is that the PNG has to draw the artwork itself rather than
     * hand the markup on, so a logo the rasteriser cannot draw is refused at
     * render time. Redcodede\QrGen\Qr\Raster\LogoRaster::rejectionFor() answers
     * that question in advance, for a caller that would rather not offer a
     * download that cannot be produced.
     */
    public function withLogo(Logo $logo, LogoBox $box): self
    {
        $clone = clone $this;
        $clone->logo = $logo;
        $clone->logoBox = $box;

        return $clone;
    }

    public function withoutLogo(): self
    {
        $clone = clone $this;
        $clone->logo = null;
        $clone->logoBox = null;

        return $clone;
    }

    public function logo(): ?Logo
    {
        return $this->logo;
    }

    public function logoBox(): ?LogoBox
    {
        return $this->logoBox;
    }

    public function hasLogo(): bool
    {
        return $this->logo !== null && $this->logoBox !== null;
    }

    /**
     * @throws InvalidArgument
     */
    public function withDpi(int $dpi): self
    {
        if ($dpi < 72 || $dpi > 4800) {
            throw InvalidArgument::outOfRange('Resolution in dpi', $dpi, 72, 4800);
        }

        $clone = clone $this;
        $clone->dpi = $dpi;

        return $clone;
    }

    /**
     * Intended printed edge length in millimetres. The file comes out at least
     * this large at the chosen resolution.
     *
     * @throws InvalidArgument
     */
    public function withPrintSizeMm(float $millimetres): self
    {
        if ($millimetres < 5.0 || $millimetres > 2000.0) {
            throw InvalidArgument::printSizeOutOfRange($millimetres);
        }

        $clone = clone $this;
        $clone->printSizeMm = $millimetres;

        return $clone;
    }

    /**
     * @throws InvalidArgument
     */
    public function withQuietZone(int $modules): self
    {
        if ($modules < 0 || $modules > 32) {
            throw InvalidArgument::outOfRange('Quiet zone', $modules, 0, 32);
        }

        $clone = clone $this;
        $clone->quietZone = $modules;

        return $clone;
    }

    /**
     * @throws InvalidArgument
     */
    public function withColors(string $dark, string $light): self
    {
        foreach (['Dark color' => $dark, 'Light color' => $light] as $name => $color) {
            if (preg_match(self::COLOR_PATTERN, $color) !== 1) {
                throw InvalidArgument::notAPrintColor($name, $color);
            }
        }

        $clone = clone $this;
        $clone->darkColor = $dark;
        $clone->lightColor = $light;

        return $clone;
    }

    /**
     * Marks the light palette entry transparent.
     *
     * Off by default and best left off for print: a RIP that has to decide what
     * sits behind a transparent area is a RIP making a decision nobody
     * documented. Useful on screen.
     */
    public function withTransparentBackground(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->transparentBackground = $enabled;

        return $clone;
    }

    public function dpi(): int
    {
        return $this->dpi;
    }

    public function printSizeMm(): float
    {
        return $this->printSizeMm;
    }

    public function quietZone(): int
    {
        return $this->quietZone;
    }

    public function hasTransparentBackground(): bool
    {
        return $this->transparentBackground;
    }

    /**
     * Smallest pixel edge length that reaches the ordered physical size.
     */
    public function minimumPixels(): int
    {
        return (int) ceil($this->printSizeMm / self::MM_PER_INCH * $this->dpi);
    }

    /**
     * Whole pixels per module, rounded up so the file never falls short.
     */
    public function pixelsPerModule(int $extentInModules): int
    {
        return max(1, (int) ceil($this->minimumPixels() / $extentInModules));
    }

    /**
     * Physical resolution for the pHYs chunk, in pixels per metre.
     *
     * This is the chunk that makes the difference between a large image and a
     * print-ready one: without it a layout application places the file at its
     * own default — usually 72 dpi — and the code lands at eight times the
     * intended size, which somebody then scales down by eye.
     */
    public function pixelsPerMetre(): int
    {
        return (int) round($this->dpi / 0.0254);
    }

    /**
     * @return array{0: int, 1: int, 2: int} Red, green, blue
     */
    public function darkRgb(): array
    {
        return self::toRgb($this->darkColor);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function lightRgb(): array
    {
        return self::toRgb($this->lightColor);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toRgb(string $hex): array
    {
        $digits = ltrim($hex, '#');

        if (strlen($digits) === 3) {
            $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
        }

        return [
            (int) hexdec(substr($digits, 0, 2)),
            (int) hexdec(substr($digits, 2, 2)),
            (int) hexdec(substr($digits, 4, 2)),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr;

use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Render\PngOptions;
use Redcodede\QrGen\Qr\Render\SvgOptions;

/**
 * The settled house configuration, in one place.
 *
 * These are decisions, not defaults to be tuned per call site. They live here
 * rather than in SvgOptions because SvgOptions has to stay the library's
 * honest, specification-conforming baseline — a package that shipped a
 * non-conforming quiet zone as its own default would be lying to anyone who
 * installed it. This class is where a project says what it decided.
 *
 * The error correction level is deliberately absent: it is not a decision to
 * be made in advance. It follows from the payload and the logo box, and LogoFit
 * works it out. Fixing it here would be fixing the wrong end.
 */
final class Preset
{
    /** Edge length of one module in pixels, for the width and height attributes. */
    public const MODULE_SIZE = 13;

    /**
     * Two modules of quiet zone — a **deliberate deviation** from ISO/IEC
     * 18004, which requires four.
     *
     * It holds only on the condition that the surrounding layout contributes
     * the missing two modules as white space. A symbol placed with two modules
     * of its own and artwork butting straight up against it has half the light
     * border a scanner expects, and becomes unreliable exactly where it matters
     * — on a small label, at an angle, in poor light.
     *
     * Whoever places the symbol owns that condition. It is not something this
     * package can check, and it is settled by a print proof rather than by
     * this comment.
     */
    public const QUIET_ZONE = 2;

    /** Cleared area for the artwork: logo plus the light margin around it. */
    public const LOGO_BOX_MODULES = 11;

    /** Light border inside the box, between the artwork and the modules. */
    public const LOGO_MARGIN_MODULES = 1;

    /**
     * Resolution of the raster deliverable, in dots per inch.
     *
     * 600 rather than 300: 300 is the figure for photographs, where the eye
     * cannot resolve more in a continuous tone. A QR code is hard edges, and an
     * edge benefits from every dot the press can place.
     */
    public const PRINT_DPI = 600;

    /**
     * Printed edge length the raster has to reach, in millimetres.
     *
     * The PNG comes out at least this large at the resolution above, so it can
     * go on paper at 5 by 5 centimetres without being scaled up. Larger is
     * free; scaling a raster up is not.
     */
    public const PRINT_SIZE_MM = 50.0;

    /**
     * Pure black on pure white.
     *
     * For the press this means **100 % K, not a rich black.** A black mixed
     * from four inks needs all four plates to register perfectly, and where
     * they do not, a module edge softens into a coloured fringe — precisely the
     * edge a scanner is measuring. Neither PNG nor SVG can carry CMYK at all,
     * so the conversion happens in prepress, and this is the instruction to
     * give with the file.
     */
    public const DARK_COLOR = '#000000';
    public const LIGHT_COLOR = '#ffffff';

    private function __construct()
    {
    }

    public static function svgOptions(): SvgOptions
    {
        return SvgOptions::default()
            ->withModuleSize(self::MODULE_SIZE)
            ->withQuietZone(self::QUIET_ZONE)
            ->withColors(self::DARK_COLOR, self::LIGHT_COLOR);
    }

    /**
     * The raster deliverable: two colours, one bit per pixel, sized from the
     * physical dimensions rather than from a pixel count someone picked.
     */
    public static function pngOptions(): PngOptions
    {
        return PngOptions::default()
            ->withDpi(self::PRINT_DPI)
            ->withPrintSizeMm(self::PRINT_SIZE_MM)
            ->withQuietZone(self::QUIET_ZONE)
            ->withColors(self::DARK_COLOR, self::LIGHT_COLOR);
    }

    public static function logoBox(): LogoBox
    {
        return LogoBox::square(self::LOGO_BOX_MODULES, self::LOGO_MARGIN_MODULES);
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

/**
 * Turns a block of colours into palette indices.
 *
 * A PNG that carries artwork cannot stay at one bit per pixel, but it does not
 * have to become truecolour either. Flat artwork over a flat backdrop produces
 * very few distinct colours: two logo colours plus the seventeen coverage steps
 * the sampling grid can express, and the overlaps between them. The mark used in the tests
 * lands around forty. A palette holds up to 256, so the file stays one byte per
 * pixel instead of three, and the modules keep sharing two entries with no
 * anti-aliasing anywhere near them.
 *
 * The reduction below is the safety net rather than the plan. It only runs on
 * artwork with gradients baked into it or dozens of tints, and it does the
 * obvious thing: keep the colours that cover the most pixels, and map the rest
 * to the nearest survivor. A colour that occupies four pixels moving by a shade
 * is not what anyone will notice about a logo a centimetre wide.
 */
final class Palette
{
    /** A PNG palette is at most 256 entries, whatever the bit depth. */
    public const MAX_ENTRIES = 256;

    private function __construct()
    {
    }

    /**
     * @param list<int> $pixels   Packed 0xRRGGBB
     * @param list<int> $reserved Colours that must take the first indices, in order
     *
     * @return array{0: list<int>, 1: list<int>} Palette entries, then one index per pixel
     */
    public static function index(array $pixels, array $reserved): array
    {
        $counts = array_count_values($pixels);

        // Reserved entries are placed first and unconditionally: index 0 has to
        // be the light colour so a transparency chunk can point at it, and
        // index 1 the dark one, whether or not the artwork happens to use them.
        $palette = [];
        $indexOf = [];

        foreach ($reserved as $color) {
            if (!isset($indexOf[$color])) {
                $indexOf[$color] = count($palette);
                $palette[] = $color;
            }
        }

        arsort($counts);

        foreach ($counts as $color => $unused) {
            if (count($palette) >= self::MAX_ENTRIES) {
                break;
            }

            if (!isset($indexOf[$color])) {
                $indexOf[$color] = count($palette);
                $palette[] = $color;
            }
        }

        $indices = [];
        $nearest = [];

        foreach ($pixels as $color) {
            if (isset($indexOf[$color])) {
                $indices[] = $indexOf[$color];

                continue;
            }

            if (!isset($nearest[$color])) {
                $nearest[$color] = self::closest($color, $palette);
            }

            $indices[] = $nearest[$color];
        }

        return [$palette, $indices];
    }

    /**
     * Nearest palette entry by squared distance in RGB.
     *
     * Squared because the ordering is all that matters and the square root
     * would not change it. RGB rather than a perceptual space because this runs
     * on artwork that already overflowed the palette, where the choice between
     * two nearly identical tints is not worth a colour-space conversion.
     *
     * @param list<int> $palette
     */
    private static function closest(int $color, array $palette): int
    {
        $red = ($color >> 16) & 0xFF;
        $green = ($color >> 8) & 0xFF;
        $blue = $color & 0xFF;

        $best = 0;
        $bestDistance = PHP_INT_MAX;

        foreach ($palette as $index => $candidate) {
            $dr = $red - (($candidate >> 16) & 0xFF);
            $dg = $green - (($candidate >> 8) & 0xFF);
            $db = $blue - ($candidate & 0xFF);
            $distance = ($dr * $dr) + ($dg * $dg) + ($db * $db);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $index;
            }
        }

        return $best;
    }
}

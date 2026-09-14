<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * Reads the colour notations that appear in exported artwork.
 *
 * Hex is what Illustrator and Figma write, and it is what every logo this
 * package has been handed uses. The functional notations are here because they
 * are cheap and a hand-edited file may carry them.
 *
 * Named colours are a deliberate short list rather than the specification's
 * hundred and forty-seven. A name outside it is refused by name, which tells
 * whoever exported the file exactly what to change; guessing at "papayawhip"
 * and getting it wrong would print the wrong colour with no warning.
 */
final class Color
{
    /** @var array<string, int> */
    private const NAMED = [
        'black' => 0x000000,
        'silver' => 0xC0C0C0,
        'gray' => 0x808080,
        'grey' => 0x808080,
        'white' => 0xFFFFFF,
        'maroon' => 0x800000,
        'red' => 0xFF0000,
        'purple' => 0x800080,
        'fuchsia' => 0xFF00FF,
        'magenta' => 0xFF00FF,
        'green' => 0x008000,
        'lime' => 0x00FF00,
        'olive' => 0x808000,
        'yellow' => 0xFFFF00,
        'navy' => 0x000080,
        'blue' => 0x0000FF,
        'teal' => 0x008080,
        'aqua' => 0x00FFFF,
        'cyan' => 0x00FFFF,
    ];

    private function __construct()
    {
    }

    /**
     * A packed 0xRRGGBB value, or null where nothing is painted.
     *
     * @throws LogoRejected on a notation this rasteriser cannot read
     */
    public static function parse(string $value): ?int
    {
        $value = strtolower(trim($value));

        if ($value === '' || $value === 'none' || $value === 'transparent') {
            return null;
        }

        if (isset(self::NAMED[$value])) {
            return self::NAMED[$value];
        }

        if (preg_match('/^#([0-9a-f]{3})$/', $value, $match) === 1) {
            $digits = $match[1];

            return (int) hexdec(
                $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2]
            );
        }

        if (preg_match('/^#([0-9a-f]{6})$/', $value, $match) === 1) {
            return (int) hexdec($match[1]);
        }

        if (preg_match('/^rgba?\(([^)]*)\)$/', $value, $match) === 1) {
            return self::functional($match[1], $value);
        }

        throw LogoRejected::unreadableColor($value);
    }

    /**
     * Alpha as a fraction, from a fill-opacity, stroke-opacity or opacity value.
     *
     * Out-of-range numbers are clamped rather than refused: the specification
     * clamps them too, so a 1.2 is not an error anyone needs telling about.
     */
    public static function opacity(string $value): float
    {
        $value = trim($value);

        if (substr($value, -1) === '%') {
            $fraction = ((float) substr($value, 0, -1)) / 100.0;
        } elseif (is_numeric($value)) {
            $fraction = (float) $value;
        } else {
            return 1.0;
        }

        return max(0.0, min(1.0, $fraction));
    }

    /**
     * @throws LogoRejected
     */
    private static function functional(string $arguments, string $original): int
    {
        $parts = preg_split('#[\s,/]+#', trim($arguments)) ?: [];
        $parts = array_values(array_filter($parts, static function (string $part): bool {
            return $part !== '';
        }));

        if (count($parts) < 3) {
            throw LogoRejected::unreadableColor($original);
        }

        $channels = [];

        for ($index = 0; $index < 3; $index++) {
            $part = $parts[$index];

            if (substr($part, -1) === '%') {
                $channels[] = (int) round(max(0.0, min(100.0, (float) substr($part, 0, -1))) * 2.55);

                continue;
            }

            if (!is_numeric($part)) {
                throw LogoRejected::unreadableColor($original);
            }

            $channels[] = (int) round(max(0.0, min(255.0, (float) $part)));
        }

        return ($channels[0] << 16) | ($channels[1] << 8) | $channels[2];
    }
}

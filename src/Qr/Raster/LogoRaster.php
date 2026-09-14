<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * Draws sanitised artwork into a block of pixels.
 *
 * This is the piece the PNG renderer was missing. The SVG output hands the
 * logo's markup to the viewer and lets it draw; a PNG has to do the drawing
 * here, which is what the three classes beside this one are for — a transform,
 * a flattener and a filler. This one walks the markup, keeps track of what each
 * element inherits, and composites the results in document order.
 *
 * It reads only what SvgLogo produces: a canonical, already-safe subset with
 * paint resolved into presentation attributes and identifiers gone. That is why
 * a regular expression is enough to walk it and why there is no XML parser
 * here — the input is this package's own output, not a file from outside.
 *
 * **What it will not draw, it refuses.** Elliptical arcs, strokes and group
 * opacity are named on refusal rather than approximated, because a PNG that
 * quietly differs from the SVG of the same artwork is the one failure nobody
 * would catch before the print run. A refusal costs the PNG for that logo and
 * nothing else: the SVG renderer is untouched by any of it, and the demo falls
 * back to offering the vector alone.
 */
final class LogoRaster
{
    /**
     * Paint state inherited by every element, with the values SVG starts from.
     * Fill defaults to black, which is why artwork with no fill anywhere still
     * draws.
     *
     * @var array<string, string>
     */
    private const INITIAL_PAINT = [
        'fill' => '#000000',
        'fill-rule' => 'nonzero',
        'fill-opacity' => '1',
        'opacity' => '1',
        'stroke' => 'none',
        'stroke-width' => '1',
        'stroke-opacity' => '1',
    ];

    private function __construct()
    {
    }

    /**
     * Renders the artwork over a solid backdrop.
     *
     * @param Transform $placement Maps the logo's own coordinates to pixels in this block
     *
     * @return list<int> Packed 0xRRGGBB, row by row, $width entries per row
     *
     * @throws LogoRejected
     */
    public static function rasterise(
        Logo $logo,
        int $width,
        int $height,
        Transform $placement,
        int $backdrop
    ): array {
        $pixels = array_fill(0, max(0, $width * $height), $backdrop);

        self::walk($logo->markup(), $placement, $width, $height, $pixels);

        return $pixels;
    }

    /**
     * Why this artwork cannot be rasterised, or null if it can.
     *
     * Same walk, nothing drawn. It exists so a caller can decide whether to
     * offer a PNG at all before anyone clicks a download that would fail, and
     * so the reason can be shown next to the artwork rather than in a log.
     */
    public static function rejectionFor(Logo $logo): ?string
    {
        $nothing = null;

        try {
            self::walk($logo->markup(), Transform::identity(), 0, 0, $nothing);
        } catch (LogoRejected $rejection) {
            return $rejection->getMessage();
        }

        return null;
    }

    /**
     * Walks the markup, drawing into $pixels — or, when that is null, only
     * checking that everything in it could be drawn.
     *
     * @param list<int>|null $pixels
     *
     * @throws LogoRejected
     */
    private static function walk(
        string $markup,
        Transform $root,
        int $width,
        int $height,
        ?array &$pixels
    ): void {
        $tag = '/<(\/?)([a-z]+)((?:\s+[a-z-]+\s*=\s*"[^"]*")*)\s*(\/?)>/';

        // The stack holds what an element inherits. Its last entry is the state
        // the next element starts from.
        $stack = [['ctm' => $root, 'paint' => self::INITIAL_PAINT]];

        preg_match_all($tag, $markup, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $element = $match[2];

            if ($match[1] === '/') {
                if (count($stack) < 2) {
                    throw LogoRejected::unbalancedMarkup();
                }

                array_pop($stack);

                continue;
            }

            $attributes = self::attributes($match[3]);
            $inherited = $stack[count($stack) - 1];

            $ctm = isset($attributes['transform'])
                ? $inherited['ctm']->concat(Transform::parse($attributes['transform']))
                : $inherited['ctm'];

            $paint = self::inherit($inherited['paint'], $attributes);

            if ($element === 'g') {
                // Group opacity is a compositing step, not an inherited value:
                // done properly the group is drawn on its own and faded as one,
                // so overlapping children do not show through each other. That
                // needs a second buffer, and no artwork here has ever asked for
                // it, so it is refused by name instead of approximated.
                if (isset($attributes['opacity']) && Color::opacity($attributes['opacity']) < 1.0) {
                    throw LogoRejected::groupOpacity();
                }

                if ($match[4] !== '/') {
                    $stack[] = ['ctm' => $ctm, 'paint' => $paint];
                }

                continue;
            }

            self::draw($element, $attributes, $ctm, $paint, $width, $height, $pixels);
        }

        if (count($stack) !== 1) {
            throw LogoRejected::unbalancedMarkup();
        }
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $paint
     * @param list<int>|null        $pixels
     *
     * @throws LogoRejected
     */
    private static function draw(
        string $element,
        array $attributes,
        Transform $ctm,
        array $paint,
        int $width,
        int $height,
        ?array &$pixels
    ): void {
        // A stroke would have to be turned into an outline of its own — joins,
        // caps, dashes — which is a second geometry engine beside the filler.
        if (Color::parse($paint['stroke']) !== null && (float) $paint['stroke-width'] > 0.0) {
            throw LogoRejected::strokedArtwork($element);
        }

        $fill = Color::parse($paint['fill']);
        $alpha = Color::opacity($paint['fill-opacity']) * Color::opacity($paint['opacity']);

        // Parsing happens even when nothing will be painted, so a broken path in
        // an invisible element is still reported rather than waiting to surface
        // the day someone gives it a colour.
        $subpaths = ShapeFlattener::flatten($element, $attributes, $ctm);

        if ($pixels === null || $fill === null || $alpha <= 0.0 || $subpaths === []) {
            return;
        }

        $coverage = ScanlineFiller::coverage(
            $subpaths,
            $width,
            $height,
            strtolower(trim($paint['fill-rule'])) === 'evenodd'
        );

        self::composite($pixels, $width, $coverage, $fill, $alpha);
    }

    /**
     * Blends one shape's coverage over what is already there.
     *
     * @param list<int>                   $pixels
     * @param array<int, array<int, int>> $coverage
     */
    private static function composite(
        array &$pixels,
        int $width,
        array $coverage,
        int $fill,
        float $alpha
    ): void {
        $red = ($fill >> 16) & 0xFF;
        $green = ($fill >> 8) & 0xFF;
        $blue = $fill & 0xFF;

        foreach ($coverage as $y => $row) {
            $offset = $y * $width;

            foreach ($row as $x => $samples) {
                $weight = ($samples / ScanlineFiller::FULL_COVERAGE) * $alpha;

                if ($weight <= 0.0) {
                    continue;
                }

                $index = $offset + $x;
                $under = $pixels[$index];
                $rest = 1.0 - $weight;

                $pixels[$index] = ((int) round(($red * $weight) + ((($under >> 16) & 0xFF) * $rest)) << 16)
                    | ((int) round(($green * $weight) + ((($under >> 8) & 0xFF) * $rest)) << 8)
                    | (int) round(($blue * $weight) + (($under & 0xFF) * $rest));
            }
        }
    }

    /**
     * @param array<string, string> $inherited
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function inherit(array $inherited, array $attributes): array
    {
        foreach ($inherited as $property => $value) {
            if (isset($attributes[$property])) {
                $inherited[$property] = $attributes[$property];
            }
        }

        // opacity is the one that does not inherit: absent on a child means
        // fully opaque, not whatever the parent had.
        if (!isset($attributes['opacity'])) {
            $inherited['opacity'] = '1';
        }

        return $inherited;
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(string $source): array
    {
        preg_match_all('/([a-z-]+)\s*=\s*"([^"]*)"/', $source, $matches, PREG_SET_ORDER);

        $attributes = [];

        foreach ($matches as $match) {
            // SvgLogo escapes on the way out, so the values arrive as entities
            // and have to come back to characters before they are numbers.
            $attributes[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $attributes;
    }
}

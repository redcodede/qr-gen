<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * Reduces the geometric elements to outlines, by way of the path grammar.
 *
 * Every one of them — rect, circle, ellipse, polygon, polyline — is defined in
 * the specification as equivalent to some path, so each is written out as one
 * and handed to PathFlattener. It costs a string build per shape and buys a
 * single flattening path: one tolerance, one curve subdivider, one set of
 * bugs. A separate circle rasteriser would be a second place for a rounding
 * error to live.
 *
 * The circular and elliptical arcs use the standard four-cubic approximation
 * rather than the arc command, which this rasteriser refuses. The control
 * points sit at 0.5523 of the radius, where the maximum radial error is under
 * a fifty-thousandth of the radius — on a 300-pixel logo that is a hundredth
 * of a pixel, well beneath the sampling grid.
 */
final class ShapeFlattener
{
    /** 4/3 × (√2 − 1): the quarter-arc control point offset, as a fraction of the radius. */
    private const KAPPA = 0.5522847498307936;

    /**
     * @param array<string, string> $attributes
     *
     * @return list<list<array{0: float, 1: float}>> Subpaths in device space
     *
     * @throws LogoRejected
     */
    public static function flatten(string $element, array $attributes, Transform $ctm): array
    {
        $d = self::pathFor($element, $attributes);

        return $d === '' ? [] : PathFlattener::flatten($d, $ctm);
    }

    /**
     * @param array<string, string> $attributes
     *
     * @throws LogoRejected
     */
    private static function pathFor(string $element, array $attributes): string
    {
        if ($element === 'path') {
            return $attributes['d'] ?? '';
        }

        if ($element === 'rect') {
            return self::rect($attributes);
        }

        if ($element === 'circle') {
            $r = self::number($attributes, 'r');

            return self::ellipseAt(self::number($attributes, 'cx'), self::number($attributes, 'cy'), $r, $r);
        }

        if ($element === 'ellipse') {
            return self::ellipseAt(
                self::number($attributes, 'cx'),
                self::number($attributes, 'cy'),
                self::number($attributes, 'rx'),
                self::number($attributes, 'ry')
            );
        }

        if ($element === 'polygon' || $element === 'polyline') {
            return self::polygon($attributes['points'] ?? '');
        }

        // A <line> has no interior. With strokes refused there is nothing it
        // could contribute, so it is skipped rather than refused: the SVG
        // renderer draws nothing for it either.
        if ($element === 'line') {
            return '';
        }

        throw LogoRejected::unrasterisableElement($element);
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function rect(array $attributes): string
    {
        $x = self::number($attributes, 'x');
        $y = self::number($attributes, 'y');
        $width = self::number($attributes, 'width');
        $height = self::number($attributes, 'height');

        if ($width <= 0.0 || $height <= 0.0) {
            return '';
        }

        // One radius given stands for both, and neither may exceed half the
        // side it rounds — the specification clamps rather than refuses.
        $rx = isset($attributes['rx']) ? self::number($attributes, 'rx') : null;
        $ry = isset($attributes['ry']) ? self::number($attributes, 'ry') : null;
        $rx = $rx ?? $ry ?? 0.0;
        $ry = $ry ?? $rx;
        $rx = min(max($rx, 0.0), $width / 2);
        $ry = min(max($ry, 0.0), $height / 2);

        if ($rx <= 0.0 || $ry <= 0.0) {
            return sprintf(
                'M%s %sH%sV%sH%sZ',
                self::f($x),
                self::f($y),
                self::f($x + $width),
                self::f($y + $height),
                self::f($x)
            );
        }

        $cx = $rx * self::KAPPA;
        $cy = $ry * self::KAPPA;
        $right = $x + $width;
        $bottom = $y + $height;

        return sprintf(
            'M%s %sH%sC%s %s %s %s %s %sV%sC%s %s %s %s %s %sH%sC%s %s %s %s %s %sV%sC%s %s %s %s %s %sZ',
            self::f($x + $rx),
            self::f($y),
            self::f($right - $rx),
            self::f($right - $rx + $cx),
            self::f($y),
            self::f($right),
            self::f($y + $ry - $cy),
            self::f($right),
            self::f($y + $ry),
            self::f($bottom - $ry),
            self::f($right),
            self::f($bottom - $ry + $cy),
            self::f($right - $rx + $cx),
            self::f($bottom),
            self::f($right - $rx),
            self::f($bottom),
            self::f($x + $rx),
            self::f($x + $rx - $cx),
            self::f($bottom),
            self::f($x),
            self::f($bottom - $ry + $cy),
            self::f($x),
            self::f($bottom - $ry),
            self::f($y + $ry),
            self::f($x),
            self::f($y + $ry - $cy),
            self::f($x + $rx - $cx),
            self::f($y),
            self::f($x + $rx),
            self::f($y)
        );
    }

    private static function ellipseAt(float $cx, float $cy, float $rx, float $ry): string
    {
        if ($rx <= 0.0 || $ry <= 0.0) {
            return '';
        }

        $ox = $rx * self::KAPPA;
        $oy = $ry * self::KAPPA;

        return sprintf(
            'M%s %sC%s %s %s %s %s %sC%s %s %s %s %s %sC%s %s %s %s %s %sC%s %s %s %s %s %sZ',
            self::f($cx + $rx),
            self::f($cy),
            self::f($cx + $rx),
            self::f($cy + $oy),
            self::f($cx + $ox),
            self::f($cy + $ry),
            self::f($cx),
            self::f($cy + $ry),
            self::f($cx - $ox),
            self::f($cy + $ry),
            self::f($cx - $rx),
            self::f($cy + $oy),
            self::f($cx - $rx),
            self::f($cy),
            self::f($cx - $rx),
            self::f($cy - $oy),
            self::f($cx - $ox),
            self::f($cy - $ry),
            self::f($cx),
            self::f($cy - $ry),
            self::f($cx + $ox),
            self::f($cy - $ry),
            self::f($cx + $rx),
            self::f($cy - $oy),
            self::f($cx + $rx),
            self::f($cy)
        );
    }

    /**
     * @throws LogoRejected
     */
    private static function polygon(string $points): string
    {
        preg_match_all('/-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $points, $found);
        $numbers = $found[0];
        $pairs = intdiv(count($numbers), 2);

        if ($pairs < 2) {
            return '';
        }

        // An odd trailing number means the list was truncated. Dropping it
        // silently would close the outline somewhere the artwork does not.
        if (count($numbers) % 2 !== 0) {
            throw LogoRejected::unreadablePoints();
        }

        $d = 'M' . $numbers[0] . ' ' . $numbers[1];

        for ($pair = 1; $pair < $pairs; $pair++) {
            $d .= 'L' . $numbers[$pair * 2] . ' ' . $numbers[($pair * 2) + 1];
        }

        // A polyline is filled as though closed, exactly as a polygon is. The
        // difference between the two is the stroke, and there is no stroke here.
        return $d . 'Z';
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function number(array $attributes, string $name): float
    {
        return isset($attributes[$name]) ? (float) $attributes[$name] : 0.0;
    }

    /**
     * %F rather than %f: the uppercase conversion is defined as locale
     * independent, so a German locale cannot put a comma in the middle of a
     * coordinate.
     */
    private static function f(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }
}

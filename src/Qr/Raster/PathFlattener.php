<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * Turns the `d` attribute of a <path> into straight-line outlines in device
 * pixels.
 *
 * Everything downstream of here is polygons. That is the trick behind fitting a
 * rasteriser into a few hundred lines rather than a few thousand: curves are
 * subdivided until the difference between the curve and a chord no longer shows
 * at the resolution being rendered, and the filler then only ever has to
 * intersect a scanline with a line segment.
 *
 * Because the tolerance is a pixel count, the transform is applied here, point
 * by point, rather than to a finished outline. A logo scaled to nine modules
 * and one scaled to a poster then each get the number of segments they need.
 *
 * **Arcs are refused.** A and a — the elliptical arc — need the
 * endpoint-to-centre conversion from the specification, and none of the artwork
 * this package has seen uses one. Refusing names the gap instead of drawing
 * something else; the SVG renderer takes the same file without complaint, so a
 * refusal here costs the PNG and nothing more.
 */
final class PathFlattener
{
    /**
     * Chord tolerance in device pixels. Curves are split until each segment is
     * within roughly this of the true curve. A third of a pixel is finer than
     * the 4x4 sampling grid can express, so flattening is not what limits
     * quality here — sampling is.
     */
    private const TOLERANCE = 0.33;

    /** Guards against a pathological curve asking for thousands of segments. */
    private const MAX_SEGMENTS = 160;

    /** Numbers accepted in a path: optional sign, decimals, exponent. */
    private const NUMBER = '-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?';

    /**
     * @return list<list<array{0: float, 1: float}>> Subpaths, each a list of device points
     *
     * @throws LogoRejected
     */
    public static function flatten(string $d, Transform $ctm): array
    {
        $tokens = self::tokenise($d);
        $magnitude = $ctm->magnitude();

        $subpaths = [];
        $current = [];

        // User-space bookkeeping: the reflected control point of a smooth curve
        // is defined in the path's own coordinates, so the state stays there and
        // only the emitted points are mapped.
        $x = 0.0;
        $y = 0.0;
        $startX = 0.0;
        $startY = 0.0;
        $controlX = null;
        $controlY = null;
        $started = false;

        $index = 0;
        $count = count($tokens);
        $command = '';

        while ($index < $count) {
            $token = $tokens[$index];

            if (is_string($token)) {
                $command = $token;
                $index++;

                if (strtolower($command) === 'z') {
                    if ($current !== []) {
                        $subpaths[] = $current;
                        $current = [];
                    }

                    $x = $startX;
                    $y = $startY;
                    $controlX = null;
                    $controlY = null;

                    continue;
                }
            } elseif ($command === '') {
                throw LogoRejected::unreadablePath('it starts with a number instead of a command.');
            }

            $lower = strtolower($command);
            // Lowercase is relative to the current point, uppercase absolute.
            $relative = $command === $lower;

            if ($lower === 'a') {
                throw LogoRejected::unsupportedPathCommand($command);
            }

            $needed = self::argumentCount($lower, $command);
            $arguments = self::take($tokens, $index, $needed, $command);
            $index += $needed;

            if ($lower === 'm') {
                if ($current !== []) {
                    $subpaths[] = $current;
                }

                $x = $relative ? $x + $arguments[0] : $arguments[0];
                $y = $relative ? $y + $arguments[1] : $arguments[1];
                $startX = $x;
                $startY = $y;
                $current = [[$ctm->applyX($x, $y), $ctm->applyY($x, $y)]];
                $controlX = null;
                $controlY = null;
                $started = true;

                // A second coordinate pair after a moveto is a lineto, and so is
                // every pair after that.
                $command = $relative ? 'l' : 'L';

                continue;
            }

            if ($current === []) {
                // A drawing command before any moveto puts the path in error
                // from there on, says the specification; treating the origin as
                // a start point would invent geometry.
                if (!$started) {
                    throw LogoRejected::unreadablePath(sprintf('"%s" comes before any moveto.', $command));
                }

                // Drawing on after a closepath is allowed, and starts a fresh
                // subpath at the point the closepath returned to.
                $current = [[$ctm->applyX($x, $y), $ctm->applyY($x, $y)]];
            }

            if ($lower === 'l' || $lower === 'h' || $lower === 'v') {
                if ($lower === 'h') {
                    $x = $relative ? $x + $arguments[0] : $arguments[0];
                } elseif ($lower === 'v') {
                    $y = $relative ? $y + $arguments[0] : $arguments[0];
                } else {
                    $x = $relative ? $x + $arguments[0] : $arguments[0];
                    $y = $relative ? $y + $arguments[1] : $arguments[1];
                }

                $current[] = [$ctm->applyX($x, $y), $ctm->applyY($x, $y)];
                $controlX = null;
                $controlY = null;

                continue;
            }

            $cubic = self::cubicFor($lower, $relative, $arguments, $x, $y, $controlX, $controlY);

            self::appendCubic($current, $ctm, $magnitude, $x, $y, $cubic);

            // What the next smooth command reflects: the second control point of
            // a cubic, or the single control point of a quadratic. The quadratic
            // one has to be recovered from the elevated cubic, because that is
            // what T mirrors.
            if ($lower === 'q' || $lower === 't') {
                $controlX = $x + (($cubic[0] - $x) * 1.5);
                $controlY = $y + (($cubic[1] - $y) * 1.5);
            } else {
                $controlX = $cubic[2];
                $controlY = $cubic[3];
            }

            $x = $cubic[4];
            $y = $cubic[5];
        }

        if ($current !== []) {
            $subpaths[] = $current;
        }

        return $subpaths;
    }

    /**
     * Splits the command letters from the numbers, refusing anything that is
     * neither.
     *
     * @return list<string|float>
     *
     * @throws LogoRejected
     */
    private static function tokenise(string $d): array
    {
        $pattern = '/([MmLlHhVvCcSsQqTtAaZz])|(' . self::NUMBER . ')|([\s,]+)/';
        $tokens = [];
        $offset = 0;
        $length = strlen($d);

        while ($offset < $length) {
            if (preg_match($pattern, $d, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                break;
            }

            $start = (int) $match[0][1];

            if ($start !== $offset) {
                throw LogoRejected::unreadablePath(sprintf(
                    'it contains "%s", which is neither a command nor a number.',
                    trim(substr($d, $offset, $start - $offset))
                ));
            }

            if (($match[1][0] ?? '') !== '') {
                $tokens[] = (string) $match[1][0];
            } elseif (($match[2][0] ?? '') !== '') {
                $tokens[] = (float) $match[2][0];
            }

            $offset = $start + strlen((string) $match[0][0]);
        }

        if ($offset < $length && trim(substr($d, $offset)) !== '') {
            throw LogoRejected::unreadablePath(sprintf(
                'it ends in "%s", which is neither a command nor a number.',
                trim(substr($d, $offset))
            ));
        }

        return $tokens;
    }

    /**
     * @throws LogoRejected
     */
    private static function argumentCount(string $lower, string $command): int
    {
        $counts = [
            'm' => 2, 'l' => 2, 'h' => 1, 'v' => 1,
            'c' => 6, 's' => 4, 'q' => 4, 't' => 2,
        ];

        if (!isset($counts[$lower])) {
            throw LogoRejected::unsupportedPathCommand($command);
        }

        return $counts[$lower];
    }

    /**
     * @param list<string|float> $tokens
     *
     * @return list<float>
     *
     * @throws LogoRejected
     */
    private static function take(array $tokens, int $index, int $needed, string $command): array
    {
        $arguments = [];

        for ($offset = 0; $offset < $needed; $offset++) {
            $value = $tokens[$index + $offset] ?? null;

            if (!is_float($value)) {
                throw LogoRejected::unreadablePath(sprintf(
                    '"%s" wants %d numbers and does not get them.',
                    $command,
                    $needed
                ));
            }

            $arguments[] = $value;
        }

        return $arguments;
    }

    /**
     * Every curve command reduced to one cubic.
     *
     * A quadratic is a cubic whose control points sit two thirds of the way
     * along each of its legs. That is an exact conversion, not an
     * approximation, so the flattener below only ever knows one kind of curve.
     *
     * @param list<float> $arguments
     *
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private static function cubicFor(
        string $lower,
        bool $relative,
        array $arguments,
        float $x,
        float $y,
        ?float $controlX,
        ?float $controlY
    ): array {
        $originX = $relative ? $x : 0.0;
        $originY = $relative ? $y : 0.0;

        if ($lower === 'c') {
            return [
                $originX + $arguments[0], $originY + $arguments[1],
                $originX + $arguments[2], $originY + $arguments[3],
                $originX + $arguments[4], $originY + $arguments[5],
            ];
        }

        if ($lower === 's') {
            // Without a preceding curve the first control point coincides with
            // the current point, which the specification states outright.
            return [
                $controlX === null ? $x : (2 * $x) - $controlX,
                $controlY === null ? $y : (2 * $y) - $controlY,
                $originX + $arguments[0], $originY + $arguments[1],
                $originX + $arguments[2], $originY + $arguments[3],
            ];
        }

        if ($lower === 'q') {
            return self::elevate(
                $x,
                $y,
                $originX + $arguments[0],
                $originY + $arguments[1],
                $originX + $arguments[2],
                $originY + $arguments[3]
            );
        }

        return self::elevate(
            $x,
            $y,
            $controlX === null ? $x : (2 * $x) - $controlX,
            $controlY === null ? $y : (2 * $y) - $controlY,
            $originX + $arguments[0],
            $originY + $arguments[1]
        );
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private static function elevate(
        float $x,
        float $y,
        float $qx,
        float $qy,
        float $endX,
        float $endY
    ): array {
        return [
            $x + ((2.0 / 3.0) * ($qx - $x)),
            $y + ((2.0 / 3.0) * ($qy - $y)),
            $endX + ((2.0 / 3.0) * ($qx - $endX)),
            $endY + ((2.0 / 3.0) * ($qy - $endY)),
            $endX,
            $endY,
        ];
    }

    /**
     * Subdivides one cubic and appends the resulting points in device space.
     *
     * The segment count comes from the control polygon, which is never shorter
     * than the curve it encloses. Erring long costs a few extra segments;
     * erring short would show as a visible facet.
     *
     * @param list<array{0: float, 1: float}>                                    $points
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $cubic
     */
    private static function appendCubic(
        array &$points,
        Transform $ctm,
        float $magnitude,
        float $x0,
        float $y0,
        array $cubic
    ): void {
        [$x1, $y1, $x2, $y2, $x3, $y3] = $cubic;

        $polygon = self::distance($x0, $y0, $x1, $y1)
            + self::distance($x1, $y1, $x2, $y2)
            + self::distance($x2, $y2, $x3, $y3);

        // Chord error on a cubic falls with the square of the segment count, so
        // the count rises with the square root of the length over the tolerance.
        $segments = (int) ceil(sqrt(($polygon * $magnitude) / self::TOLERANCE));
        $segments = max(1, min(self::MAX_SEGMENTS, $segments));

        for ($step = 1; $step <= $segments; $step++) {
            $t = $step / $segments;
            $u = 1.0 - $t;

            $bx = ($u * $u * $u * $x0)
                + (3 * $u * $u * $t * $x1)
                + (3 * $u * $t * $t * $x2)
                + ($t * $t * $t * $x3);
            $by = ($u * $u * $u * $y0)
                + (3 * $u * $u * $t * $y1)
                + (3 * $u * $t * $t * $y2)
                + ($t * $t * $t * $y3);

            $points[] = [$ctm->applyX($bx, $by), $ctm->applyY($bx, $by)];
        }
    }

    private static function distance(float $x0, float $y0, float $x1, float $y1): float
    {
        return sqrt((($x1 - $x0) ** 2) + (($y1 - $y0) ** 2));
    }
}

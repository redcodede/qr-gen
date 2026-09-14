<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Raster\PathFlattener;
use Redcodede\QrGen\Qr\Raster\Transform;

/**
 * The path grammar, one command at a time.
 *
 * The case that earns its keep here is the first one. Case in a path command is
 * not decoration: uppercase is absolute and lowercase is relative, and swapping
 * them produces a path that is still a closed, plausible-looking outline — it
 * just is not the artwork. Nothing about the output announces the mistake, so
 * the test has to.
 */
final class PathFlattenerTest extends TestCase
{
    /**
     * @return list<array{0: float, 1: float}>
     */
    private function onlySubpath(string $d): array
    {
        $subpaths = PathFlattener::flatten($d, Transform::identity());

        self::assertCount(1, $subpaths, 'Expected exactly one subpath.');

        return $subpaths[0];
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @param list<array{0: float, 1: float}> $expected
     */
    private static function assertPoints(array $expected, array $points, string $message = ''): void
    {
        self::assertCount(count($expected), $points, $message . ' (count)');

        foreach ($expected as $index => $point) {
            self::assertEqualsWithDelta($point[0], $points[$index][0], 0.000001, $message . " (point $index x)");
            self::assertEqualsWithDelta($point[1], $points[$index][1], 0.000001, $message . " (point $index y)");
        }
    }

    public function testUppercaseIsAbsolute(): void
    {
        self::assertPoints(
            [[2.0, 2.0], [8.0, 2.0], [8.0, 8.0], [2.0, 8.0]],
            $this->onlySubpath('M2 2H8V8H2Z')
        );
    }

    public function testLowercaseIsRelativeToTheCurrentPoint(): void
    {
        self::assertPoints(
            [[2.0, 2.0], [8.0, 2.0], [8.0, 8.0], [2.0, 8.0]],
            $this->onlySubpath('m2 2h6v6h-6z')
        );
    }

    public function testAbsoluteAndRelativeMix(): void
    {
        self::assertPoints(
            [[10.0, 10.0], [20.0, 10.0], [20.0, 25.0]],
            $this->onlySubpath('M10 10 h10 V25')
        );
    }

    public function testASecondPairAfterAMovetoIsALineto(): void
    {
        self::assertPoints(
            [[1.0, 1.0], [2.0, 2.0], [3.0, 3.0]],
            $this->onlySubpath('M1 1 2 2 3 3')
        );
    }

    public function testASecondPairAfterARelativeMovetoIsARelativeLineto(): void
    {
        self::assertPoints(
            [[1.0, 1.0], [3.0, 3.0], [6.0, 6.0]],
            $this->onlySubpath('m1 1 2 2 3 3')
        );
    }

    public function testEveryMovetoStartsANewSubpath(): void
    {
        $subpaths = PathFlattener::flatten('M0 0H1V1ZM5 5H6V6Z', Transform::identity());

        self::assertCount(2, $subpaths);
        self::assertPoints([[0.0, 0.0], [1.0, 0.0], [1.0, 1.0]], $subpaths[0]);
        self::assertPoints([[5.0, 5.0], [6.0, 5.0], [6.0, 6.0]], $subpaths[1]);
    }

    /**
     * Drawing on after a closepath is legal and starts a fresh subpath where the
     * closepath returned to. Refusing it would refuse valid artwork.
     */
    public function testDrawingContinuesAfterAClosepath(): void
    {
        $subpaths = PathFlattener::flatten('M0 0H4V4ZL9 9', Transform::identity());

        self::assertCount(2, $subpaths);
        self::assertPoints([[0.0, 0.0], [9.0, 9.0]], $subpaths[1], 'restarts at the closepath point');
    }

    public function testTheTransformIsAppliedToEveryPoint(): void
    {
        $subpaths = PathFlattener::flatten('M1 1H2', Transform::translation(10.0, 100.0));

        self::assertPoints([[11.0, 101.0], [12.0, 101.0]], $subpaths[0]);
    }

    public function testACurveIsSubdividedIntoSegmentsThatEndOnTheCurve(): void
    {
        $points = $this->onlySubpath('M0 0C0 100 100 100 100 0');

        self::assertGreaterThan(4, count($points), 'A curve of this size needs subdividing.');
        self::assertPoints([[0.0, 0.0]], [$points[0]], 'starts at the start point');

        $last = $points[count($points) - 1];
        self::assertEqualsWithDelta(100.0, $last[0], 0.000001, 'ends at the end point (x)');
        self::assertEqualsWithDelta(0.0, $last[1], 0.000001, 'ends at the end point (y)');
    }

    /**
     * A bigger transform means more pixels on the page for the same curve, so it
     * has to be cut into more pieces to stay smooth.
     */
    public function testACurveGetsMoreSegmentsWhenItIsScaledUp(): void
    {
        $small = PathFlattener::flatten('M0 0C0 10 10 10 10 0', Transform::scaling(1.0, 1.0));
        $large = PathFlattener::flatten('M0 0C0 10 10 10 10 0', Transform::scaling(40.0, 40.0));

        self::assertGreaterThan(count($small[0]), count($large[0]));
    }

    /**
     * Raising a quadratic to a cubic is exact, not an approximation, so every
     * flattened point has to sit on the original curve rather than merely near
     * it. Q((0,0), (10,20), (20,0)) works out to x = 20t and y = 40t(1 - t),
     * and the points come out at t = i / segments.
     */
    public function testAQuadraticLandsOnTheRealCurve(): void
    {
        $points = $this->onlySubpath('M0 0Q10 20 20 0');
        $segments = count($points) - 1;

        self::assertGreaterThan(2, $segments, 'A curve this size needs subdividing.');

        foreach ($points as $index => $point) {
            $t = $index / $segments;

            self::assertEqualsWithDelta(20 * $t, $point[0], 0.000001, "point $index x");
            self::assertEqualsWithDelta(40 * $t * (1 - $t), $point[1], 0.000001, "point $index y");
        }
    }

    /**
     * A smooth curve reflects the previous control point. Reflecting the wrong
     * one, or none, puts a kink where the artwork is smooth.
     */
    public function testASmoothCubicReflectsThePreviousControlPoint(): void
    {
        $reflected = $this->onlySubpath('M0 0C0 10 10 10 10 0S20 -10 20 0');
        $spelled = $this->onlySubpath('M0 0C0 10 10 10 10 0C10 -10 20 -10 20 0');

        self::assertPoints($spelled, $reflected, 'S is C with the first control point mirrored');
    }

    public function testASmoothQuadraticReflectsThePreviousControlPoint(): void
    {
        $reflected = $this->onlySubpath('M0 0Q5 10 10 0T20 0');
        $spelled = $this->onlySubpath('M0 0Q5 10 10 0Q15 -10 20 0');

        self::assertPoints($spelled, $reflected);
    }

    public function testScientificNotationAndCommasAreRead(): void
    {
        self::assertPoints(
            [[1.5, 2.5], [0.15, 2.5]],
            $this->onlySubpath('M1.5,2.5H1.5e-1')
        );
    }

    public function testNegativeNumbersNeedNoSeparator(): void
    {
        self::assertPoints([[1.0, 1.0], [-3.0, -4.0]], $this->onlySubpath('M1 1L-3-4'));
    }

    public function testAnArcIsRefusedByName(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/elliptical arc/');

        PathFlattener::flatten('M0 0A5 5 0 0 1 10 10', Transform::identity());
    }

    public function testAMissingCoordinateIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/wants 2 numbers/');

        PathFlattener::flatten('M0 0L5', Transform::identity());
    }

    public function testAPathStartingWithANumberIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        PathFlattener::flatten('5 5 L10 10', Transform::identity());
    }

    public function testRubbishInThePathIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        PathFlattener::flatten('M0 0 L10 $$$ 10', Transform::identity());
    }
}

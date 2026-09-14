<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Raster\Transform;

/**
 * The order in which transforms compose is the thing worth testing here.
 *
 * Get it backwards and artwork still appears, in the wrong place and at the
 * wrong size, which looks like a placement bug anywhere but here.
 */
final class TransformTest extends TestCase
{
    private static function assertMapsTo(
        Transform $transform,
        float $x,
        float $y,
        float $expectedX,
        float $expectedY,
        string $message = ''
    ): void {
        self::assertEqualsWithDelta($expectedX, $transform->applyX($x, $y), 0.000001, $message . ' (x)');
        self::assertEqualsWithDelta($expectedY, $transform->applyY($x, $y), 0.000001, $message . ' (y)');
    }

    public function testIdentityLeavesAPointWhereItIs(): void
    {
        self::assertMapsTo(Transform::identity(), 3.0, 7.0, 3.0, 7.0);
    }

    public function testATransformListAppliesRightToLeft(): void
    {
        // The specification is explicit: in "translate(…) scale(…)" the scale
        // happens first, so the translation is not itself scaled.
        self::assertMapsTo(
            Transform::parse('translate(10 20) scale(2)'),
            3.0,
            4.0,
            16.0,
            28.0,
            'scale first, then translate'
        );

        self::assertMapsTo(
            Transform::parse('scale(2) translate(10 20)'),
            3.0,
            4.0,
            26.0,
            48.0,
            'translate first, then scale — the translation is scaled too'
        );
    }

    public function testConcatNestsTheInnerTransformInsideTheOuterOne(): void
    {
        $parent = Transform::translation(100.0, 0.0);
        $child = Transform::scaling(3.0, 3.0);

        self::assertMapsTo($parent->concat($child), 2.0, 1.0, 106.0, 3.0);
    }

    public function testScaleWithOneArgumentScalesBothAxes(): void
    {
        self::assertMapsTo(Transform::parse('scale(4)'), 2.0, 3.0, 8.0, 12.0);
    }

    public function testTranslateWithOneArgumentLeavesYAlone(): void
    {
        self::assertMapsTo(Transform::parse('translate(5)'), 1.0, 1.0, 6.0, 1.0);
    }

    public function testRotateTurnsClockwiseAsSvgDefinesIt(): void
    {
        // Clockwise on screen, because y points down.
        self::assertMapsTo(Transform::parse('rotate(90)'), 1.0, 0.0, 0.0, 1.0);
    }

    public function testRotateAboutAPointLeavesThatPointFixed(): void
    {
        self::assertMapsTo(Transform::parse('rotate(37 8 5)'), 8.0, 5.0, 8.0, 5.0);
    }

    public function testMatrixTakesItsSixNumbersInSvgOrder(): void
    {
        self::assertMapsTo(Transform::parse('matrix(2 0 0 3 10 20)'), 1.0, 1.0, 12.0, 23.0);
    }

    public function testCommasAndExtraSpaceAreSeparators(): void
    {
        self::assertMapsTo(Transform::parse('  translate( 10 , 20 )  '), 0.0, 0.0, 10.0, 20.0);
    }

    public function testAnEmptyListIsTheIdentity(): void
    {
        self::assertMapsTo(Transform::parse('   '), 5.0, 6.0, 5.0, 6.0);
    }

    /**
     * The magnitude drives curve subdivision. Too small and curves come out
     * faceted, so it has to follow the scale rather than sit at one.
     */
    public function testMagnitudeFollowsTheScale(): void
    {
        self::assertEqualsWithDelta(1.0, Transform::identity()->magnitude(), 0.000001);
        self::assertEqualsWithDelta(7.0, Transform::scaling(7.0, 7.0)->magnitude(), 0.000001);
        self::assertEqualsWithDelta(6.0, Transform::scaling(4.0, 9.0)->magnitude(), 0.000001);
        self::assertEqualsWithDelta(1.0, Transform::translation(50.0, 50.0)->magnitude(), 0.000001);
    }

    public function testAnUnknownFunctionIsRefusedByName(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/could not be read/');

        Transform::parse('perspective(30)');
    }

    public function testAWrongArgumentCountIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        Transform::parse('matrix(1 0 0 1)');
    }

    /**
     * Anything left over after the recognised calls means something was not
     * understood. Ignoring it would move the artwork silently.
     */
    public function testTrailingRubbishIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        Transform::parse('translate(10 20) nonsense');
    }
}

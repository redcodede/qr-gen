<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Raster\Color;
use Redcodede\QrGen\Qr\Raster\Palette;
use Redcodede\QrGen\Qr\Raster\ScanlineFiller;
use Redcodede\QrGen\Qr\Raster\ShapeFlattener;
use Redcodede\QrGen\Qr\Raster\Transform;

/**
 * The smaller pieces of the rasteriser: shapes, colours and the palette.
 */
final class RasterPartsTest extends TestCase
{
    // ------------------------------------------------------------ shapes ---

    /**
     * How far under the true area a flattened curve is allowed to land.
     *
     * A chord across a convex curve cuts inside it, so a curve measured after
     * flattening always comes out slightly small — by roughly two thirds of the
     * perimeter times the sagitta. At r = 12 the sagitta is about a twentieth of
     * a pixel and the shortfall lands near two thirds of a percent, which is
     * what this allows. It shrinks as the artwork grows, because the tolerance
     * is in pixels and the segment count rises with the scale.
     *
     * The direction is asserted too. A tolerance on its own would accept a
     * radius that came out too large, and only the deficit is explainable.
     */
    private const CHORD_ALLOWANCE = 0.01;

    private static function assertCurvedArea(float $expected, float $measured, string $message = ''): void
    {
        self::assertLessThanOrEqual(
            $expected * 1.001,
            $measured,
            $message . ' — flattening can only lose area, never add it'
        );

        self::assertGreaterThanOrEqual(
            $expected * (1 - self::CHORD_ALLOWANCE),
            $measured,
            $message . ' — more than the chords can account for'
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    private function area(string $element, array $attributes, int $size = 40): float
    {
        $coverage = ScanlineFiller::coverage(
            ShapeFlattener::flatten($element, $attributes, Transform::identity()),
            $size,
            $size,
            false
        );

        $total = 0;

        foreach ($coverage as $row) {
            $total += array_sum($row);
        }

        return $total / ScanlineFiller::FULL_COVERAGE;
    }

    public function testARectCoversItsWidthTimesItsHeight(): void
    {
        self::assertEqualsWithDelta(
            200.0,
            $this->area('rect', ['x' => '5', 'y' => '5', 'width' => '20', 'height' => '10']),
            0.5
        );
    }

    public function testARoundedRectLosesTheCorners(): void
    {
        $square = $this->area('rect', ['x' => '5', 'y' => '5', 'width' => '20', 'height' => '20']);
        $rounded = $this->area('rect', [
            'x' => '5', 'y' => '5', 'width' => '20', 'height' => '20', 'rx' => '10',
        ]);

        // With rx at half the side the rect is a circle.
        self::assertEqualsWithDelta(400.0, $square, 1.0);
        self::assertCurvedArea(M_PI * 100.0, $rounded, 'rounded rect');
        self::assertLessThan($square, $rounded, 'Rounding corners can only take area away.');
    }

    public function testACircleCoversPiRSquared(): void
    {
        self::assertCurvedArea(
            M_PI * 144.0,
            $this->area('circle', ['cx' => '20', 'cy' => '20', 'r' => '12']),
            'circle'
        );
    }

    public function testAnEllipseCoversPiTimesBothRadii(): void
    {
        self::assertCurvedArea(
            M_PI * 15.0 * 8.0,
            $this->area('ellipse', ['cx' => '20', 'cy' => '20', 'rx' => '15', 'ry' => '8']),
            'ellipse'
        );
    }

    public function testAPolygonCoversItsOutline(): void
    {
        self::assertEqualsWithDelta(
            400.0,
            $this->area('polygon', ['points' => '0,0 20,0 20,20 0,20']),
            1.0
        );
    }

    public function testAPolylineIsFilledAsThoughClosed(): void
    {
        $polygon = $this->area('polygon', ['points' => '4,4 24,4 24,24 4,24']);
        $polyline = $this->area('polyline', ['points' => '4,4 24,4 24,24 4,24']);

        self::assertEqualsWithDelta($polygon, $polyline, 0.001);
    }

    /**
     * A line has no interior, and strokes are refused, so it contributes
     * nothing rather than being an error.
     */
    public function testALineDrawsNothing(): void
    {
        self::assertSame(
            [],
            ShapeFlattener::flatten('line', ['x1' => '0', 'y1' => '0', 'x2' => '9', 'y2' => '9'], Transform::identity())
        );
    }

    public function testAZeroSizedRectDrawsNothing(): void
    {
        self::assertSame(
            [],
            ShapeFlattener::flatten('rect', ['width' => '0', 'height' => '5'], Transform::identity())
        );
    }

    public function testAnOddCoordinateListIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/odd number of coordinates/');

        ShapeFlattener::flatten('polygon', ['points' => '0,0 10,0 10'], Transform::identity());
    }

    public function testAnElementWithNoRuleIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        ShapeFlattener::flatten('text', [], Transform::identity());
    }

    /**
     * Shapes go through the path grammar, and coordinates are written into it as
     * strings. A locale that writes a comma for the decimal point would produce
     * a path with an extra coordinate in it.
     */
    public function testShapeCoordinatesDoNotFollowTheLocale(): void
    {
        $before = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German');

        try {
            self::assertCurvedArea(
                M_PI * 100.0,
                $this->area('circle', ['cx' => '20.5', 'cy' => '20.5', 'r' => '10']),
                'circle under a comma-decimal locale'
            );
        } finally {
            setlocale(LC_NUMERIC, $before === false ? 'C' : $before);
        }
    }

    // ----------------------------------------------------------- colours ---

    public function testHexIsRead(): void
    {
        self::assertSame(0x009879, Color::parse('#009879'));
        self::assertSame(0xAABBCC, Color::parse('#abc'));
        self::assertSame(0xFFFFFF, Color::parse('#FFF'));
    }

    public function testFunctionalNotationIsRead(): void
    {
        self::assertSame(0x0A141E, Color::parse('rgb(10, 20, 30)'));
        self::assertSame(0xFF0000, Color::parse('rgba(255 0 0 / 0.5)'));
        self::assertSame(0xFFFFFF, Color::parse('rgb(100%, 100%, 100%)'));
    }

    public function testNamesAreRead(): void
    {
        self::assertSame(0x000000, Color::parse('black'));
        self::assertSame(0x808080, Color::parse('grey'));
        self::assertSame(0x808080, Color::parse('GRAY'));
    }

    public function testNothingPaintedIsNull(): void
    {
        self::assertNull(Color::parse('none'));
        self::assertNull(Color::parse(''));
        self::assertNull(Color::parse('transparent'));
    }

    public function testAColourThatCannotBeReadIsRefusedByName(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/papayawhip/');

        Color::parse('papayawhip');
    }

    public function testOpacityIsClampedRatherThanRefused(): void
    {
        self::assertSame(0.5, Color::opacity('0.5'));
        self::assertSame(0.25, Color::opacity('25%'));
        self::assertSame(1.0, Color::opacity('1.4'));
        self::assertSame(0.0, Color::opacity('-2'));
        self::assertSame(1.0, Color::opacity('inherit'), 'Unreadable means opaque, not invisible.');
    }

    // ----------------------------------------------------------- palette ---

    public function testReservedColoursTakeTheFirstIndices(): void
    {
        [$entries, $indices] = Palette::index(
            [0x336699, 0x336699, 0x336699],
            [0xFFFFFF, 0x000000]
        );

        self::assertSame([0xFFFFFF, 0x000000, 0x336699], $entries);
        self::assertSame([2, 2, 2], $indices);
    }

    public function testAReservedColourIsNotListedTwice(): void
    {
        [$entries] = Palette::index([0xFFFFFF, 0x000000], [0xFFFFFF, 0x000000]);

        self::assertSame([0xFFFFFF, 0x000000], $entries);
    }

    public function testARepeatedReservedColourCollapses(): void
    {
        [$entries, $indices] = Palette::index([0x123456], [0x808080, 0x808080]);

        self::assertSame([0x808080, 0x123456], $entries, 'The same colour twice is one entry.');
        self::assertSame([1], $indices);
    }

    /**
     * More colours than a palette holds is the case the reduction exists for.
     * It has to stay inside the limit and put every pixel somewhere close.
     */
    public function testTooManyColoursAreReducedToTheNearestSurvivor(): void
    {
        $pixels = [];

        for ($grey = 0; $grey < 400; $grey++) {
            $value = $grey % 256;
            $pixels[] = ($value << 16) | ($value << 8) | $value;
        }

        [$entries, $indices] = Palette::index($pixels, [0xFFFFFF, 0x000000]);

        self::assertLessThanOrEqual(Palette::MAX_ENTRIES, count($entries));
        self::assertCount(count($pixels), $indices);

        $worst = 0;

        foreach ($pixels as $position => $color) {
            $chosen = $entries[$indices[$position]];
            $worst = max($worst, abs((($color >> 16) & 0xFF) - (($chosen >> 16) & 0xFF)));
        }

        self::assertLessThanOrEqual(8, $worst, 'A reduced colour should land close to where it was.');
    }
}

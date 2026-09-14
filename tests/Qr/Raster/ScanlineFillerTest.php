<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Raster\PathFlattener;
use Redcodede\QrGen\Qr\Raster\ScanlineFiller;
use Redcodede\QrGen\Qr\Raster\Transform;

/**
 * The filler, checked by drawing small shapes and reading the pixels back.
 *
 * Written as pictures rather than as assertions about numbers, because that is
 * how a fill goes wrong: the coverage figures stay plausible and the shape
 * stops being the shape. A row of characters that has to match exactly catches
 * a winding error, a half-pixel shift and a leaked span all at once, and says
 * what went wrong at a glance when it fails.
 */
final class ScanlineFillerTest extends TestCase
{
    /** Coverage as characters: space empty, # full, + partial. */
    private function picture(string $d, int $size, bool $evenOdd = false): string
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten($d, Transform::identity()),
            $size,
            $size,
            $evenOdd
        );

        $lines = [];

        for ($y = 0; $y < $size; $y++) {
            $line = '';

            for ($x = 0; $x < $size; $x++) {
                $value = $coverage[$y][$x] ?? 0;
                $line .= $value === 0 ? ' ' : ($value === ScanlineFiller::FULL_COVERAGE ? '#' : '+');
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public function testASquareIsASquare(): void
    {
        self::assertSame(
            implode("\n", [
                '      ',
                ' #### ',
                ' #### ',
                ' #### ',
                ' #### ',
                '      ',
            ]),
            $this->picture('M1 1H5V5H1Z', 6)
        );
    }

    /**
     * A hole wound against its outline cancels to zero, which is what the
     * nonzero rule means and how every real logo punches a counter.
     */
    public function testOppositeWindingPunchesAHole(): void
    {
        self::assertSame(
            implode("\n", [
                '      ',
                ' #### ',
                ' #  # ',
                ' #  # ',
                ' #### ',
                '      ',
            ]),
            $this->picture('M1 1H5V5H1ZM2 2V4H4V2Z', 6)
        );
    }

    public function testSameWindingStaysSolidUnderNonzero(): void
    {
        self::assertSame(
            implode("\n", [
                '      ',
                ' #### ',
                ' #### ',
                ' #### ',
                ' #### ',
                '      ',
            ]),
            $this->picture('M1 1H5V5H1ZM2 2H4V4H2Z', 6)
        );
    }

    public function testSameWindingIsHollowUnderEvenOdd(): void
    {
        self::assertSame(
            implode("\n", [
                '      ',
                ' #### ',
                ' #  # ',
                ' #  # ',
                ' #### ',
                '      ',
            ]),
            $this->picture('M1 1H5V5H1ZM2 2H4V4H2Z', 6, true)
        );
    }

    public function testTwoSubpathsStayTwoShapes(): void
    {
        self::assertSame(
            implode("\n", [
                '##    ',
                '##    ',
                '      ',
                '      ',
                '    ##',
                '    ##',
            ]),
            $this->picture('M0 0H2V2H0ZM4 4H6V6H4Z', 6)
        );
    }

    /**
     * Half a pixel of a shape has to read as half covered. Without that a thin
     * feature drops out instead of going grey, which is what makes small artwork
     * look broken rather than soft.
     */
    public function testAPartialPixelIsPartiallyCovered(): void
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten('M0 0H0.5V1H0Z', Transform::identity()),
            2,
            2,
            false
        );

        self::assertSame(
            intdiv(ScanlineFiller::FULL_COVERAGE, 2),
            $coverage[0][0] ?? 0,
            'Half a pixel wide is half the samples.'
        );
    }

    public function testACoveredPixelIsFullyCovered(): void
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten('M0 0H2V2H0Z', Transform::identity()),
            2,
            2,
            false
        );

        self::assertSame(ScanlineFiller::FULL_COVERAGE, $coverage[1][1] ?? 0);
    }

    /**
     * A diagonal is the case supersampling exists for. Every pixel along it has
     * to be somewhere between empty and full.
     */
    public function testADiagonalEdgeIsAntiAliased(): void
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten('M0 0L8 8L0 8Z', Transform::identity()),
            8,
            8,
            false
        );

        $partial = 0;

        foreach ($coverage as $row) {
            foreach ($row as $value) {
                if ($value > 0 && $value < ScanlineFiller::FULL_COVERAGE) {
                    $partial++;
                }
            }
        }

        self::assertGreaterThanOrEqual(6, $partial, 'The hypotenuse should leave partial pixels.');
    }

    /**
     * A triangle covers half its bounding box. Summing the coverage is a
     * measurement of the whole fill rather than of one pixel, so it catches a
     * systematic half-pixel bias that individual pixels would hide.
     */
    public function testTotalCoverageMatchesTheArea(): void
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten('M0 0H40V40Z', Transform::identity()),
            40,
            40,
            false
        );

        $total = 0;

        foreach ($coverage as $row) {
            $total += array_sum($row);
        }

        $area = $total / ScanlineFiller::FULL_COVERAGE;

        self::assertEqualsWithDelta(800.0, $area, 8.0, 'Half of 40 x 40, within one percent.');
    }

    public function testGeometryOutsideTheBlockIsClipped(): void
    {
        $coverage = ScanlineFiller::coverage(
            PathFlattener::flatten('M-50 -50H50V50H-50Z', Transform::identity()),
            4,
            4,
            false
        );

        self::assertCount(4, $coverage, 'Four rows, not ninety-nine.');

        foreach ($coverage as $y => $row) {
            self::assertSame([0, 1, 2, 3], array_keys($row), "row $y stays inside the block");
        }
    }

    public function testAnEmptyOutlineCoversNothing(): void
    {
        self::assertSame([], ScanlineFiller::coverage([], 8, 8, false));
    }

    public function testADegenerateSubpathCoversNothing(): void
    {
        self::assertSame(
            [],
            ScanlineFiller::coverage([[[1.0, 1.0], [3.0, 3.0]]], 8, 8, false),
            'Two points enclose no area.'
        );
    }

    public function testAHorizontalSliverCoversNothing(): void
    {
        self::assertSame(
            [],
            ScanlineFiller::coverage([[[1.0, 1.0], [5.0, 1.0], [3.0, 1.0]]], 8, 8, false),
            'All on one line, so there is no interior.'
        );
    }
}

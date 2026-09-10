<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Logo;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\ModuleMatrix;

final class LogoBoxTest extends TestCase
{
    private function blankMatrix(int $size): ModuleMatrix
    {
        return new ModuleMatrix(array_fill(0, $size, array_fill(0, $size, false)));
    }

    /**
     * A QR symbol is always an odd number of modules across (17 + 4 × version).
     * An even box would therefore sit half a module off the grid and clear parts
     * of modules instead of whole ones.
     */
    public function testAnEvenBoxIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('odd number of modules');

        LogoBox::square(10);
    }

    public function testAnOddBoxIsAccepted(): void
    {
        $box = LogoBox::square(11, 1);

        self::assertSame(11, $box->width());
        self::assertSame(11, $box->height());
        self::assertSame(1, $box->margin());
    }

    public function testARectangularBoxIsAllowedBecauseLogosAreNotSquare(): void
    {
        $box = LogoBox::of(15, 9, 1);

        self::assertSame(15, $box->width());
        self::assertSame(9, $box->height());
    }

    public function testAMarginThatLeavesNothingToDrawInIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('leaves nothing to draw in');

        LogoBox::square(5, 3);
    }

    /**
     * @dataProvider aspectRatios
     */
    public function testABoxCanBeFittedToAnAspectRatio(
        float $ratio,
        int $widthModules,
        int $expectedWidth,
        int $expectedHeight
    ): void {
        $box = LogoBox::forAspectRatio($ratio, $widthModules);

        self::assertSame($expectedWidth, $box->width());
        self::assertSame($expectedHeight, $box->height());
        self::assertSame(1, $box->width() % 2, 'The width has to come out odd.');
        self::assertSame(1, $box->height() % 2, 'The height has to come out odd.');
    }

    /**
     * @return iterable<string, array{float, int, int, int}>
     */
    public static function aspectRatios(): iterable
    {
        yield 'square' => [1.0, 11, 11, 11];
        yield 'the demo svg, 1:1' => [1.0, 9, 9, 9];
        yield 'the gvoe svg, 1.20:1' => [383 / 319, 13, 13, 11];
        yield 'the gvoe png, 1.65:1' => [1556 / 942, 15, 15, 9];
        yield 'tall' => [0.5, 11, 5, 11];
    }

    public function testTheBoxIsCentred(): void
    {
        $placement = LogoBox::square(11)->placeIn($this->blankMatrix(33));

        self::assertSame(11, $placement->x());
        self::assertSame(11, $placement->y());
        self::assertSame(121, $placement->clearedModules());
    }

    public function testTheDrawableAreaSitsInsideTheMargin(): void
    {
        $placement = LogoBox::square(11, 1)->placeIn($this->blankMatrix(33));

        self::assertSame(12, $placement->drawableX());
        self::assertSame(12, $placement->drawableY());
        self::assertSame(9, $placement->drawableWidth());
        self::assertSame(9, $placement->drawableHeight());
    }

    public function testABoxLargerThanTheSymbolIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('does not fit');

        LogoBox::square(25)->placeIn($this->blankMatrix(21));
    }

    /**
     * The finder patterns and their separators occupy the first and last eight
     * modules of each axis. Checked geometrically, so a matrix built by hand
     * without a function pattern mask is still protected.
     */
    public function testABoxThatReachesAFinderCornerIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('finder pattern');

        LogoBox::square(11)->placeIn($this->blankMatrix(21));
    }

    /**
     * Alignment patterns carry no error correction, and for some versions one
     * sits close enough to the centre to be hit. This is the case the geometric
     * check cannot see and the mask can.
     */
    public function testABoxOverAnAlignmentPatternIsRefused(): void
    {
        // 23 bytes at level M lands on version 2, whose alignment pattern spans
        // modules 16 to 20 — a 9x9 box centred on 25 reaches module 16.
        $matrix = (new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::medium());

        self::assertSame(25, $matrix->size(), 'Guard: this test assumes a version 2 symbol.');

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('function pattern');

        LogoBox::square(9)->placeIn($matrix);
    }

    /**
     * The same payload at level H moves to a larger version whose alignment
     * pattern sits elsewhere, which is why a logo wants H for more than the
     * recovery rate alone.
     */
    public function testTheSameBoxFitsOnceAHigherLevelMovesTheAlignmentPattern(): void
    {
        $matrix = (new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::high());
        $placement = LogoBox::square(9)->placeIn($matrix);

        self::assertSame(81, $placement->clearedModules());
    }

    public function testWithoutAMaskOnlyTheGeometricCheckApplies(): void
    {
        $matrix = $this->blankMatrix(33);

        self::assertFalse($matrix->hasReservedInfo());

        // Would be refused against a real symbol's mask; here there is nothing
        // to check it against, and the class says so rather than pretending.
        $placement = LogoBox::square(15)->placeIn($matrix);

        self::assertSame(9, $placement->x());
    }
}

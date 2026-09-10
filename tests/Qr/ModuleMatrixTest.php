<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;

final class ModuleMatrixTest extends TestCase
{
    public function testItReportsItsSide(): void
    {
        self::assertSame(2, (new ModuleMatrix([[true, false], [false, true]]))->size());
    }

    public function testItReadsModulesByCoordinate(): void
    {
        $matrix = new ModuleMatrix([
            [true, false],
            [false, true],
        ]);

        self::assertTrue($matrix->isDark(0, 0));
        self::assertFalse($matrix->isDark(1, 0));
        self::assertFalse($matrix->isDark(0, 1));
        self::assertTrue($matrix->isDark(1, 1));
    }

    /**
     * Rows are indexed [y][x], the order a renderer walks them in. Getting this
     * the wrong way round produces a mirrored symbol that still looks plausible,
     * so it is worth asserting rather than assuming.
     */
    public function testCoordinatesAreXThenYAndRowsAreYThenX(): void
    {
        $matrix = new ModuleMatrix([
            [false, true],
            [false, false],
        ]);

        self::assertTrue($matrix->isDark(1, 0), 'The dark module sits at x=1, y=0.');
        self::assertTrue($matrix->rows()[0][1]);
    }

    public function testItRejectsAnEmptyGrid(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('cannot be empty');

        new ModuleMatrix([]);
    }

    public function testItRejectsAGridThatIsNotSquare(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('has to be square');

        new ModuleMatrix([[true, false, true], [false, true, false]]);
    }

    public function testItRejectsModulesThatAreNotBooleans(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Module at 1,0 is not a boolean');

        /** @phpstan-ignore-next-line intentionally wrong type */
        new ModuleMatrix([[true, 1], [false, false]]);
    }

    /**
     * @dataProvider coordinatesOutsideATwoByTwo
     */
    public function testItRejectsCoordinatesOutsideTheGrid(int $x, int $y): void
    {
        $matrix = new ModuleMatrix([[true, true], [true, true]]);

        $this->expectException(InvalidArgument::class);

        $matrix->isDark($x, $y);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function coordinatesOutsideATwoByTwo(): iterable
    {
        yield 'negative x' => [-1, 0];
        yield 'negative y' => [0, -1];
        yield 'x past the edge' => [2, 0];
        yield 'y past the edge' => [0, 2];
    }

    public function testKeysAreNormalizedSoASparseInputStillIndexesFromZero(): void
    {
        $matrix = new ModuleMatrix([
            5 => [3 => true, 4 => false],
            9 => [7 => false, 8 => true],
        ]);

        self::assertTrue($matrix->isDark(0, 0));
        self::assertTrue($matrix->isDark(1, 1));
    }

    /**
     * A matrix built by hand has no idea which modules are function patterns,
     * and the difference between "not one" and "nobody said" matters: the first
     * clears a logo placement, the second means it cannot be checked.
     */
    public function testWithoutAMaskItSaysSoRatherThanClaimingNothingIsReserved(): void
    {
        $matrix = new ModuleMatrix([[true, false], [false, true]]);

        self::assertFalse($matrix->hasReservedInfo());
        self::assertFalse($matrix->isReserved(0, 0));
    }

    public function testAMaskMarksWhichModulesAreFunctionPatterns(): void
    {
        $matrix = new ModuleMatrix(
            [[true, false], [false, true]],
            [[true, true], [false, false]]
        );

        self::assertTrue($matrix->hasReservedInfo());
        self::assertTrue($matrix->isReserved(0, 0));
        self::assertTrue($matrix->isReserved(1, 0));
        self::assertFalse($matrix->isReserved(0, 1));
    }

    public function testAMaskOfTheWrongSizeIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('function pattern mask');

        new ModuleMatrix(
            [[true, false, true], [false, true, false], [true, false, true]],
            [[true, true], [false, false]]
        );
    }

    /**
     * @dataProvider sidesAndVersions
     */
    public function testItDerivesTheVersionFromItsSide(int $size, int $expected): void
    {
        $matrix = new ModuleMatrix(array_fill(0, $size, array_fill(0, $size, false)));

        self::assertSame($expected, $matrix->version());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sidesAndVersions(): iterable
    {
        yield 'smallest' => [21, 1];
        yield 'version 4' => [33, 4];
        yield 'largest' => [177, 40];
    }

    public function testAsciiArtDrawsDarkModulesAndKeepsRowsOnSeparateLines(): void
    {
        $matrix = new ModuleMatrix([
            [true, false],
            [false, true],
        ]);

        self::assertSame("#.\n.#", $matrix->toAsciiArt('#', '.'));
    }
}

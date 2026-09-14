<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Raster\RasterScaler;

/**
 * Resampling, with the halo case first because it is the one that goes wrong.
 */
final class RasterScalerTest extends TestCase
{
    /**
     * @param list<array{0: int, 1: int, 2: int, 3: int}> $pixels
     */
    private static function pack(array $pixels): string
    {
        $out = '';

        foreach ($pixels as $pixel) {
            $out .= chr($pixel[0]) . chr($pixel[1]) . chr($pixel[2]) . chr($pixel[3]);
        }

        return $out;
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private static function unpack(string $rgba): array
    {
        $out = [];

        for ($at = 0; $at < strlen($rgba); $at += 4) {
            $out[] = [ord($rgba[$at]), ord($rgba[$at + 1]), ord($rgba[$at + 2]), ord($rgba[$at + 3])];
        }

        return $out;
    }

    /**
     * The one that matters.
     *
     * A transparent pixel has a colour underneath its zero alpha, and exporters
     * leave anything there — usually black. Averaged without premultiplying,
     * that black is mixed into the neighbour and the artwork comes back with a
     * dark rim it never had. Premultiplied, a fully transparent pixel
     * contributes nothing but transparency.
     */
    public function testATransparentNeighbourDoesNotDarkenTheColour(): void
    {
        $source = self::pack([
            [255, 255, 255, 255],   // opaque white
            [0, 0, 0, 0],           // transparent, with black hiding under it
        ]);

        $scaled = self::unpack(RasterScaler::resample($source, 2, 1, 1, 1));

        self::assertSame(
            [[255, 255, 255, 128]],
            $scaled,
            'The colour stays white and only the alpha halves.'
        );
    }

    public function testShrinkingAveragesTheWholeArea(): void
    {
        $source = self::pack([
            [0, 0, 0, 255], [100, 100, 100, 255],
            [200, 200, 200, 255], [0, 0, 0, 255],
        ]);

        self::assertSame(
            [[75, 75, 75, 255]],
            self::unpack(RasterScaler::resample($source, 2, 2, 1, 1)),
            'The mean of 0, 100, 200 and 0.'
        );
    }

    /**
     * Halving each side means four source pixels behind each destination one.
     * A filter that sampled instead of averaging would keep only one of them
     * and pass this test by accident on a flat image, so the fixture is not
     * flat.
     */
    public function testShrinkingByHalfKeepsTheStructure(): void
    {
        $source = self::pack([
            [255, 0, 0, 255], [255, 0, 0, 255], [0, 0, 255, 255], [0, 0, 255, 255],
            [255, 0, 0, 255], [255, 0, 0, 255], [0, 0, 255, 255], [0, 0, 255, 255],
            [0, 255, 0, 255], [0, 255, 0, 255], [255, 255, 255, 255], [255, 255, 255, 255],
            [0, 255, 0, 255], [0, 255, 0, 255], [255, 255, 255, 255], [255, 255, 255, 255],
        ]);

        self::assertSame(
            [
                [255, 0, 0, 255], [0, 0, 255, 255],
                [0, 255, 0, 255], [255, 255, 255, 255],
            ],
            self::unpack(RasterScaler::resample($source, 4, 4, 2, 2))
        );
    }

    public function testEnlargingKeepsAFlatColour(): void
    {
        $source = self::pack([[10, 20, 30, 200]]);

        $scaled = self::unpack(RasterScaler::resample($source, 1, 1, 3, 3));

        self::assertCount(9, $scaled);

        foreach ($scaled as $index => $pixel) {
            self::assertSame([10, 20, 30, 200], $pixel, "pixel $index");
        }
    }

    /**
     * Enlarging interpolates, so the middle of a two-pixel gradient has to land
     * between the two. Repeating pixels would give a hard step.
     */
    public function testEnlargingInterpolatesBetweenNeighbours(): void
    {
        $source = self::pack([[0, 0, 0, 255], [255, 255, 255, 255]]);

        $scaled = self::unpack(RasterScaler::resample($source, 2, 1, 4, 1));

        self::assertSame(0, $scaled[0][0], 'The left end stays black.');
        self::assertSame(255, $scaled[3][0], 'The right end stays white.');
        self::assertGreaterThan($scaled[0][0], $scaled[1][0], 'It should climb, not step.');
        self::assertGreaterThan($scaled[1][0], $scaled[2][0]);
    }

    public function testTheSameSizeIsHandedBackUntouched(): void
    {
        $source = self::pack([[1, 2, 3, 4], [5, 6, 7, 8]]);

        self::assertSame($source, RasterScaler::resample($source, 2, 1, 2, 1));
    }

    public function testFullyTransparentSourcesStayTransparent(): void
    {
        $source = self::pack([[0, 0, 0, 0], [255, 255, 255, 0]]);

        self::assertSame(
            [[0, 0, 0, 0]],
            self::unpack(RasterScaler::resample($source, 2, 1, 1, 1))
        );
    }

    public function testTheResultIsAlwaysFourBytesPerPixel(): void
    {
        $source = str_repeat("\x40\x80\xc0\xff", 7 * 5);

        foreach ([[3, 2], [11, 9], [7, 5], [1, 1]] as $target) {
            self::assertSame(
                $target[0] * $target[1] * 4,
                strlen(RasterScaler::resample($source, 7, 5, $target[0], $target[1])),
                sprintf('%d x %d', $target[0], $target[1])
            );
        }
    }
}

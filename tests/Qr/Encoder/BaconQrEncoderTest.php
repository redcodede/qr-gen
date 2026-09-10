<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Encoder;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\EncodingFailed;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * A wrong QR code does not look wrong, so these tests check the structures the
 * specification fixes in place rather than only the shape of the output.
 *
 * If the encoder is ever swapped for one of our own, this file is the contract
 * it has to satisfy, and the finder-pattern and timing-pattern assertions are
 * what will catch an off-by-one in module placement or an inverted polarity.
 */
final class BaconQrEncoderTest extends TestCase
{
    private const DEMO_URL = 'https://www.redcode.de/';

    public function testAShortPayloadFitsTheSmallestSymbol(): void
    {
        $matrix = (new BaconQrEncoder())->encode('HI', ErrorCorrection::low());

        self::assertSame(21, $matrix->size(), 'Version 1 is 21 modules per side.');
    }

    /**
     * Every QR version is 17 + 4 × version modules wide, so a valid side length
     * is always one more than a multiple of four.
     */
    public function testTheSideLengthIsAValidQrVersion(): void
    {
        $matrix = (new BaconQrEncoder())->encode(self::DEMO_URL, ErrorCorrection::medium());

        self::assertGreaterThanOrEqual(21, $matrix->size());
        self::assertLessThanOrEqual(177, $matrix->size());
        self::assertSame(1, $matrix->size() % 4);
    }

    public function testTheThreeFinderPatternsAreWhereTheSpecPutsThem(): void
    {
        $matrix = (new BaconQrEncoder())->encode(self::DEMO_URL, ErrorCorrection::medium());
        $last = $matrix->size() - 7;

        $this->assertFinderPatternAt($matrix, 0, 0);
        $this->assertFinderPatternAt($matrix, $last, 0);
        $this->assertFinderPatternAt($matrix, 0, $last);
    }

    /**
     * There is no finder in the bottom right corner. If one turned up there the
     * symbol would be unreadable, because that corner is how a scanner works out
     * the rotation.
     */
    public function testThereIsNoFinderPatternInTheFourthCorner(): void
    {
        $matrix = (new BaconQrEncoder())->encode(self::DEMO_URL, ErrorCorrection::medium());
        $last = $matrix->size() - 7;
        $corner = [];

        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $corner[] = $matrix->isDark($last + $x, $last + $y);
            }
        }

        self::assertNotSame($this->finderPatternModules(), $corner);
    }

    /**
     * Row six and column six alternate dark and light between the finders, dark
     * on even coordinates. This is what a scanner measures the module pitch
     * against.
     */
    public function testTheTimingPatternsAlternate(): void
    {
        $matrix = (new BaconQrEncoder())->encode(self::DEMO_URL, ErrorCorrection::medium());

        for ($i = 8; $i < $matrix->size() - 8; $i++) {
            $expected = $i % 2 === 0;

            self::assertSame($expected, $matrix->isDark($i, 6), "Horizontal timing at x={$i}.");
            self::assertSame($expected, $matrix->isDark(6, $i), "Vertical timing at y={$i}.");
        }
    }

    /**
     * The specification fixes one module as always dark, at (8, 4 × version + 9).
     * It is the cheapest single check that placement is aligned.
     */
    public function testTheAlwaysDarkModuleIsDark(): void
    {
        $matrix = (new BaconQrEncoder())->encode(self::DEMO_URL, ErrorCorrection::medium());
        $version = intdiv($matrix->size() - 17, 4);

        self::assertTrue($matrix->isDark(8, (4 * $version) + 9));
    }

    public function testEncodingIsDeterministic(): void
    {
        $encoder = new BaconQrEncoder();

        self::assertSame(
            $encoder->encode(self::DEMO_URL, ErrorCorrection::high())->rows(),
            $encoder->encode(self::DEMO_URL, ErrorCorrection::high())->rows()
        );
    }

    /**
     * More recovery means more codewords for the same payload, so the symbol can
     * only stay the same size or grow.
     */
    public function testAStrongerLevelNeverProducesASmallerSymbol(): void
    {
        $encoder = new BaconQrEncoder();
        $previous = 0;

        foreach (ErrorCorrection::all() as $level) {
            $size = $encoder->encode(self::DEMO_URL, ErrorCorrection::fromString($level))->size();

            self::assertGreaterThanOrEqual($previous, $size, "Level {$level} shrank the symbol.");

            $previous = $size;
        }
    }

    public function testItRefusesAnEmptyPayload(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('nothing to encode');

        (new BaconQrEncoder())->encode('', ErrorCorrection::medium());
    }

    public function testItFailsWhenThePayloadExceedsTheLargestSymbol(): void
    {
        $this->expectException(EncodingFailed::class);

        (new BaconQrEncoder())->encode(str_repeat('a', 5000), ErrorCorrection::high());
    }

    /**
     * The failure reports how much data there was, not what it was. Anything put
     * into this message ends up in whatever log catches the exception, and a
     * package that promises not to store the URLs it processes cannot then post
     * them into a stack trace.
     */
    public function testTheFailureMessageReportsTheLengthAndNotThePayload(): void
    {
        $secret = str_repeat('https://gvoe.de/return/7K4M2?nope=', 200);

        try {
            (new BaconQrEncoder())->encode($secret, ErrorCorrection::high());
            self::fail('Expected an EncodingFailed.');
        } catch (EncodingFailed $exception) {
            self::assertStringContainsString((string) strlen($secret), $exception->getMessage());
            self::assertStringNotContainsString('gvoe.de', $exception->getMessage());
            self::assertStringNotContainsString('7K4M2', $exception->getMessage());
        }
    }

    private function assertFinderPatternAt(ModuleMatrix $matrix, int $originX, int $originY): void
    {
        $expected = $this->finderPatternModules();
        $actual = [];

        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $actual[] = $matrix->isDark($originX + $x, $originY + $y);
            }
        }

        self::assertSame(
            $expected,
            $actual,
            "The 7x7 finder pattern at {$originX},{$originY} is not the one the spec describes."
        );
    }

    /**
     * A finder pattern read row by row: a dark 7x7 border, a light ring inside
     * it, and a dark 3x3 core. Expressed as the Chebyshev distance from the
     * centre, rings 0 and 1 are dark, ring 2 is light, ring 3 is dark.
     *
     * @return list<bool>
     */
    private function finderPatternModules(): array
    {
        $modules = [];

        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $ring = max(abs($x - 3), abs($y - 3));
                $modules[] = $ring !== 2;
            }
        }

        return $modules;
    }
}

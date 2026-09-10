<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;

final class ErrorCorrectionTest extends TestCase
{
    public function testNamedConstructorsCarryTheSpecLetters(): void
    {
        self::assertSame('L', ErrorCorrection::low()->value());
        self::assertSame('M', ErrorCorrection::medium()->value());
        self::assertSame('Q', ErrorCorrection::quartile()->value());
        self::assertSame('H', ErrorCorrection::high()->value());
    }

    /**
     * A level arrives as a string from a config file or a form, so parsing has
     * to tolerate the shapes those produce.
     *
     * @dataProvider acceptedSpellings
     */
    public function testFromStringNormalizesCaseAndWhitespace(string $given, string $expected): void
    {
        self::assertSame($expected, ErrorCorrection::fromString($given)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedSpellings(): iterable
    {
        yield 'upper' => ['H', 'H'];
        yield 'lower' => ['h', 'H'];
        yield 'padded' => ['  m  ', 'M'];
    }

    public function testFromStringRejectsAnythingElse(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Unknown error correction level "X"');

        ErrorCorrection::fromString('X');
    }

    public function testTheRejectionListsWhatWouldHaveWorked(): void
    {
        try {
            ErrorCorrection::fromString('best');
            self::fail('Expected an InvalidArgument.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('L, M, Q, H', $exception->getMessage());
        }
    }

    public function testAllReturnsTheFourLevelsWeakestFirst(): void
    {
        self::assertSame(['L', 'M', 'Q', 'H'], ErrorCorrection::all());
    }

    public function testLevelsCompareByValueRatherThanIdentity(): void
    {
        self::assertTrue(ErrorCorrection::high()->equals(ErrorCorrection::fromString('h')));
        self::assertFalse(ErrorCorrection::high()->equals(ErrorCorrection::low()));
    }

    /**
     * @dataProvider recoveryRates
     */
    public function testItKnowsItsRecoveryRate(string $level, float $expected): void
    {
        self::assertSame($expected, ErrorCorrection::fromString($level)->recoveryRate());
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function recoveryRates(): iterable
    {
        yield 'L' => ['L', 0.07];
        yield 'M' => ['M', 0.15];
        yield 'Q' => ['Q', 0.25];
        yield 'H' => ['H', 0.30];
    }

    public function testTheRecoveryRateRisesWithTheLevel(): void
    {
        $previous = 0.0;

        foreach (ErrorCorrection::all() as $level) {
            $rate = ErrorCorrection::fromString($level)->recoveryRate();

            self::assertGreaterThan($previous, $rate, "Level {$level} did not raise the rate.");

            $previous = $rate;
        }
    }

    public function testItCastsToItsLetter(): void
    {
        self::assertSame('Q', (string) ErrorCorrection::quartile());
    }
}

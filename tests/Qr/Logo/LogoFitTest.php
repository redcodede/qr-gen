<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Logo;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\NoFittingLevel;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\LogoFit;

final class LogoFitTest extends TestCase
{
    private function fit(?float $budgetShare = null): LogoFit
    {
        return $budgetShare === null
            ? new LogoFit(new BaconQrEncoder())
            : new LogoFit(new BaconQrEncoder(), $budgetShare);
    }

    /**
     * The case that was reported: an 11x11 box refused at level M. The answer is
     * H, and the class says so instead of leaving someone to work out that the
     * level changes the symbol size.
     */
    public function testItResolvesTheReportedCaseToLevelH(): void
    {
        $result = $this->fit()->lowestLevelFor('https://www.redcode.de/', LogoBox::square(11, 1));

        self::assertSame('H', $result->level()->value());
        self::assertSame(29, $result->matrix()->size());
        self::assertSame(121, $result->placement()->clearedModules());
    }

    /**
     * The lowest level that survives, not the highest available. A lower level
     * means fewer modules across the same printed width, and therefore larger
     * modules, which is what a scanner has an easier time with.
     *
     * The steps are visible here: a 3x3 box costs 1.4% of a version 2 symbol
     * and fits inside L's allowance of 3.5%; a 5x5 costs 4.0% and needs M.
     *
     * @dataProvider boxesAndLevels
     */
    public function testTheSmallestBoxThatSurvivesPicksTheLowestLevel(int $modules, string $expected): void
    {
        $result = $this->fit()->lowestLevelFor('https://www.redcode.de/', LogoBox::square($modules, 1));

        self::assertSame($expected, $result->level()->value());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function boxesAndLevels(): iterable
    {
        yield '3 modules' => [3, 'L'];
        yield '5 modules' => [5, 'M'];
        yield '9 modules' => [9, 'Q'];
        yield '11 modules' => [11, 'H'];
    }

    /**
     * A longer payload makes a larger symbol, so the same box covers a smaller
     * share of it. Counterintuitive but useful: the real briefing URL has more
     * headroom than the shorter demo one.
     */
    public function testALongerPayloadLeavesMoreHeadroomForTheSameBox(): void
    {
        $fit = $this->fit();
        $box = LogoBox::square(11, 1);

        $short = $fit->lowestLevelFor('https://www.redcode.de/', $box);
        $briefing = $fit->lowestLevelFor('https://example.org/qr/7K4M2', $box);

        self::assertSame(29, $short->matrix()->size());
        self::assertSame(33, $briefing->matrix()->size());
        self::assertGreaterThan($short->headroom(), $briefing->headroom());
    }

    public function testItReportsTheNumbersBehindTheDecision(): void
    {
        $result = $this->fit()->lowestLevelFor('https://example.org/qr/7K4M2', LogoBox::square(11, 1));

        self::assertSame(11.1, round($result->clearedShare() * 100, 1));
        self::assertSame(15.0, round($result->budget() * 100, 1));
        self::assertGreaterThan(0.2, $result->headroom());
        self::assertFalse($result->compromisesAlignment());
    }

    /**
     * The safety factor is the whole reason this is defensible: the cleared area
     * is a share of modules, the recovery rate a share of codewords. Spending
     * the entire allowance leaves a code that scans on a screen and fails on a
     * shelf, so half of it is the default — and it is adjustable, because
     * whoever ran a print proof knows better than this class.
     */
    public function testASlackerSafetyFactorAcceptsALowerLevel(): void
    {
        $box = LogoBox::square(11, 1);

        self::assertSame(
            'H',
            $this->fit()->lowestLevelFor('https://www.redcode.de/', $box)->level()->value()
        );

        self::assertSame(
            'Q',
            $this->fit(1.0)->lowestLevelFor('https://www.redcode.de/', $box)->level()->value()
        );
    }

    public function testAStricterSafetyFactorCanRuleOutEveryLevel(): void
    {
        $this->expectException(NoFittingLevel::class);

        $this->fit(0.1)->lowestLevelFor('https://www.redcode.de/', LogoBox::square(11, 1));
    }

    /**
     * @dataProvider invalidSafetyFactors
     */
    public function testAnImpossibleSafetyFactorIsRefused(float $share): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('safety factor');

        new LogoFit(new BaconQrEncoder(), $share);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidSafetyFactors(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-0.5];
        yield 'above one' => [1.5];
    }

    /**
     * "It does not fit" is useless on its own. Whether to shrink the box,
     * shorten the payload or accept a compromise depends on which wall was hit
     * at which level, so all four are reported.
     */
    public function testAnImpossibleBoxReportsEveryLevelAndWhatWouldWork(): void
    {
        try {
            $this->fit()->lowestLevelFor('https://www.redcode.de/', LogoBox::square(17, 1));
            self::fail('Expected a NoFittingLevel.');
        } catch (NoFittingLevel $exception) {
            self::assertSame(['L', 'M', 'Q', 'H'], array_keys($exception->reasons()));

            foreach ($exception->reasons() as $level => $reason) {
                self::assertStringContainsString('symbol', $reason, "Level {$level} gave no symbol size.");
            }

            self::assertStringContainsString(
                'largest box that survives is 11x11',
                $exception->getMessage()
            );
        }
    }

    public function testItFindsTheLargestBoxThatSurvivesAtALevel(): void
    {
        $largest = $this->fit()->largestFittingAt(
            'https://www.redcode.de/',
            ErrorCorrection::high(),
            LogoBox::square(21, 1)
        );

        self::assertNotNull($largest);
        self::assertSame(11, $largest->width());
    }

    public function testTheLargestBoxNeverGrowsBeyondWhatWasAskedFor(): void
    {
        $largest = $this->fit()->largestFittingAt(
            'https://example.org/qr/7K4M2',
            ErrorCorrection::high(),
            LogoBox::square(7, 1)
        );

        self::assertNotNull($largest);
        self::assertSame(7, $largest->width());
    }

    /**
     * A payload that lands on a centre-occupied version at **every** level, so
     * a strict box finds nowhere to go and the permissive one succeeds. Without
     * the permission these symbols would be logo-proof.
     *
     * 140 bytes gives versions 7, 8, 10 and 12 across L, M, Q and H — all four
     * inside the 7-to-13 band where an alignment pattern sits at the centre.
     */
    public function testASymbolWithAnOccupiedCentreNeedsThePermission(): void
    {
        $fit = $this->fit();
        $payload = str_repeat('a', 140);

        try {
            $fit->lowestLevelFor($payload, LogoBox::square(11, 1));
            self::fail('Expected a NoFittingLevel: every level puts an alignment pattern in the middle.');
        } catch (NoFittingLevel $exception) {
            self::assertStringContainsString('alignment pattern', $exception->getMessage());
        }

        $result = $fit->lowestLevelFor($payload, LogoBox::square(11, 1)->allowingAlignmentPatterns());

        self::assertTrue($result->compromisesAlignment());
        self::assertSame(25, $result->placement()->coveredAlignmentModules());
    }

    public function testTheResultCarriesTheMatrixSoNothingIsEncodedTwice(): void
    {
        $result = $this->fit()->lowestLevelFor('https://example.org/qr/7K4M2', LogoBox::square(11, 1));

        self::assertSame(
            (new BaconQrEncoder())->encode('https://example.org/qr/7K4M2', ErrorCorrection::high())->rows(),
            $result->matrix()->rows()
        );
    }
}

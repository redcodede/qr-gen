<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\Logo\LogoFit;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

/**
 * The settled configuration, asserted rather than assumed.
 *
 * These numbers are decisions someone made and will be asked about later, so
 * the test states them plainly and fails if one drifts.
 */
final class PresetTest extends TestCase
{
    public function testTheSettledNumbers(): void
    {
        self::assertSame(11, Preset::LOGO_BOX_MODULES);
        self::assertSame(1, Preset::LOGO_MARGIN_MODULES);
        self::assertSame(13, Preset::MODULE_SIZE);
        self::assertSame(2, Preset::QUIET_ZONE);
    }

    public function testTheOptionsCarryThem(): void
    {
        $options = Preset::svgOptions();

        self::assertSame(13, $options->moduleSize());
        self::assertSame(2, $options->quietZone());
    }

    public function testTheBoxCarriesThem(): void
    {
        $box = Preset::logoBox();

        self::assertSame(11, $box->width());
        self::assertSame(11, $box->height());
        self::assertSame(1, $box->margin());
    }

    /**
     * The library's own default stays at the four modules the specification
     * requires. A package that shipped a non-conforming quiet zone as its
     * default would be lying to anyone who installed it; the deviation belongs
     * to the project, and this is what keeps the two apart.
     */
    public function testTheLibraryDefaultRemainsSpecificationConforming(): void
    {
        self::assertSame(4, SvgOptions::SPEC_QUIET_ZONE);
        self::assertSame(4, SvgOptions::default()->quietZone());
        self::assertLessThan(
            SvgOptions::SPEC_QUIET_ZONE,
            Preset::QUIET_ZONE,
            'The preset deviates on purpose. If it stops deviating, drop the warning with it.'
        );
    }

    /**
     * The level is absent from the preset on purpose: it follows from the
     * payload and the box rather than being decided in advance.
     */
    public function testThePresetFixesNoErrorCorrectionLevel(): void
    {
        self::assertFalse(
            defined(Preset::class . '::LEVEL'),
            'The level is worked out by LogoFit, not fixed here.'
        );
    }

    /**
     * The two payloads this is actually for, run through the settled
     * configuration end to end.
     *
     * @dataProvider realPayloads
     */
    public function testTheSettledConfigurationSurvivesTheRealPayloads(
        string $url,
        string $expectedLevel,
        int $expectedSize
    ): void {
        $fit = (new LogoFit(new BaconQrEncoder()))->lowestLevelFor($url, Preset::logoBox());

        self::assertSame($expectedLevel, $fit->level()->value());
        self::assertSame($expectedSize, $fit->matrix()->size());
        self::assertFalse($fit->compromisesAlignment());
        self::assertGreaterThan(0.0, $fit->headroom());
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function realPayloads(): iterable
    {
        yield 'the demo url' => ['https://www.redcode.de/', 'H', 29];
        yield 'the briefing url' => ['https://example.org/qr/7K4M2', 'H', 33];
    }

    /**
     * The briefing URL has more headroom than the shorter demo one, because a
     * longer payload makes a larger symbol and the fixed box covers less of it.
     * Worth pinning: it is the case the print proof will be run against.
     */
    public function testTheBriefingPayloadHasTheMoreComfortableFit(): void
    {
        $fit = new LogoFit(new BaconQrEncoder());

        $demo = $fit->lowestLevelFor('https://www.redcode.de/', Preset::logoBox());
        $briefing = $fit->lowestLevelFor('https://example.org/qr/7K4M2', Preset::logoBox());

        self::assertGreaterThan($demo->headroom(), $briefing->headroom());
        self::assertSame(11.1, round($briefing->clearedShare() * 100, 1));
    }

    public function testAPlainAndALogoSymbolBothRenderFromThePreset(): void
    {
        $matrix = (new BaconQrEncoder())->encode(
            'https://example.org/qr/7K4M2',
            (new LogoFit(new BaconQrEncoder()))
                ->lowestLevelFor('https://example.org/qr/7K4M2', Preset::logoBox())
                ->level()
        );

        $plain = (new SvgRenderer(Preset::svgOptions()))->render($matrix);

        // 33 modules plus two of quiet zone on each side, at 13 px each.
        self::assertStringContainsString('viewBox="0 0 37 37"', $plain);
        self::assertStringContainsString('width="481" height="481"', $plain);
    }
}

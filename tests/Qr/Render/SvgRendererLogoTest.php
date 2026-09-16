<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

final class SvgRendererLogoTest extends TestCase
{
    /**
     * All dark, so every module the logo box clears shows up as a gap in the
     * path and can be counted.
     */
    private function solidMatrix(int $size = 33): ModuleMatrix
    {
        return new ModuleMatrix(array_fill(0, $size, array_fill(0, $size, true)));
    }

    private function logo(): SvgLogo
    {
        return SvgLogo::fromMarkup(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<path d="M0 0h10v5z" fill="#e2b0d1"/></svg>'
        );
    }

    private function options(): SvgOptions
    {
        return SvgOptions::default()
            ->withModuleSize(1)
            ->withQuietZone(0)
            ->withLogo($this->logo(), LogoBox::square(11, 1));
    }

    /**
     * Modules under the box are skipped rather than drawn and covered, so the
     * artwork sits on the backdrop instead of on top of dark modules and the
     * path stays as small as the symbol allows.
     */
    public function testTheBoxIsClearedRatherThanCoveredOver(): void
    {
        $svg = (new SvgRenderer($this->options()))->render($this->solidMatrix(33));

        $drawn = 0;

        foreach ($this->runs($svg) as [$x, $y, $run]) {
            $drawn += $run;

            for ($offset = 0; $offset < $run; $offset++) {
                self::assertFalse(
                    $x + $offset >= 11 && $x + $offset <= 21 && $y >= 11 && $y <= 21,
                    sprintf('Module %d,%d was drawn inside the logo box.', $x + $offset, $y)
                );
            }
        }

        self::assertSame((33 * 33) - 121, $drawn, 'Exactly the box has to be missing.');
    }

    public function testTheClearedAreaIsPaintedInTheLightColour(): void
    {
        $svg = (new SvgRenderer($this->options()))->render($this->solidMatrix(33));

        self::assertStringContainsString('<rect x="11" y="11" width="11" height="11" fill="#ffffff"/>', $svg);
    }

    /**
     * The transform works in module units. A 100x50 logo into a 9x9 drawable
     * area scales by 9/100, comes out 9 by 4.5, and the leftover height is
     * split above and below.
     */
    public function testTheLogoIsScaledToFitAndCentredInWhatIsLeftOver(): void
    {
        $svg = (new SvgRenderer($this->options()))->render($this->solidMatrix(33));

        self::assertStringContainsString('<g transform="translate(12 14.25) scale(0.09)"', $svg);
    }

    public function testASquareLogoFillsTheDrawableAreaExactly(): void
    {
        $square = SvgLogo::fromMarkup(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500">'
            . '<path d="M0 0h1v1z" fill="#000"/></svg>'
        );

        $svg = (new SvgRenderer(
            SvgOptions::default()->withModuleSize(1)->withQuietZone(0)
                ->withLogo($square, LogoBox::square(11, 1))
        ))->render($this->solidMatrix(33));

        self::assertStringContainsString('<g transform="translate(12 12) scale(0.018)"', $svg);
    }

    public function testTheQuietZoneShiftsTheLogoWithTheSymbol(): void
    {
        $svg = (new SvgRenderer($this->options()->withQuietZone(4)))->render($this->solidMatrix(33));

        self::assertStringContainsString('<rect x="15" y="15" width="11" height="11"', $svg);
        self::assertStringContainsString('translate(16 18.25)', $svg);
    }

    /**
     * The root element asks for crispEdges, which is right for modules —
     * axis-aligned squares that a scanner wants hard-edged — and wrong for
     * artwork. Inherited by curved paths at this scale, a 500-unit logo
     * squeezed into nine modules, it drops thin features and jags the curves,
     * which reads as "the logo did not render". The group has to override it.
     */
    public function testTheLogoGroupTurnsAntiAliasingBackOn(): void
    {
        $svg = (new SvgRenderer($this->options()))->render($this->solidMatrix(33));

        self::assertStringContainsString('shape-rendering="crispEdges"', $svg, 'The modules keep it.');
        self::assertStringContainsString(
            'shape-rendering="geometricPrecision">',
            $svg,
            'The logo group has to switch it off for its subtree.'
        );

        // On the group, not somewhere else.
        self::assertMatchesRegularExpression(
            '/<g transform="[^"]*" shape-rendering="geometricPrecision">/',
            $svg
        );
    }

    public function testTheSanitisedLogoMarkupEndsUpInTheOutput(): void
    {
        $svg = (new SvgRenderer($this->options()))->render($this->solidMatrix(33));

        self::assertStringContainsString('<path d="M0 0h10v5z" fill="#e2b0d1"/>', $svg);
    }

    /**
     * With a transparent background whatever sits behind the symbol shows
     * through the cleared area, and a scanner then sees neither light nor dark
     * where it needs light. Refused rather than rendered into a code that
     * happens to work over white and fails over anything else.
     */
    public function testALogoOnATransparentBackgroundIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('opaque backdrop');

        (new SvgRenderer($this->options()->withColors('#000000', 'none')))->render($this->solidMatrix(33));
    }

    public function testABoxOverAFunctionPatternIsRefusedAtRenderTime(): void
    {
        $matrix = (new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::medium());

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('function pattern');

        (new SvgRenderer(
            SvgOptions::default()->withLogo($this->logo(), LogoBox::square(9, 1))
        ))->render($matrix);
    }

    public function testWithoutALogoNothingChanges(): void
    {
        $plain = SvgOptions::default()->withModuleSize(1)->withQuietZone(0);

        self::assertSame(
            (new SvgRenderer($plain))->render($this->solidMatrix(33)),
            (new SvgRenderer($this->options()->withoutLogo()))->render($this->solidMatrix(33))
        );
    }

    /**
     * @dataProvider realSymbols
     */
    public function testTheOutputStaysWellFormedXml(string $level, int $boxModules): void
    {
        $matrix = (new BaconQrEncoder())->encode(
            'https://example.org/qr/7K4M2',
            ErrorCorrection::fromString($level)
        );

        $svg = (new SvgRenderer(
            SvgOptions::default()->withLogo($this->logo(), LogoBox::square($boxModules, 1))
        ))->render($matrix);

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($svg);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue($parsed, 'The renderer produced markup libxml refused to parse.');
        self::assertSame([], $errors);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function realSymbols(): iterable
    {
        yield 'level H, 11 modules' => ['H', 11];
        yield 'level H, 13 modules' => ['H', 13];
        yield 'level Q, 9 modules' => ['Q', 9];
    }

    /**
     * The briefing URL at level H, which is the case this is actually for.
     */
    public function testTheBriefingCaseClearsElevenModulesOfAThirtyThreeModuleSymbol(): void
    {
        $matrix = (new BaconQrEncoder())->encode('https://example.org/qr/7K4M2', ErrorCorrection::high());
        $placement = LogoBox::square(11, 1)->placeIn($matrix);

        self::assertSame(33, $matrix->size());
        self::assertSame(121, $placement->clearedModules());
        self::assertSame(
            11.1,
            round($placement->clearedModules() / ($matrix->size() ** 2) * 100, 1),
            'The share of cleared modules is what gets weighed against the recovery rate.'
        );
    }

    /**
     * @return list<array{int, int, int}> x, y, length in module coordinates
     */
    private function runs(string $svg): array
    {
        if (preg_match('/<path fill="#000000" d="([^"]*)"/', $svg, $attribute) !== 1) {
            self::fail('The rendered SVG has no module path.');
        }

        preg_match_all('/M(\d+) (\d+)h(\d+)v1h-\d+z/', $attribute[1], $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $m): array => [(int) $m[1], (int) $m[2], (int) $m[3]],
            $matches
        );
    }
}

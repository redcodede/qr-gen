<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\LabelOptions;
use Redcodede\QrGen\Qr\Render\LabelSvgRenderer;
use Redcodede\QrGen\Qr\Text\SvgFont;
use Redcodede\QrGen\Qr\Text\TextLine;

/**
 * The label as a whole, measured against the file the designer delivered.
 *
 * The case that matters most is the dullest to read: **the artwork's own two
 * lines have to come out at the artwork's own size.** Everything else in this
 * renderer exists so that longer text still fits, and a fitting rule that
 * quietly shrinks the original as well would replace a signed-off layout with
 * something nobody approved.
 */
final class LabelSvgRendererTest extends TestCase
{
    private const SHIPPED = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    /** The two lines of the delivered artwork. */
    private const ARTWORK_TEXT = 'Rückgabe über das GVÖ-SYSTEM';

    private static function font(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::SHIPPED));
    }

    private static function renderer(?LabelOptions $options = null): LabelSvgRenderer
    {
        return new LabelSvgRenderer(LabelLayout::standard(), self::font(), $options);
    }

    /**
     * A three by three grid, small enough that the emitted path can be read.
     */
    private static function matrix(): ModuleMatrix
    {
        return new ModuleMatrix([
            [true, true, false],
            [false, true, false],
            [true, false, true],
        ]);
    }

    public function testTheCanvasIsTheDeliveredSize(): void
    {
        $svg = self::renderer()->render(self::matrix());

        self::assertStringContainsString('viewBox="0 0 90.05 36.28"', $svg);
        self::assertStringContainsString('width="90.05mm"', $svg);
        self::assertStringContainsString('height="36.28mm"', $svg);
    }

    /**
     * 6.8 mm over a 1000-unit em is a scale of 0.0068. Seeing that factor in the
     * output is seeing that the type was not shrunk.
     */
    public function testTheArtworkTextKeepsTheArtworkSize(): void
    {
        $svg = self::renderer()->render(self::matrix(), self::ARTWORK_TEXT);

        self::assertStringContainsString('scale(0.0068 -0.0068)', $svg);
    }

    /**
     * The layout's column has to admit the longest line of the artwork. If it
     * did not, the paragraph above would be satisfied by shrinking instead.
     */
    public function testTheArtworkLinesFitTheTextColumn(): void
    {
        $layout = LabelLayout::standard();
        $font = self::font();

        foreach (['Rückgabe über', 'das GVÖ-SYSTEM'] as $line) {
            self::assertLessThan(
                $layout->textWidth(),
                TextLine::set($font, $line, $layout->textSize())->width(),
                sprintf('"%s" does not fit the column.', $line)
            );
        }
    }

    /**
     * The column stops short of the frame. Type that runs into a printed rule
     * is a defect, and the margin is the same 1.90 mm the artwork leaves below
     * its last line.
     */
    public function testTheTextColumnStopsShortOfTheFrame(): void
    {
        $layout = LabelLayout::standard();
        $right = $layout->textX() + $layout->textWidth();
        $frame = $layout->width() - $layout->frameInset();

        self::assertGreaterThan(1.5, $frame - $right);
    }

    public function testLongerTextIsSetSmaller(): void
    {
        $long = 'Rückgabe über das GVÖ-SYSTEM für Altöl und Gebinde aus der Mineralölwirtschaft';

        $svg = self::renderer()->render(self::matrix(), $long);

        self::assertStringNotContainsString('scale(0.0068 -0.0068)', $svg);
        self::assertMatchesRegularExpression('/scale\(0\.00[0-6]\d* -0\.00[0-6]/', $svg);
    }

    public function testTextThatCannotBeMadeToFitIsRefused(): void
    {
        $this->expectException(TextRejected::class);

        // One word, no break possible, far wider than the column at any size
        // the floor allows.
        self::renderer()->render(self::matrix(), str_repeat('Mineralölwirtschaft', 12));
    }

    public function testTheCodeColorIsTheOnlyDifferenceBetweenTheVariants(): void
    {
        $dark = self::renderer()->render(self::matrix(), self::ARTWORK_TEXT);
        $green = self::renderer(LabelOptions::default()->withCodeColor('#009a7c'))
            ->render(self::matrix(), self::ARTWORK_TEXT);

        self::assertNotSame($dark, $green);
        self::assertSame($dark, str_replace('#009a7c', LabelOptions::DEFAULT_INK, $green));
    }

    /**
     * Modules are emitted in module units and scaled into place, so every
     * coordinate inside the symbol path is a whole number. A rounded
     * millimetre would put a seam between two modules of one run.
     */
    public function testTheSymbolPathIsDrawnInWholeModules(): void
    {
        $svg = self::renderer()->render(self::matrix());

        self::assertSame(1, preg_match('/<path fill="[^"]*" d="(M[^"]*)"/', $svg, $match));
        self::assertMatchesRegularExpression('/^(?:M\d+ \d+h\d+v1h-\d+z)+$/', $match[1]);
    }

    /**
     * The printer gets one file. A reference to a font, an image or a
     * stylesheet would be a second one, and nobody would notice until it was
     * missing.
     */
    public function testNothingOutsideTheFileIsReferenced(): void
    {
        $svg = self::renderer()->render(self::matrix(), self::ARTWORK_TEXT);

        foreach (['<text', '<image', 'href', 'font-family', '@import', 'url('] as $needle) {
            self::assertStringNotContainsString($needle, $svg, sprintf('Found "%s".', $needle));
        }
    }

    public function testEmptyTextLeavesTheLabelWithoutType(): void
    {
        $svg = self::renderer()->render(self::matrix(), '   ');

        self::assertStringNotContainsString('scale(0.0068', $svg);
        self::assertStringContainsString('viewBox=', $svg);
    }
}

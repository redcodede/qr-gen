<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Text;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Text\SvgFont;
use Redcodede\QrGen\Qr\Text\TextLine;

/**
 * Setting and measuring one line.
 *
 * The first two cases measure the two lines of the GVÖ artwork against the
 * numbers taken off the designer's file. They are the ones that would catch a
 * wrong scale, a swapped units-per-em or a dropped advance — a synthetic font
 * with round numbers catches none of that, because every plausible mistake
 * still produces a plausible number.
 *
 * The artwork sets 6.8 mm type in a column 53.80 mm wide. The second line fills
 * 94 % of it, so this layout has no slack, and a regression of half a
 * millimetre is a line that no longer fits.
 */
final class TextLineTest extends TestCase
{
    private const SHIPPED = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    /** Type size of the artwork, in millimetres. */
    private const ARTWORK_SIZE = 6.8;

    /** Text column of the artwork: from x 36.13 to the frame at 89.93. */
    private const ARTWORK_COLUMN = 53.80;

    /**
     * The core does not open files, so the test does it. See
     * {@see \Redcodede\QrGen\Tests\Qr\CoreIsFrameworkFreeTest}.
     */
    private static function shipped(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::SHIPPED));
    }

    private static function font(string $glyphs): SvgFont
    {
        return SvgFont::fromMarkup(
            '<svg><defs><font id="Test" horiz-adv-x="500">'
            . '<font-face units-per-em="1000" ascent="800" descent="-200"/>'
            . $glyphs
            . '</font></defs></svg>'
        );
    }

    public function testFirstLineOfTheArtwork(): void
    {
        $line = TextLine::set(self::shipped(), 'Rückgabe über', self::ARTWORK_SIZE);

        self::assertEqualsWithDelta(43.11, $line->width(), 0.01);
        self::assertLessThan(self::ARTWORK_COLUMN, $line->width());
    }

    public function testSecondLineOfTheArtworkFillsAlmostTheWholeColumn(): void
    {
        $line = TextLine::set(self::shipped(), 'das GVÖ-SYSTEM', self::ARTWORK_SIZE);

        self::assertEqualsWithDelta(50.65, $line->width(), 0.01);
        self::assertLessThan(self::ARTWORK_COLUMN, $line->width());
        self::assertGreaterThan(0.9 * self::ARTWORK_COLUMN, $line->width());
    }

    /**
     * The limit agreed for the input field is 72 characters. At the artwork's
     * size that is far more than two lines, which is why the layout shrinks
     * type instead of trusting the limit.
     */
    public function testSeventyTwoCharactersDoNotFitTheArtworkColumn(): void
    {
        $text = str_repeat('Rückgabe über das GVÖ-SYSTEM ', 3);
        $text = rtrim(substr($text, 0, 80));

        $line = TextLine::set(self::shipped(), $text, self::ARTWORK_SIZE);

        self::assertGreaterThan(3 * self::ARTWORK_COLUMN, $line->width());
    }

    public function testWidthIsTheSumOfTheAdvances(): void
    {
        $font = self::font(
            '<glyph unicode="A" horiz-adv-x="400" d="M0,0Z"/>'
            . '<glyph unicode="B" horiz-adv-x="600" d="M0,0Z"/>'
        );

        // 1000 units at a size of 10 is exactly one em.
        self::assertSame(10.0, TextLine::set($font, 'AB', 10.0)->width());
    }

    public function testOffsetsFollowTheAdvancesAndAreScaled(): void
    {
        $font = self::font(
            '<glyph unicode="A" horiz-adv-x="400" d="M0,0Z"/>'
            . '<glyph unicode="B" horiz-adv-x="600" d="M0,0Z"/>'
        );

        $glyphs = TextLine::set($font, 'ABA', 10.0)->glyphs();

        self::assertCount(3, $glyphs);
        self::assertSame(0.0, $glyphs[0]->offset());
        self::assertSame(4.0, $glyphs[1]->offset());
        self::assertSame(10.0, $glyphs[2]->offset());
    }

    /**
     * A space belongs in the width and not in the output. An empty path costs
     * the rasteriser a walk and the SVG a node, and neither draws anything.
     */
    public function testSpaceMovesThePenButIsNotEmitted(): void
    {
        $font = self::font(
            '<glyph unicode="A" horiz-adv-x="400" d="M0,0Z"/>'
            . '<glyph unicode=" " horiz-adv-x="200"/>'
        );

        $line = TextLine::set($font, 'A A', 10.0);

        self::assertCount(2, $line->glyphs());
        self::assertSame(10.0, $line->width());
        self::assertSame(6.0, $line->glyphs()[1]->offset());
    }

    public function testScaleAscentAndDescentAreInOutputUnits(): void
    {
        $line = TextLine::set(self::font('<glyph unicode="A" d="M0,0Z"/>'), 'A', 10.0);

        self::assertSame(0.01, $line->scale());
        self::assertSame(8.0, $line->ascent());
        self::assertSame(-2.0, $line->descent());
    }

    public function testEmptyTextIsAnEmptyLine(): void
    {
        $line = TextLine::set(self::font('<glyph unicode="A" d="M0,0Z"/>'), '', 10.0);

        self::assertTrue($line->isEmpty());
        self::assertSame(0.0, $line->width());
    }

    public function testShrinkingLeavesALineThatAlreadyFits(): void
    {
        $font = self::shipped();
        $line = TextLine::set($font, 'das GVÖ-SYSTEM', self::ARTWORK_SIZE);

        self::assertSame($line, $line->shrunkToWidth($font, self::ARTWORK_COLUMN));
    }

    public function testShrinkingMeetsTheAvailableWidthExactly(): void
    {
        $font = self::shipped();
        $line = TextLine::set($font, 'das GVÖ-SYSTEM', self::ARTWORK_SIZE);

        $shrunk = $line->shrunkToWidth($font, 20.0);

        self::assertEqualsWithDelta(20.0, $shrunk->width(), 0.000001);
        self::assertLessThan(self::ARTWORK_SIZE, $shrunk->size());
    }

    public function testUnknownCharacterIsRefused(): void
    {
        $this->expectException(TextRejected::class);

        TextLine::set(self::font('<glyph unicode="A" d="M0,0Z"/>'), 'AB', 10.0);
    }

    public function testSizeHasToBeGreaterThanZero(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('greater than zero');

        TextLine::set(self::font('<glyph unicode="A" d="M0,0Z"/>'), 'A', 0.0);
    }
}

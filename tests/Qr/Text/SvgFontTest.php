<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Text;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * Reading an SVG font, and the shipped one in particular.
 *
 * The test that earns its keep is the one on the real file. Everything else
 * here checks a rule against markup written for the occasion, which proves the
 * parser does what it was told; only the shipped font proves it was told the
 * right thing. A font is not a format we control, and the numbers this package
 * sets type with all come out of that one file.
 */
final class SvgFontTest extends TestCase
{
    private const SHIPPED = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    /**
     * The core does not open files, so the test does it. See
     * {@see \Redcodede\QrGen\Tests\Qr\CoreIsFrameworkFreeTest}.
     */
    private static function shipped(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::SHIPPED));
    }

    private static function font(
        string $glyphs,
        string $fontAttributes = 'horiz-adv-x="500"',
        string $faceAttributes = 'units-per-em="1000" ascent="800" descent="-200"'
    ): string {
        return '<svg xmlns="http://www.w3.org/2000/svg"><defs>'
            . '<font id="Test" ' . $fontAttributes . '>'
            . '<font-face font-family="Test" ' . $faceAttributes . '/>'
            . $glyphs
            . '</font></defs></svg>';
    }

    public function testReadsTheShippedFont(): void
    {
        $font = self::shipped();

        self::assertSame(202, $font->glyphCount());
        self::assertSame(1000.0, $font->unitsPerEm());
        self::assertSame(1018.0, $font->ascent());
        self::assertSame(-276.0, $font->descent());
    }

    /**
     * The umlauts are the reason this subset was chosen. If a future download
     * drops them, the label reads "Rckgabe ber" and this is where it shows.
     */
    public function testTheShippedFontCoversTheArtworkText(): void
    {
        $font = self::shipped();

        foreach (preg_split('//u', 'Rückgabe über das GVÖ-SYSTEM', -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            self::assertTrue($font->has($character), sprintf('The font lacks "%s".', $character));
        }
    }

    public function testGlyphCarriesItsAdvanceAndOutline(): void
    {
        $glyph = self::shipped()->glyph('ü');

        self::assertSame(539.0, $glyph->advance());
        self::assertTrue($glyph->draws());
        self::assertStringStartsWith('M', $glyph->outline());
    }

    public function testSpaceAdvancesWithoutDrawing(): void
    {
        $glyph = self::shipped()->glyph(' ');

        self::assertSame(267.0, $glyph->advance());
        self::assertFalse($glyph->draws());
        self::assertSame('', $glyph->outline());
    }

    /**
     * Outlines have to come through untouched. A path is fed to the flattener
     * verbatim, so a decoder that mangled a number would move a letter.
     */
    public function testOutlinesSurviveUnchanged(): void
    {
        $font = SvgFont::fromMarkup(self::font('<glyph unicode="A" d="M0,0H10V10H0Z"/>'));

        self::assertSame('M0,0H10V10H0Z', $font->glyph('A')->outline());
    }

    public function testDecodesNumericCharacterReferences(): void
    {
        $font = SvgFont::fromMarkup(self::font(
            '<glyph unicode="&#xfc;" horiz-adv-x="539" d="M0,0Z"/>'
            . '<glyph unicode="&#214;" horiz-adv-x="777" d="M0,0Z"/>'
        ));

        self::assertTrue($font->has('ü'));
        self::assertTrue($font->has('Ö'));
        self::assertSame(777.0, $font->glyph('Ö')->advance());
    }

    public function testDecodesTheFiveXmlEntities(): void
    {
        $font = SvgFont::fromMarkup(self::font(
            '<glyph unicode="&amp;" d="M0,0Z"/>'
            . '<glyph unicode="&lt;" d="M0,0Z"/>'
            . '<glyph unicode="&gt;" d="M0,0Z"/>'
            . '<glyph unicode="&quot;" d="M0,0Z"/>'
            . '<glyph unicode="&apos;" d="M0,0Z"/>'
        ));

        foreach (['&', '<', '>', '"', "'"] as $character) {
            self::assertTrue($font->has($character), sprintf('Missing "%s".', $character));
        }
    }

    public function testGlyphWithoutItsOwnWidthFallsBackToTheFont(): void
    {
        $font = SvgFont::fromMarkup(self::font('<glyph unicode="A" d="M0,0Z"/>'));

        self::assertSame(500.0, $font->glyph('A')->advance());
    }

    /**
     * A ligature names several characters at once and needs a substitution pass
     * this package does not have. Passing it over has to leave the single
     * characters alone, which is what this checks.
     */
    public function testLigaturesArePassedOver(): void
    {
        $font = SvgFont::fromMarkup(self::font(
            '<glyph unicode="fi" horiz-adv-x="900" d="M0,0Z"/>'
            . '<glyph unicode="f" horiz-adv-x="300" d="M0,0Z"/>'
        ));

        self::assertSame(1, $font->glyphCount());
        self::assertTrue($font->has('f'));
        self::assertSame(300.0, $font->glyph('f')->advance());
    }

    public function testUnitsPerEmDefaultsToAThousand(): void
    {
        $font = SvgFont::fromMarkup(self::font('<glyph unicode="A" d="M0,0Z"/>', 'horiz-adv-x="500"', ''));

        self::assertSame(1000.0, $font->unitsPerEm());
    }

    public function testUnknownCharacterIsRefused(): void
    {
        $font = SvgFont::fromMarkup(self::font('<glyph unicode="A" d="M0,0Z"/>'));

        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('U+0141');

        $font->glyph('Ł');
    }

    public function testMarkupWithoutAFontIsRefused(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('not an SVG font');

        SvgFont::fromMarkup('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0,0Z"/></svg>');
    }

    public function testFontWithoutFontFaceIsRefused(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('units-per-em');

        SvgFont::fromMarkup(
            '<svg><defs><font id="Test"><glyph unicode="A" d="M0,0Z"/></font></defs></svg>'
        );
    }

    public function testFontWithoutGlyphsIsRefused(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('no usable <glyph>');

        SvgFont::fromMarkup(self::font(''));
    }

    public function testZeroUnitsPerEmIsRefused(): void
    {
        $this->expectException(TextRejected::class);

        SvgFont::fromMarkup(self::font('<glyph unicode="A" d="M0,0Z"/>', '', 'units-per-em="0"'));
    }
}

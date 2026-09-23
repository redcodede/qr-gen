<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Layout;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\Layout\LabelText;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * Breaking and sizing the text, measured against the delivered file.
 *
 * The first case is the one worth having. The artwork's two lines sit on
 * baselines at 24.22 and 32.38, and those numbers were never given to the
 * fitting rule: they fall out of a box, a line height and a centring. If the
 * rule reproduces them, it is the rule the designer used.
 */
final class LabelTextTest extends TestCase
{
    private const SHIPPED = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    private static function font(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::SHIPPED));
    }

    public function testTheArtworkComesBackOutUnchanged(): void
    {
        $set = LabelText::fit(LabelLayout::standard(), self::font(), 'Rückgabe über das GVÖ-SYSTEM');

        self::assertSame(6.8, $set->size());
        self::assertCount(2, $set->lines());
        self::assertSame('Rückgabe über', $set->lines()[0]->text());
        self::assertSame('das GVÖ-SYSTEM', $set->lines()[1]->text());

        // The baselines of the delivered file, to a hundredth of a millimetre.
        self::assertEqualsWithDelta(24.22, $set->baselines()[0], 0.01);
        self::assertEqualsWithDelta(32.38, $set->baselines()[1], 0.01);
    }

    public function testEmptyTextIsEmpty(): void
    {
        $set = LabelText::fit(LabelLayout::standard(), self::font(), "  \n ");

        self::assertTrue($set->isEmpty());
        self::assertSame([], $set->lines());
        self::assertSame(0.0, $set->size());
    }

    public function testSeventyTwoCharactersWrapAndShrink(): void
    {
        $layout = LabelLayout::standard();
        $text = 'Rückgabe über das GVÖ-SYSTEM für Altöl und Gebinde aus der Mineralölwirt';

        self::assertSame(72, count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []));

        $set = LabelText::fit($layout, self::font(), $text);

        self::assertLessThan($layout->textSize(), $set->size());
        self::assertGreaterThanOrEqual($layout->minimumTextSize(), $set->size());
        self::assertGreaterThan(2, count($set->lines()));
    }

    /**
     * Every line has to fit the column, not just on average. A greedy wrap that
     * placed one word too many would still produce plausible-looking output.
     */
    public function testEveryLineFitsTheColumn(): void
    {
        $layout = LabelLayout::standard();
        $set = LabelText::fit(
            $layout,
            self::font(),
            'Rückgabe über das GVÖ-SYSTEM für Altöl und Gebinde aus der Mineralölwirt'
        );

        foreach ($set->lines() as $line) {
            self::assertLessThanOrEqual($layout->textWidth(), $line->width(), $line->text());
        }
    }

    public function testTheBlockStaysInsideItsBox(): void
    {
        $layout = LabelLayout::standard();
        $set = LabelText::fit(
            $layout,
            self::font(),
            'Rückgabe über das GVÖ-SYSTEM für Altöl und Gebinde aus der Mineralölwirt'
        );

        $baselines = $set->baselines();
        $first = $set->lines()[0];

        self::assertGreaterThanOrEqual($layout->textTop(), $baselines[0] - $first->ascent());
        self::assertLessThanOrEqual(
            $layout->textBottom(),
            $baselines[count($baselines) - 1] - $first->descent()
        );
    }

    public function testAWordWiderThanTheColumnIsRefusedByName(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('Mineralölwirtschaftsverbandsvorstandsvorsitzendenstellvertreterposten');

        LabelText::fit(
            LabelLayout::standard(),
            self::font(),
            'Mineralölwirtschaftsverbandsvorstandsvorsitzendenstellvertreterposten'
        );
    }

    public function testTooMuchTextIsRefused(): void
    {
        $this->expectException(TextRejected::class);
        $this->expectExceptionMessage('does not fit');

        LabelText::fit(LabelLayout::standard(), self::font(), str_repeat('Rückgabe über ', 40));
    }
}

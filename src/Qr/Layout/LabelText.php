<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Layout;

use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Text\SvgFont;
use Redcodede\QrGen\Qr\Text\TextLine;

/**
 * The text of a label, broken and sized to fit its box, with its baselines.
 *
 * **This lives apart from either renderer because both have to agree.** The SVG
 * and the PNG of one label are meant to be the same picture; if each worked out
 * its own line breaks, they would agree until the day a word landed exactly on
 * the column width, and then they would differ in a way only a print proof
 * would show.
 *
 * The rule it implements: the box is fixed, the type gives way. The delivered
 * artwork sets two lines that fill 94 % of their column, while the agreed input
 * allows 72 characters, which is about four and a half lines at that size.
 * Something has to yield, and a box that grew would push into the mark above it
 * and the frame below.
 *
 * Below {@see LabelLayout::minimumTextSize()} the label is refused. Type that
 * small on a container is not an instruction any more, and shipping it would
 * mean the package decided on its own that unreadable beats absent.
 */
final class LabelText
{
    /** How finely the search steps down, in millimetres. */
    private const SIZE_STEP = 0.05;

    /** @var float */
    private $size;

    /** @var list<TextLine> */
    private $lines;

    /** @var list<float> */
    private $baselines;

    /**
     * @param list<TextLine> $lines
     * @param list<float>    $baselines
     */
    private function __construct(float $size, array $lines, array $baselines)
    {
        $this->size = $size;
        $this->lines = $lines;
        $this->baselines = $baselines;
    }

    /**
     * @throws TextRejected if the text cannot be set in the space available
     */
    public static function fit(LabelLayout $layout, SvgFont $font, string $text): self
    {
        if (trim($text) === '') {
            return new self(0.0, [], []);
        }

        $available = $layout->textHeight();
        $unbreakable = null;

        for (
            $size = $layout->textSize();
            $size >= $layout->minimumTextSize();
            $size -= self::SIZE_STEP
        ) {
            $lines = self::wrap($layout, $font, $text, $size, $unbreakable);

            if ($lines === null) {
                continue;
            }

            $height = self::blockHeight($layout, $lines, $size);

            if ($height <= $available) {
                return new self($size, $lines, self::baselinesFor($layout, $lines, $size, $height));
            }
        }

        // A word that is still too wide at the smallest size is a different
        // problem from too much text, and it has a different answer: no line
        // break can help, so the word itself has to go. Naming it beats a
        // message about the label as a whole.
        //
        // The word is taken from the last attempt rather than the first,
        // because a word that does not fit at 6.8 mm may well fit at 3.
        if ($unbreakable !== null) {
            throw TextRejected::unbreakableWord($unbreakable);
        }

        throw TextRejected::doesNotFit($layout->minimumTextSize());
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /** The size the text ended up at, in millimetres. Zero when there is none. */
    public function size(): float
    {
        return $this->size;
    }

    /**
     * @return list<TextLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Baseline of each line, in millimetres from the top of the label.
     *
     * @return list<float>
     */
    public function baselines(): array
    {
        return $this->baselines;
    }

    /**
     * Font units to millimetres. Zero when there is no text.
     */
    public function scale(): float
    {
        return $this->lines === [] ? 0.0 : $this->lines[0]->scale();
    }

    /**
     * @param list<TextLine> $lines
     */
    private static function blockHeight(LabelLayout $layout, array $lines, float $size): float
    {
        $first = $lines[0];

        return $first->ascent() - $first->descent()
            + ($size * $layout->lineHeightFactor() * (count($lines) - 1));
    }

    /**
     * Centred in the box.
     *
     * With the artwork's two lines at 6.8 mm the block fills the box exactly,
     * so the original comes back out of this unmoved. That is the property the
     * whole fitting rule is measured against.
     *
     * @param list<TextLine> $lines
     *
     * @return list<float>
     */
    private static function baselinesFor(LabelLayout $layout, array $lines, float $size, float $blockHeight): array
    {
        $first = $layout->textTop()
            + (($layout->textHeight() - $blockHeight) / 2)
            + $lines[0]->ascent();

        $lineHeight = $size * $layout->lineHeightFactor();
        $baselines = [];

        foreach (array_keys($lines) as $index) {
            $baselines[] = $first + ($lineHeight * $index);
        }

        return $baselines;
    }

    /**
     * Greedy word wrapping at a given size.
     *
     * Returns null when a single word is wider than the column, because a
     * smaller size may still solve that and the caller is stepping down through
     * sizes. The word is reported back through $unbreakable so the caller can
     * name it if no size works.
     *
     * @param string|null $unbreakable Set to the offending word when one is found
     *
     * @return non-empty-list<TextLine>|null
     *
     * @throws TextRejected
     */
    private static function wrap(
        LabelLayout $layout,
        SvgFont $font,
        string $text,
        float $size,
        ?string &$unbreakable
    ): ?array {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            throw TextRejected::invalidEncoding();
        }

        $column = $layout->textWidth();
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (TextLine::set($font, $word, $size)->width() > $column) {
                $unbreakable = $word;

                return null;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (TextLine::set($font, $candidate, $size)->width() <= $column) {
                $current = $candidate;

                continue;
            }

            $lines[] = TextLine::set($font, $current, $size);
            $current = $word;
        }

        $lines[] = TextLine::set($font, $current, $size);

        return $lines;
    }
}

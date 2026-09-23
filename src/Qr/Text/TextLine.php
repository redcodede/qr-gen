<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Text;

use Redcodede\QrGen\Qr\Exception\TextRejected;

/**
 * One line of text, set in a font at a size, and measured.
 *
 * Measuring is the point. The layout this package has to reproduce is set to
 * the edge — the second line of the GVÖ artwork fills 94 % of its column — so
 * whether a line fits is not something to estimate from an average character
 * width. Every glyph carries its own advance, and the sum of them is the
 * answer.
 *
 * **There is no kerning.** The SVG font shipped with the package declares no
 * kerning pairs, so pairs like "Rü" or "Ta" sit at their nominal distance. The
 * original artwork was set in Illustrator with kerning applied, which is why
 * its lines are split into several `<text>` elements. Our output is metrically
 * correct rather than identical to it.
 *
 * A missing glyph is refused, not skipped. See {@see TextRejected}.
 */
final class TextLine
{
    /** @var string */
    private $text;

    /** @var float */
    private $size;

    /** @var float */
    private $scale;

    /** @var list<PlacedGlyph> */
    private $glyphs;

    /** @var float */
    private $width;

    /** @var float */
    private $ascent;

    /** @var float */
    private $descent;

    /**
     * @param list<PlacedGlyph> $glyphs
     */
    private function __construct(
        string $text,
        float $size,
        float $scale,
        array $glyphs,
        float $width,
        float $ascent,
        float $descent
    ) {
        $this->text = $text;
        $this->size = $size;
        $this->scale = $scale;
        $this->glyphs = $glyphs;
        $this->width = $width;
        $this->ascent = $ascent;
        $this->descent = $descent;
    }

    /**
     * @param float $size Height of the em square in the output's units
     *
     * @throws TextRejected on an invalid size, invalid UTF-8 or a character the font lacks
     */
    public static function set(SvgFont $font, string $text, float $size): self
    {
        if ($size <= 0.0) {
            throw TextRejected::invalidSize($size);
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($characters === false) {
            throw TextRejected::invalidEncoding();
        }

        $scale = $size / $font->unitsPerEm();

        $glyphs = [];
        $pen = 0.0;

        foreach ($characters as $character) {
            $glyph = $font->glyph($character);

            // A glyph that draws nothing still moves the pen, but it does not
            // belong in the output: an empty path in an SVG is noise, and the
            // rasteriser would walk it for nothing.
            if ($glyph->draws()) {
                $glyphs[] = PlacedGlyph::at($glyph, $pen * $scale);
            }

            $pen += $glyph->advance();
        }

        return new self(
            $text,
            $size,
            $scale,
            $glyphs,
            $pen * $scale,
            $font->ascent() * $scale,
            $font->descent() * $scale
        );
    }

    public function text(): string
    {
        return $this->text;
    }

    public function size(): float
    {
        return $this->size;
    }

    /**
     * Font units to output units.
     *
     * Only the x-axis. Drawing space counts y downwards and a font counts it
     * upwards, so placing a glyph scales y by the negative of this.
     */
    public function scale(): float
    {
        return $this->scale;
    }

    /** Width of the whole line, in the output's units. */
    public function width(): float
    {
        return $this->width;
    }

    /** Above the baseline, in the output's units. */
    public function ascent(): float
    {
        return $this->ascent;
    }

    /** Below the baseline, in the output's units. Negative. */
    public function descent(): float
    {
        return $this->descent;
    }

    /**
     * The glyphs that draw something, in reading order.
     *
     * @return list<PlacedGlyph>
     */
    public function glyphs(): array
    {
        return $this->glyphs;
    }

    public function isEmpty(): bool
    {
        return $this->glyphs === [];
    }

    /**
     * The same line at whatever size makes it fit into $available.
     *
     * Advances scale linearly with the size, so the largest fitting size is one
     * division rather than a search. Returns this line unchanged when it
     * already fits, so a caller can apply it unconditionally.
     */
    public function shrunkToWidth(SvgFont $font, float $available): self
    {
        if ($available <= 0.0) {
            throw TextRejected::invalidSize($available);
        }

        if ($this->width <= $available || $this->width <= 0.0) {
            return $this;
        }

        return self::set($font, $this->text, $this->size * $available / $this->width);
    }
}

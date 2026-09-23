<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Text;

/**
 * One glyph of a set line, with the distance from the start of that line.
 *
 * The offset is in the line's own units, so it is already scaled by the font
 * size; the outline it points at is still in font units. That split is
 * deliberate. Placing a glyph means one translation and one scaling, and
 * keeping the outline unscaled lets the caller build that as a single
 * {@see \Redcodede\QrGen\Qr\Raster\Transform} instead of rewriting path data.
 */
final class PlacedGlyph
{
    /** @var Glyph */
    private $glyph;

    /** @var float */
    private $offset;

    private function __construct(Glyph $glyph, float $offset)
    {
        $this->glyph = $glyph;
        $this->offset = $offset;
    }

    public static function at(Glyph $glyph, float $offset): self
    {
        return new self($glyph, $offset);
    }

    public function glyph(): Glyph
    {
        return $this->glyph;
    }

    /** Distance from the start of the line to this glyph's origin. */
    public function offset(): float
    {
        return $this->offset;
    }
}

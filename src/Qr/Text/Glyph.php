<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Text;

/**
 * One character of a font: how far it moves the pen, and what it draws.
 *
 * Both numbers are in font units, not in millimetres or pixels. Scaling is the
 * caller's business, and doing it here would mean every glyph carried a size it
 * does not have: a font holds one outline per character, and the size is a
 * property of the line being set.
 *
 * A glyph without an outline is normal and not an error. The space is the
 * obvious one: it advances the pen and draws nothing.
 */
final class Glyph
{
    /** @var string */
    private $character;

    /** @var float */
    private $advance;

    /** @var string */
    private $outline;

    private function __construct(string $character, float $advance, string $outline)
    {
        $this->character = $character;
        $this->advance = $advance;
        $this->outline = $outline;
    }

    public static function of(string $character, float $advance, string $outline = ''): self
    {
        return new self($character, $advance, $outline);
    }

    public function character(): string
    {
        return $this->character;
    }

    /** How far the pen moves after drawing this glyph, in font units. */
    public function advance(): float
    {
        return $this->advance;
    }

    /** The `d` attribute of the outline, in font units, or an empty string. */
    public function outline(): string
    {
        return $this->outline;
    }

    public function draws(): bool
    {
        return $this->outline !== '';
    }
}

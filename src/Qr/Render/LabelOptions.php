<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

/**
 * The colours of a label, and the two switches around the file itself.
 *
 * Kept apart from {@see \Redcodede\QrGen\Qr\Layout\LabelLayout} because the two
 * answer different questions and change at different times. The layout is
 * geometry and is settled by a print proof. The colours are a setting: the
 * whole reason there are two label variants is that nobody yet knows whether
 * the manufacturers will print in colour, and that answer must not need a code
 * change.
 *
 * The defaults are the artwork's own values. **`#1d1d1b` is not black**, it is
 * what Illustrator shows for 100 % K in RGB, and 100 % K is exactly what
 * {@see \Redcodede\QrGen\Qr\Preset} asks the press for. Neither PNG nor SVG can
 * carry CMYK, so the conversion happens in prepress and this is the instruction
 * to send with the file.
 */
final class LabelOptions
{
    public const DEFAULT_INK = '#1d1d1b';

    public const DEFAULT_LIGHT = '#ffffff';

    /** @var string */
    private $codeColor = self::DEFAULT_INK;

    /** @var string */
    private $inkColor = self::DEFAULT_INK;

    /** @var string */
    private $lightColor = self::DEFAULT_LIGHT;

    /** @var string|null */
    private $title;

    /** @var bool */
    private $xmlDeclaration = false;

    private function __construct()
    {
    }

    public static function default(): self
    {
        return new self();
    }

    /** The modules of the symbol. The one colour the two variants differ in. */
    public function withCodeColor(string $color): self
    {
        $clone = clone $this;
        $clone->codeColor = $color;

        return $clone;
    }

    /** Type and frame. Not the mark: artwork brings its own colours. */
    public function withInkColor(string $color): self
    {
        $clone = clone $this;
        $clone->inkColor = $color;

        return $clone;
    }

    public function withLightColor(string $color): self
    {
        $clone = clone $this;
        $clone->lightColor = $color;

        return $clone;
    }

    public function withTitle(?string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function withXmlDeclaration(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->xmlDeclaration = $enabled;

        return $clone;
    }

    public function codeColor(): string
    {
        return $this->codeColor;
    }

    public function inkColor(): string
    {
        return $this->inkColor;
    }

    public function lightColor(): string
    {
        return $this->lightColor;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function hasXmlDeclaration(): bool
    {
        return $this->xmlDeclaration;
    }
}

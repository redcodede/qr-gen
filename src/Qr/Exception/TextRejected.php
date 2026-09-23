<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use InvalidArgumentException;

/**
 * The text could not be set with the font that was handed over.
 *
 * Same stance as {@see LogoRejected}: refuse and name the reason rather than
 * produce something that looks finished. A missing glyph is the case that
 * matters here. Dropping it silently would ship a label that reads
 * "Rckgabe ber", and nothing downstream would notice — not the renderer, not
 * the press, not the person who typed the text.
 */
final class TextRejected extends InvalidArgumentException implements QrGenException
{
    public static function notAFont(): self
    {
        return new self('The file holds no <font> element, so it is not an SVG font.');
    }

    public static function withoutFontFace(): self
    {
        return new self(
            'The <font> has no <font-face>, so its units-per-em is unknown and nothing '
            . 'can be scaled.'
        );
    }

    public static function withoutGlyphs(): self
    {
        return new self('The <font> holds no usable <glyph>.');
    }

    public static function unreadableFont(string $reason): self
    {
        return new self(sprintf('The SVG font cannot be read: %s', $reason));
    }

    /**
     * The character is reported by code point as well as by itself, because a
     * non-printing or look-alike character is exactly the kind that goes
     * missing, and quoting it alone would leave the reader guessing.
     */
    public static function unknownCharacter(string $character): self
    {
        return new self(sprintf(
            'The font has no glyph for "%s" (U+%04X). Either the text uses a character outside '
            . 'the font\'s subset, or the wrong font was supplied.',
            $character,
            self::codePoint($character)
        ));
    }

    public static function invalidEncoding(): self
    {
        return new self('The text is not valid UTF-8.');
    }

    public static function invalidSize(float $size): self
    {
        return new self(sprintf('The font size has to be greater than zero, %s given.', (string) $size));
    }

    /**
     * Shrinking has a floor. Below it the type stops being an instruction on a
     * container and becomes decoration, so the label is refused instead of
     * being set in something nobody can read.
     */
    public static function doesNotFit(float $minimumSize): self
    {
        return new self(sprintf(
            'The text does not fit the label, not even at the smallest permitted size of %s mm. '
            . 'Shorten it, or use a layout with a larger text area.',
            rtrim(rtrim(number_format($minimumSize, 2, '.', ''), '0'), '.')
        ));
    }

    public static function unbreakableWord(string $word): self
    {
        return new self(sprintf(
            'The word "%s" is wider than the text column on its own, so no line break helps. '
            . 'Shorten it.',
            $word
        ));
    }

    /**
     * The code point of the first character, without ext-mbstring.
     */
    private static function codePoint(string $character): int
    {
        $bytes = unpack('C*', $character);

        if ($bytes === false || $bytes === []) {
            return 0;
        }

        $first = $bytes[1];

        if ($first < 0x80) {
            return $first;
        }

        if (($first & 0xE0) === 0xC0) {
            return (($first & 0x1F) << 6) | (($bytes[2] ?? 0) & 0x3F);
        }

        if (($first & 0xF0) === 0xE0) {
            return (($first & 0x0F) << 12)
                | ((($bytes[2] ?? 0) & 0x3F) << 6)
                | (($bytes[3] ?? 0) & 0x3F);
        }

        return (($first & 0x07) << 18)
            | ((($bytes[2] ?? 0) & 0x3F) << 12)
            | ((($bytes[3] ?? 0) & 0x3F) << 6)
            | (($bytes[4] ?? 0) & 0x3F);
    }
}

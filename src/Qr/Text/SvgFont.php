<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Text;

use Redcodede\QrGen\Qr\Exception\TextRejected;

/**
 * A font read from an SVG font file, which is the short way to outlines.
 *
 * The package needs type as outlines rather than as live text: a `<text>`
 * element only renders where the font happens to be installed, and the PNG has
 * no notion of a font at all. The usual route to outlines is a TrueType parser,
 * which means `glyf`, `loca`, `cmap` and a quadratic curve reconstruction.
 *
 * **An SVG font is that parser's output, already written down.** Every glyph
 * carries its outline as a `d` attribute in the same path grammar
 * {@see \Redcodede\QrGen\Qr\Raster\PathFlattener} already speaks, and its
 * advance width as `horiz-adv-x`. So the file is read with the same kind of
 * pattern matching the rest of the package uses, and no binary format has to be
 * understood.
 *
 * SVG fonts were dropped from the browser platform, which is what makes them
 * cheap here rather than risky: nothing renders them any more, so the file is
 * pure data. Entities are not expanded — only the five XML ones and numeric
 * references are decoded — so a DOCTYPE cannot be used to inflate the input.
 *
 * Coordinates are in font units and the y-axis points **up**, the opposite of
 * SVG's drawing space. Flipping is the caller's job, because only the caller
 * knows where the baseline sits.
 */
final class SvgFont
{
    /** What the SVG specification prescribes when <font-face> omits it. */
    private const DEFAULT_UNITS_PER_EM = 1000.0;

    /** @var array<string, Glyph> */
    private $glyphs;

    /** @var float */
    private $unitsPerEm;

    /** @var float */
    private $ascent;

    /** @var float */
    private $descent;

    /**
     * @param array<string, Glyph> $glyphs
     */
    private function __construct(array $glyphs, float $unitsPerEm, float $ascent, float $descent)
    {
        $this->glyphs = $glyphs;
        $this->unitsPerEm = $unitsPerEm;
        $this->ascent = $ascent;
        $this->descent = $descent;
    }

    /**
     * Takes markup, not a path. The core neither reads nor writes files, so
     * whoever owns the font file opens it and hands the contents over. See
     * {@see \Redcodede\QrGen\Tests\Qr\CoreIsFrameworkFreeTest}.
     *
     * @throws TextRejected if the markup is not an SVG font
     */
    public static function fromMarkup(string $markup): self
    {
        $font = self::element($markup, 'font');

        if ($font === null) {
            throw TextRejected::notAFont();
        }

        $fontFace = self::element($markup, 'font-face');

        if ($fontFace === null) {
            throw TextRejected::withoutFontFace();
        }

        $unitsPerEm = self::number($fontFace, 'units-per-em', self::DEFAULT_UNITS_PER_EM);

        if ($unitsPerEm <= 0.0) {
            throw TextRejected::unreadableFont('units-per-em has to be greater than zero.');
        }

        // A font-wide horiz-adv-x is what a glyph falls back to. The
        // specification's own default is zero, which would set every glyph
        // without its own width on top of its neighbour, so it is kept as
        // given rather than guessed at.
        $defaultAdvance = self::number($font, 'horiz-adv-x', 0.0);

        $glyphs = self::glyphs($markup, $defaultAdvance);

        if ($glyphs === []) {
            throw TextRejected::withoutGlyphs();
        }

        return new self(
            $glyphs,
            $unitsPerEm,
            self::number($fontFace, 'ascent', $unitsPerEm),
            self::number($fontFace, 'descent', 0.0)
        );
    }

    /** The size of the em square, in font units. */
    public function unitsPerEm(): float
    {
        return $this->unitsPerEm;
    }

    /**
     * How far the tallest letters reach above the baseline, in font units.
     *
     * Falls back to the em square when <font-face> omits it. The font shipped
     * with this package declares both this and the descent.
     */
    public function ascent(): float
    {
        return $this->ascent;
    }

    /** How far letters reach below the baseline, in font units. Negative. */
    public function descent(): float
    {
        return $this->descent;
    }

    public function has(string $character): bool
    {
        return isset($this->glyphs[$character]);
    }

    /**
     * @throws TextRejected if the font has no glyph for this character
     */
    public function glyph(string $character): Glyph
    {
        if (!isset($this->glyphs[$character])) {
            throw TextRejected::unknownCharacter($character);
        }

        return $this->glyphs[$character];
    }

    public function glyphCount(): int
    {
        return count($this->glyphs);
    }

    /**
     * @return array<string, Glyph>
     */
    private static function glyphs(string $markup, float $defaultAdvance): array
    {
        if (preg_match_all('/<glyph\b([^>]*)>/', $markup, $matches, PREG_SET_ORDER) === false) {
            throw TextRejected::unreadableFont('the glyph list could not be scanned.');
        }

        $glyphs = [];

        foreach ($matches as $match) {
            $attributes = $match[1];
            $unicode = self::attribute($attributes, 'unicode');

            if ($unicode === null) {
                continue;
            }

            $character = self::decode($unicode);

            // Ligatures declare several characters in one unicode attribute.
            // They need a substitution pass this package does not have, so
            // they are passed over rather than half-applied.
            if ($character === '' || self::characterCount($character) !== 1) {
                continue;
            }

            // First declaration wins. A font that names a character twice is
            // malformed, and picking one silently beats failing on a file that
            // renders fine everywhere else.
            if (isset($glyphs[$character])) {
                continue;
            }

            $glyphs[$character] = Glyph::of(
                $character,
                self::number($attributes, 'horiz-adv-x', $defaultAdvance),
                self::decode(self::attribute($attributes, 'd') ?? '')
            );
        }

        return $glyphs;
    }

    /**
     * The attribute list of the first element with this name.
     */
    private static function element(string $markup, string $name): ?string
    {
        $pattern = sprintf('/<%s\b([^>]*)>/', preg_quote($name, '/'));

        if (preg_match($pattern, $markup, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private static function attribute(string $attributes, string $name): ?string
    {
        $pattern = sprintf('/\b%s\s*=\s*"([^"]*)"/', preg_quote($name, '/'));

        if (preg_match($pattern, $attributes, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private static function number(string $attributes, string $name, float $fallback): float
    {
        $value = self::attribute($attributes, $name);

        if ($value === null || !is_numeric(trim($value))) {
            return $fallback;
        }

        return (float) trim($value);
    }

    /**
     * Resolves the five XML entities and numeric character references.
     *
     * Deliberately not `html_entity_decode`: that knows several hundred HTML
     * names an XML document does not have, and the point here is to decode what
     * the format actually allows and leave everything else alone.
     */
    private static function decode(string $value): string
    {
        $value = (string) preg_replace_callback(
            '/&#(?:x([0-9A-Fa-f]+)|([0-9]+));/',
            static function (array $match): string {
                $code = $match[1] !== ''
                    ? (int) hexdec($match[1])
                    : (int) $match[2];

                return self::utf8($code);
            },
            $value
        );

        return strtr($value, [
            '&lt;' => '<',
            '&gt;' => '>',
            '&quot;' => '"',
            '&apos;' => "'",
            '&amp;' => '&',
        ]);
    }

    /**
     * A code point as UTF-8, without ext-mbstring and without ext-iconv.
     */
    private static function utf8(int $code): string
    {
        if ($code < 0 || $code > 0x10FFFF) {
            return '';
        }

        if ($code < 0x80) {
            return chr($code);
        }

        if ($code < 0x800) {
            return chr(0xC0 | ($code >> 6)) . chr(0x80 | ($code & 0x3F));
        }

        if ($code < 0x10000) {
            return chr(0xE0 | ($code >> 12))
                . chr(0x80 | (($code >> 6) & 0x3F))
                . chr(0x80 | ($code & 0x3F));
        }

        return chr(0xF0 | ($code >> 18))
            . chr(0x80 | (($code >> 12) & 0x3F))
            . chr(0x80 | (($code >> 6) & 0x3F))
            . chr(0x80 | ($code & 0x3F));
    }

    private static function characterCount(string $text): int
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $characters === false ? 0 : count($characters);
    }
}

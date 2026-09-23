<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Die beiden Code-Arten, mit festen Namen.
 *
 * Zeichenketten statt eines Enums, weil das Paket auf PHP 8.0 zielt und
 * `enum` erst 8.1 kennt. Konstanten an einer Stelle genügen für den Zweck:
 * sie sind das, was in der Adresszeile, in der YAML und im Blueprint steht,
 * und ein Tippfehler soll an einer Stelle auffallen und nicht an vier.
 */
final class Variant
{
    /** Ohne Bildmarke. */
    public const PLAIN = 'plain';

    /** Mit Bildmarke in der Mitte. */
    public const LOGO = 'logo';

    /** Das Etikett, Code in der Schriftfarbe. */
    public const LABEL = 'label';

    /** Dasselbe Etikett, Code in der eingestellten Farbe. */
    public const LABEL_COLOR = 'label_color';

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PLAIN, self::LOGO, self::LABEL, self::LABEL_COLOR];
    }

    public static function isKnown(string $variant): bool
    {
        return in_array($variant, self::all(), true);
    }

    /**
     * Ob dieser Typ ein Etikett ist, also Rahmen, Bildmarke daneben und Text
     * mitbringt statt nur des Symbols.
     *
     * Die Unterscheidung faellt an genug Stellen an, dass sie einmal hier
     * stehen soll und nicht viermal als `=== LABEL || === LABEL_COLOR`.
     */
    public static function isLabel(string $variant): bool
    {
        return $variant === self::LABEL || $variant === self::LABEL_COLOR;
    }
}

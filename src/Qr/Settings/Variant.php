<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Die Code-Arten, mit festen Namen.
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

    /**
     * Das Etikett „Informationen zur Rückgabe".
     *
     * Ein Preset und keine Einstellung: Rahmen, Piktogramm und Text sind fest
     * und kommen als mitgelieferte Grafik, variabel ist allein der Code. Keine
     * Bildmarke und kein eingegebener Text, und das ist der Zweck: nach
     * Art. 12 Abs. 9 PPWR darf die Teilnahme an einem System nur digital
     * gekennzeichnet werden, also steht auf der Verpackung ein neutraler
     * Hinweis und die Aussage selbst erst hinter dem Code.
     */
    public const RETURN_INFO = 'return_info';

    /**
     * Dasselbe Etikett auf Englisch, „Return information".
     *
     * Ein eigener Typ und keine Sprache von {@see self::RETURN_INFO}: auf die
     * Verpackung kommt genau eine Grafik, und welche, entscheidet der Hersteller
     * beim Herunterladen, nicht der Browser. Deshalb stehen beide nebeneinander,
     * wenn beide eingeschaltet sind.
     */
    public const RETURN_INFO_EN = 'return_info_en';

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PLAIN, self::LOGO, self::LABEL, self::LABEL_COLOR, self::RETURN_INFO, self::RETURN_INFO_EN];
    }

    /**
     * Ob dieser Typ eines der beiden Etiketten „Informationen zur Rückgabe"
     * ist. Beide werden gleich gebaut, nur die Grafik unterscheidet sich.
     */
    public static function isReturnInfo(string $variant): bool
    {
        return $variant === self::RETURN_INFO || $variant === self::RETURN_INFO_EN;
    }

    /**
     * Die Sprache, in der dieser Typ beschriftet ist, als kurzer Code.
     *
     * Nur die beiden Etiketten „Informationen zur Rückgabe" haben eine: ihr Text
     * ist fest. Die übrigen Typen tragen keinen oder den eingestellten Text und
     * damit keine Sprache, die das Paket kennt.
     */
    public static function language(string $variant): ?string
    {
        if ($variant === self::RETURN_INFO) {
            return 'de';
        }

        return $variant === self::RETURN_INFO_EN ? 'en' : null;
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
     *
     * {@see self::RETURN_INFO} zaehlt nicht dazu, obwohl es auch einen Rahmen
     * hat: die Frage hier ist, ob Bildmarke und Text aus den Einstellungen
     * kommen, und dort kommt keins von beiden her.
     */
    public static function isLabel(string $variant): bool
    {
        return $variant === self::LABEL || $variant === self::LABEL_COLOR;
    }
}

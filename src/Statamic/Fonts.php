<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * Öffnet die mitgelieferte Schrift. Der Kern tut das nicht.
 *
 * `Qr\Text\SvgFont` nimmt Markup und keinen Pfad, weil `src/Qr` weder liest
 * noch schreibt; ein Test erzwingt das. Damit braucht es jemanden, der die
 * Datei aufmacht, und das ist die Hülle. Genau wie {@see Artwork} für
 * Bildmarken.
 *
 * **Einmal je Anfrage.** Die Datei hat 202 Glyphen, und ein Etikett braucht
 * dieselbe Schrift wie das nächste. Zweimal zu lesen hiesse, zweimal dasselbe
 * zu zerlegen.
 */
final class Fonts
{
    private const LABEL = __DIR__ . '/../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    /** @var SvgFont|null */
    private static $label;

    private function __construct()
    {
    }

    public static function label(): SvgFont
    {
        if (self::$label === null) {
            self::$label = SvgFont::fromMarkup((string) file_get_contents(self::LABEL));
        }

        return self::$label;
    }

    /**
     * Setzt den Zwischenspeicher zurück. Nur für Tests, die die Schrift
     * austauschen.
     */
    public static function forget(): void
    {
        self::$label = null;
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\PngLogo;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Statamic\Facades\Asset;

/**
 * Holt eine Bildmarke aus einem Statamic-Asset, oder eine mitgelieferte Grafik.
 *
 * Die Stelle liegt bewusst in der Huelle. Der Kern nimmt Bytes, keinen Pfad:
 * dieselbe Bildmarke kann damit aus einem Asset, einer Testvorlage oder einem
 * Lieferordner kommen, ohne dass er den Unterschied kennt. Dasselbe gilt fuer
 * die Schrift, die {@see Fonts} oeffnet.
 */
final class Artwork
{
    /** Der feste Teil des Etiketts „Informationen zur Rückgabe". */
    private const RETURN_INFO = __DIR__ . '/../../resources/artwork/rueckgabe-information.svg';

    /** @var Logo|null */
    private static $returnInfo;

    private function __construct()
    {
    }

    /**
     * Piktogramm und Text des Etiketts „Informationen zur Rückgabe".
     *
     * Kein Asset, sondern Teil des Pakets: die Grafik ist das Preset, und ein
     * Redakteur soll sie nicht austauschen koennen. Einmal je Anfrage gelesen,
     * wie die Schrift.
     *
     * Anders als {@see self::load()} faengt diese Methode nichts ab. Eine
     * Bildmarke aus der Mediathek darf fehlen oder falsch sein, das ist eine
     * Frage der Pflege. Lehnt der Kern die mitgelieferte Datei ab, ist das
     * Paket kaputt, und das soll im Panel stehen statt still zu verschwinden.
     *
     * @throws QrGenException
     */
    public static function returnInfo(): Logo
    {
        if (self::$returnInfo === null) {
            self::$returnInfo = SvgLogo::fromMarkup((string) file_get_contents(self::RETURN_INFO));
        }

        return self::$returnInfo;
    }

    /**
     * @param string|null $reference Asset-ID oder Pfad im konfigurierten Container
     */
    public static function load(?string $reference): ?Logo
    {
        if ($reference === null || trim($reference) === '') {
            return null;
        }

        $asset = self::find(trim($reference));

        if ($asset === null) {
            return null;
        }

        $bytes = (string) $asset->contents();

        if ($bytes === '') {
            return null;
        }

        try {
            return strtolower((string) $asset->extension()) === 'png'
                ? PngLogo::fromBinary($bytes)
                : SvgLogo::fromMarkup($bytes);
        } catch (QrGenException $exception) {
            // Eine Bildmarke, die das Paket nicht annimmt, darf die Seite nicht
            // abraeumen. Der Code ohne Marke entsteht weiterhin, und der Grund
            // steht im Log statt in einem 500er.
            logger()->warning('qr-gen: Bildmarke abgelehnt', [
                'asset' => $reference,
                'grund' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private static function find(string $reference)
    {
        if (strpos($reference, '::') !== false) {
            return Asset::find($reference);
        }

        $container = (string) config('qr-gen.container', 'assets');

        return Asset::find($container . '::' . ltrim($reference, '/'));
    }
}

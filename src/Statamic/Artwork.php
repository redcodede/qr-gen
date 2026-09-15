<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\PngLogo;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Statamic\Facades\Asset;

/**
 * Holt eine Bildmarke aus einem Statamic-Asset.
 *
 * Das ist die einzige Stelle, an der das Paket ein Dateisystem anfasst, und sie
 * liegt bewusst in der Huelle. Der Kern nimmt Bytes, keinen Pfad: dieselbe
 * Bildmarke kann damit aus einem Asset, einer Testvorlage oder einem
 * Lieferordner kommen, ohne dass er den Unterschied kennt.
 */
final class Artwork
{
    private function __construct()
    {
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

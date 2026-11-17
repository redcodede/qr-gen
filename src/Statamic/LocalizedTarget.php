<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Qr\Settings\Variant;
use Statamic\Facades\Site;

/**
 * Die Adresse im Code, in der Sprachfassung des Etiketts.
 *
 * Ein Etikett mit festem Text hat eine Sprache, und sein Code soll auf die
 * Seite in dieser Sprache führen: das englische „Return information" auf die
 * englische Fassung, das deutsche auf die deutsche. Ein kleines Multisite für
 * genau diese beiden Typen.
 *
 * **Umgesetzt wird über die Pfade der Statamic-Fassungen.** Liegt die Adresse
 * unter der deutschen Fassung (`/`) und gibt es eine englische (`/en/`), wird
 * aus `https://example.org/return/7K4M2` für das englische Etikett
 * `https://example.org/en/return/7K4M2`. Ob es die Seite dort gibt, prüft das
 * Paket nicht; das ist Sache der Installation.
 *
 * **Der Host kommt aus der Adresse, nicht aus der Anfrage.** Statamic setzt bei
 * einer Fassung mit relativer Adresse den Host der laufenden Anfrage davor. Wer
 * die Download-Seite über einen anderen Host aufruft als den, der im Code
 * steht, etwa ohne `www`, fände sonst keine Fassung, und das Etikett zeigte
 * still auf die falsche Sprache.
 *
 * Unverändert bleibt die Adresse, wenn der Typ keine Sprache hat, wenn es keine
 * Fassung in dieser Sprache gibt, wenn die Adresse zu keiner Fassung gehört
 * (etwa die eigene Seite eines Herstellers) oder wenn sie schon in der richtigen
 * liegt. Eine Installation mit einer einzigen Fassung merkt von alldem nichts.
 */
final class LocalizedTarget
{
    private function __construct()
    {
    }

    public static function for(string $url, string $variant): string
    {
        $sprache = Variant::language($variant);

        if ($sprache === null) {
            return $url;
        }

        $teile = parse_url($url);

        if (!is_array($teile) || !isset($teile['scheme'], $teile['host'])) {
            return $url;
        }

        $herkunft = $teile['scheme'] . '://' . $teile['host'] . (isset($teile['port']) ? ':' . $teile['port'] : '');
        $pfad = $teile['path'] ?? '/';

        $fassungen = Site::all();

        $ziel = $fassungen->first(static function ($site) use ($sprache): bool {
            return $site->shortLocale() === $sprache;
        });

        if ($ziel === null) {
            return $url;
        }

        // Die Fassung, unter der die Adresse liegt. Die mit dem längsten Pfad
        // gewinnt: `/en/return` gehört zu `/en`, nicht zu `/`.
        $quelle = null;
        $quellBasis = '';

        foreach ($fassungen as $site) {
            $basis = self::basis($site->url(), $herkunft);

            if (self::liegtUnter($herkunft . $pfad, $basis) && strlen($basis) > strlen($quellBasis)) {
                $quelle = $site;
                $quellBasis = $basis;
            }
        }

        if ($quelle === null || $quelle->handle() === $ziel->handle()) {
            return $url;
        }

        $rest = substr($herkunft . $pfad, strlen($quellBasis));
        $neu = self::basis($ziel->url(), $herkunft) . '/' . ltrim($rest, '/');

        if (isset($teile['query'])) {
            $neu .= '?' . $teile['query'];
        }

        if (isset($teile['fragment'])) {
            $neu .= '#' . $teile['fragment'];
        }

        return $neu;
    }

    /**
     * Die Adresse einer Fassung ohne Schrägstrich am Ende. Eine relative
     * bekommt den Host der Adresse im Code, siehe oben.
     */
    private static function basis(string $siteUrl, string $herkunft): string
    {
        $basis = strpos($siteUrl, '/') === 0 ? $herkunft . $siteUrl : $siteUrl;

        return rtrim($basis, '/');
    }

    /** Liegt die Adresse unter der Basis, an einer Pfadgrenze? */
    private static function liegtUnter(string $adresse, string $basis): bool
    {
        return $adresse === $basis || strpos($adresse, $basis . '/') === 0;
    }
}

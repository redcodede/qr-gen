<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Illuminate\Support\Facades\URL;
use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\LogoFit;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\PngRenderer;
use Redcodede\QrGen\Qr\Render\SvgRenderer;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Http\Controllers\ImageController;

/**
 * Baut aus einer URL die Daten, die ein Panel braucht.
 *
 * Die Bruecke zwischen dem framework-freien Kern und der Huelle: hier werden
 * Encoder, LogoFit und Renderer zusammengesteckt, und hier entstehen die
 * signierten Adressen fuer Download und "direkt oeffnen".
 *
 * Ausnahmen aus dem Kern werden gefangen und als Text weitergereicht. Eine
 * Bildmarke, die nicht passt, oder eine URL, die zu lang ist, darf die Seite
 * nicht abraeumen.
 */
final class Symbols
{
    private function __construct()
    {
    }

    /**
     * @param string|null $logoReference Asset-Pfad, wie ihn die Bild-Route wieder aufloest
     *
     * @return array<string, mixed>
     */
    public static function panel(
        string $url,
        string $variant,
        ?Logo $logo,
        ?string $logoReference,
        EffectiveSettings $settings
    ): array {
        $mitMarke = $variant === Variant::LOGO && $logo !== null;

        try {
            $matrix = self::matrix($url, $mitMarke, $logo);
            $svg = self::svgRenderer($mitMarke, $logo)->render($matrix);
        } catch (QrGenException $exception) {
            return ['variant' => $variant, 'failure' => $exception->getMessage()];
        }

        $marke = $mitMarke ? $logoReference : null;

        return [
            'variant' => $variant,
            'with_logo' => $mitMarke,
            'preview' => $svg,
            'svg' => $settings->offersSvg() ? self::imageUrl($url, $variant, 'svg', true, $marke) : null,
            'png' => $settings->offersPng() ? self::imageUrl($url, $variant, 'png', true, $marke) : null,
            'raw' => self::imageUrl($url, $variant, 'svg', false, $marke),
        ];
    }

    public static function matrix(string $url, bool $mitMarke, ?Logo $logo): ModuleMatrix
    {
        $encoder = new BaconQrEncoder();

        if (!$mitMarke) {
            return $encoder->encode($url, ErrorCorrection::high());
        }

        // Die niedrigste Stufe, bei der der Kasten ueberlebt. Besser als H zu
        // erzwingen: ein kleineres Symbol hat groessere Module und damit mehr
        // Reserve im Druck.
        $level = (new LogoFit($encoder))->lowestLevelFor($url, Preset::logoBox())->level();

        return $encoder->encode($url, $level);
    }

    public static function svgRenderer(bool $mitMarke, ?Logo $logo): SvgRenderer
    {
        $options = Preset::svgOptions();

        if ($mitMarke && $logo !== null) {
            $options = $options->withLogo($logo, Preset::logoBox());
        }

        return new SvgRenderer($options);
    }

    public static function pngRenderer(bool $mitMarke, ?Logo $logo): PngRenderer
    {
        $options = Preset::pngOptions();

        if ($mitMarke && $logo !== null) {
            $options = $options->withLogo($logo, Preset::logoBox());
        }

        return new PngRenderer($options);
    }

    /**
     * Ohne Signatur waere der Endpunkt ein kostenloser QR-Generator auf fremder
     * Domain: wer beliebige URLs einsetzen kann, laesst sich Codes fuer Links
     * erzeugen, die dort niemand haben will.
     */
    private static function imageUrl(
        string $url,
        string $variant,
        string $format,
        bool $download,
        ?string $logoReference
    ): string {
        $parameter = [
            'url' => $url,
            'variant' => $variant,
            'format' => $format,
            'download' => $download ? 1 : 0,
        ];

        if ($logoReference !== null && $logoReference !== '') {
            $parameter['logo'] = $logoReference;
        }

        return URL::signedRoute(ImageController::ROUTE, $parameter);
    }
}

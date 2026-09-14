<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\SvgRenderer;
use Statamic\Facades\Addon;

/**
 * Dass die Hülle überhaupt hochkommt, und dass sie den Kern nicht verbiegt.
 *
 * Ein Gerüst, das sich nicht booten lässt, fällt sonst erst auf, wenn die erste
 * echte Funktion darauf steht, und dann sucht man den Fehler an der falschen
 * Stelle.
 */
final class ServiceProviderTest extends TestCase
{
    public function testDieAnwendungBootetMitDerErweiterung(): void
    {
        self::assertTrue($this->app->isBooted());
    }

    public function testStatamicKenntDieErweiterung(): void
    {
        $addon = Addon::get('redcodede/qr-gen');

        self::assertNotNull($addon, 'Statamic hat die Erweiterung nicht im Manifest gefunden.');
        self::assertSame('qr-gen', $addon->slug());
    }

    /**
     * Das Verzeichnis leitet Statamic aus dem PSR-4-Eintrag des Providers ab.
     * Stimmt es nicht, findet die Erweiterung ihre eigene Konfiguration, ihre
     * Views und ihre Fieldsets nicht, und zwar ohne Fehlermeldung.
     */
    public function testDasAddonVerzeichnisZeigtAufDasPaket(): void
    {
        $directory = Addon::get('redcodede/qr-gen')->directory();

        self::assertFileExists($directory . 'composer.json');
        self::assertFileExists($directory . 'src/Qr/Preset.php');
    }

    public function testDieKonfigurationLiegtUnterQrGen(): void
    {
        self::assertIsArray(config('qr-gen'));
        self::assertTrue(config('qr-gen.variants.plain'));
        self::assertTrue(config('qr-gen.variants.logo'));
        self::assertTrue(config('qr-gen.downloads.svg'));
        self::assertTrue(config('qr-gen.downloads.png'));
        self::assertNull(config('qr-gen.logo'));
        self::assertNull(config('qr-gen.url'));
    }

    /**
     * Die Druckwerte gehören nicht in die Konfiguration. Sie sind entschieden,
     * nicht eingestellt, und ein freigegebener Andruck gilt für genau sie.
     */
    public function testDieDruckwerteSindKeineKonfiguration(): void
    {
        self::assertNull(config('qr-gen.print'));
        self::assertNull(config('qr-gen.dpi'));
        self::assertSame(600, Preset::PRINT_DPI);
        self::assertSame(50.0, Preset::PRINT_SIZE_MM);
    }

    /**
     * Der Kern muss innerhalb einer laufenden Laravel-Anwendung genau dasselbe
     * tun wie ohne. Er kennt das Framework nicht, also darf dessen Anwesenheit
     * am Ergebnis nichts ändern.
     */
    public function testDerKernRendertInnerhalbVonLaravelUnveraendert(): void
    {
        $matrix = (new BaconQrEncoder())->encode(
            'https://gvoe.de/return/7K4M2',
            ErrorCorrection::high()
        );

        $svg = (new SvgRenderer(Preset::svgOptions()))->render($matrix);

        self::assertStringStartsWith('<svg xmlns=', $svg);
        self::assertStringEndsWith('</svg>', $svg);
        self::assertSame(33, $matrix->size());
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Layout;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Raster\PngDecoder;
use Redcodede\QrGen\Qr\Render\LabelOptions;
use Redcodede\QrGen\Qr\Render\LabelPngRenderer;
use Redcodede\QrGen\Qr\Render\LabelSvgRenderer;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * Das Etikett „Informationen zur Rückgabe", gemessen an der Vorlage vom
 * 24.09.2026.
 *
 * Die Vorlage ist ein Rasterbild. Piktogramm und Text sind daraus als
 * Vektorgrafik nachgebaut und liegen als Datei im Paket; diese Tests halten
 * fest, dass die Datei dort landet, wo sie in der Vorlage steht, und dass SVG
 * und PNG dasselbe zeigen.
 *
 * Die Stellen, an denen die Pixel geprüft werden, sind aus der Vorlage
 * abgelesen: Mitte der linken Gehäusekante, das Innere des Handys über den
 * Scan-Ecken, der Stamm des „I" von „Informationen".
 */
final class ReturnInfoLabelTest extends TestCase
{
    private const ARTWORK = __DIR__ . '/../../../resources/artwork/rueckgabe-information.svg';

    private const FONT = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    private static function artwork(): SvgLogo
    {
        return SvgLogo::fromMarkup((string) file_get_contents(self::ARTWORK));
    }

    private static function font(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::FONT));
    }

    private static function options(): LabelOptions
    {
        return LabelOptions::default()
            ->withCodeColor(Preset::DARK_COLOR)
            ->withInkColor(Preset::DARK_COLOR)
            ->withLightColor(Preset::LIGHT_COLOR);
    }

    private static function matrix(): ModuleMatrix
    {
        return (new BaconQrEncoder())->encode('https://gvoe.de/return/Q7K3M', ErrorCorrection::high());
    }

    /**
     * Der Kasten hat die Maße der Datei. Sonst würde die Grafik eingepasst,
     * also verkleinert und mittig verschoben, und läge nicht mehr dort, wo sie
     * in der Vorlage steht.
     */
    public function testDieGrafikPasstUnverkleinertInIhrenKasten(): void
    {
        $layout = LabelLayout::returnInfo();
        $artwork = self::artwork();

        self::assertEqualsWithDelta($layout->logoWidth(), $artwork->width(), 1e-9);
        self::assertEqualsWithDelta($layout->logoHeight(), $artwork->height(), 1e-9);
    }

    public function testRahmenUndCodeflaecheSindDieDerStandardfassung(): void
    {
        $standard = LabelLayout::standard();
        $layout = LabelLayout::returnInfo();

        self::assertSame($standard->width(), $layout->width());
        self::assertSame($standard->height(), $layout->height());
        self::assertSame($standard->frameInset(), $layout->frameInset());
        self::assertSame($standard->frameStroke(), $layout->frameStroke());
        self::assertSame($standard->codeX(), $layout->codeX());
        self::assertSame($standard->codeY(), $layout->codeY());
        self::assertSame($standard->codeSize(), $layout->codeSize());
    }

    public function testDieGrafikLiegtImRahmenUndRechtsNebenDemCode(): void
    {
        $layout = LabelLayout::returnInfo();
        $innen = $layout->frameInset() + ($layout->frameStroke() / 2);

        self::assertGreaterThan($layout->codeX() + $layout->codeSize(), $layout->logoX());
        self::assertGreaterThan($innen, $layout->logoY());
        self::assertLessThan($layout->width() - $innen, $layout->logoX() + $layout->logoWidth());
        self::assertLessThan($layout->height() - $innen, $layout->logoY() + $layout->logoHeight());
    }

    /**
     * Die Signallinien reichen weiter nach links als der Text der
     * Standardfassung. Die Ruhezone rechts vom Code misst deshalb bis zur
     * Grafik, und sie ist die schmalste der vier Seiten.
     */
    public function testDieRuhezoneRechtsMisstBisZurGrafik(): void
    {
        $layout = LabelLayout::returnInfo();
        $modul = $layout->moduleSizeFor(29);
        $rechts = $layout->logoX() - ($layout->codeX() + $layout->codeSize());

        self::assertEqualsWithDelta($rechts / $modul, $layout->quietZoneInModules(29), 1e-9);
        self::assertLessThan(
            LabelLayout::standard()->quietZoneInModules(29),
            $layout->quietZoneInModules(29)
        );
    }

    /**
     * Nur Flächen, keine Konturen und keine Bögen. Was der Rasterisierer
     * ablehnt, gäbe es nur als SVG.
     */
    public function testDerRasterisiererNimmtDieGrafik(): void
    {
        self::assertNull(LogoRaster::rejectionFor(self::artwork()));
    }

    public function testDasSvgIstGueltigesXmlOhneSchriftUndInSchwarz(): void
    {
        $layout = LabelLayout::returnInfo();
        $svg = (new LabelSvgRenderer($layout, self::font(), self::options()))
            ->render(self::matrix(), '', self::artwork());

        $document = new DOMDocument();
        self::assertTrue(@$document->loadXML($svg), 'Kein gültiges XML.');

        self::assertStringNotContainsString('<text', $svg);
        self::assertStringNotContainsString(LabelOptions::DEFAULT_INK, $svg, 'Die Vorlage ist reines Schwarz.');
        self::assertStringContainsString(
            sprintf('translate(%s %s) scale(1)', $layout->logoX(), $layout->logoY()),
            $svg,
            'Die Grafik sitzt unverkleinert an ihrer Stelle.'
        );
        self::assertSame(1, substr_count($svg, self::artwork()->markup()));
    }

    public function testDasPngZeigtDieGrafikAnDerStelleDerVorlage(): void
    {
        $layout = LabelLayout::returnInfo();
        $renderer = new LabelPngRenderer($layout, self::font(), self::options());
        $matrix = self::matrix();

        $image = PngDecoder::decode($renderer->render($matrix, '', self::artwork()));
        [$breite, $hoehe] = $renderer->pixelSize($matrix);

        self::assertSame([$breite, $hoehe], [$image['width'], $image['height']]);

        $pixel = static function (float $x, float $y) use ($image, $layout): string {
            $px = (int) floor($x / $layout->width() * $image['width']);
            $py = (int) floor($y / $layout->height() * $image['height']);

            return '#' . bin2hex(substr($image['rgba'], (($py * $image['width']) + $px) * 4, 3));
        };

        self::assertSame('#000000', $pixel(41.08, 17.10), 'Linke Kante des Gehäuses.');
        self::assertSame('#ffffff', $pixel(45.12, 12.59), 'Im Handy, über den Scan-Ecken.');
        self::assertSame('#000000', $pixel(53.36, 14.43), 'Stamm des „I".');
    }
}

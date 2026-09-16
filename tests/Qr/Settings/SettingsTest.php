<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Settings;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Redcodede\QrGen\Qr\Settings\PageSettings;
use Redcodede\QrGen\Qr\Settings\Variant;

/**
 * Die Vorrangregel zwischen den beiden Konfigurationsebenen.
 *
 * Global steht, was überhaupt angeboten wird und was gilt, wenn nichts anderes
 * dasteht. Pro Seite steht, was diese eine Seite ausmacht. Im Zweifel gewinnt
 * die Seite.
 *
 * Das ist die Sorte Regel, die man für offensichtlich hält und dann doch
 * herumdreht, sobald sie an drei Stellen gleichzeitig gilt. Deshalb steht sie
 * hier Fall für Fall.
 */
final class SettingsTest extends TestCase
{
    private function effective(GlobalSettings $global, PageSettings $page): EffectiveSettings
    {
        return EffectiveSettings::from($global, $page);
    }

    // ------------------------------------------------------------- Werte ---

    public function testDieSeiteSchlaegtDenGlobalenWert(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDefaultUrl('https://global.example/'),
            PageSettings::empty()->withUrl('https://seite.example/')
        );

        self::assertSame('https://seite.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_PAGE, $effective->urlSource());
    }

    public function testOhneSeitenwertGiltDerGlobale(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDefaultUrl('https://global.example/'),
            PageSettings::empty()
        );

        self::assertSame('https://global.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_GLOBAL, $effective->urlSource());
    }

    public function testOhneBeideGibtEsKeinenWertUndDasStehtDran(): void
    {
        $effective = $this->effective(GlobalSettings::default(), PageSettings::empty());

        self::assertNull($effective->url());
        self::assertSame(EffectiveSettings::FROM_NOWHERE, $effective->urlSource());
    }

    /**
     * Ein leeres Feld im Blueprint heisst „der globale Wert gilt", nicht „es
     * soll keinen geben". Wer das verwechselt, baut eine Oberflaeche, in der
     * sich ein einmal gesetzter globaler Wert nicht mehr abschalten laesst, und
     * eine, in der ein leergeraeumtes Feld die Seite kaputtmacht.
     */
    public function testEineLeereEingabeGiltAlsNichtGesetzt(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDefaultUrl('https://global.example/'),
            PageSettings::fromArray(['url' => '   '])
        );

        self::assertSame('https://global.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_GLOBAL, $effective->urlSource());
    }

    public function testDasselbeGiltFuerDieBildmarke(): void
    {
        $global = GlobalSettings::default()->withDefaultLogo('marke.svg');

        self::assertSame('marke.svg', $this->effective($global, PageSettings::empty())->logo());
        self::assertSame(
            'anders.svg',
            $this->effective($global, PageSettings::empty()->withLogo('anders.svg'))->logo()
        );
    }

    // ---------------------------------------------------------- Varianten ---

    public function testEineFrischeSeiteZeigtAllesAngebotene(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDefaultLogo('marke.svg'),
            PageSettings::empty()
        );

        self::assertTrue($effective->showsPlain());
        self::assertTrue($effective->showsLogo());
    }

    public function testGlobalAbgeschaltetSchlaegtDenWunschDerSeite(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withVariants(true, false)->withDefaultLogo('marke.svg'),
            PageSettings::empty()->withVariants([Variant::PLAIN, Variant::LOGO])
        );

        self::assertTrue($effective->showsPlain());
        self::assertFalse($effective->showsLogo(), 'Global verbietet, die Seite kann nur waehlen.');
    }

    public function testDieSeiteKannEineAngeboteneVarianteWeglassen(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDefaultLogo('marke.svg'),
            PageSettings::empty()->withVariants([Variant::LOGO])
        );

        self::assertFalse($effective->showsPlain());
        self::assertTrue($effective->showsLogo());
    }

    public function testEineSeiteOhneGewaehlteVarianteZeigtNichts(): void
    {
        $effective = $this->effective(
            GlobalSettings::default(),
            PageSettings::empty()->withVariants([])
        );

        self::assertFalse($effective->showsPlain());
        self::assertFalse($effective->showsLogo());
        self::assertFalse($effective->showsAnything());
    }

    /**
     * Ohne Bildmarke gibt es die Variante mit Bildmarke nicht. Sonst wuerde die
     * Oberflaeche ein Panel versprechen, das nur eine Fehlermeldung enthalten
     * kann.
     */
    public function testOhneBildmarkeEntfaelltDieVarianteMitBildmarke(): void
    {
        $effective = $this->effective(GlobalSettings::default(), PageSettings::empty());

        self::assertTrue($effective->showsPlain());
        self::assertFalse($effective->showsLogo());
        self::assertNull($effective->logo());
    }

    // ----------------------------------------------------------- Formate ---

    /**
     * Eine Seite entscheidet, was sie zeigt, nicht, in welchen Dateiformaten
     * das Haus liefert.
     */
    public function testDieFormateSindNurGlobal(): void
    {
        $effective = $this->effective(
            GlobalSettings::default()->withDownloads(true, false),
            PageSettings::fromArray(['downloads' => ['png' => true]])
        );

        self::assertTrue($effective->offersSvg());
        self::assertFalse($effective->offersPng());
    }

    // ------------------------------------------------------- Serialisieren --

    public function testDieGlobalenWerteUeberlebenDenRundlauf(): void
    {
        $global = GlobalSettings::default()
            ->withVariants(true, false)
            ->withDownloads(false, true)
            ->withDefaultLogo('marke.svg')
            ->withDefaultUrl('https://example.org/qr/7K4M2');

        $again = GlobalSettings::fromArray($global->toArray());

        self::assertSame($global->toArray(), $again->toArray());
        self::assertFalse($again->offersLogo());
        self::assertTrue($again->offersPng());
        self::assertSame('marke.svg', $again->defaultLogo());
    }

    /**
     * Eine halb geschriebene Konfigurationsdatei darf die Erweiterung nicht
     * stilllegen: ein fehlender Schluessel ist kein „aus".
     */
    public function testFehlendeSchluesselBehaltenDenStandard(): void
    {
        $global = GlobalSettings::fromArray(['variants' => ['logo' => false]]);

        self::assertTrue($global->offersPlain(), 'Nicht genannt heisst nicht abgeschaltet.');
        self::assertFalse($global->offersLogo());
        self::assertTrue($global->offersSvg());
        self::assertTrue($global->offersPng());
    }

    public function testUnbekannteVariantenWerdenVerworfen(): void
    {
        $page = PageSettings::fromArray(['variants' => ['plain', 'unsinn', 'logo']]);

        self::assertSame([Variant::PLAIN, Variant::LOGO], $page->variants());
    }

    public function testDieKonfigurationsdateiDesPaketsPasstAufDieKlasse(): void
    {
        $global = GlobalSettings::fromArray(require __DIR__ . '/../../../config/qr-gen.php');

        self::assertTrue($global->offersPlain());
        self::assertTrue($global->offersLogo());
        self::assertTrue($global->offersSvg());
        self::assertTrue($global->offersPng());
        self::assertNull($global->defaultLogo());
        self::assertNull($global->defaultUrl());
    }

    // --------------------------------------------------------- Leerstellen --

    public function testOhneAngeboteneVarianteMeldetDasGlobaleObjektEs(): void
    {
        self::assertFalse(GlobalSettings::default()->withVariants(false, false)->offersAnyVariant());
        self::assertFalse(GlobalSettings::default()->withDownloads(false, false)->offersAnyDownload());
        self::assertTrue(GlobalSettings::default()->offersAnyVariant());
        self::assertTrue(GlobalSettings::default()->offersAnyDownload());
    }

    public function testDieObjekteSindUnveraenderlich(): void
    {
        $global = GlobalSettings::default();
        $global->withVariants(false, false);

        self::assertTrue($global->offersPlain(), 'Ein Wither darf das Original nicht anfassen.');

        $page = PageSettings::empty();
        $page->withUrl('https://beispiel.example/');

        self::assertNull($page->url());
    }
}

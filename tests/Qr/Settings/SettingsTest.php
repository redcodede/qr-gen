<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Settings;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;

/**
 * Eine Ebene wird eingestellt, eine Angabe kommt je Stelle dazu.
 *
 * Bis zum 23.09.2026 gab es zwei Ebenen mit einer Vorrangregel, und die Tests
 * hier hielten sie Fall für Fall fest. Der Rückbau hat die Regel abgeschafft,
 * nicht nur Felder entfernt: **es gibt nichts mehr, was eine Stelle
 * überschreiben könnte, außer der Adresse im Code.**
 *
 * Was bleibt, ist die Auflösung der Adresse und die Frage, wann es die Variante
 * mit Bildmarke überhaupt gibt. Beides ist immer noch die Sorte Regel, die man
 * für offensichtlich hält und dann doch herumdreht.
 */
final class SettingsTest extends TestCase
{
    // ------------------------------------------------------------ Adresse ---

    public function testDieAdresseDerStelleSchlaegtDieDefaultUrl(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withDefaultUrl('https://global.example/'),
            'https://stelle.example/'
        );

        self::assertSame('https://stelle.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_PAGE, $effective->urlSource());
    }

    public function testOhneAdresseGiltDieDefaultUrl(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withDefaultUrl('https://global.example/')
        );

        self::assertSame('https://global.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_GLOBAL, $effective->urlSource());
    }

    public function testOhneBeideGibtEsKeinenWertUndDasStehtDran(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default());

        self::assertNull($effective->url());
        self::assertSame(EffectiveSettings::FROM_NOWHERE, $effective->urlSource());
    }

    /**
     * Ein leeres Feld im Blueprint heisst „die Default-URL gilt", nicht „es
     * soll keine geben". Wer das verwechselt, baut eine Oberflaeche, in der ein
     * leergeraeumtes Feld die Stelle kaputtmacht.
     */
    public function testEineLeereEingabeGiltAlsNichtGesetzt(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withDefaultUrl('https://global.example/'),
            '   '
        );

        self::assertSame('https://global.example/', $effective->url());
        self::assertSame(EffectiveSettings::FROM_GLOBAL, $effective->urlSource());
    }

    // ---------------------------------------------------------- Bildmarke ---

    /**
     * Die Bildmarke kommt nur noch global. Eine je Hersteller war der erste
     * Entwurf und ist am 16.09.2026 schon in der Seite entfallen; seit dem
     * Rueckbau gibt es das Feld auch im Paket nicht mehr.
     */
    public function testDieBildmarkeKommtNurGlobal(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withDefaultLogo('marke.svg'),
            'https://stelle.example/'
        );

        self::assertSame('marke.svg', $effective->logo());
        self::assertSame(EffectiveSettings::FROM_GLOBAL, $effective->logoSource());
    }

    public function testOhneBildmarkeStehtDasAuchDran(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default());

        self::assertNull($effective->logo());
        self::assertSame(EffectiveSettings::FROM_NOWHERE, $effective->logoSource());
    }

    // ---------------------------------------------------------- Varianten ---

    public function testAngebotenWirdWasGlobalAngebotenWird(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default()->withDefaultLogo('marke.svg'));

        self::assertTrue($effective->showsPlain());
        self::assertTrue($effective->showsLogo());
        self::assertTrue($effective->showsAnything());
    }

    public function testGlobalAbgeschaltetHeisstNirgendwoZuSehen(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withVariants(true, false)->withDefaultLogo('marke.svg'),
            'https://stelle.example/'
        );

        self::assertTrue($effective->showsPlain());
        self::assertFalse($effective->showsLogo());
    }

    public function testBeideAbgeschaltetZeigtNichts(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default()->withVariants(false, false));

        self::assertFalse($effective->showsAnything());
    }

    /**
     * Ohne Bildmarke gibt es die Variante mit Bildmarke nicht. Sonst wuerde die
     * Oberflaeche ein Panel versprechen, das nur eine Fehlermeldung enthalten
     * kann.
     */
    public function testOhneBildmarkeEntfaelltDieVarianteMitBildmarke(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default());

        self::assertTrue($effective->showsPlain());
        self::assertFalse($effective->showsLogo());
        self::assertNull($effective->logo());
    }

    // ------------------------------------------------------------ Formate ---

    public function testDieFormateSindGlobal(): void
    {
        $effective = EffectiveSettings::from(
            GlobalSettings::default()->withDownloads(true, false),
            'https://stelle.example/'
        );

        self::assertTrue($effective->offersSvg());
        self::assertFalse($effective->offersPng());
    }

    // ------------------------------------------------------ Serialisieren ---

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

    // -------------------------------------------------------- Leerstellen ---

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
    }

    // ----------------------------------------------------------- Rueckbau ---

    /**
     * Der Rueckbau, festgehalten an der Stelle, an der er sich rueckgaengig
     * machen liesse: das Fieldset, das ein Blueprint importiert.
     *
     * **Genau ein Feld.** Wer hier ein zweites einfuegt, fuehrt die zweite
     * Konfigurationsebene wieder ein, und zwar leise: es funktionierte ja, und
     * erst beim naechsten „warum wirkt die Einstellung hier nicht" faellt auf,
     * dass es wieder zwei Orte gibt.
     */
    public function testDasFieldsetTraegtNurDieZielUrl(): void
    {
        $fieldset = (string) file_get_contents(__DIR__ . '/../../../resources/fieldsets/qr_code.yaml');

        preg_match_all('/^\s*handle:\s*(\S+)/m', $fieldset, $treffer);

        self::assertSame(['qr_url'], $treffer[1]);
    }
}

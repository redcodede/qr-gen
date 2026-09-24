<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Settings;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Redcodede\QrGen\Qr\Settings\Variant;

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

    public function testAlleAbgeschaltetZeigtNichts(): void
    {
        $global = GlobalSettings::default()
            ->withVariants(false, false)
            ->withLabels(false, false)
            ->withReturnInfo(false);

        self::assertFalse(EffectiveSettings::from($global)->showsAnything());
    }

    // ---------------------------------------- Informationen zur Rückgabe ---

    public function testDasEtikettZurRueckgabeIstEinEigenerTyp(): void
    {
        self::assertContains(Variant::RETURN_INFO, Variant::all());
        self::assertTrue(Variant::isKnown(Variant::RETURN_INFO));

        // Bildmarke und Text kommen hier nicht aus den Einstellungen, und
        // genau das fragt isLabel().
        self::assertFalse(Variant::isLabel(Variant::RETURN_INFO));
    }

    /**
     * Es haengt an keinem anderen Wert. Ohne Bildmarke, ohne Text und ohne
     * Farbe ist es trotzdem da, denn es bringt alles selbst mit.
     */
    public function testDasEtikettZurRueckgabeBrauchtNurSeinenSchalter(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default(), 'https://stelle.example/');

        self::assertNull($effective->logo());
        self::assertNull($effective->labelText());
        self::assertNull($effective->codeColor());
        self::assertTrue($effective->showsReturnInfo());
        self::assertTrue($effective->shows(Variant::RETURN_INFO));
    }

    public function testDasEtikettZurRueckgabeLaesstSichAbschalten(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default()->withReturnInfo(false));

        self::assertFalse($effective->showsReturnInfo());
        self::assertFalse($effective->shows(Variant::RETURN_INFO));
        self::assertTrue($effective->showsPlain(), 'Die anderen bleiben, wie sie sind.');
    }

    /**
     * Eine gespeicherte Einstellung von vor diesem Typ kennt den Schluessel
     * nicht. Er gilt dann als an, wie jeder fehlende Schalter.
     */
    public function testEineAeltereEinstellungOhneDenSchluesselBietetEsAn(): void
    {
        $global = GlobalSettings::fromArray([
            'variants' => ['plain' => false, 'logo' => false, 'label' => true, 'label_color' => true],
        ]);

        self::assertTrue($global->offersReturnInfo());
        self::assertFalse(GlobalSettings::fromArray(['variants' => ['return_info' => false]])->offersReturnInfo());
    }

    // ------------------------------------------------------------ Etikett ---

    public function testDieEtikettenWerdenAngebotenWennEineFarbeSteht(): void
    {
        $global = GlobalSettings::default()
            ->withLabelText('Rückgabe über das GVÖ-SYSTEM')
            ->withCodeColor('#009877');

        $effective = EffectiveSettings::from($global, 'https://stelle.example/');

        self::assertTrue($effective->showsLabel());
        self::assertTrue($effective->showsLabelColor());
        self::assertSame('Rückgabe über das GVÖ-SYSTEM', $effective->labelText());
        self::assertSame('#009877', $effective->codeColor());
    }

    /**
     * Dieselbe Regel wie bei der Bildmarke: ohne Farbe kein farbiges Etikett.
     * Sonst stuenden zwei Etiketten nebeneinander, die gleich aussehen, und
     * niemand wuesste, warum es zwei sind.
     */
    public function testOhneFarbeEntfaelltDasFarbigeEtikett(): void
    {
        $effective = EffectiveSettings::from(GlobalSettings::default());

        self::assertTrue($effective->showsLabel());
        self::assertFalse($effective->showsLabelColor());
        self::assertNull($effective->codeColor());
    }

    public function testEinAbgeschaltetesEtikettBleibtAusAuchMitFarbe(): void
    {
        $global = GlobalSettings::default()
            ->withLabels(false, false)
            ->withCodeColor('#009877');

        $effective = EffectiveSettings::from($global);

        self::assertFalse($effective->showsLabel());
        self::assertFalse($effective->showsLabelColor());
    }

    /**
     * Die Auskunft, ob ein Typ hier erscheint, liegt an einer Stelle. Vorher
     * fragte der Tag anders als die Bild-Route, und die beiden haetten
     * auseinanderlaufen koennen.
     */
    public function testShowsBeantwortetAlleTypen(): void
    {
        $global = GlobalSettings::default()
            ->withVariants(true, false)
            ->withLabels(true, true)
            ->withReturnInfo(true)
            ->withCodeColor('#009877');

        $effective = EffectiveSettings::from($global);

        self::assertTrue($effective->shows(Variant::PLAIN));
        self::assertFalse($effective->shows(Variant::LOGO), 'Abgeschaltet.');
        self::assertTrue($effective->shows(Variant::LABEL));
        self::assertTrue($effective->shows(Variant::LABEL_COLOR));
        self::assertTrue($effective->shows(Variant::RETURN_INFO));
        self::assertFalse($effective->shows('unsinn'));
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
            ->withReturnInfo(false)
            ->withDownloads(false, true)
            ->withDefaultLogo('marke.svg')
            ->withDefaultUrl('https://example.org/qr/7K4M2');

        $again = GlobalSettings::fromArray($global->toArray());

        self::assertSame($global->toArray(), $again->toArray());
        self::assertFalse($again->offersLogo());
        self::assertFalse($again->offersReturnInfo());
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
        self::assertTrue($global->offersReturnInfo());
        self::assertTrue($global->offersSvg());
        self::assertTrue($global->offersPng());
        self::assertNull($global->defaultLogo());
        self::assertNull($global->defaultUrl());
    }

    // -------------------------------------------------------- Leerstellen ---

    public function testOhneAngeboteneVarianteMeldetDasGlobaleObjektEs(): void
    {
        $nichts = GlobalSettings::default()
            ->withVariants(false, false)
            ->withLabels(false, false)
            ->withReturnInfo(false);

        self::assertFalse($nichts->offersAnyVariant());
        self::assertFalse(GlobalSettings::default()->withDownloads(false, false)->offersAnyDownload());
        self::assertTrue(GlobalSettings::default()->offersAnyVariant());
        self::assertTrue(GlobalSettings::default()->offersAnyDownload());

        // Ein einziger uebrig gebliebener Typ zaehlt auch.
        self::assertTrue(
            GlobalSettings::default()->withVariants(false, false)->offersAnyVariant(),
            'Die Etiketten stehen noch.'
        );
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

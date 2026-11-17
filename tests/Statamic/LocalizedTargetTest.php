<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Artwork;
use Redcodede\QrGen\Statamic\LocalizedTarget;
use Redcodede\QrGen\Statamic\Symbols;
use Statamic\Facades\Antlers;
use Statamic\Facades\Site;

/**
 * Das kleine Multisite der beiden Etiketten „Informationen zur Rückgabe": jedes
 * führt auf die Sprachfassung, in der es beschriftet ist.
 */
final class LocalizedTargetTest extends TestCase
{
    private const DEUTSCH = 'https://www.gvoe.de/return/Q7K3M';

    private const ENGLISCH = 'https://www.gvoe.de/en/return/Q7K3M';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('qr-gen', require __DIR__ . '/../../config/qr-gen.php');
        $app['config']->set('qr-gen.settings_path', $this->tempDirectory . '/qr-gen/settings.yaml');
        $app['config']->set('qr-gen.variants.return_info_en', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::zweiFassungen();
    }

    private static function zweiFassungen(): void
    {
        Site::setConfig(['sites' => [
            'default' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/'],
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/en/'],
        ]]);
    }

    public function testDasEnglischeEtikettFuehrtAufDieEnglischeFassung(): void
    {
        self::assertSame(self::ENGLISCH, LocalizedTarget::for(self::DEUTSCH, Variant::RETURN_INFO_EN));
    }

    public function testDasDeutscheEtikettBleibtAufDerDeutschen(): void
    {
        self::assertSame(self::DEUTSCH, LocalizedTarget::for(self::DEUTSCH, Variant::RETURN_INFO));
    }

    public function testUndUmgekehrt(): void
    {
        self::assertSame(self::DEUTSCH, LocalizedTarget::for(self::ENGLISCH, Variant::RETURN_INFO));
        self::assertSame(self::ENGLISCH, LocalizedTarget::for(self::ENGLISCH, Variant::RETURN_INFO_EN));
    }

    /**
     * Die Anfrage läuft im Test über `localhost`, die Adresse im Code nennt
     * www.gvoe.de. Umgesetzt wird trotzdem, weil der Host aus der Adresse
     * kommt und nicht aus der Anfrage.
     */
    public function testDerHostKommtAusDerAdresseNichtAusDerAnfrage(): void
    {
        self::assertStringNotContainsString('www.gvoe.de', request()->getSchemeAndHttpHost());
        self::assertSame(self::ENGLISCH, LocalizedTarget::for(self::DEUTSCH, Variant::RETURN_INFO_EN));
    }

    public function testAbfrageUndAnkerBleiben(): void
    {
        self::assertSame(
            'https://www.gvoe.de/en/return/Q7K3M?a=1#b',
            LocalizedTarget::for('https://www.gvoe.de/return/Q7K3M?a=1#b', Variant::RETURN_INFO_EN)
        );
    }

    public function testDieStartseiteWirdZurStartseite(): void
    {
        self::assertSame('https://www.gvoe.de/en/', LocalizedTarget::for('https://www.gvoe.de/', Variant::RETURN_INFO_EN));
    }

    /**
     * Ein Pfad, der nur mit dem der Fassung beginnt, liegt nicht darunter.
     */
    public function testEinPfadMitGleichemAnfangIstKeineFassung(): void
    {
        self::assertSame(
            'https://www.gvoe.de/en/english/Q7K3M',
            LocalizedTarget::for('https://www.gvoe.de/english/Q7K3M', Variant::RETURN_INFO_EN)
        );
    }

    /**
     * Die übrigen Typen haben keine Sprache und führen, wohin die Seite sagt.
     */
    public function testAndereTypenBleibenUnveraendert(): void
    {
        foreach ([Variant::PLAIN, Variant::LOGO, Variant::LABEL, Variant::LABEL_COLOR] as $typ) {
            self::assertSame(self::DEUTSCH, LocalizedTarget::for(self::DEUTSCH, $typ), $typ);
        }
    }

    public function testOhneEnglischeFassungBleibtAllesWieEsIst(): void
    {
        Site::setConfig(['sites' => ['default' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/']]]);

        self::assertSame(self::DEUTSCH, LocalizedTarget::for(self::DEUTSCH, Variant::RETURN_INFO_EN));
    }

    /**
     * Die eigene Seite eines Herstellers gehört zu keiner Fassung, wenn die
     * Fassungen feste Adressen haben.
     */
    public function testEineFremdeAdresseBleibtUnveraendert(): void
    {
        Site::setConfig(['sites' => [
            'default' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => 'https://www.gvoe.de/'],
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'https://www.gvoe.de/en/'],
        ]]);

        self::assertSame(self::ENGLISCH, LocalizedTarget::for(self::DEUTSCH, Variant::RETURN_INFO_EN));
        self::assertSame(
            'https://hersteller.example/rueckgabe',
            LocalizedTarget::for('https://hersteller.example/rueckgabe', Variant::RETURN_INFO_EN)
        );
    }

    /**
     * Im Bild steckt die englische Adresse, für Vorschau und Download.
     */
    public function testImEnglischenEtikettStecktDieEnglischeAdresse(): void
    {
        $settings = EffectiveSettings::from(GlobalSettings::default()->withReturnInfoEn(true));

        foreach (['svg', 'png'] as $format) {
            [$bild] = Symbols::image(self::DEUTSCH, Variant::RETURN_INFO_EN, null, $settings, $format);

            $erwartet = Symbols::returnInfoRenderer($format)
                ->render(Symbols::matrix(self::ENGLISCH, false, null), '', Artwork::returnInfoEn());

            self::assertSame($erwartet, $bild, $format);
        }
    }

    /**
     * Das Panel sagt, wohin der Code führt, wenn es nicht die Adresse der
     * Seite ist. Beim deutschen ist es dieselbe, da steht nichts.
     */
    public function testDasPanelNenntDieAbweichendeAdresse(): void
    {
        $markup = (string) Antlers::parse('{{ qr_gen url="' . self::DEUTSCH . '" }}');

        self::assertSame(1, substr_count($markup, 'class="qr-gen-target"'));
        self::assertStringContainsString(
            '<p class="qr-gen-target">' . e(__('qr-gen::texts.panel.target')) . ' <a href="' . self::ENGLISCH . '">' . self::ENGLISCH . '</a></p>',
            $markup
        );

        // Steht im englischen Panel, nicht im deutschen.
        self::assertGreaterThan(
            strpos($markup, 'qr-gen-panel--return_info_en"'),
            strpos($markup, 'class="qr-gen-target"')
        );
    }
}

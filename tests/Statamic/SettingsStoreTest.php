<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Redcodede\QrGen\Statamic\Settings\SettingsStore;

/**
 * Die Ablage der globalen Einstellungen.
 *
 * Geprüft wird vor allem die Schichtung: was gespeichert wurde, liegt über
 * `config/qr-gen.php`, und was nicht gespeichert wurde, kommt von dort. Das ist
 * die Regel, an der eine halb ausgefüllte Datei sonst die ganze Erweiterung
 * stilllegt.
 */
final class SettingsStoreTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('qr-gen', require __DIR__ . '/../../config/qr-gen.php');
        $app['config']->set('qr-gen.settings_path', $this->tempDirectory . '/qr-gen/settings.yaml');

        // Zwei Fassungen, damit „je Sprachfassung" tatsächlich geprüft wird
        // und nicht nur der Einzelfall.
        $app['config']->set('statamic.sites.sites', [
            'default' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'url' => '/'],
            'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => '/en/'],
        ]);
    }

    public function testOhneDateiGiltDieKonfiguration(): void
    {
        self::assertFalse(SettingsStore::exists());

        $settings = SettingsStore::global();

        self::assertTrue($settings->offersPlain());
        self::assertTrue($settings->offersLogo());
        self::assertTrue($settings->offersSvg());
        self::assertTrue($settings->offersPng());
        self::assertNull($settings->defaultUrl());
    }

    public function testGespeichertesLiegtUeberDerKonfiguration(): void
    {
        SettingsStore::save([
            'variant_plain' => false,
            'variant_logo' => true,
            'download_svg' => true,
            'download_png' => false,
            'default_url' => 'https://beispiel.test/return/ABCDE',
            'default_logo' => 'marken/probe.svg',
        ]);

        $settings = SettingsStore::global();

        self::assertFalse($settings->offersPlain());
        self::assertTrue($settings->offersLogo());
        self::assertTrue($settings->offersSvg());
        self::assertFalse($settings->offersPng());
        self::assertSame('https://beispiel.test/return/ABCDE', $settings->defaultUrl());
        self::assertSame('marken/probe.svg', $settings->defaultLogo());
    }

    /**
     * Die Schalter liegen eine Ebene tief. Ein `array_merge` würde `variants`
     * als Ganzes ersetzen, und ein Schalter, den eine ältere Fassung der Datei
     * noch nicht kannte, fiele auf `false` statt auf seinen Standard.
     */
    public function testEinFehlenderSchalterBehaeltSeinenStandard(): void
    {
        $verzeichnis = dirname((string) config('qr-gen.settings_path'));
        mkdir($verzeichnis, 0777, true);

        file_put_contents(
            (string) config('qr-gen.settings_path'),
            "variants:\n  plain: false\n"
        );

        $settings = SettingsStore::global();

        self::assertFalse($settings->offersPlain());
        self::assertTrue($settings->offersLogo(), 'logo stand nicht in der Datei und behaelt den Standard');
        self::assertTrue($settings->offersSvg());
    }

    public function testLeereTexteWerdenTatsaechlichLeer(): void
    {
        SettingsStore::save([
            'variant_plain' => true,
            'variant_logo' => true,
            'download_svg' => true,
            'download_png' => true,
            'default_url' => '   ',
            'default_logo' => null,
        ]);

        $settings = SettingsStore::global();

        self::assertNull($settings->defaultUrl());
        self::assertNull($settings->defaultLogo());
    }

    /**
     * Das Asset-Feld liefert je nach Herkunft einen String oder ein Array mit
     * einem Eintrag. Beides muss dieselbe Zeile in der YAML ergeben.
     */
    public function testDasAssetFeldDarfEinArraySein(): void
    {
        self::assertSame(
            'marken/probe.svg',
            SettingsStore::fromForm(['default_logo' => ['marken/probe.svg']])['logo']
        );

        self::assertSame(
            'marken/probe.svg',
            SettingsStore::fromForm(['default_logo' => 'marken/probe.svg'])['logo']
        );
    }

    /**
     * Hin und zurück muss dasselbe ergeben, sonst zeigt das Formular etwas
     * anderes an, als gespeichert ist.
     */
    public function testFormularUndSpeicherformPassenZusammen(): void
    {
        $eingabe = [
            'variant_plain' => false,
            'variant_logo' => true,
            'variant_label' => true,
            'variant_label_color' => false,
            'variant_return_info' => false,
            'download_svg' => false,
            'download_png' => true,
            'default_url' => 'https://beispiel.test',
            'default_logo' => 'marken/probe.svg',
            'label_text' => 'Rückgabe über das GVÖ-SYSTEM',
            'label_color' => '#009877',
        ];

        // Jede Fassung bekommt eigene Texte, damit der Rundlauf keine
        // auslässt, egal wie viele es sind.
        foreach (SettingsStore::sites() as $handle) {
            $eingabe[SettingsStore::textField($handle, 'title')] = 'Codes ' . $handle;
            $eingabe[SettingsStore::textField($handle, 'lead')] = 'Codes ' . $handle . ' für {url}';
        }

        SettingsStore::save($eingabe);

        $zurueck = SettingsStore::toForm(SettingsStore::global());

        // Nach Schluessel sortiert, weil die Reihenfolge der Felder nichts
        // zusichert: sie folgt dem Blueprint und darf sich aendern.
        ksort($eingabe);
        ksort($zurueck);

        self::assertSame($eingabe, $zurueck);
    }

    /**
     * Die gespeicherte Datei hat dieselbe Form wie `config/qr-gen.php`. Wer
     * die eine versteht, versteht die andere, und `GlobalSettings::fromArray()`
     * liest beide.
     */
    public function testDieDateiHatDieFormDerKonfiguration(): void
    {
        SettingsStore::save([
            'variant_plain' => true,
            'variant_logo' => false,
            'variant_label' => true,
            'variant_label_color' => false,
            'variant_return_info' => true,
            'download_svg' => true,
            'download_png' => true,
            'label_text' => 'Rückgabe über das GVÖ-SYSTEM',
            'label_color' => '#009877',
        ]);

        self::assertSame([
            'variants' => [
                'plain' => true,
                'logo' => false,
                'label' => true,
                'label_color' => false,
                'return_info' => true,
            ],
            'downloads' => ['svg' => true, 'png' => true],
            'logo' => null,
            'url' => null,
            'label_text' => 'Rückgabe über das GVÖ-SYSTEM',
            'code_color' => '#009877',
            'texts' => [],
        ], SettingsStore::stored());
    }

    /**
     * Ein leeres Textfeld heißt „nimm den mitgelieferten Text" und nicht
     * „zeig nichts". Sonst stünde auf der Seite eine leere Überschrift, sobald
     * jemand die Einstellungen einmal speichert, ohne dort etwas einzutragen.
     */
    public function testOhneEigenenTextGiltDerMitgelieferteInDerSpracheDerFassung(): void
    {
        SettingsStore::save(['variant_plain' => true]);

        self::assertSame('QR Codes', SettingsStore::text('title', 'default'));
        self::assertSame('QR codes', SettingsStore::text('title', 'en'));

        self::assertStringContainsString('{url}', SettingsStore::text('lead', 'default'));
        self::assertStringContainsString('{url}', SettingsStore::text('lead', 'en'));
    }

    /**
     * Ein eigener Text gilt für seine Fassung und nur für sie. Sonst wäre die
     * Einstellung „je Sprachfassung" keine.
     */
    public function testEinEigenerTextGiltNurFuerSeineFassung(): void
    {
        SettingsStore::save([
            SettingsStore::textField('default', 'title') => 'Codes zum Mitnehmen',
        ]);

        self::assertSame('Codes zum Mitnehmen', SettingsStore::text('title', 'default'));
        self::assertSame('QR codes', SettingsStore::text('title', 'en'));
    }

    public function testEinEigenerTextGewinnt(): void
    {
        SettingsStore::save([
            SettingsStore::textField('default', 'title') => '  Codes zum Mitnehmen  ',
        ]);

        self::assertSame('Codes zum Mitnehmen', SettingsStore::text('title', 'default'));
    }

    /**
     * Eine Sprachfassung ohne einen einzigen eigenen Text taucht in der Datei
     * nicht auf. Ein Block aus lauter `null` sagt nichts und wäre nur eine
     * Zeile mehr, die jemand lesen muss.
     */
    public function testEineFassungOhneTexteStehtNichtInDerDatei(): void
    {
        SettingsStore::save([
            SettingsStore::textField('default', 'title') => 'Codes',
        ]);

        self::assertSame(['default' => ['title' => 'Codes']], SettingsStore::stored()['texts']);
    }
}

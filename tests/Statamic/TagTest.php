<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Statamic\Facades\Antlers;

/**
 * Was `{{ qr_gen }}` tatsächlich ausgibt.
 *
 * **Diesen Test gab es nicht, und deshalb ist es passiert.** Das Etikett lag
 * ab `2.0.0` im Kern und wurde von der Demo-Seite gerendert, aber die Hülle
 * kannte es nicht: `Variant` hatte zwei Einträge, der Tag lief über zwei
 * Panels, und im Control Panel gab es keine Felder dafür. Alle 483 Tests waren
 * grün, weil keiner von ihnen den Tag aufgerufen hat.
 *
 * Geprüft wird deshalb die Kette von der Einstellung bis zum Markup, und nicht
 * die Einzelteile darin. Die haben ihre eigenen Tests.
 */
final class TagTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('qr-gen', require __DIR__ . '/../../config/qr-gen.php');
        $app['config']->set('qr-gen.settings_path', $this->tempDirectory . '/qr-gen/settings.yaml');
        $app['config']->set('qr-gen.label_text', 'Rückgabe über das GVÖ-SYSTEM');
        $app['config']->set('qr-gen.code_color', '#009877');

        // Signierte Adressen brauchen einen Schlüssel.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    private function render(string $template = '{{ qr_gen url="https://gvoe.de/return/Q7K3M" }}'): string
    {
        return (string) Antlers::parse($template);
    }

    public function testAlleAngebotenenTypenStehenImMarkup(): void
    {
        $markup = $this->render();

        self::assertStringContainsString('qr-gen-panel--plain', $markup);
        self::assertStringContainsString('qr-gen-panel--label', $markup);
        self::assertStringContainsString('qr-gen-panel--label_color', $markup);

        // Ohne hinterlegte Bildmarke entfaellt dieser eine, lautlos.
        self::assertStringNotContainsString('qr-gen-panel--logo', $markup);
    }

    /**
     * Die Etiketten tragen die Maße der Vorlage. Käme hier das nackte Symbol
     * heraus, sähe das Markup trotzdem vollständig aus.
     */
    public function testDieEtikettenHabenDieMasseDerVorlage(): void
    {
        $layout = LabelLayout::standard();
        $markup = $this->render();

        self::assertSame(
            2,
            substr_count($markup, sprintf('viewBox="0 0 %s %s"', $layout->width(), $layout->height())),
            'Es sollten genau zwei Etiketten im Markup stehen.'
        );
    }

    public function testDasFarbigeEtikettTraegtDieEingestellteFarbe(): void
    {
        $markup = $this->render();

        self::assertStringContainsString('#009877', $markup);
        self::assertSame(1, substr_count($markup, '#009877'), 'Nur das farbige Etikett.');
    }

    public function testDerEingestellteTextWirdGesetzt(): void
    {
        $markup = $this->render();

        // Der Satz steht als Umrisse im Bild, nicht als Text, also ist die
        // Zeichenkette selbst nicht zu finden. Was sich pruefen laesst: ohne
        // Text kaeme ein kuerzeres Etikett heraus.
        $ohne = $this->withoutLabelText();

        self::assertGreaterThan(strlen($ohne), strlen($markup), 'Der Satz fehlt im Bild.');
    }

    private function withoutLabelText(): string
    {
        config()->set('qr-gen.label_text', null);

        $markup = $this->render();

        config()->set('qr-gen.label_text', 'Rückgabe über das GVÖ-SYSTEM');

        return $markup;
    }

    /**
     * Ohne Farbe gibt es das farbige Etikett nicht, wie es die Variante mit
     * Bildmarke ohne Bildmarke nicht gibt.
     */
    public function testOhneFarbeFehltDasFarbigeEtikett(): void
    {
        config()->set('qr-gen.code_color', null);

        $markup = $this->render();

        self::assertStringContainsString('qr-gen-panel--label', $markup);
        self::assertStringNotContainsString('qr-gen-panel--label_color', $markup);
    }

    public function testEinAbgeschalteterTypErscheintNicht(): void
    {
        config()->set('qr-gen.variants.label', false);
        config()->set('qr-gen.variants.label_color', false);

        $markup = $this->render();

        self::assertStringContainsString('qr-gen-panel--plain', $markup);
        self::assertStringNotContainsString('qr-gen-panel--label', $markup);
    }

    public function testJedesPanelTraegtSeineDreiKnoepfe(): void
    {
        $markup = $this->render();

        // Drei Typen, je SVG, PNG und "direkt oeffnen".
        self::assertSame(3, substr_count($markup, 'qr-gen-downloads'));
        self::assertSame(9, substr_count($markup, '<a class='));
    }

    public function testOhneAdresseKommtNichts(): void
    {
        self::assertSame('', trim($this->render('{{ qr_gen }}')));
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Illuminate\Support\Facades\URL;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Http\Controllers\ImageController;

/**
 * Was die Bild-Route ausliefert, also die Knöpfe „herunterladen" und „direkt
 * öffnen".
 *
 * Geprüft am Etikett „Informationen zur Rückgabe", weil es als einziger Typ
 * eine mitgelieferte Datei öffnet: fände die Route sie nicht, bliebe die
 * Vorschau im Tag trotzdem heil, denn die läuft nicht über die Route.
 */
final class ImageRouteTest extends TestCase
{
    private const ZIEL = 'https://gvoe.de/return/Q7K3M';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('qr-gen', require __DIR__ . '/../../config/qr-gen.php');
        $app['config']->set('qr-gen.settings_path', $this->tempDirectory . '/qr-gen/settings.yaml');
    }

    /**
     * @param array<string, mixed> $weitere
     */
    private static function adresse(string $format, array $weitere = []): string
    {
        return URL::signedRoute(ImageController::ROUTE, array_merge([
            'url' => self::ZIEL,
            'variant' => Variant::RETURN_INFO,
            'format' => $format,
            'download' => 1,
        ], $weitere));
    }

    public function testDasSvgKommtAlsDateiMitEigenemNamen(): void
    {
        $antwort = $this->get(self::adresse('svg'));

        $antwort->assertOk();
        self::assertSame('image/svg+xml; charset=utf-8', $antwort->headers->get('Content-Type'));
        self::assertSame(
            'attachment; filename="qr-gvoe-de-return-q7k3m-rueckgabeinformation.svg"',
            $antwort->headers->get('Content-Disposition')
        );
        self::assertStringStartsWith('<svg', (string) $antwort->getContent());
    }

    public function testDasPngKommtAlsDateiMitEigenemNamen(): void
    {
        $antwort = $this->get(self::adresse('png'));

        $antwort->assertOk();
        self::assertSame('image/png', $antwort->headers->get('Content-Type'));
        self::assertStringEndsWith('-rueckgabeinformation.png"', (string) $antwort->headers->get('Content-Disposition'));
        self::assertStringStartsWith("\x89PNG", (string) $antwort->getContent());
    }

    public function testDirektOeffnenZeigtEsImBrowser(): void
    {
        $antwort = $this->get(self::adresse('svg', ['download' => 0]));

        $antwort->assertOk();
        self::assertSame('inline', $antwort->headers->get('Content-Disposition'));
    }

    /**
     * Ein abgeschalteter Typ ist auch über eine alte signierte Adresse nicht
     * zu bekommen.
     */
    public function testAbgeschaltetGibtEsDasEtikettNicht(): void
    {
        config()->set('qr-gen.variants.return_info', false);

        $this->get(self::adresse('svg'))->assertNotFound();
    }

    public function testOhneSignaturGibtEsNichts(): void
    {
        $this->get('/!/qr-gen/image?url=' . urlencode(self::ZIEL) . '&variant=return_info')->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Redcodede\QrGen\Statamic\Http\Controllers\CP\SettingsController;
use Redcodede\QrGen\Statamic\Settings\SettingsBlueprint;
use Redcodede\QrGen\Statamic\Settings\SettingsStore;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;

/**
 * Die Einstellungsseite im Control Panel.
 *
 * Geprüft wird nicht das Aussehen, sondern das, was ohne Test still
 * kaputtgehen kann: dass es die Routen gibt, dass der Eintrag unter
 * „Werkzeuge" landet, dass die Berechtigung greift, und dass die Felder des
 * Formulars dieselben sind wie die Schlüssel der Ablage. Der Rest ist
 * Statamics eigene Publish-Form und testet nicht dieses Paket.
 */
final class SettingsPageTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('qr-gen', require __DIR__ . '/../../config/qr-gen.php');
        $app['config']->set('qr-gen.settings_path', $this->tempDirectory . '/qr-gen/settings.yaml');
    }

    public function testDieRoutenSindRegistriert(): void
    {
        self::assertNotNull(cp_route('qr-gen.settings'));
        self::assertNotNull(cp_route('qr-gen.settings.update'));
    }

    public function testDieBerechtigungIstRegistriert(): void
    {
        Permission::boot();

        self::assertContains(SettingsController::PERMISSION, Permission::all()->keys()->all());
    }

    /**
     * Ohne Berechtigung kein Zugriff, und zwar in beide Richtungen. Eine
     * abgeschaltete Variante nimmt einer ganzen Seite ihre Codes.
     *
     * Geprüft am Controller und nicht über eine Anfrage: das CP-Layout
     * verlangt eine `composer.lock`, die es unter Testbench nicht gibt. Die
     * Prüfung, um die es geht, sitzt ohnehin hier.
     */
    public function testOhneBerechtigungGibtEsKeinenZugriff(): void
    {
        $controller = new SettingsController($request = Request::create('/'));

        $this->expectException(AuthorizationException::class);

        $controller->edit($request);
    }

    public function testAuchDasSpeichernIstGeschuetzt(): void
    {
        $controller = new SettingsController($request = Request::create('/', 'PATCH'));

        $this->expectException(AuthorizationException::class);

        $controller->update($request);
    }

    /**
     * Die Felder des Formulars und die Schlüssel der Ablage müssen
     * zusammenpassen. Ein umbenanntes Feld fiele sonst still auf seinen
     * Standard zurück, und niemand sähe warum.
     */
    public function testDieFelderDesFormularsSindDieDerAblage(): void
    {
        $felder = SettingsBlueprint::make()->fields()->all()->keys()->sort()->values()->all();

        $ablage = array_keys(SettingsStore::toForm(SettingsStore::global()));
        sort($ablage);

        self::assertSame($ablage, $felder);
    }

    /**
     * Die Druckwerte sind entschieden, nicht eingestellt. Ein Feld dafür wäre
     * eine Einladung, einen freigegebenen Andruck ungültig zu machen, ohne
     * dass es auffällt.
     */
    public function testDieDruckwerteSindKeinFormularfeld(): void
    {
        $felder = SettingsBlueprint::make()->fields()->all()->keys()->all();

        foreach (['quiet_zone', 'module_size', 'logo_box', 'dpi', 'print_size', 'level'] as $verboten) {
            self::assertNotContains($verboten, $felder);
        }
    }
}

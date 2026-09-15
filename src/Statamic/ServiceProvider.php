<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Statamic\Http\Controllers\CP\SettingsController;
use Redcodede\QrGen\Statamic\Tags\QrGen;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

/**
 * Die Statamic-Hülle um das Paket.
 *
 * Alles, was Laravel oder Statamic kennt, lebt unterhalb von `src/Statamic`.
 * `src/Qr` bleibt frei davon, und ein Test erzwingt das. Der Grund steht in der
 * README: Statamic 3 und Statamic 6 lassen sich nicht mit einer Fassung
 * bedienen, und wenn die Fachlogik framework-frei bleibt, ist der Umzug eine
 * neue Hülle statt einer zweiten Umsetzung.
 *
 * Diese Klasse hält deshalb nur Verdrahtung. Sie enthält keine Fachlogik, und
 * das soll so bleiben.
 */
class ServiceProvider extends AddonServiceProvider
{
    /**
     * Unter diesem Namen findet ein fremder Blueprint unsere Felder:
     * `import: qr-gen::qr_code`. Ohne die Angabe nähme Statamic den Slug des
     * Addons, was heute dasselbe wäre; ausgeschrieben steht es fest, auch wenn
     * das Paket einmal anders heißt.
     */
    protected $fieldsetNamespace = 'qr-gen';

    /** Ebenso für Views: `qr-gen::cp.settings`. */
    protected $viewNamespace = 'qr-gen';

    /**
     * `config/qr-gen.php` wird unter dem Schlüssel `qr-gen` eingehängt und ist
     * per `php artisan vendor:publish` überschreibbar.
     */
    protected $config = true;

    /** Die DE- und EN-Kataloge aus `resources/lang`. */
    protected $translations = true;

    /** `{{ qr_gen url="…" }}` gibt die Panels aus. */
    protected $tags = [
        QrGen::class,
    ];

    /**
     * Die Bild-Route landet unter `/!/qr-gen/image`. Action-Routen bekommen den
     * Slug des Addons als Prefix, deshalb steht im Routen-File nur `image`.
     */
    protected $routes = [
        'actions' => __DIR__ . '/../../routes/actions.php',
        'cp' => __DIR__ . '/../../routes/cp.php',
    ];

    public function bootAddon(): void
    {
        $this->bootPermission();
        $this->bootNav();
    }

    /**
     * Ohne eigene Berechtigung dürfte jeder, der ins Control Panel kommt, die
     * Einstellungen ändern. Eine abgeschaltete Variante nimmt einer ganzen
     * Seite ihre Codes, und das soll niemand im Vorbeigehen können.
     *
     * Über `extend` und nicht direkt: die Rückrufe laufen erst, wenn Statamic
     * die Berechtigungen einsammelt. Eine direkte Registrierung beim Booten
     * käme je nach Reihenfolge zu früh.
     */
    private function bootPermission(): void
    {
        Permission::extend(function () {
            Permission::group('qr-gen', __('qr-gen::texts.cp.title'), function () {
                Permission::register(SettingsController::PERMISSION)
                    ->label(__('qr-gen::texts.cp.permission'));
            });
        });
    }

    /**
     * Der Eintrag steht unter „Werkzeuge", neben Formularen und Hilfsmitteln,
     * und nicht zwischen den Inhalten: er konfiguriert die Erweiterung, er
     * pflegt nichts.
     */
    private function bootNav(): void
    {
        Nav::extend(function ($nav) {
            $nav->tools(__('qr-gen::texts.cp.nav'))
                ->route('qr-gen.settings')
                ->icon('grid')
                ->can(SettingsController::PERMISSION);
        });
    }
}

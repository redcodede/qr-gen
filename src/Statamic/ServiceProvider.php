<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic;

use Redcodede\QrGen\Statamic\Tags\QrGen;
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
    ];
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Statamic;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Redcodede\QrGen\Statamic\ServiceProvider;
use Statamic\Extend\Manifest;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;

/**
 * Grundlage für die Tests der Statamic-Hülle.
 *
 * Statamic 3.4 bringt keine Testhilfe für Erweiterungen mit; `src/Testing` gibt
 * es dort noch nicht, das kam erst mit Statamic 4. Also wird die Anwendung mit
 * `orchestra/testbench` hochgefahren, das Statamic selbst in `require-dev`
 * führt, und drei Dinge werden von Hand nachgereicht.
 *
 * **Das Manifest.** Statamic findet Erweiterungen sonst über
 * `vendor/composer/installed.json`. Dort steht dieses Paket nicht, weil es das
 * Paket selbst ist. Der Eintrag wird deshalb direkt gesetzt, mit denselben
 * Feldern, die `Manifest::formatPackage()` sonst zusammenbaut.
 *
 * **Die Konfiguration.** Testbench kennt nur Laravels Standardwerte. Statamic
 * erwartet seine eigenen unter `statamic.*`, sonst scheitert schon das Booten.
 *
 * **Ein Dateiwurzelverzeichnis.** Statamics Stache und die Asset-Container
 * wollen Pfade, die es gibt. Sie zeigen auf ein temporäres Verzeichnis, das
 * nach jedem Test wieder verschwindet, damit kein Testlauf den nächsten sieht.
 */
abstract class TestCase extends OrchestraTestCase
{
    /** @var string */
    protected $tempDirectory;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/qr-gen-test-' . bin2hex(random_bytes(6));
        mkdir($this->tempDirectory, 0777, true);

        parent::setUp();

        // Laravel setzt beim Booten error_reporting(-1), siehe
        // Illuminate\Foundation\Bootstrap\HandleExceptions. Damit ist die
        // Einstellung des Containers wieder weg, und Laravel 8 auf PHP 8.4
        // meldet aus jeder zweiten Klasse eine Deprecation. Die Ausgabe macht
        // unter beStrictAboutOutputDuringTests jeden Test "risky" und verdeckt
        // alles, was man lesen wollte.
        //
        // Der Zielserver steht auf demselben Wert (error_reporting 22527,
        // geprueft am 14.09.2026). Hier wird also nichts stillgelegt, was dort
        // meldet. Eigene Deprecations sieht man mit `composer test:deprecations`.
        error_reporting(E_ALL & ~E_DEPRECATED);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->tempDirectory)) {
            self::removeDirectory($this->tempDirectory);
        }
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            StatamicServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Statamic' => Statamic::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $directory = __DIR__ . '/../../vendor/statamic/cms/config';

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $app['config']->set('statamic.' . basename($file, '.php'), require $file);
        }
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Ohne Schluessel scheitert alles, was signiert oder verschluesselt.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $app['config']->set('statamic.stache.stores.entries.directory', $this->tempDirectory . '/content/collections');
        $app['config']->set('statamic.stache.stores.taxonomies.directory', $this->tempDirectory . '/content/taxonomies');
        $app['config']->set('statamic.stache.stores.globals.directory', $this->tempDirectory . '/content/globals');
        $app['config']->set('statamic.stache.stores.users.directory', $this->tempDirectory . '/users');

        $app->make(Manifest::class)->manifest = [
            'redcodede/qr-gen' => [
                'id' => 'redcodede/qr-gen',
                'slug' => 'qr-gen',
                // Was ein Quell-Einbau meldet. Eine feste Zahl waere eine
                // Angabe, die bei jeder Freigabe veraltet, ohne dass ein Test
                // darauf anspringt.
                'version' => 'dev-main',
                'namespace' => 'Redcodede\\QrGen\\Statamic',
                'autoload' => 'src/Statamic',
                'provider' => ServiceProvider::class,
            ],
        ];
    }

    private static function removeDirectory(string $path): void
    {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;

            is_dir($full) ? self::removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }
}

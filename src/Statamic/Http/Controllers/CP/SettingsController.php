<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Http\Controllers\CP;

use Illuminate\Http\Request;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Statamic\Settings\SettingsBlueprint;
use Redcodede\QrGen\Statamic\Settings\SettingsStore;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Die Einstellungsseite im Control Panel.
 *
 * Ohne eigenes JavaScript: die Blade-View rendert Statamics eigene
 * `publish-form`-Komponente, die das Speichern, die Fehlerbehandlung und
 * Strg+S schon kann. Das ist dieselbe Bauweise, die Statamic für seine Globals
 * benutzt, und sie kostet keinen Build im Paket.
 */
class SettingsController extends CpController
{
    /** Ohne eigene Berechtigung dürfte jeder Redakteur daran drehen. */
    public const PERMISSION = 'configure qr-gen';

    public function edit(Request $request)
    {
        $this->authorize(self::PERMISSION);

        $blueprint = SettingsBlueprint::make();

        $fields = $blueprint->fields()
            ->addValues(SettingsStore::toForm(SettingsStore::global()))
            ->preProcess();

        return view('qr-gen::cp.settings', [
            'title' => __('qr-gen::texts.cp.title'),
            'intro' => __('qr-gen::texts.cp.intro'),
            'action' => cp_route('qr-gen.settings.update'),
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta()->all(),
            'fixed' => self::fixed(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize(self::PERMISSION);

        $fields = SettingsBlueprint::make()->fields()->addValues($request->all());

        $fields->validate();

        SettingsStore::save($fields->process()->values()->all());

        return ['message' => __('qr-gen::texts.cp.saved')];
    }

    /**
     * Die Werte, die nicht eingestellt werden. Sie stehen auf der Seite, damit
     * niemand sie sucht, und sie stehen dort als Text, nicht als
     * schreibgeschütztes Feld: ein Feld sieht aus, als ginge es doch.
     *
     * @return list<array{label: string, value: string, hint: string|null}>
     */
    private static function fixed(): array
    {
        return [
            [
                'label' => __('qr-gen::texts.cp.fixed.box'),
                'value' => __('qr-gen::texts.cp.fixed.box.value', [
                    'box' => Preset::LOGO_BOX_MODULES,
                    'margin' => Preset::LOGO_MARGIN_MODULES,
                ]),
                'hint' => null,
            ],
            [
                'label' => __('qr-gen::texts.cp.fixed.quietZone'),
                'value' => __('qr-gen::texts.cp.fixed.modules', ['count' => Preset::QUIET_ZONE]),
                'hint' => null,
            ],
            [
                'label' => __('qr-gen::texts.cp.fixed.moduleSize'),
                'value' => Preset::MODULE_SIZE . ' px',
                'hint' => null,
            ],
            [
                'label' => __('qr-gen::texts.cp.fixed.print'),
                'value' => __('qr-gen::texts.cp.fixed.print.value', [
                    'size' => rtrim(rtrim(number_format(Preset::PRINT_SIZE_MM, 1, ',', ''), '0'), ','),
                    'dpi' => Preset::PRINT_DPI,
                ]),
                'hint' => null,
            ],
            [
                'label' => __('qr-gen::texts.cp.fixed.level'),
                'value' => __('qr-gen::texts.cp.fixed.level.value'),
                'hint' => null,
            ],
            [
                'label' => __('qr-gen::texts.cp.fixed.container'),
                'value' => (string) config('qr-gen.container', 'assets'),
                'hint' => __('qr-gen::texts.cp.fixed.container.hint'),
            ],
        ];
    }
}

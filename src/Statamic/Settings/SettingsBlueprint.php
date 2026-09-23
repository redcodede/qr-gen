<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Settings;

use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;

/**
 * Der Blueprint der Einstellungsseite.
 *
 * In PHP gebaut und nicht als YAML mitgeliefert, aus einem einzigen Grund:
 * Statamic gibt `display` und `instructions` unverändert zurück, ohne sie
 * durch den Übersetzer zu schicken. Ein Übersetzungsschlüssel in einer
 * Blueprint-YAML stünde also wörtlich im Formular. Hier kommen die Texte aus
 * `resources/lang`, und damit steht jeder Text der Erweiterung an einer Stelle.
 *
 * Der Blueprint gehört dem Paket und nicht der Seite. Er beschreibt, was die
 * Erweiterung kann, und das ändert sich mit dem Paket, nicht mit der
 * Installation.
 *
 * Was hier **nicht** steht, sind die Druckwerte. Logokasten, Ruhezone,
 * Modulgröße, Auflösung und Druckgröße liegen in `Qr\Preset` und sind
 * entschieden, nicht eingestellt: ein freigegebener Andruck gilt für genau
 * diese Werte. Die Seite zeigt sie schreibgeschützt an, damit niemand sie
 * sucht, aber sie sind kein Formularfeld.
 */
final class SettingsBlueprint
{
    /**
     * Zeichen, die auf ein Etikett passen, vereinbart am 23.09.2026.
     *
     * Eine Zusicherung ist es nicht: was wirklich passt, hängt von den Zeichen
     * ab, und `Qr\Layout\LabelText` misst das. Das Limit hier steht im
     * Formular, damit niemand erst beim Speichern erfährt, dass es eins gibt.
     */
    public const LABEL_TEXT_LIMIT = 72;

    private function __construct()
    {
    }

    public static function make(): \Statamic\Fields\Blueprint
    {
        return Blueprint::makeFromSections(self::sections());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function sections(): array
    {
        return array_merge(self::fixedSections(), self::textSections());
    }

    /**
     * Ein Abschnitt je Sprachfassung, mit Überschrift und Einleitung der
     * Seite. Welche Fassungen es gibt, weiß die Seite und nicht das Paket,
     * deshalb kommen sie aus Statamic statt aus einer Liste hier.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function textSections(): array
    {
        $mehrere = Site::hasMultiple();
        $abschnitte = [];

        foreach (Site::all() as $site) {
            $handle = $site->handle();

            $abschnitte['texts_' . $handle] = [
                'display' => $mehrere
                    ? __('qr-gen::texts.cp.section.texts.site', ['site' => $site->name()])
                    : __('qr-gen::texts.cp.section.texts'),
                'fields' => [
                    SettingsStore::textField($handle, 'title') => [
                        'type' => 'text',
                        'input_type' => 'text',
                        'display' => __('qr-gen::texts.cp.texts.title'),
                        'instructions' => __('qr-gen::texts.cp.texts.title.hint'),
                        'instructions_position' => 'above',
                        'width' => 50,
                    ],
                    SettingsStore::textField($handle, 'lead') => [
                        'type' => 'text',
                        'input_type' => 'text',
                        'display' => __('qr-gen::texts.cp.texts.lead'),
                        'instructions' => __('qr-gen::texts.cp.texts.lead.hint'),
                        'instructions_position' => 'above',
                        'width' => 50,
                    ],
                ],
            ];
        }

        return $abschnitte;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fixedSections(): array
    {
        return [
            'variants' => [
                'display' => __('qr-gen::texts.cp.section.variants'),
                'fields' => [
                    'variant_plain' => self::toggle('cp.variants.plain'),
                    'variant_logo' => self::toggle('cp.variants.logo'),
                    'variant_label' => self::toggle('cp.variants.label'),
                    'variant_label_color' => self::toggle('cp.variants.labelColor'),
                    'download_svg' => self::toggle('cp.downloads.svg'),
                    'download_png' => self::toggle('cp.downloads.png'),
                ],
            ],
            'label' => [
                'display' => __('qr-gen::texts.cp.section.label'),
                'instructions' => __('qr-gen::texts.cp.section.label.hint'),
                'fields' => [
                    'label_text' => [
                        'type' => 'text',
                        'input_type' => 'text',
                        'display' => __('qr-gen::texts.cp.label.text'),
                        'instructions' => __('qr-gen::texts.cp.label.text.hint', [
                            'max' => self::LABEL_TEXT_LIMIT,
                        ]),
                        'instructions_position' => 'above',
                        'character_limit' => self::LABEL_TEXT_LIMIT,
                        'width' => 50,
                    ],
                    'label_color' => [
                        'type' => 'color',
                        'display' => __('qr-gen::texts.cp.label.color'),
                        'instructions' => __('qr-gen::texts.cp.label.color.hint'),
                        'instructions_position' => 'above',
                        'lock_opacity' => true,
                        'width' => 50,
                    ],
                ],
            ],
            'defaults' => [
                'display' => __('qr-gen::texts.cp.section.defaults'),
                'fields' => [
                    'default_logo' => [
                        'type' => 'assets',
                        'display' => __('qr-gen::texts.cp.defaultLogo'),
                        'instructions' => __('qr-gen::texts.cp.defaultLogo.hint'),
                        'instructions_position' => 'above',
                        'container' => (string) config('qr-gen.container', 'assets'),
                        'max_files' => 1,
                        'mode' => 'grid',
                        'allow_uploads' => true,
                        'show_filename' => true,
                        'width' => 50,
                    ],
                    'default_url' => [
                        'type' => 'text',
                        'input_type' => 'text',
                        'display' => __('qr-gen::texts.cp.defaultUrl'),
                        'instructions' => __('qr-gen::texts.cp.defaultUrl.hint'),
                        'instructions_position' => 'above',
                        'width' => 50,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function toggle(string $key): array
    {
        return [
            'type' => 'toggle',
            'display' => __('qr-gen::texts.' . $key),
            'instructions' => __('qr-gen::texts.' . $key . '.hint'),
            'instructions_position' => 'above',
            'width' => 50,
        ];
    }
}

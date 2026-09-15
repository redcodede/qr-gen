<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Settings;

use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Statamic\Facades\YAML;

/**
 * Die globalen Einstellungen, wie sie auf der Platte liegen.
 *
 * Eine YAML, die die Erweiterung besitzt, und `config/qr-gen.php` als
 * Rückfall darunter. Die Reihenfolge ist der Punkt: die Datei im Paket sagt,
 * was gilt, solange niemand etwas eingestellt hat, und sie ist damit auch der
 * Stand, mit dem eine frische Installation läuft. Was im Control Panel
 * gespeichert wird, liegt darüber.
 *
 * **Kein Global Set.** Ein Global Set stünde in der Redakteursnavigation
 * zwischen den Inhalten und wäre versehentlich änderbar. Eine im Vorbeigehen
 * verstellte Einstellung nimmt einer ganzen Seite ihre Codes, ohne dass
 * irgendwo stünde warum.
 *
 * Die Form der YAML ist absichtlich dieselbe wie die der Konfigurationsdatei:
 * `GlobalSettings::fromArray()` liest beide, und wer die eine versteht,
 * versteht die andere. Die flachen Feldnamen des Formulars werden hier
 * umgesetzt und nirgends sonst.
 */
final class SettingsStore
{
    /**
     * Wo die Datei liegt.
     *
     * Unter `content/`, weil sie versioniert und mitgesichert gehört: sie ist
     * Konfiguration, nicht Zwischenstand. `storage/` wäre beides nicht.
     */
    public static function path(): string
    {
        $configured = config('qr-gen.settings_path');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return base_path('content/qr-gen/settings.yaml');
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    /**
     * Die gespeicherten Werte allein, ohne die Rückfallwerte darunter.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        if (!self::exists()) {
            return [];
        }

        $parsed = YAML::parse((string) file_get_contents(self::path()));

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Was am Ende gilt.
     *
     * `array_replace_recursive` und nicht `array_merge`: die Schalter liegen
     * eine Ebene tief, und ein `array_merge` würde `variants` als Ganzes
     * ersetzen statt Schalter für Schalter.
     */
    public static function global(): GlobalSettings
    {
        return GlobalSettings::fromArray(array_replace_recursive(
            (array) config('qr-gen', []),
            self::stored()
        ));
    }

    /**
     * @param array<string, mixed> $values Die flachen Werte aus dem Formular
     */
    public static function save(array $values): void
    {
        $verzeichnis = dirname(self::path());

        if (!is_dir($verzeichnis)) {
            mkdir($verzeichnis, 0755, true);
        }

        file_put_contents(self::path(), YAML::dump(self::fromForm($values)));
    }

    /**
     * Formular → Speicherform.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function fromForm(array $values): array
    {
        return [
            'variants' => [
                'plain' => (bool) ($values['variant_plain'] ?? false),
                'logo' => (bool) ($values['variant_logo'] ?? false),
            ],
            'downloads' => [
                'svg' => (bool) ($values['download_svg'] ?? false),
                'png' => (bool) ($values['download_png'] ?? false),
            ],
            'logo' => self::firstAsset($values['default_logo'] ?? null),
            'url' => self::trimmedOrNull($values['default_url'] ?? null),
        ];
    }

    /**
     * Speicherform → Formular.
     *
     * @return array<string, mixed>
     */
    public static function toForm(GlobalSettings $settings): array
    {
        return [
            'variant_plain' => $settings->offersPlain(),
            'variant_logo' => $settings->offersLogo(),
            'download_svg' => $settings->offersSvg(),
            'download_png' => $settings->offersPng(),
            'default_logo' => $settings->defaultLogo(),
            'default_url' => $settings->defaultUrl(),
        ];
    }

    /**
     * Ein Asset-Feld mit `max_files: 1` liefert je nach Herkunft einen String
     * oder ein Array mit einem Eintrag.
     *
     * @param mixed $value
     */
    private static function firstAsset($value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) ? self::trimmedOrNull($value) : null;
    }

    /**
     * @param mixed $value
     */
    private static function trimmedOrNull($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

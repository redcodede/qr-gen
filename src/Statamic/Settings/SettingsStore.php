<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Settings;

use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Statamic\Facades\Site;
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
     * Die Schlüssel der Texte, die eine Seite selbst besitzt.
     *
     * Titel und Einleitung stehen auf der Seite und gehören ihr, nicht dem
     * Paket: eine andere Installation will dort etwas anderes lesen. Die
     * Beschriftung der beiden Codes bleibt dagegen im Katalog, weil sie
     * benennt, was dieses Paket erzeugt, und sich mit ihm ändert.
     *
     * @var list<string>
     */
    public const TEXTS = ['title', 'lead'];

    /**
     * Ein Seitentext für eine Sprachfassung.
     *
     * Erst das, was im Control Panel steht. Ist dort nichts hinterlegt, der
     * mitgelieferte Text in der Sprache dieser Fassung — und wenn das Paket
     * die Sprache nicht kennt, der deutsche. Ein leeres Feld heißt „nimm den
     * mitgelieferten" und nicht „zeig nichts".
     *
     * @param string|null $site Handle der Sprachfassung, `null` für die aktuelle
     */
    public static function text(string $key, ?string $site = null): string
    {
        $site = $site ?? Site::current()->handle();

        $gespeichert = self::stored()['texts'][$site][$key] ?? null;

        if (is_string($gespeichert) && trim($gespeichert) !== '') {
            return trim($gespeichert);
        }

        $sprache = optional(Site::get($site))->shortLocale() ?? 'de';
        $schluessel = 'qr-gen::texts.page.' . $key;

        $text = __($schluessel, [], $sprache);

        // Kennt das Paket die Sprache nicht, gibt Laravel den Schlüssel selbst
        // zurück, und zwar ohne Fehler. Dann lieber Deutsch als der Schlüssel.
        return is_string($text) && $text !== $schluessel ? $text : (string) __($schluessel, [], 'de');
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
            'texts' => self::textsFromForm($values),
        ];
    }

    /**
     * Die Texte liegen je Sprachfassung, im Formular flach als
     * `text_{fassung}_{schluessel}`. Eine Fassung ohne einen einzigen Text
     * taucht in der Datei nicht auf: ein Block aus lauter `null` sagt nichts
     * und wäre nur eine Zeile mehr, die jemand lesen muss.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, array<string, string>>
     */
    private static function textsFromForm(array $values): array
    {
        $texte = [];

        foreach (self::sites() as $handle) {
            foreach (self::TEXTS as $key) {
                $wert = self::trimmedOrNull($values[self::textField($handle, $key)] ?? null);

                if ($wert !== null) {
                    $texte[$handle][$key] = $wert;
                }
            }
        }

        return $texte;
    }

    /** Der Feldname im Formular. An einer Stelle, weil ihn zwei Seiten kennen. */
    public static function textField(string $site, string $key): string
    {
        return 'text_' . $site . '_' . $key;
    }

    /**
     * @return list<string>
     */
    public static function sites(): array
    {
        return Site::all()->map->handle()->values()->all();
    }

    /**
     * Speicherform → Formular.
     *
     * @return array<string, mixed>
     */
    public static function toForm(GlobalSettings $settings): array
    {
        $werte = [
            'variant_plain' => $settings->offersPlain(),
            'variant_logo' => $settings->offersLogo(),
            'download_svg' => $settings->offersSvg(),
            'download_png' => $settings->offersPng(),
            'default_logo' => $settings->defaultLogo(),
            'default_url' => $settings->defaultUrl(),
        ];

        // Nur was tatsächlich gespeichert ist. Der mitgelieferte Text gehört
        // nicht ins Formular: er stünde dort wie ein eigener, und wer ihn
        // einmal speichert, hat ihn von da an als eigenen und bekommt eine
        // spätere Verbesserung des Pakets nicht mehr mit.
        $gespeichert = self::stored()['texts'] ?? [];

        foreach (self::sites() as $handle) {
            foreach (self::TEXTS as $key) {
                $werte[self::textField($handle, $key)] = $gespeichert[$handle][$key] ?? null;
            }
        }

        return $werte;
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

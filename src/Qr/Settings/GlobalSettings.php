<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Was die Erweiterung überhaupt anbietet, und was gilt, wenn nichts anderes
 * dasteht.
 *
 * Die obere der beiden Ebenen. Im Control Panel gepflegt und in einer YAML
 * abgelegt (siehe `Statamic\Settings\SettingsStore`); die Demo-Seite füllt
 * dasselbe Objekt aus der Adresszeile. Beide bauen es, deshalb steht es hier
 * im Kern und nicht in der Statamic-Hülle: es kennt kein Framework und lässt
 * sich ohne eines prüfen.
 *
 * **Ein hier abgeschalteter Typ steht einer Seite nicht zur Verfügung.** Das
 * ist der Sinn der oberen Ebene. Ohne diese Regel liesse sich eine Seite auf
 * etwas einstellen, das nie erscheint, und niemand sähe warum.
 *
 * Nicht hier stehen die Druckwerte. Druckgröße, Auflösung, Logokasten und
 * Ruhezone liegen in {@see \Redcodede\QrGen\Qr\Preset} und sind entschieden,
 * nicht eingestellt: ein freigegebener Andruck gilt für genau diese Werte.
 */
final class GlobalSettings
{
    /** @var bool */
    private $plain = true;

    /** @var bool */
    private $logo = true;

    /** @var bool */
    private $svg = true;

    /** @var bool */
    private $png = true;

    /** @var bool */
    private $label = true;

    /** @var bool */
    private $labelColor = true;

    /** @var string|null */
    private $defaultLogo;

    /** @var string|null */
    private $defaultUrl;

    /** @var string|null */
    private $labelText;

    /**
     * Die Codefarbe des farbigen Etiketts.
     *
     * Ohne Wert gibt es diesen Typ nicht, genau wie es die Variante mit
     * Bildmarke ohne Bildmarke nicht gibt. Einen Standardton mitzuliefern hiesse,
     * die Farbe eines Hauses in ein allgemeines Paket zu schreiben, und ein
     * zweites Etikett in derselben Farbe wie das erste waere ohnehin keins.
     *
     * @var string|null
     */
    private $codeColor;

    private function __construct()
    {
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * Aus der Form, in der die Werte gespeichert werden.
     *
     * Fehlende Schlüssel behalten den Standard, statt als „aus" zu gelten. Eine
     * halb geschriebene Konfigurationsdatei soll die Erweiterung nicht
     * stilllegen.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $settings = new self();

        $settings->plain = self::boolean($values, 'variants.plain', $settings->plain);
        $settings->logo = self::boolean($values, 'variants.logo', $settings->logo);
        $settings->label = self::boolean($values, 'variants.label', $settings->label);
        $settings->labelColor = self::boolean($values, 'variants.label_color', $settings->labelColor);
        $settings->svg = self::boolean($values, 'downloads.svg', $settings->svg);
        $settings->png = self::boolean($values, 'downloads.png', $settings->png);
        $settings->defaultLogo = self::text($values, 'logo');
        $settings->defaultUrl = self::text($values, 'url');
        $settings->labelText = self::text($values, 'label_text');
        $settings->codeColor = self::text($values, 'code_color');

        return $settings;
    }

    /**
     * @return array<string, mixed> Dieselbe Form, die fromArray() liest
     */
    public function toArray(): array
    {
        return [
            'variants' => [
                'plain' => $this->plain,
                'logo' => $this->logo,
                'label' => $this->label,
                'label_color' => $this->labelColor,
            ],
            'downloads' => ['svg' => $this->svg, 'png' => $this->png],
            'logo' => $this->defaultLogo,
            'url' => $this->defaultUrl,
            'label_text' => $this->labelText,
            'code_color' => $this->codeColor,
        ];
    }

    public function withVariants(bool $plain, bool $logo): self
    {
        $clone = clone $this;
        $clone->plain = $plain;
        $clone->logo = $logo;

        return $clone;
    }

    public function withDownloads(bool $svg, bool $png): self
    {
        $clone = clone $this;
        $clone->svg = $svg;
        $clone->png = $png;

        return $clone;
    }

    public function withDefaultLogo(?string $logo): self
    {
        $clone = clone $this;
        $clone->defaultLogo = self::trimmedOrNull($logo);

        return $clone;
    }

    public function withDefaultUrl(?string $url): self
    {
        $clone = clone $this;
        $clone->defaultUrl = self::trimmedOrNull($url);

        return $clone;
    }

    public function withLabels(bool $label, bool $labelColor): self
    {
        $clone = clone $this;
        $clone->label = $label;
        $clone->labelColor = $labelColor;

        return $clone;
    }

    public function withLabelText(?string $text): self
    {
        $clone = clone $this;
        $clone->labelText = self::trimmedOrNull($text);

        return $clone;
    }

    public function withCodeColor(?string $color): self
    {
        $clone = clone $this;
        $clone->codeColor = self::trimmedOrNull($color);

        return $clone;
    }

    public function offersPlain(): bool
    {
        return $this->plain;
    }

    public function offersLogo(): bool
    {
        return $this->logo;
    }

    public function offersLabel(): bool
    {
        return $this->label;
    }

    public function offersLabelColor(): bool
    {
        return $this->labelColor;
    }

    public function labelText(): ?string
    {
        return $this->labelText;
    }

    public function codeColor(): ?string
    {
        return $this->codeColor;
    }

    public function offersSvg(): bool
    {
        return $this->svg;
    }

    public function offersPng(): bool
    {
        return $this->png;
    }

    public function defaultLogo(): ?string
    {
        return $this->defaultLogo;
    }

    public function defaultUrl(): ?string
    {
        return $this->defaultUrl;
    }

    /**
     * Ohne eine Variante gibt es nichts zu zeigen, ohne ein Format nichts
     * mitzunehmen. Beides ist eine gültige, aber erklärungsbedürftige
     * Einstellung, und die Oberfläche soll es benennen statt eine leere Seite
     * zu zeigen.
     */
    public function offersAnyVariant(): bool
    {
        return $this->plain || $this->logo || $this->label || $this->labelColor;
    }

    public function offersAnyDownload(): bool
    {
        return $this->svg || $this->png;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function boolean(array $values, string $path, bool $fallback): bool
    {
        $found = $values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($found) || !array_key_exists($segment, $found)) {
                return $fallback;
            }

            $found = $found[$segment];
        }

        return (bool) $found;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function text(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? self::trimmedOrNull($value) : null;
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

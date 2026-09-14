<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Was eine einzelne Seite will.
 *
 * Die untere der beiden Ebenen. Später kommt sie aus den Feldern, die der
 * Blueprint der Seite pflegt; heute füllt die Demo-Seite sie aus der
 * Adresszeile.
 *
 * **Null heisst „nicht gesetzt", nicht „leer".** Der Unterschied trägt die
 * ganze Vorrangregel: ein leeres Feld im Blueprint bedeutet, dass der globale
 * Wert gilt, und nicht, dass es keinen geben soll. Deshalb ist jedes Feld hier
 * nullbar, und deshalb wird eine leere Eingabe zu null und nicht zu einer
 * leeren Zeichenkette.
 */
final class PageSettings
{
    /** @var string|null */
    private $url;

    /** @var string|null */
    private $logo;

    /**
     * Die gewünschten Varianten, oder null für „nicht entschieden".
     *
     * Nicht entschieden heisst: alles nehmen, was global angeboten wird. Das
     * ist der Zustand einer frisch angelegten Seite, und er soll etwas zeigen
     * statt nichts.
     *
     * @var list<string>|null
     */
    private $variants;

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $page = new self();

        $page->url = self::text($values['url'] ?? null);
        $page->logo = self::text($values['logo'] ?? null);

        $variants = $values['variants'] ?? null;

        if (is_array($variants)) {
            $page->variants = array_values(array_filter(
                array_map('strval', $variants),
                static function (string $variant): bool {
                    return $variant === Variant::PLAIN || $variant === Variant::LOGO;
                }
            ));
        }

        return $page;
    }

    public function withUrl(?string $url): self
    {
        $clone = clone $this;
        $clone->url = self::text($url);

        return $clone;
    }

    public function withLogo(?string $logo): self
    {
        $clone = clone $this;
        $clone->logo = self::text($logo);

        return $clone;
    }

    /**
     * @param list<string>|null $variants
     */
    public function withVariants(?array $variants): self
    {
        $clone = clone $this;
        $clone->variants = $variants;

        return $clone;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function logo(): ?string
    {
        return $this->logo;
    }

    /**
     * @return list<string>|null
     */
    public function variants(): ?array
    {
        return $this->variants;
    }

    public function wants(string $variant): bool
    {
        return $this->variants === null || in_array($variant, $this->variants, true);
    }

    private static function text($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

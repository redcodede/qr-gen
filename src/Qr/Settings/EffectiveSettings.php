<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Was nach der Verrechnung beider Ebenen tatsächlich herauskommt.
 *
 * Die Regel in einem Satz: **global steht, was überhaupt angeboten wird und was
 * gilt, wenn nichts anderes dasteht; pro Seite steht, was diese eine Seite
 * ausmacht, und im Zweifel gewinnt die Seite.**
 *
 * Das Ergebnis merkt sich zusätzlich, **woher** ein Wert stammt. Das ist keine
 * Bequemlichkeit, sondern der einzige Weg, die Vorrangregel sichtbar zu machen:
 * wer eine Seite vor sich hat, auf der eine unerwartete URL steht, muss ohne
 * Suchen erkennen können, ob sie aus dem Blueprint oder aus den globalen
 * Einstellungen kommt.
 *
 * Die Formate sind bewusst nur global. Eine Seite entscheidet, was sie zeigt,
 * nicht, in welchen Dateiformaten das Haus liefert.
 */
final class EffectiveSettings
{
    /** Der Wert stammt aus den globalen Einstellungen. */
    public const FROM_GLOBAL = 'global';

    /** Der Wert stammt aus der Seite und hat den globalen überschrieben. */
    public const FROM_PAGE = 'page';

    /** Es gibt keinen Wert, weder hier noch dort. */
    public const FROM_NOWHERE = 'nowhere';

    /** @var string|null */
    private $url;

    /** @var string */
    private $urlSource;

    /** @var string|null */
    private $logo;

    /** @var string */
    private $logoSource;

    /** @var bool */
    private $plain;

    /** @var bool */
    private $logoVariant;

    /** @var bool */
    private $svg;

    /** @var bool */
    private $png;

    private function __construct()
    {
    }

    public static function from(GlobalSettings $global, PageSettings $page): self
    {
        $effective = new self();

        [$effective->url, $effective->urlSource] = self::pick($page->url(), $global->defaultUrl());
        [$effective->logo, $effective->logoSource] = self::pick($page->logo(), $global->defaultLogo());

        // Eine Variante erscheint nur, wenn sie global angeboten wird und die
        // Seite sie will. Die Reihenfolge der beiden Bedingungen ist gleich,
        // das Und ist der Punkt: global kann verbieten, die Seite nur waehlen.
        $effective->plain = $global->offersPlain() && $page->wants(Variant::PLAIN);
        $effective->logoVariant = $global->offersLogo() && $page->wants(Variant::LOGO);

        // Ohne Bildmarke gibt es die Variante mit Bildmarke nicht. Das ist kein
        // Fehler, sondern eine unvollstaendige Konfiguration, und die
        // Oberflaeche soll es so benennen.
        if ($effective->logo === null) {
            $effective->logoVariant = false;
        }

        $effective->svg = $global->offersSvg();
        $effective->png = $global->offersPng();

        return $effective;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function urlSource(): string
    {
        return $this->urlSource;
    }

    public function logo(): ?string
    {
        return $this->logo;
    }

    public function logoSource(): string
    {
        return $this->logoSource;
    }

    public function showsPlain(): bool
    {
        return $this->plain;
    }

    public function showsLogo(): bool
    {
        return $this->logoVariant;
    }

    public function showsAnything(): bool
    {
        return $this->plain || $this->logoVariant;
    }

    public function offersSvg(): bool
    {
        return $this->svg;
    }

    public function offersPng(): bool
    {
        return $this->png;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function pick(?string $fromPage, ?string $fromGlobal): array
    {
        if ($fromPage !== null) {
            return [$fromPage, self::FROM_PAGE];
        }

        if ($fromGlobal !== null) {
            return [$fromGlobal, self::FROM_GLOBAL];
        }

        return [null, self::FROM_NOWHERE];
    }
}

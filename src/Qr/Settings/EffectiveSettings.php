<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Settings;

/**
 * Was an einer Stelle tatsächlich gilt.
 *
 * **Es gibt nur noch eine Ebene, die eingestellt wird: die globale.** Was die
 * Erweiterung anbietet, welche Bildmarke sie benutzt und wie das Etikett
 * aussieht, steht im Control Panel und gilt überall gleich. Das Einzige, was
 * von Stelle zu Stelle verschieden sein **muss**, ist die Adresse im Code, denn
 * die ist der ganze Zweck: jeder Hersteller hat seine eigene.
 *
 * Bis zum 23.09.2026 gab es daneben eine zweite Ebene je Seite, die globale
 * Werte überschreiben durfte. Sie ist zurückgebaut. Ihre Begründung lautete,
 * eine Seite müsse sagen können, was sie ausmacht; tatsächlich macht eine Seite
 * nichts aus außer ihrer Adresse. Zwei Ebenen kosteten dafür ein Vorrangmodell,
 * ein Feld je Einstellung im Blueprint und die wiederkehrende Frage, warum eine
 * Einstellung an einer Stelle nicht wirkt.
 *
 * Geblieben ist die Auskunft, **woher** die Adresse stammt. Wer eine
 * unerwartete URL vor sich hat, muss ohne Suchen erkennen können, ob sie an
 * dieser Stelle eingetragen wurde oder aus den globalen Einstellungen kommt.
 */
final class EffectiveSettings
{
    /** Der Wert stammt aus den globalen Einstellungen. */
    public const FROM_GLOBAL = 'global';

    /** Der Wert wurde an dieser Stelle eingetragen. */
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
    private $label;

    /** @var bool */
    private $labelColor;

    /** @var bool */
    private $returnInfo;

    /** @var string|null */
    private $labelText;

    /** @var string|null */
    private $codeColor;

    /** @var bool */
    private $svg;

    /** @var bool */
    private $png;

    private function __construct()
    {
    }

    /**
     * @param string|null $pageUrl Die Adresse dieser einen Stelle, oder null
     */
    public static function from(GlobalSettings $global, ?string $pageUrl = null): self
    {
        $effective = new self();

        $pageUrl = self::trimmedOrNull($pageUrl);

        if ($pageUrl !== null) {
            $effective->url = $pageUrl;
            $effective->urlSource = self::FROM_PAGE;
        } elseif ($global->defaultUrl() !== null) {
            $effective->url = $global->defaultUrl();
            $effective->urlSource = self::FROM_GLOBAL;
        } else {
            $effective->url = null;
            $effective->urlSource = self::FROM_NOWHERE;
        }

        $effective->logo = $global->defaultLogo();
        $effective->logoSource = $effective->logo === null ? self::FROM_NOWHERE : self::FROM_GLOBAL;

        $effective->plain = $global->offersPlain();

        // Ohne Bildmarke gibt es die Variante mit Bildmarke nicht. Das ist kein
        // Fehler, sondern eine unvollstaendige Konfiguration, und die
        // Oberflaeche soll es so benennen.
        $effective->logoVariant = $global->offersLogo() && $effective->logo !== null;

        $effective->labelText = $global->labelText();
        $effective->codeColor = $global->codeColor();

        $effective->label = $global->offersLabel();

        // Dieselbe Regel wie bei der Bildmarke: ohne Farbe gibt es das farbige
        // Etikett nicht. Ein Standardton im Paket waere die Farbe eines Hauses
        // in einem allgemeinen Paket, und ein zweites Etikett in der Farbe des
        // ersten waere ohnehin keins.
        $effective->labelColor = $global->offersLabelColor() && $effective->codeColor !== null;

        // Keine Bedingung ausser dem Schalter. Das Etikett bringt alles mit,
        // was es braucht, und haengt an keinem anderen Wert.
        $effective->returnInfo = $global->offersReturnInfo();

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

    public function showsLabel(): bool
    {
        return $this->label;
    }

    public function showsLabelColor(): bool
    {
        return $this->labelColor;
    }

    public function showsReturnInfo(): bool
    {
        return $this->returnInfo;
    }

    /** Der Aufdruck des Etiketts. Leer heisst: Etikett ohne Text. */
    public function labelText(): ?string
    {
        return $this->labelText;
    }

    /** Die Codefarbe des farbigen Etiketts. */
    public function codeColor(): ?string
    {
        return $this->codeColor;
    }

    /**
     * Ob dieser Typ hier ueberhaupt gezeigt wird.
     */
    public function shows(string $variant): bool
    {
        if ($variant === Variant::PLAIN) {
            return $this->plain;
        }

        if ($variant === Variant::LOGO) {
            return $this->logoVariant;
        }

        if ($variant === Variant::LABEL) {
            return $this->label;
        }

        if ($variant === Variant::LABEL_COLOR) {
            return $this->labelColor;
        }

        return $variant === Variant::RETURN_INFO && $this->returnInfo;
    }

    public function showsAnything(): bool
    {
        return $this->plain || $this->logoVariant || $this->label || $this->labelColor || $this->returnInfo;
    }

    public function offersSvg(): bool
    {
        return $this->svg;
    }

    public function offersPng(): bool
    {
        return $this->png;
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

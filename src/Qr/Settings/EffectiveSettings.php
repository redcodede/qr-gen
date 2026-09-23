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

    private static function trimmedOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

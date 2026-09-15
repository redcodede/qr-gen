<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Tags;

use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\GlobalSettings;
use Redcodede\QrGen\Qr\Settings\PageSettings;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Artwork;
use Redcodede\QrGen\Statamic\Symbols;
use Statamic\Tags\Tags;

/**
 * `{{ qr_gen url="…" logo="…" }}` gibt die Panels aus.
 *
 * Der Tag kennt weder Hersteller noch Taxonomien. Er bekommt eine Ziel-URL und
 * optional eine Bildmarke und macht daraus, was die globalen Einstellungen
 * erlauben. Wer den Code aufloest, ist Sache der Seite, und das ist Absicht:
 * die Zuordnung Code zu Partner gehoert der GVOE-Seite, nicht diesem Paket.
 *
 * Die Vorschau steht als SVG direkt im Markup und kostet keine zweite Anfrage.
 * Ueber die Bild-Route laufen nur Download und "direkt oeffnen".
 */
class QrGen extends Tags
{
    protected static $handle = 'qr_gen';

    public function index(): string
    {
        $global = GlobalSettings::fromArray((array) config('qr-gen', []));

        $page = PageSettings::empty()
            ->withUrl($this->params->get('url'))
            ->withLogo($this->params->get('logo'));

        if ($this->params->has('variants')) {
            $page = $page->withVariants(array_values(array_filter(
                array_map('trim', explode('|', (string) $this->params->get('variants'))),
                [Variant::class, 'isKnown']
            )));
        }

        $effective = EffectiveSettings::from($global, $page);
        $url = (string) $effective->url();

        // Ohne Ziel gibt es nichts zu zeigen. Die aufrufende Seite
        // entscheidet, ob das ein Fehler ist.
        if ($url === '') {
            return '';
        }

        $logo = Artwork::load($effective->logo());

        $panels = [];

        foreach ([Variant::PLAIN, Variant::LOGO] as $variant) {
            $zeigen = $variant === Variant::PLAIN ? $effective->showsPlain() : $effective->showsLogo();

            if (!$zeigen) {
                continue;
            }

            $panels[] = Symbols::panel($url, $variant, $logo, $effective->logo(), $effective);
        }

        return (string) view('qr-gen::panels', [
            'panels' => $panels,
            'url' => $url,
            'nothing' => $panels === [],
        ])->render();
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Tags;

use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\PageSettings;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Artwork;
use Redcodede\QrGen\Statamic\Settings\SettingsStore;
use Redcodede\QrGen\Statamic\Symbols;
use Statamic\Tags\Tags;

/**
 * `{{ qr_gen url="…" logo="…" }}` gibt die Panels aus.
 *
 * Der Tag kennt weder Hersteller noch Taxonomien. Er bekommt eine Ziel-URL und
 * optional eine Bildmarke und macht daraus, was die globalen Einstellungen
 * erlauben. Wer den Code aufloest, ist Sache der Seite, und das ist Absicht:
 * die Zuordnung Code zu Hersteller gehoert der einbindenden Seite, nicht
 * diesem Paket.
 *
 * Die Vorschau steht als SVG direkt im Markup und kostet keine zweite Anfrage.
 * Ueber die Bild-Route laufen nur Download und "direkt oeffnen".
 *
 * Ueberschrift und Einleitung kommen aus den globalen Einstellungen, je
 * Sprachfassung, mit den mitgelieferten Texten als Rueckfall. Die Klassen der
 * Knoepfe und die Ebene der Ueberschrift sind Parameter: das Paket bringt kein
 * Aussehen mit, und in welcher Ueberschriftenebene der Baustein steckt, weiss
 * nur die Seite, die ihn einbaut.
 */
class QrGen extends Tags
{
    protected static $handle = 'qr_gen';

    /** Steht in der Einleitung fuer die Adresse, die im Code steckt. */
    private const URL_PLACEHOLDER = '{url}';

    /** @var list<string> */
    private const HEADING_LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5'];

    public function index(): string
    {
        $global = SettingsStore::global();

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
            'heading' => SettingsStore::text('title'),
            'heading_level' => $ebene = self::headingLevel((string) $this->params->get('heading', 'h1')),
            'panel_heading_level' => self::nextLevel($ebene),
            'lead' => self::lead($url),
            'button_class' => (string) $this->params->get('button_class', 'qr-gen-button'),
            'button_class_secondary' => (string) $this->params->get(
                'button_class_secondary',
                'qr-gen-button qr-gen-button--secondary'
            ),
        ])->render();
    }

    /**
     * Die Einleitung, mit der Adresse an der Stelle von `{url}` und dort
     * verlinkt.
     *
     * Fertiges Markup und kein Rohtext, weil Antlers nicht von sich aus
     * maskiert. Was hier herauskommt, ist an genau einer Stelle maskiert, und
     * eine Vorlage, die es ausgibt, kann nichts falsch machen.
     */
    private static function lead(string $url): string
    {
        $text = SettingsStore::text('lead');

        if (strpos($text, self::URL_PLACEHOLDER) === false) {
            return e($text);
        }

        $anker = '<a href="' . e($url) . '">' . e($url) . '</a>';

        [$vor, $nach] = explode(self::URL_PLACEHOLDER, $text, 2);

        return e($vor) . $anker . e($nach);
    }

    /**
     * In welcher Ebene die Ueberschrift steht, entscheidet die Seite. Geprueft
     * wird trotzdem: der Wert landet als Tagname im Markup.
     */
    private static function headingLevel(string $level): string
    {
        $level = strtolower(trim($level));

        return in_array($level, self::HEADING_LEVELS, true) ? $level : 'h1';
    }

    /**
     * Die Beschriftung eines Codes ist der Ueberschrift der Ausgabe
     * untergeordnet und steht deshalb eine Stufe darunter. Wie gross sie
     * aussieht, entscheidet die Seite; welche Stufe sie hat, ergibt sich aus
     * dem Aufbau und ist keine Geschmacksfrage.
     */
    private static function nextLevel(string $level): string
    {
        $stelle = array_search($level, self::HEADING_LEVELS, true);
        $letzte = count(self::HEADING_LEVELS) - 1;

        if ($stelle === false) {
            return self::HEADING_LEVELS[1];
        }

        return self::HEADING_LEVELS[min($stelle + 1, $letzte)];
    }
}

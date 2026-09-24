<?php

/**
 * Deutsche Oberflächentexte. Die Standardsprache.
 *
 * Aufbau und Platzhalterschreibweise sind absichtlich die von Laravel:
 * ein `return`-Array mit punktgetrennten Schlüsseln und `:name` als
 * Platzhalter. Damit lässt sich dieselbe Datei später von Laravels
 * Übersetzer laden, ohne sie anzufassen — die Statamic-Hülle bringt keine
 * zweite Textsammlung mit.
 *
 * Was hier **nicht** hineingehört, sind die Meldungen der Ausnahmen aus
 * `src/Qr/`. Die sind Entwicklerdiagnostik: sie landen in Logs und
 * Stacktraces, nennen Klassennamen und Modulzahlen und richten sich an
 * jemanden, der den Code liest. Wer sie einem Endnutzer zeigt, soll sie hier
 * auf einen eigenen Text abbilden, statt sie zu übersetzen.
 */

declare(strict_types=1);

return [
    'app.title' => 'qr-gen',
    'app.subtitle' => 'URL rein, zwei Codes raus — einer schlicht, einer mit Bildmarke in der '
        . 'Mitte. Als SVG für den Druck, dazu ein druckfertiges PNG für den schlichten. Es wird '
        . 'nichts auf die Platte geschrieben: jede Anfrage kodiert und rendert von neuem, und der '
        . 'Download erzeugt neu, statt eine Datei zu holen.',

    'form.logo.none' => 'keine in demo/logos',

    'group.global.heading' => 'Globale Einstellungen',
    'group.global.note' => 'Die einzige Ebene, die eingestellt wird. Im Control Panel unter '
        . 'Werkzeuge, QR-Codes. Was hier steht, gilt überall gleich.',
    'group.page.heading' => 'Diese eine Stelle',
    'group.page.note' => 'Die Adresse im Code ist das Einzige, was von Stelle zu Stelle '
        . 'verschieden sein muss. Später das Feld im Blueprint. Leer lassen heißt: die '
        . 'Default-URL gilt.',
    'group.output.heading' => 'Ausgabe',
    'group.output.note' => 'Was daraus tatsächlich entsteht.',

    'form.variants.label' => 'Diese Codes werden angeboten',
    'form.downloads.label' => 'Diese Formate werden angeboten',
    'form.defaultUrl.label' => 'Default-URL',
    'form.defaultLogo.label' => 'Default-Bildmarke',
    'form.pageUrl.label' => 'Ziel-URL dieser Stelle',
    'form.inherit.empty' => 'nichts hinterlegt',
    'form.submit' => 'Erzeugen',
    'form.reset' => 'Zurücksetzen',
    'form.fixed.heading' => 'Feste Vorgaben',
    'form.fixed.note' => 'Logokasten :box Module, Rand :margin, Modulgröße :moduleSize px, '
        . 'Ruhezone :quietZone Module. Die Fehlerkorrekturstufe wird ausgerechnet, nicht gewählt.',

    'panel.plain' => 'Ohne Bildmarke',
    'panel.logo' => 'Mit Bildmarke',
    'panel.download.svg' => 'SVG herunterladen',
    'panel.download.png' => 'PNG herunterladen',
    'panel.raw' => 'Direkt öffnen',
    'panel.nothing' => 'Nichts gerendert.',
    'panel.noLogo' => 'Kein SVG und kein PNG in demo/logos. Leg eines hinein und lade neu.',
    'panel.png.whichFormat' => 'Beide Formate zeigen dieselbe Zeichnung, an derselben Stelle. '
        . 'In die Druckerei geht das SVG: Vektor, beliebig skalierbar, gestochen scharf in jeder '
        . 'Größe. Das PNG ist gerastert — 8 Bit indiziert, kantengeglättet, in der bestellten '
        . 'Druckgröße — und damit das Richtige für Bildschirm, Office und E-Mail, wo ein SVG '
        . 'Ärger macht.',
    'panel.png.refused' => 'Von dieser Bildmarke gibt es kein PNG. :reason',

    'group.label.heading' => 'Etikett',
    'group.label.note' => 'Code, Bildmarke und Text in einem Bild, in den Maßen der Vorlage: '
        . ':width x :height Millimeter, Codefläche :code Millimeter.',
    'panel.label' => 'Etikett, dunkler Code',
    'panel.labelColor' => 'Etikett, farbiger Code',
    'form.labelText.label' => 'Text auf dem Etikett',
    'form.labelText.hint' => 'Höchstens :max Zeichen. Der Satz bricht um und verkleinert sich, '
        . 'bis er in den Kasten passt. Reicht das nicht, wird er abgelehnt statt unleserlich '
        . 'gesetzt.',
    'form.labelColor.label' => 'Farbe des Codes',
    'form.labelColor.hint' => 'Gilt nur für das rechte Etikett. Der Wert ist RGB. Welches CMYK '
        . 'im Andruck daraus wird, entscheidet die Druckerei.',
    'label.facts' => 'Ruhezone :zone Module, ISO/IEC 18004 verlangt 4. Modulgröße :moduleSize '
        . 'Millimeter bei :moduleCount Modulen.',

    'group.returnInfo.heading' => 'Etikett „Informationen zur Rückgabe"',
    'group.returnInfo.note' => 'Ein Preset nach der Vorlage vom 24.09.2026, :width x :height '
        . 'Millimeter. Rahmen, Piktogramm und Text sind fest, variabel ist allein der Code. Keine '
        . 'Bildmarke und kein eigener Text: die Teilnahme am System steht erst hinter dem Code.',
    'panel.returnInfo' => 'Etikett, Informationen zur Rückgabe',

    'resolution.url' => 'URL:',
    'resolution.logo' => 'Bildmarke:',
    'resolution.none' => 'keine',
    'resolution.from.page' => 'aus der Seite',
    'resolution.from.global' => 'global',
    'resolution.from.nowhere' => 'nirgends gesetzt',

    'output.nothing' => 'Keine Variante ausgewählt. Es gibt nichts zu zeigen, und das ist eine '
        . 'gültige Einstellung, kein Fehler.',
    'output.noDownloads' => 'Beide Formate sind global abgeschaltet. Die Codes erscheinen, '
        . 'herunterladen lässt sich nichts.',

    'facts.heading' => 'Was herausgekommen ist',
    'facts.payload' => 'Nutzlast',
    'facts.payload.value' => ':bytes Bytes',
    'facts.level' => 'Fehlerkorrektur',
    'facts.level.auto' => 'niedrigste Stufe, die diesen Kasten überlebt',
    'facts.version' => 'QR-Version',
    'facts.version.value' => ':version von 40',
    'facts.modules' => 'Module',
    'facts.modules.value' => ':size × :size = :total',
    'facts.allowance' => 'Reserve',
    'facts.allowance.value' => ':used % von :budget % verbraucht, :headroom % übrig',
    'facts.alignment' => 'Ausrichtungsmuster',
    'facts.alignment.intact' => 'unangetastet',
    'facts.alignment.given' => ':modules Module aufgegeben',
    'facts.box' => 'Logokasten',
    'facts.box.value' => ':box × :box Module',
    'facts.cleared' => 'Freigeräumt',
    'facts.cleared.value' => ':modules Module, :share % des Symbols',
    'facts.margin' => 'Rand',
    'facts.margin.value' => ':margin Modul(e), es bleiben :drawable × :drawable zum Zeichnen',
    'facts.logoWidth' => 'Breite der Bildmarke',
    'facts.logoWidth.value' => ':percent % des Symbols',
    'facts.largestBox' => 'Größter Kasten, der die Suchmuster freilässt',
    'facts.largestBox.value' => ':modules Module',
    'facts.svgSize' => 'SVG-Größe',
    'facts.svgSize.value' => ':plain gegen :logo Bytes',
    'facts.png' => 'PNG',
    'facts.png.value' => ':pixels × :pixels px, :perModule px je Modul, 1 Bit, :bytes Bytes',
    'facts.artworkArea' => 'Zeichenfläche der Bildmarke',
    'facts.artworkArea.value' => ':width × :height px im gedruckten PNG',
    'facts.artworkArea.enough' => 'Vorlage :width × :height px — reicht, wird verkleinert',
    'facts.artworkArea.short' => 'Vorlage nur :width × :height px — wird vergrößert und '
        . 'entsprechend weich. Eine größere Vorlage anfordern',
    'facts.pngLogo' => 'PNG mit Bildmarke',
    'facts.pngLogo.value' => ':bytes Bytes, 8 Bit indiziert',
    'facts.printSize' => 'Gedruckte Größe',
    'facts.printSize.value' => ':size mm bei :dpi dpi — ordentlich für :ordered mm, ohne '
        . 'Hochskalieren',
    'facts.preview' => 'Vorschau zeigt',
    'facts.preview.value' => ':format, das kleinere von beiden',

    'source.summary' => 'SVG-Quelltext, mit Bildmarke',

    'level.L' => 'L — etwa 7 % Wiederherstellung',
    'level.M' => 'M — etwa 15 % Wiederherstellung',
    'level.Q' => 'Q — etwa 25 % Wiederherstellung',
    'level.H' => 'H — etwa 30 % Wiederherstellung, nötig für eine Bildmarke',

    'notice.quietZone' => 'Die Ruhezone steht auf :quietZone Modulen. Die Norm verlangt 4. '
        . 'Das trägt nur, wenn das Layout drumherum die fehlenden Module an Weißraum beisteuert — '
        . 'grenzt der Code direkt an Grafik, wird er unzuverlässig. Der Andruck entscheidet.',

    'notice.print' => 'Für die Druckerei: Schwarz als :dark, Weiß als :light, und im CMYK-Umbruch '
        . 'ausdrücklich 100 % K — kein Rich Black. Ein aus vier Farben gemischtes Schwarz braucht '
        . 'vier passgenaue Platten, und wo sie nicht passen, weicht eine Modulkante zu einem '
        . 'farbigen Saum auf. Genau diese Kante vermisst ein Scanner. Weder PNG noch SVG können '
        . 'CMYK überhaupt tragen; die Umwandlung passiert im Umbruch.',

    'error.url.missing' => 'Es gibt keine URL. Trag eine in den Seiten-Einstellungen ein oder '
        . 'hinterleg eine Default-URL in den globalen Einstellungen.',
    'error.url.tooLong' => 'Die URL ist :length Bytes lang. Diese Seite nimmt höchstens :max.',
    'error.url.notHttp' => 'Das ist keine http- oder https-URL. Der Encoder selbst nimmt jede '
        . 'Zeichenkette, aber hier geht es um URLs, also besteht die Seite darauf.',

    'footer' => 'Die freigeräumte Fläche wird gegen die Fehlerkorrekturstufe abgewogen, und das ist '
        . 'eine Faustregel — Module und Codewörter sind nicht dieselbe Einheit. Keine Faustregel '
        . 'sind die Funktionsmuster: Such-, Takt- und Formatmuster tragen keine Fehlerkorrektur, '
        . 'und ein Kasten darüber wird abgewiesen. Ob der gedruckte Code gelesen wird, entscheidet '
        . 'ein Andruck in Originalgröße auf dem echten Material, nicht diese Seite.',

    // Die Seite im Control Panel.

    'cp.nav' => 'QR-Codes',
    'cp.title' => 'QR-Codes',
    'cp.intro' => 'Hier steht, was die Erweiterung anbietet. Diese Werte gelten überall gleich. '
        . 'Das Einzige, was daneben je Stelle eingetragen wird, ist die Ziel-URL im Blueprint.',

    'cp.section.variants' => 'Was angeboten wird',
    'cp.section.label' => 'Das Etikett',
    'cp.section.label.hint' => 'Gilt für die beiden Etiketten mit Bildmarke und Text, nicht für '
        . 'das Etikett „Informationen zur Rückgabe". Die Maße kommen aus der gelieferten Vorlage und '
        . 'sind nicht einstellbar.',
    'cp.section.texts' => 'Texte auf der Seite',
    'cp.section.texts.site' => 'Texte auf der Seite (:site)',
    'cp.section.defaults' => 'Rückfallwerte',
    'cp.section.fixed' => 'Feste Vorgaben',

    'cp.variants.plain' => 'Code ohne Bildmarke',
    'cp.variants.plain.hint' => 'Der schlichte Code. Ohne ihn bleibt nur die Variante mit '
        . 'Bildmarke, und die gibt es nur, wenn eine Bildmarke hinterlegt ist.',
    'cp.variants.logo' => 'Code mit Bildmarke',
    'cp.variants.logo.hint' => 'Erscheint nur, wenn unten eine Bildmarke hinterlegt ist. Ohne '
        . 'sie entfällt er lautlos, das ist keine Fehlkonfiguration.',
    'cp.variants.label' => 'Etikett, dunkler Code',
    'cp.variants.label.hint' => 'Code, Bildmarke daneben und Text in einem Bild, in den Maßen '
        . 'der Vorlage. Der Code steht in der Schriftfarbe.',
    'cp.variants.labelColor' => 'Etikett, farbiger Code',
    'cp.variants.labelColor.hint' => 'Dasselbe Etikett mit dem Code in der Farbe unten. Ohne '
        . 'gesetzte Farbe entfällt es lautlos, wie die Variante ohne Bildmarke.',
    'cp.variants.returnInfo' => 'Etikett „Informationen zur Rückgabe"',
    'cp.variants.returnInfo.hint' => 'Code, Handy-Piktogramm und der Satz „Informationen zur '
        . 'Rückgabe" in einem Bild, fest nach der Vorlage vom 24.09.2026. Ohne Bildmarke, ohne '
        . 'eigenen Text, in Schwarz: variabel ist allein der Code. Die Felder unter „Das Etikett" '
        . 'und die Bildmarke gelten hierfür nicht.',

    'cp.label.text' => 'Text auf dem Etikett',
    'cp.label.text.hint' => 'Steht rechts neben dem Code. Höchstens :max Zeichen. Der Satz '
        . 'bricht um und verkleinert sich, bis er in den Kasten passt; reicht das nicht, wird '
        . 'das Etikett abgelehnt statt unleserlich gesetzt. Leer lassen heißt: Etikett ohne '
        . 'Text.',
    'cp.label.color' => 'Farbe des Codes',
    'cp.label.color.hint' => 'Nur für das farbige Etikett. Der Wert ist RGB; welches CMYK im '
        . 'Andruck daraus wird, entscheidet die Druckerei. Ohne Farbe gibt es das farbige '
        . 'Etikett nicht.',

    'cp.downloads.svg' => 'SVG herunterladen',
    'cp.downloads.svg.hint' => 'Das Format für die Druckerei. Verlustfrei skalierbar.',
    'cp.downloads.png' => 'PNG herunterladen',
    'cp.downloads.png.hint' => 'Die Beilage für Bildschirm, Office und E-Mail. Gerechnet aus '
        . 'der Druckgröße, nicht aus einer Pixelzahl.',

    'cp.defaultLogo' => 'Bildmarke',
    'cp.defaultLogo.hint' => 'Gilt überall gleich, im Code und auf dem Etikett. SVG ist die '
        . 'bessere Zulieferung, ein PNG geht auch. Leer lassen ist erlaubt: dann entfällt die '
        . 'Variante mit Bildmarke, und das Etikett bleibt ohne Marke.',
    'cp.defaultUrl' => 'Default-URL',
    'cp.defaultUrl.hint' => 'Gilt, wo an einer Stelle keine eigene eingetragen ist. Eine volle '
        . 'Adresse mit http oder https. Verarbeitet wird, was dasteht — kein Ergänzen oder '
        . 'Entfernen von www.',

    'cp.fixed.hint' => 'Diese Werte sind entschieden, nicht eingestellt. Ein freigegebener '
        . 'Andruck gilt für genau sie, und ein Feld, an dem jemand im Vorbeigehen dreht, würde '
        . 'ihn ungültig machen, ohne dass es auffällt. Zu ändern in Qr\\Preset.',
    'cp.fixed.box' => 'Logokasten',
    'cp.fixed.box.value' => ':box Module, davon :margin Rand',
    'cp.fixed.moduleSize' => 'Modulgröße',
    'cp.fixed.quietZone' => 'Ruhezone',
    'cp.fixed.modules' => ':count Module',
    'cp.fixed.print' => 'Druckgröße',
    'cp.fixed.print.value' => ':size mm bei :dpi dpi',
    'cp.fixed.level' => 'Fehlerkorrektur',
    'cp.fixed.level.value' => 'wird ausgerechnet, nicht gewählt',
    'cp.fixed.container' => 'Asset-Container',
    'cp.fixed.container.hint' => 'Wo Bildmarken liegen, wenn ein Pfad ohne Container-Angabe '
        . 'kommt. Eine Installationstatsache, deshalb in config/qr-gen.php und nicht hier.',

    'cp.texts.title' => 'Überschrift',
    'cp.texts.title.hint' => 'Leer lassen für den mitgelieferten Text.',
    'cp.texts.lead' => 'Einleitung',
    'cp.texts.lead.hint' => '{url} wird durch die Adresse ersetzt, die im Code steht, und dabei '
        . 'verlinkt. Leer lassen für den mitgelieferten Text.',

    'cp.permission' => 'QR-Code-Einstellungen ändern',
    'cp.saved' => 'Gespeichert.',
    'cp.nothing' => 'Es ist keine Variante angehakt. Damit erscheint auf keiner Seite ein Code.',
    'cp.noDownload' => 'Es ist kein Format angehakt. Die Codes sind dann nur zu sehen, nicht '
        . 'mitzunehmen.',

    // Die Panels in der Seite.
    //
    // Titel und Einleitung stehen hier nur als Rückfall: sie sind Text, den
    // die Seite besitzt, und lassen sich je Sprachfassung im Control Panel
    // setzen. Die Beschriftung der beiden Codes bleibt dagegen hier, weil sie
    // benennt, was dieses Paket erzeugt, und sich mit ihm ändert.

    'page.title' => 'QR Codes',
    'page.lead' => 'QR Codes für {url} zum Herunterladen für digital und Print',

    'panel.failure' => 'Dieser Code ist nicht entstanden: :grund',
    'panel.alt' => 'QR-Code',
    'panel.alt.logo' => 'QR-Code mit Bildmarke',
    'panel.alt.label' => 'Etikett mit QR-Code, Bildmarke und Text',
    'panel.alt.returnInfo' => 'Etikett mit QR-Code und dem Hinweis „Informationen zur Rückgabe"',
];

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

    'form.url.label' => 'URL',
    'form.logo.label' => 'Bildmarke',
    'form.logo.none' => 'keine in demo/logos',
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
    'panel.noLogo' => 'Kein SVG in demo/logos. Leg eines hinein und lade neu.',
    'panel.png.unavailable' => 'Für den Druck ist das SVG das richtige Format: Vektor, beliebig '
        . 'skalierbar, gestochen scharf in jeder Größe. Ein PNG mit Bildmarke gibt es hier nicht, '
        . 'weil dafür Vektorpfade gerastert werden müssten — Bézierkurven, Bögen, Füllregeln — und '
        . 'das ist ein 2D-Rasterisierer und keine hundert Zeilen. Wer ein Raster mit Bildmarke '
        . 'braucht, exportiert es beim Layout aus dem SVG in der benötigten Größe.',

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

    'error.url.tooLong' => 'Die URL ist :length Bytes lang. Diese Seite nimmt höchstens :max.',
    'error.url.notHttp' => 'Das ist keine http- oder https-URL. Der Encoder selbst nimmt jede '
        . 'Zeichenkette, aber hier geht es um URLs, also besteht die Seite darauf.',

    'footer' => 'Die freigeräumte Fläche wird gegen die Fehlerkorrekturstufe abgewogen, und das ist '
        . 'eine Faustregel — Module und Codewörter sind nicht dieselbe Einheit. Keine Faustregel '
        . 'sind die Funktionsmuster: Such-, Takt- und Formatmuster tragen keine Fehlerkorrektur, '
        . 'und ein Kasten darüber wird abgewiesen. Ob der gedruckte Code gelesen wird, entscheidet '
        . 'ein Andruck in Originalgröße auf dem echten Material, nicht diese Seite.',
];

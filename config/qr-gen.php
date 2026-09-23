<?php

declare(strict_types=1);

/**
 * Rückfallwerte der Erweiterung.
 *
 * Was hier steht, gilt, solange im Control Panel nichts anderes eingestellt
 * ist. Die Einstellungen im CP überschreiben diese Werte, und die Angaben im
 * Blueprint einer Seite überschreiben wiederum die aus dem CP. Die Reihenfolge
 * ist Absicht: global steht, was überhaupt angeboten wird und was gilt, wenn
 * nichts anderes dasteht; pro Seite steht, was diesen einen Hersteller
 * ausmacht.
 *
 * Nicht hier stehen die Druckwerte. Druckgröße, Auflösung, Logokasten und
 * Ruhezone liegen in `Redcodede\QrGen\Qr\Preset` und sind entschieden, nicht
 * eingestellt. Ein freigegebener Andruck gilt für genau diese Werte, und ein
 * Feld, an dem jemand im Vorbeigehen dreht, würde ihn ungültig machen, ohne
 * dass es auffällt.
 */
return [

    /*
     * Welche Codes die Erweiterung überhaupt erzeugt.
     *
     * Ein hier abgeschalteter Typ taucht auch im Blueprint einer Seite nicht
     * als Auswahl auf. Sonst liesse sich eine Seite auf etwas einstellen, das
     * nie erscheint, ohne dass irgendwo stünde warum.
     */
    'variants' => [
        'plain' => true,
        'logo' => true,
        'label' => true,
        'label_color' => true,
    ],

    /*
     * Der Aufdruck des Etiketts.
     *
     * Steht rechts neben dem Code, höchstens 72 Zeichen. Der Satz bricht um und
     * verkleinert sich, bis er in seinen Kasten passt; reicht das nicht, wird
     * das Etikett abgelehnt. `null` heißt: Etikett ohne Text, und das ist kein
     * Fehler.
     */
    'label_text' => null,

    /*
     * Die Codefarbe des farbigen Etiketts, als `#rrggbb`.
     *
     * Ohne Wert gibt es diesen Typ nicht, genau wie es die Variante mit
     * Bildmarke ohne Bildmarke nicht gibt. Einen Ton mitzuliefern hieße, die
     * Farbe eines Hauses in ein allgemeines Paket zu schreiben, und ein zweites
     * Etikett in der Farbe des ersten wäre ohnehin keins.
     */
    'code_color' => null,

    /*
     * Welche Formate zum Herunterladen angeboten werden.
     *
     * Beide zeigen dieselbe Zeichnung an derselben Stelle. In die Druckerei
     * geht das SVG; das PNG ist die Beilage für Bildschirm, Office und E-Mail.
     */
    'downloads' => [
        'svg' => true,
        'png' => true,
    ],

    /*
     * Die Bildmarke, die genommen wird, wenn eine Seite keine eigene angibt.
     *
     * Ein Asset-Pfad, kein Dateisystempfad. Erlaubt sind SVG und PNG. Ein
     * Vektor ist die bessere Zulieferung: ein Raster hat eine Auflösung, und
     * darüber hinaus vergrößert wird es weich. Wie viele Pixel die Druckgröße
     * verlangt, beantwortet PngRenderer::artworkPixels().
     */
    'logo' => null,

    /*
     * Der Asset-Container, in dem Bildmarken liegen, wenn ein Pfad ohne
     * Container-Angabe kommt.
     */
    'container' => 'assets',

    /*
     * Wo die im Control Panel gespeicherten Einstellungen liegen.
     *
     * Unter `content/`, weil sie versioniert und mitgesichert gehören: sie
     * sind Konfiguration, nicht Zwischenstand. Was dort steht, liegt über den
     * Werten dieser Datei; was dort fehlt, kommt von hier. `null` heißt
     * `content/qr-gen/settings.yaml` unterhalb der Anwendung.
     */
    'settings_path' => null,

    /*
     * Die Ziel-URL, die genommen wird, wenn eine Seite keine eigene angibt.
     *
     * Eine volle URL mit Schema. Verarbeitet wird, was dasteht: kein Ergänzen
     * oder Entfernen von `www`, keine Umschreibung. Welche Adresse am Ende auf
     * der Verpackung steht, entscheidet die Eingabe und nicht dieses Paket.
     */
    'url' => null,

];

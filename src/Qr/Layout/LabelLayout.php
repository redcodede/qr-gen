<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Layout;

/**
 * Where everything sits on the label, in millimetres.
 *
 * A label is the symbol plus the things a package has to carry next to it: a
 * mark, a line of explanation, and a frame around the lot. The measurements
 * below are read off the artwork the GVÖ delivered, not invented here, and they
 * are decisions in the same sense as {@see \Redcodede\QrGen\Qr\Preset}: a print
 * proof is approved for these numbers and no others.
 *
 * **The unit is the millimetre and the coordinate system is the SVG one**, y
 * pointing down, origin at the top left of the label.
 *
 * Two of these numbers deserve their own note.
 *
 * The **code box is the symbol itself, without a quiet zone of its own.** The
 * light margin comes from the label: between the symbol and the frame there are
 * 3.78 mm, which at the artwork's module size is 3.8 modules. ISO/IEC 18004
 * asks for 4. That is a knowing deviation, it is the same kind of deviation
 * {@see \Redcodede\QrGen\Qr\Preset::QUIET_ZONE} already documents, and it is
 * settled by a print proof rather than by this comment.
 *
 * The **code box does not grow with the payload.** A longer URL means more
 * modules in the same 28.66 mm, so the modules get smaller. That follows from
 * the decision to stay close to the delivered artwork. Where the floor for a
 * printable module lies is an open question and belongs to the print proof.
 */
final class LabelLayout
{
    /** @var float */
    private $width;

    /** @var float */
    private $height;

    /** @var float */
    private $frameInset;

    /** @var float */
    private $frameStroke;

    /** @var float */
    private $codeX;

    /** @var float */
    private $codeY;

    /** @var float */
    private $codeSize;

    /** @var float */
    private $logoX;

    /** @var float */
    private $logoY;

    /** @var float */
    private $logoWidth;

    /** @var float */
    private $logoHeight;

    /** @var float */
    private $textX;

    /** @var float */
    private $textWidth;

    /** @var float */
    private $textTop;

    /** @var float */
    private $textBottom;

    /** @var float */
    private $textSize;

    /** @var float */
    private $lineHeightFactor;

    /** @var float */
    private $minimumTextSize;

    private function __construct()
    {
        $this->width = 90.05;
        $this->height = 36.28;

        // The frame is a hairline set half a stroke inside the edge, exactly as
        // the artwork draws it.
        $this->frameInset = 0.12;
        $this->frameStroke = 0.25;

        $this->codeX = 3.90;
        $this->codeY = 3.67;
        $this->codeSize = 28.66;

        // Measured off the artwork by flattening the mark's outlines, not
        // estimated from the drawing.
        $this->logoX = 36.23;
        $this->logoY = 3.73;
        $this->logoWidth = 22.24;
        $this->logoHeight = 13.45;

        $this->textX = 36.13;

        // Bis zum Rahmen wären es 53.80. Das ist aber kein Satzspiegel, sondern
        // die Kante: ein Wort dürfte sie berühren. Die Vorlage lässt unter der
        // letzten Grundlinie 1.90 bis zum Rahmen, und derselbe Rand gilt hier
        // rechts. Die längste Zeile der Vorlage misst 50.65 und passt damit
        // weiterhin, was sie muss, sonst käme das Original verkleinert heraus.
        $this->textWidth = 51.90;

        // The box the artwork's two lines occupy: the first baseline at 24.22
        // less its ascent, down to the second baseline at 32.38 plus its
        // descent. Two lines at 6.8 mm fill it exactly, which is how the
        // original comes back out of a layout that also has to hold four.
        $this->textTop = 17.30;
        $this->textBottom = 34.26;

        $this->textSize = 6.8;
        $this->lineHeightFactor = 1.2;

        // Below this the type stops being an instruction on a container and
        // becomes decoration. Refusing beats setting something nobody can read.
        $this->minimumTextSize = 2.5;
    }

    /**
     * The settled geometry, as delivered.
     */
    public static function standard(): self
    {
        return new self();
    }

    /**
     * Das Etikett „Informationen zur Rückgabe", Vorlage vom 24.09.2026.
     *
     * Rahmen und Codefläche sind die der Standardfassung, aus der die Vorlage
     * erkennbar entstanden ist. Ausgetauscht ist die rechte Hälfte: statt
     * Bildmarke und eingegebenem Text eine feste Grafik aus Handy-Piktogramm
     * und dem Satz „Informationen zur Rückgabe".
     *
     * Die Codefläche der Vorlage misst 28,75 x 28,80 bei x 3,82 und y 3,74,
     * also bis zu 0,2 Millimeter neben der freigegebenen von 28,66 bei 3,90
     * und 3,67. Gesetzt ist die freigegebene. Die Vorlage ist ein Rasterbild,
     * und ihr Code ist ohnehin nicht der, der hier entsteht: wie viele Module
     * er hat, hängt an der Adresse.
     *
     * Diese Grafik steht im Kasten der Bildmarke. Der Kasten hat **genau die
     * Maße der mitgelieferten Datei**, damit sie unverkleinert und
     * unverschoben dort landet, wo sie in der Vorlage steht; ein Test hält
     * beides zusammen. Gemessen ist er an den Mitten der Rahmenlinien, siehe
     * `resources/artwork/rueckgabe-information.svg`.
     *
     * Der Textkasten bleibt unbenutzt. Dieses Etikett nimmt keinen Text an.
     */
    public static function returnInfo(): self
    {
        $layout = new self();

        $layout->logoX = 35.90;
        $layout->logoY = 9.67;
        $layout->logoWidth = 51.26;
        $layout->logoHeight = 14.90;

        return $layout;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function height(): float
    {
        return $this->height;
    }

    public function frameInset(): float
    {
        return $this->frameInset;
    }

    public function frameStroke(): float
    {
        return $this->frameStroke;
    }

    public function codeX(): float
    {
        return $this->codeX;
    }

    public function codeY(): float
    {
        return $this->codeY;
    }

    public function codeSize(): float
    {
        return $this->codeSize;
    }

    public function logoX(): float
    {
        return $this->logoX;
    }

    public function logoY(): float
    {
        return $this->logoY;
    }

    public function logoWidth(): float
    {
        return $this->logoWidth;
    }

    public function logoHeight(): float
    {
        return $this->logoHeight;
    }

    public function textX(): float
    {
        return $this->textX;
    }

    public function textWidth(): float
    {
        return $this->textWidth;
    }

    public function textTop(): float
    {
        return $this->textTop;
    }

    public function textBottom(): float
    {
        return $this->textBottom;
    }

    public function textHeight(): float
    {
        return $this->textBottom - $this->textTop;
    }

    /** The largest type size, the one the artwork uses. */
    public function textSize(): float
    {
        return $this->textSize;
    }

    public function lineHeightFactor(): float
    {
        return $this->lineHeightFactor;
    }

    public function minimumTextSize(): float
    {
        return $this->minimumTextSize;
    }

    /**
     * The smallest module the symbol will be drawn with, given its size.
     *
     * Not a rule, a number to look at: it is what the print proof has to judge.
     */
    public function moduleSizeFor(int $modules): float
    {
        return $modules > 0 ? $this->codeSize / $modules : 0.0;
    }

    /**
     * The light margin around the symbol, expressed in modules.
     *
     * The narrowest of the four sides, because a scanner only needs one of them
     * to be too small for the reading to suffer.
     */
    public function quietZoneInModules(int $modules): float
    {
        $moduleSize = $this->moduleSizeFor($modules);

        if ($moduleSize <= 0.0) {
            return 0.0;
        }

        $left = $this->codeX - $this->frameInset;
        $top = $this->codeY - $this->frameInset;
        $bottom = ($this->height - $this->frameInset) - ($this->codeY + $this->codeSize);

        // Rechts grenzt an, was näher liegt, Text oder Bildmarke. In der
        // Standardfassung ist das der Text, beim Etikett „Informationen zur
        // Rückgabe" die Grafik, deren Signallinien weiter nach links reichen.
        $right = min($this->textX, $this->logoX) - ($this->codeX + $this->codeSize);

        return min($left, $top, $bottom, $right) / $moduleSize;
    }
}

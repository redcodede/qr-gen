<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

/**
 * Resamples 8-bit RGBA pixels to a different size.
 *
 * Two filters, picked by direction, because no single one is right for both.
 * **Shrinking averages the whole source area** a destination pixel covers: a
 * 613-pixel logo going into 230 has five source pixels behind each one, and
 * taking a single sample from the middle of that would throw the other
 * twenty-four away — which is what makes a downscaled logo look ragged and
 * sparkly rather than smooth. **Enlarging interpolates between the four
 * neighbours**, because there is no area to average and repeating pixels would
 * give visible blocks. Blurry is the better failure here; blocky reads as
 * broken.
 *
 * **Alpha is premultiplied before anything is averaged and divided out
 * afterwards.** Skip that and the colour of fully transparent pixels — usually
 * black, sometimes whatever the exporter left there — gets mixed into every
 * edge, and the artwork comes back with a dark halo around it that nobody put
 * there. It is the single most common mistake in image scaling and it only
 * shows on artwork with soft edges, which is exactly what a logo has.
 */
final class RasterScaler
{
    private function __construct()
    {
    }

    /**
     * @param string $rgba Four bytes per pixel, row by row
     *
     * @return string The same, at the new size
     */
    public static function resample(
        string $rgba,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight
    ): string {
        if ($targetWidth < 1 || $targetHeight < 1 || $sourceWidth < 1 || $sourceHeight < 1) {
            return '';
        }

        if ($targetWidth === $sourceWidth && $targetHeight === $sourceHeight) {
            return $rgba;
        }

        $shrinking = $targetWidth <= $sourceWidth && $targetHeight <= $sourceHeight;

        return $shrinking
            ? self::average($rgba, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight)
            : self::interpolate($rgba, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
    }

    /**
     * Box filter: every source pixel under a destination pixel, weighted by how
     * much of it falls inside.
     */
    private static function average(
        string $rgba,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight
    ): string {
        $scaleX = $sourceWidth / $targetWidth;
        $scaleY = $sourceHeight / $targetHeight;
        $out = '';

        for ($y = 0; $y < $targetHeight; $y++) {
            $fromY = $y * $scaleY;
            $toY = ($y + 1) * $scaleY;
            $firstRow = (int) $fromY;
            $lastRow = min($sourceHeight - 1, (int) ceil($toY) - 1);

            for ($x = 0; $x < $targetWidth; $x++) {
                $fromX = $x * $scaleX;
                $toX = ($x + 1) * $scaleX;
                $firstColumn = (int) $fromX;
                $lastColumn = min($sourceWidth - 1, (int) ceil($toX) - 1);

                $red = 0.0;
                $green = 0.0;
                $blue = 0.0;
                $alpha = 0.0;
                $weightTotal = 0.0;

                for ($row = $firstRow; $row <= $lastRow; $row++) {
                    $heightWeight = min($toY, $row + 1) - max($fromY, (float) $row);

                    if ($heightWeight <= 0.0) {
                        continue;
                    }

                    $rowStart = $row * $sourceWidth * 4;

                    for ($column = $firstColumn; $column <= $lastColumn; $column++) {
                        $widthWeight = min($toX, $column + 1) - max($fromX, (float) $column);

                        if ($widthWeight <= 0.0) {
                            continue;
                        }

                        $weight = $heightWeight * $widthWeight;
                        $at = $rowStart + ($column * 4);
                        $sampleAlpha = ord($rgba[$at + 3]);

                        // Premultiplied: a transparent pixel contributes its
                        // weight to the alpha total and nothing to the colour.
                        $premultiplied = $weight * $sampleAlpha / 255;

                        $red += ord($rgba[$at]) * $premultiplied;
                        $green += ord($rgba[$at + 1]) * $premultiplied;
                        $blue += ord($rgba[$at + 2]) * $premultiplied;
                        $alpha += $sampleAlpha * $weight;
                        $weightTotal += $weight;
                    }
                }

                $out .= self::pixel($red, $green, $blue, $alpha, $weightTotal);
            }
        }

        return $out;
    }

    /**
     * Bilinear: the four source pixels around the sample point, weighted by
     * distance.
     */
    private static function interpolate(
        string $rgba,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight
    ): string {
        $scaleX = $sourceWidth / $targetWidth;
        $scaleY = $sourceHeight / $targetHeight;
        $out = '';

        for ($y = 0; $y < $targetHeight; $y++) {
            // The centre of the destination pixel, mapped back into the source.
            $sourceY = (($y + 0.5) * $scaleY) - 0.5;
            $topRow = (int) floor($sourceY);
            $downWeight = $sourceY - $topRow;
            $top = max(0, min($sourceHeight - 1, $topRow));
            $bottom = max(0, min($sourceHeight - 1, $topRow + 1));

            for ($x = 0; $x < $targetWidth; $x++) {
                $sourceX = (($x + 0.5) * $scaleX) - 0.5;
                $leftColumn = (int) floor($sourceX);
                $rightWeight = $sourceX - $leftColumn;
                $left = max(0, min($sourceWidth - 1, $leftColumn));
                $right = max(0, min($sourceWidth - 1, $leftColumn + 1));

                $red = 0.0;
                $green = 0.0;
                $blue = 0.0;
                $alpha = 0.0;

                $corners = [
                    [$left, $top, (1 - $rightWeight) * (1 - $downWeight)],
                    [$right, $top, $rightWeight * (1 - $downWeight)],
                    [$left, $bottom, (1 - $rightWeight) * $downWeight],
                    [$right, $bottom, $rightWeight * $downWeight],
                ];

                foreach ($corners as $corner) {
                    [$column, $row, $weight] = $corner;

                    if ($weight <= 0.0) {
                        continue;
                    }

                    $at = (($row * $sourceWidth) + $column) * 4;
                    $sampleAlpha = ord($rgba[$at + 3]);
                    $premultiplied = $weight * $sampleAlpha / 255;

                    $red += ord($rgba[$at]) * $premultiplied;
                    $green += ord($rgba[$at + 1]) * $premultiplied;
                    $blue += ord($rgba[$at + 2]) * $premultiplied;
                    $alpha += $sampleAlpha * $weight;
                }

                $out .= self::pixel($red, $green, $blue, $alpha, 1.0);
            }
        }

        return $out;
    }

    /**
     * Divides the premultiplied sums back out and packs one pixel.
     */
    private static function pixel(
        float $red,
        float $green,
        float $blue,
        float $alpha,
        float $weightTotal
    ): string {
        if ($weightTotal <= 0.0) {
            return "\x00\x00\x00\x00";
        }

        $averageAlpha = $alpha / $weightTotal;

        if ($averageAlpha <= 0.0) {
            // Nothing visible here, so there is no colour to recover — and
            // dividing by the alpha would be a division by zero.
            return "\x00\x00\x00\x00";
        }

        // The colour sums are premultiplied and weighted; dividing by the same
        // alpha they were multiplied by gives the straight colour back.
        $divisor = $weightTotal * $averageAlpha / 255;

        return chr(self::clamp($red / $divisor))
            . chr(self::clamp($green / $divisor))
            . chr(self::clamp($blue / $divisor))
            . chr(self::clamp($averageAlpha));
    }

    private static function clamp(float $value): int
    {
        $rounded = (int) round($value);

        return $rounded < 0 ? 0 : ($rounded > 255 ? 255 : $rounded);
    }
}

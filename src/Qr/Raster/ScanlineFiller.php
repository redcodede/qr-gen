<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

/**
 * Fills flattened outlines and reports how much of each pixel is covered.
 *
 * A scanline filler, sampled four times over in each direction. Sixteen samples
 * per pixel give seventeen levels of coverage, which is more than enough for
 * artwork a centimetre wide: the eye stops counting steps on a curved edge at
 * about eight. What it buys is that a thin feature — the crossbar of a letter,
 * the gap in a ring — comes out grey rather than dropping out or jumping to
 * solid, and dropping out is what makes a small logo look broken.
 *
 * Supersampling rather than exact analytic coverage because the two disagree
 * only where several edges cross inside one pixel, and that difference costs a
 * signed-area computation per edge fragment. This is the cheap approximation
 * everyone uses, and at this scale nothing distinguishes them.
 *
 * **Why it is not the naive version.** Testing every edge against every sample
 * row would be a few million operations for one logo, which is seconds in PHP.
 * Two things bring it down to a few tens of thousands. Edges enter and leave an
 * active list as the scan passes them, so each row only looks at the edges that
 * actually cross it. And a run of fully covered pixels is recorded as two
 * entries in a difference array rather than as one increment per pixel, so a
 * wide fill costs the same as a narrow one.
 */
final class ScanlineFiller
{
    /** Samples per pixel along each axis. */
    public const SAMPLES_PER_AXIS = 4;

    /** Coverage of a pixel that is entirely inside the outline. */
    public const FULL_COVERAGE = self::SAMPLES_PER_AXIS * self::SAMPLES_PER_AXIS;

    /**
     * Coverage per pixel, as a sparse map of row to column to sample count.
     *
     * Sparse because artwork touches a fraction of its bounding box and the
     * caller composites only what it is given. An untouched pixel never appears.
     *
     * @param list<list<array{0: float, 1: float}>> $subpaths Device-space outlines
     *
     * @return array<int, array<int, int>>
     */
    public static function coverage(array $subpaths, int $width, int $height, bool $evenOdd): array
    {
        $edges = self::edges($subpaths);

        if ($edges === [] || $width < 1 || $height < 1) {
            return [];
        }

        $samples = self::SAMPLES_PER_AXIS;
        $sampleColumns = $width * $samples;

        $firstRow = max(0, (int) floor(self::minimumY($edges) * $samples));
        $lastRow = min(($height * $samples) - 1, (int) ceil(self::maximumY($edges) * $samples));

        if ($lastRow < $firstRow) {
            return [];
        }

        // Each edge is queued at the first sample row it can cross, so the scan
        // never looks at an edge before it is due.
        $queued = [];

        foreach ($edges as $edge) {
            $entry = max($firstRow, (int) ceil(($edge[0] * $samples) - 0.5));

            if ($entry <= $lastRow) {
                $queued[$entry][] = $edge;
            }
        }

        $coverage = [];
        $active = [];

        $partial = [];
        $difference = [];
        $touchedFrom = PHP_INT_MAX;
        $touchedTo = -1;

        for ($row = $firstRow; $row <= $lastRow; $row++) {
            $sampleY = ($row + 0.5) / $samples;

            if (isset($queued[$row])) {
                foreach ($queued[$row] as $edge) {
                    $active[] = $edge;
                }
            }

            $crossings = [];

            foreach ($active as $key => $edge) {
                // Half-open in y, so a vertex shared by two edges is counted
                // once. Counting it twice would punch a hole at every corner.
                if ($edge[1] <= $sampleY) {
                    unset($active[$key]);

                    continue;
                }

                if ($edge[0] > $sampleY) {
                    continue;
                }

                $crossings[] = [$edge[2] + (($sampleY - $edge[0]) * $edge[3]), $edge[4]];
            }

            if ($crossings !== []) {
                usort($crossings, static function (array $left, array $right): int {
                    return $left[0] <=> $right[0];
                });

                self::accumulate(
                    $crossings,
                    $evenOdd,
                    $samples,
                    $sampleColumns,
                    $partial,
                    $difference,
                    $touchedFrom,
                    $touchedTo
                );
            }

            // A pixel row is finished once its last sample row has been scanned.
            if ($row % $samples === $samples - 1) {
                self::flush(
                    $coverage,
                    intdiv($row, $samples),
                    $partial,
                    $difference,
                    $touchedFrom,
                    $touchedTo
                );
            }
        }

        self::flush($coverage, intdiv($lastRow, $samples), $partial, $difference, $touchedFrom, $touchedTo);

        return $coverage;
    }

    /**
     * Outlines to edges, each as [yTop, yBottom, xAtYTop, dx/dy, direction].
     *
     * Horizontal edges are dropped: they cross no scanline, and the winding they
     * would contribute is already carried by the edges on either side.
     *
     * @param list<list<array{0: float, 1: float}>> $subpaths
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float, 4: int}>
     */
    private static function edges(array $subpaths): array
    {
        $edges = [];

        foreach ($subpaths as $points) {
            $count = count($points);

            if ($count < 3) {
                continue;
            }

            for ($index = 0; $index < $count; $index++) {
                // The closing edge back to the start is implicit: a fill always
                // treats a subpath as closed, whether or not it ended in a Z.
                [$x0, $y0] = $points[$index];
                [$x1, $y1] = $points[($index + 1) % $count];

                if ($y0 === $y1) {
                    continue;
                }

                $downwards = $y0 < $y1;
                $topY = $downwards ? $y0 : $y1;
                $bottomY = $downwards ? $y1 : $y0;
                $topX = $downwards ? $x0 : $x1;

                $edges[] = [
                    $topY,
                    $bottomY,
                    $topX,
                    ($x1 - $x0) / ($y1 - $y0),
                    $downwards ? 1 : -1,
                ];
            }
        }

        return $edges;
    }

    /**
     * @param list<array{0: float, 1: float, 2: float, 3: float, 4: int}> $edges
     */
    private static function minimumY(array $edges): float
    {
        $minimum = $edges[0][0];

        foreach ($edges as $edge) {
            if ($edge[0] < $minimum) {
                $minimum = $edge[0];
            }
        }

        return $minimum;
    }

    /**
     * @param list<array{0: float, 1: float, 2: float, 3: float, 4: int}> $edges
     */
    private static function maximumY(array $edges): float
    {
        $maximum = $edges[0][1];

        foreach ($edges as $edge) {
            if ($edge[1] > $maximum) {
                $maximum = $edge[1];
            }
        }

        return $maximum;
    }

    /**
     * Walks the sorted crossings of one sample row and records the spans between
     * them that lie inside the outline.
     *
     * @param list<array{0: float, 1: int}> $crossings
     * @param array<int, int>               $partial
     * @param array<int, int>               $difference
     */
    private static function accumulate(
        array $crossings,
        bool $evenOdd,
        int $samples,
        int $sampleColumns,
        array &$partial,
        array &$difference,
        int &$touchedFrom,
        int &$touchedTo
    ): void {
        $winding = 0;
        $spanStart = 0.0;
        $inside = false;

        foreach ($crossings as $crossing) {
            $winding += $evenOdd ? 1 : $crossing[1];
            $nowInside = $evenOdd ? ($winding % 2 !== 0) : ($winding !== 0);

            if ($nowInside && !$inside) {
                $spanStart = $crossing[0];
            } elseif (!$nowInside && $inside) {
                self::span(
                    $spanStart,
                    $crossing[0],
                    $samples,
                    $sampleColumns,
                    $partial,
                    $difference,
                    $touchedFrom,
                    $touchedTo
                );
            }

            $inside = $nowInside;
        }
    }

    /**
     * Marks one horizontal span as covered.
     *
     * The two pixels the span starts and ends in are counted sample by sample;
     * everything between them is whole, and is recorded as a pair of entries in
     * a difference array that the flush turns back into a run. That is what
     * keeps a fill the width of the artwork as cheap as a hairline.
     *
     * @param array<int, int> $partial
     * @param array<int, int> $difference
     */
    private static function span(
        float $from,
        float $to,
        int $samples,
        int $sampleColumns,
        array &$partial,
        array &$difference,
        int &$touchedFrom,
        int &$touchedTo
    ): void {
        // Sample centres sit at (column + 0.5) / samples, so a centre falls in
        // the span when column >= from × samples − 0.5.
        $firstColumn = max(0, (int) ceil(($from * $samples) - 0.5));
        $lastColumn = min($sampleColumns - 1, (int) ceil(($to * $samples) - 0.5) - 1);

        if ($lastColumn < $firstColumn) {
            return;
        }

        $firstPixel = intdiv($firstColumn, $samples);
        $lastPixel = intdiv($lastColumn, $samples);

        if ($firstPixel < $touchedFrom) {
            $touchedFrom = $firstPixel;
        }

        if ($lastPixel > $touchedTo) {
            $touchedTo = $lastPixel;
        }

        if ($firstPixel === $lastPixel) {
            $partial[$firstPixel] = ($partial[$firstPixel] ?? 0) + ($lastColumn - $firstColumn + 1);

            return;
        }

        $partial[$firstPixel] = ($partial[$firstPixel] ?? 0)
            + ((($firstPixel + 1) * $samples) - $firstColumn);
        $partial[$lastPixel] = ($partial[$lastPixel] ?? 0)
            + ($lastColumn - ($lastPixel * $samples) + 1);

        if ($lastPixel > $firstPixel + 1) {
            $difference[$firstPixel + 1] = ($difference[$firstPixel + 1] ?? 0) + $samples;
            $difference[$lastPixel] = ($difference[$lastPixel] ?? 0) - $samples;
        }
    }

    /**
     * Turns the two accumulators into one finished pixel row and resets them.
     *
     * @param array<int, array<int, int>> $coverage
     * @param array<int, int>             $partial
     * @param array<int, int>             $difference
     */
    private static function flush(
        array &$coverage,
        int $pixelRow,
        array &$partial,
        array &$difference,
        int &$touchedFrom,
        int &$touchedTo
    ): void {
        if ($touchedTo < $touchedFrom) {
            return;
        }

        $running = 0;
        $row = [];

        for ($x = $touchedFrom; $x <= $touchedTo; $x++) {
            $running += $difference[$x] ?? 0;
            $total = $running + ($partial[$x] ?? 0);

            if ($total > 0) {
                $row[$x] = $total > self::FULL_COVERAGE ? self::FULL_COVERAGE : $total;
            }
        }

        if ($row !== []) {
            $coverage[$pixelRow] = isset($coverage[$pixelRow])
                ? self::merge($coverage[$pixelRow], $row)
                : $row;
        }

        $partial = [];
        $difference = [];
        $touchedFrom = PHP_INT_MAX;
        $touchedTo = -1;
    }

    /**
     * @param array<int, int> $existing
     * @param array<int, int> $addition
     *
     * @return array<int, int>
     */
    private static function merge(array $existing, array $addition): array
    {
        foreach ($addition as $x => $value) {
            $total = ($existing[$x] ?? 0) + $value;
            $existing[$x] = $total > self::FULL_COVERAGE ? self::FULL_COVERAGE : $total;
        }

        return $existing;
    }
}

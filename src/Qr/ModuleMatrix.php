<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr;

use Redcodede\QrGen\Qr\Exception\InvalidArgument;

/**
 * The finished grid of a QR symbol: true is a dark module, false a light one.
 *
 * This is the whole boundary between encoding and rendering. Everything an
 * encoder knows ends here, and everything a renderer needs starts here — which
 * is why swapping the encoder later touches nothing downstream.
 *
 * The matrix carries no quiet zone. That belongs to rendering, because its size
 * depends on how the symbol is placed, not on how it was encoded.
 */
final class ModuleMatrix
{
    /** @var list<list<bool>> */
    private $rows;

    /** @var int */
    private $size;

    /**
     * @param list<list<bool>> $rows Row-major, indexed [y][x].
     *
     * @throws InvalidArgument if the grid is empty, not square, or holds anything but booleans
     */
    public function __construct(array $rows)
    {
        $size = count($rows);

        if ($size === 0) {
            throw InvalidArgument::emptyMatrix();
        }

        foreach ($rows as $y => $row) {
            if (count($row) !== $size) {
                throw InvalidArgument::notASquareMatrix($size, count($row));
            }

            foreach ($row as $x => $module) {
                if (!is_bool($module)) {
                    throw InvalidArgument::notABooleanModule((int) $x, (int) $y);
                }
            }
        }

        $this->rows = array_values(array_map('array_values', $rows));
        $this->size = $size;
    }

    /**
     * Number of modules per side.
     *
     * Always 17 + 4 × version, so 21 for version 1 and 177 for version 40.
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * @throws InvalidArgument if the coordinates lie outside the matrix
     */
    public function isDark(int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            throw InvalidArgument::moduleOutOfBounds($x, $y, $this->size);
        }

        return $this->rows[$y][$x];
    }

    /**
     * @return list<list<bool>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Renders the matrix as text, for debugging and for reading a test failure.
     *
     * The default glyphs are two characters wide because a terminal cell is
     * taller than it is wide, and a symbol drawn one character per module comes
     * out squashed and hard to compare against a scan.
     */
    public function toAsciiArt(string $dark = '██', string $light = '  '): string
    {
        $lines = [];

        foreach ($this->rows as $row) {
            $line = '';

            foreach ($row as $module) {
                $line .= $module ? $dark : $light;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}

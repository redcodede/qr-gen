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
 *
 * Optionally it also carries which modules are **function patterns** — finders,
 * separators, timing patterns, alignment patterns, format and version
 * information. Those are not covered by error correction, so anything that
 * removes modules (a logo) has to know where they are. Only the encoder knows,
 * hence it travels with the matrix.
 */
final class ModuleMatrix
{
    /** @var list<list<bool>> */
    private $rows;

    /** @var list<list<bool>>|null */
    private $reserved;

    /** @var list<list<bool>>|null */
    private $alignment;

    /** @var int */
    private $size;

    /**
     * @param list<list<bool>>      $rows      Row-major, indexed [y][x].
     * @param list<list<bool>>|null $reserved  Same shape; true where the module
     *                                         belongs to any function pattern.
     *                                         Null when the encoder did not say.
     * @param list<list<bool>>|null $alignment Same shape; the subset of the
     *                                         above that is an alignment
     *                                         pattern. Kept apart because
     *                                         covering one is a compromise
     *                                         while covering a finder is fatal.
     *
     * @throws InvalidArgument if a grid is empty, not square, or holds anything but booleans
     */
    public function __construct(array $rows, ?array $reserved = null, ?array $alignment = null)
    {
        $this->rows = $this->normalize($rows);
        $this->size = count($this->rows);
        $this->reserved = $reserved === null ? null : $this->normalize($reserved);
        $this->alignment = $alignment === null ? null : $this->normalize($alignment);

        if ($this->reserved !== null && count($this->reserved) !== $this->size) {
            throw InvalidArgument::reservedMaskDoesNotMatch($this->size, count($this->reserved));
        }

        if ($this->alignment !== null && count($this->alignment) !== $this->size) {
            throw InvalidArgument::reservedMaskDoesNotMatch($this->size, count($this->alignment));
        }
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     *
     * @return list<list<bool>>
     */
    private function normalize(array $rows): array
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

        /** @var list<list<bool>> $normalized */
        $normalized = array_values(array_map('array_values', $rows));

        return $normalized;
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
     * The QR version this side length corresponds to, 1 to 40.
     */
    public function version(): int
    {
        return intdiv($this->size - 17, 4);
    }

    /**
     * @throws InvalidArgument if the coordinates lie outside the matrix
     */
    public function isDark(int $x, int $y): bool
    {
        $this->guardBounds($x, $y);

        return $this->rows[$y][$x];
    }

    /**
     * Whether the module belongs to a function pattern and therefore must not
     * be covered.
     *
     * Reports false when the encoder supplied no mask. Ask hasReservedInfo()
     * first if the difference between "not a function pattern" and "nobody
     * said" matters — for validating a logo placement, it does.
     *
     * @throws InvalidArgument if the coordinates lie outside the matrix
     */
    public function isReserved(int $x, int $y): bool
    {
        $this->guardBounds($x, $y);

        return $this->reserved !== null && $this->reserved[$y][$x];
    }

    public function hasReservedInfo(): bool
    {
        return $this->reserved !== null;
    }

    /**
     * Whether the module belongs to an alignment pattern.
     *
     * Alignment patterns are the one kind of function pattern where covering is
     * a judgement call rather than a mistake. A scanner uses them to correct
     * perspective and warp, and a larger symbol has several — losing one costs
     * tolerance on a curved or angled surface, but the remaining ones still
     * locate the grid. Covering a finder or a timing pattern, by contrast,
     * removes the geometry a scanner needs to find the symbol at all.
     *
     * The distinction is not academic. For a good many versions **an alignment
     * pattern sits exactly at the centre of the symbol**, so a centred logo
     * cannot avoid one however small it is. Measured against the library's
     * version table: versions 7 to 13, and 21, 23, 25 and 27. Versions 1 to 6,
     * 14 to 20 and 28 upward leave the middle free. Refusing outright would
     * make a centred logo impossible on all of the former.
     *
     * @throws InvalidArgument if the coordinates lie outside the matrix
     */
    public function isAlignmentPattern(int $x, int $y): bool
    {
        $this->guardBounds($x, $y);

        return $this->alignment !== null && $this->alignment[$y][$x];
    }

    public function hasAlignmentInfo(): bool
    {
        return $this->alignment !== null;
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

    private function guardBounds(int $x, int $y): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            throw InvalidArgument::moduleOutOfBounds($x, $y, $this->size);
        }
    }
}

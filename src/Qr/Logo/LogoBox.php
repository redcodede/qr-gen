<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * How much of the symbol a logo may take, in modules.
 *
 * The box is the **cleared** area: logo plus the light margin around it. That
 * margin does two things — it separates the artwork visually, and it stops a
 * logo edge from being read as a module edge.
 *
 * Both sides have to be odd. A QR symbol is always an odd number of modules
 * across (17 + 4 × version), so an even box would sit half a module off the
 * grid and clear parts of modules instead of whole ones.
 *
 * How large is safe is not a fixed number. Error correction recovers a share of
 * **codewords** while this box is measured in **modules**, so the two are not
 * the same unit and the relationship is a rule of thumb: at level H, around
 * a third of the symbol's width is comfortable. What is not a rule of thumb is
 * the function patterns — those carry no error correction at all, and
 * placeIn() refuses a box that touches one.
 */
final class LogoBox
{
    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var int */
    private $margin;

    private function __construct(int $width, int $height, int $margin)
    {
        if ($width % 2 === 0 || $height % 2 === 0) {
            throw InvalidArgument::logoBoxNotOdd($width, $height);
        }

        if ($width < 3 || $height < 3) {
            throw InvalidArgument::outOfRange('Logo box side', min($width, $height), 3, 177);
        }

        if ($margin < 0) {
            throw InvalidArgument::outOfRange('Logo margin', $margin, 0, 8);
        }

        if ((2 * $margin) >= min($width, $height)) {
            throw InvalidArgument::logoMarginTooLarge($margin, min($width, $height));
        }

        $this->width = $width;
        $this->height = $height;
        $this->margin = $margin;
    }

    public static function square(int $modules, int $margin = 1): self
    {
        return new self($modules, $modules, $margin);
    }

    public static function of(int $width, int $height, int $margin = 1): self
    {
        return new self($width, $height, $margin);
    }

    /**
     * Fits a box of the given aspect ratio into a share of the symbol's width.
     *
     * Rounds each side down to the nearest odd number, which is why the result
     * is never larger than asked for.
     */
    public static function forAspectRatio(float $aspectRatio, int $widthModules, int $margin = 1): self
    {
        $height = $aspectRatio >= 1.0
            ? (int) round($widthModules / $aspectRatio)
            : $widthModules;

        $width = $aspectRatio >= 1.0
            ? $widthModules
            : (int) round($widthModules * $aspectRatio);

        return new self(self::toOdd($width), self::toOdd($height), $margin);
    }

    private static function toOdd(int $value): int
    {
        return $value % 2 === 0 ? max(3, $value - 1) : max(3, $value);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function margin(): int
    {
        return $this->margin;
    }

    /**
     * Centres the box in the matrix and checks it may go there.
     *
     * @throws InvalidArgument if the box does not fit, reaches a finder corner,
     *                         or covers a function pattern
     */
    public function placeIn(ModuleMatrix $matrix): LogoPlacement
    {
        $size = $matrix->size();

        if ($this->width > $size || $this->height > $size) {
            throw InvalidArgument::logoBoxTooLarge($this->width, $this->height, $size);
        }

        $x = intdiv($size - $this->width, 2);
        $y = intdiv($size - $this->height, 2);

        $this->guardFinderZones($x, $y, $size);
        $this->guardFunctionPatterns($matrix, $x, $y);

        return new LogoPlacement($x, $y, $this->width, $this->height, $this->margin);
    }

    /**
     * The three finder patterns and their separators occupy the first and last
     * eight modules of each axis. Checked geometrically so that a matrix built
     * by hand, without a function pattern mask, is still protected.
     */
    private function guardFinderZones(int $x, int $y, int $size): void
    {
        $reach = 8;

        if ($x < $reach || $y < $reach || $x + $this->width > $size - $reach || $y + $this->height > $size - $reach) {
            throw InvalidArgument::logoTouchesFinderZone();
        }
    }

    private function guardFunctionPatterns(ModuleMatrix $matrix, int $x, int $y): void
    {
        if (!$matrix->hasReservedInfo()) {
            return;
        }

        $covered = 0;

        for ($row = $y; $row < $y + $this->height; $row++) {
            for ($column = $x; $column < $x + $this->width; $column++) {
                if ($matrix->isReserved($column, $row)) {
                    $covered++;
                }
            }
        }

        if ($covered > 0) {
            throw InvalidArgument::logoCoversFunctionPattern($covered);
        }
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

/**
 * Where a logo box actually sits, once a LogoBox has been checked against a
 * matrix. All values are in modules, relative to the symbol without quiet zone.
 *
 * Separate from LogoBox because a box is a request and this is the answer: the
 * same box placed in a larger symbol lands somewhere else.
 */
final class LogoPlacement
{
    /** @var int */
    private $x;

    /** @var int */
    private $y;

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var int */
    private $margin;

    public function __construct(int $x, int $y, int $width, int $height, int $margin)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->margin = $margin;
    }

    /**
     * Whether this module is cleared, and therefore must not be drawn.
     */
    public function covers(int $x, int $y): bool
    {
        return $x >= $this->x
            && $x < $this->x + $this->width
            && $y >= $this->y
            && $y < $this->y + $this->height;
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    /**
     * Number of modules the box clears. Against the symbol's total this is the
     * figure to weigh against the error correction level.
     */
    public function clearedModules(): int
    {
        return $this->width * $this->height;
    }

    public function drawableX(): int
    {
        return $this->x + $this->margin;
    }

    public function drawableY(): int
    {
        return $this->y + $this->margin;
    }

    public function drawableWidth(): int
    {
        return $this->width - (2 * $this->margin);
    }

    public function drawableHeight(): int
    {
        return $this->height - (2 * $this->margin);
    }
}

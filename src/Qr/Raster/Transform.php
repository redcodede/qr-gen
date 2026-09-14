<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * An affine 2×3 matrix, the only coordinate maths the rasteriser needs.
 *
 * SVG places artwork with `transform` attributes that nest, and the flattener
 * wants its points in device pixels rather than in the logo's own units: a
 * curve is subdivided until it is flat *on the page*, which is a statement
 * about pixels. So the current transformation matrix is carried down the tree
 * and applied as each point is produced, rather than the geometry being
 * flattened first and moved afterwards.
 *
 * Laid out as SVG writes it, `matrix(a b c d e f)`:
 *
 *     | a  c  e |
 *     | b  d  f |
 *     | 0  0  1 |
 */
final class Transform
{
    /** @var float */
    private $a;

    /** @var float */
    private $b;

    /** @var float */
    private $c;

    /** @var float */
    private $d;

    /** @var float */
    private $e;

    /** @var float */
    private $f;

    private function __construct(float $a, float $b, float $c, float $d, float $e, float $f)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
        $this->d = $d;
        $this->e = $e;
        $this->f = $f;
    }

    public static function identity(): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, 0.0, 0.0);
    }

    public static function of(float $a, float $b, float $c, float $d, float $e, float $f): self
    {
        return new self($a, $b, $c, $d, $e, $f);
    }

    public static function translation(float $x, float $y): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, $x, $y);
    }

    public static function scaling(float $x, float $y): self
    {
        return new self($x, 0.0, 0.0, $y, 0.0, 0.0);
    }

    /**
     * This matrix followed by $inner applied first — the product `$this × $inner`.
     *
     * That order is what both uses need. A `transform="translate(…) scale(…)"`
     * list means scale first, then translate, so the list folds left to right
     * with this method; and a child element's transform happens inside its
     * parent's, so the parent's matrix concatenated with the child's is the
     * child's device mapping.
     */
    public function concat(self $inner): self
    {
        return new self(
            $this->a * $inner->a + $this->c * $inner->b,
            $this->b * $inner->a + $this->d * $inner->b,
            $this->a * $inner->c + $this->c * $inner->d,
            $this->b * $inner->c + $this->d * $inner->d,
            $this->a * $inner->e + $this->c * $inner->f + $this->e,
            $this->b * $inner->e + $this->d * $inner->f + $this->f
        );
    }

    public function applyX(float $x, float $y): float
    {
        return $this->a * $x + $this->c * $y + $this->e;
    }

    public function applyY(float $x, float $y): float
    {
        return $this->b * $x + $this->d * $y + $this->f;
    }

    /**
     * How much this matrix magnifies lengths, as one number.
     *
     * The square root of the determinant is the factor by which areas grow,
     * which is the right single answer for a matrix that scales the axes
     * differently: it sits between the two, so the flattening tolerance is
     * never wrong by more than the anisotropy. Curve subdivision needs a
     * length scale, not an exact one.
     */
    public function magnitude(): float
    {
        return sqrt(abs($this->a * $this->d - $this->b * $this->c));
    }

    /**
     * Parses an SVG transform list.
     *
     * @throws LogoRejected on a function this rasteriser does not know
     */
    public static function parse(string $list): self
    {
        $list = trim($list);

        if ($list === '') {
            return self::identity();
        }

        $pattern = '/([A-Za-z]+)\s*\(([^)]*)\)/';

        if (preg_match_all($pattern, $list, $matches, PREG_SET_ORDER) === false) {
            throw LogoRejected::unreadableTransform($list);
        }

        // Everything outside the recognised calls has to be separators. A
        // leftover means something was not understood, and silently ignoring it
        // would move the artwork.
        $remainder = trim((string) preg_replace($pattern, '', $list), " \t\r\n,");

        if ($remainder !== '') {
            throw LogoRejected::unreadableTransform($list);
        }

        $combined = self::identity();

        foreach ($matches as $match) {
            $combined = $combined->concat(self::call(strtolower($match[1]), self::numbers($match[2]), $list));
        }

        return $combined;
    }

    /**
     * @param list<float> $arguments
     */
    private static function call(string $name, array $arguments, string $source): self
    {
        $count = count($arguments);

        if ($name === 'matrix' && $count === 6) {
            return new self(...$arguments);
        }

        if ($name === 'translate' && ($count === 1 || $count === 2)) {
            return self::translation($arguments[0], $count === 2 ? $arguments[1] : 0.0);
        }

        if ($name === 'scale' && ($count === 1 || $count === 2)) {
            return self::scaling($arguments[0], $count === 2 ? $arguments[1] : $arguments[0]);
        }

        if ($name === 'rotate' && ($count === 1 || $count === 3)) {
            $radians = deg2rad($arguments[0]);
            $rotation = new self(cos($radians), sin($radians), -sin($radians), cos($radians), 0.0, 0.0);

            if ($count === 1) {
                return $rotation;
            }

            return self::translation($arguments[1], $arguments[2])
                ->concat($rotation)
                ->concat(self::translation(-$arguments[1], -$arguments[2]));
        }

        if ($name === 'skewx' && $count === 1) {
            return new self(1.0, 0.0, tan(deg2rad($arguments[0])), 1.0, 0.0, 0.0);
        }

        if ($name === 'skewy' && $count === 1) {
            return new self(1.0, tan(deg2rad($arguments[0])), 0.0, 1.0, 0.0, 0.0);
        }

        throw LogoRejected::unreadableTransform($source);
    }

    /**
     * @return list<float>
     */
    private static function numbers(string $arguments): array
    {
        preg_match_all('/-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/', $arguments, $found);

        return array_map('floatval', $found[0]);
    }
}

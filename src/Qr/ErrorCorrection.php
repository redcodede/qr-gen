<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr;

use Redcodede\QrGen\Qr\Exception\InvalidArgument;

/**
 * One of the four error correction levels defined by ISO/IEC 18004.
 *
 * A higher level survives more damage but needs more modules for the same
 * payload, which makes the printed symbol denser. The percentages below are the
 * share of codewords that can be recovered.
 *
 * Not a native enum on purpose: this package supports PHP 8.0, where enums do
 * not exist yet.
 */
final class ErrorCorrection
{
    /** ~7% recovery. Smallest symbol, least reserve. */
    public const LOW = 'L';

    /** ~15% recovery. The usual choice for a code without a logo. */
    public const MEDIUM = 'M';

    /** ~25% recovery. */
    public const QUARTILE = 'Q';

    /** ~30% recovery. Required once a logo covers part of the symbol. */
    public const HIGH = 'H';

    /** @var string */
    private $level;

    private function __construct(string $level)
    {
        $this->level = $level;
    }

    public static function low(): self
    {
        return new self(self::LOW);
    }

    public static function medium(): self
    {
        return new self(self::MEDIUM);
    }

    public static function quartile(): self
    {
        return new self(self::QUARTILE);
    }

    public static function high(): self
    {
        return new self(self::HIGH);
    }

    /**
     * Accepts "L", "M", "Q", "H" in either case, so a value straight out of a
     * config file or a form works.
     *
     * @throws InvalidArgument if the level is not one of the four
     */
    public static function fromString(string $level): self
    {
        $normalized = strtoupper(trim($level));

        if (!in_array($normalized, self::all(), true)) {
            throw InvalidArgument::unknownErrorCorrectionLevel($level, self::all());
        }

        return new self($normalized);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::LOW, self::MEDIUM, self::QUARTILE, self::HIGH];
    }

    public function value(): string
    {
        return $this->level;
    }

    /**
     * Share of codewords the level can recover, per ISO/IEC 18004.
     *
     * Careful with what this is compared against. It is a share of
     * **codewords**, while a logo box is measured in **modules**. The two are
     * not the same unit, so weighing one against the other is a heuristic and
     * wants a safety factor — see LogoFit. What is exact is the function
     * pattern check, which does not involve this number at all.
     */
    public function recoveryRate(): float
    {
        switch ($this->level) {
            case self::LOW:
                return 0.07;

            case self::MEDIUM:
                return 0.15;

            case self::QUARTILE:
                return 0.25;

            default:
                return 0.30;
        }
    }

    public function equals(self $other): bool
    {
        return $this->level === $other->level;
    }

    public function __toString(): string
    {
        return $this->level;
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * A configuration that survives: the level that was chosen, the symbol it
 * produced, and where the logo goes in it.
 *
 * The matrix comes along so the caller does not encode a second time, and the
 * numbers come along so a report can say why this configuration and not
 * another.
 */
final class LogoFitResult
{
    /** @var ErrorCorrection */
    private $level;

    /** @var ModuleMatrix */
    private $matrix;

    /** @var LogoPlacement */
    private $placement;

    /** @var float */
    private $budget;

    public function __construct(
        ErrorCorrection $level,
        ModuleMatrix $matrix,
        LogoPlacement $placement,
        float $budget
    ) {
        $this->level = $level;
        $this->matrix = $matrix;
        $this->placement = $placement;
        $this->budget = $budget;
    }

    public function level(): ErrorCorrection
    {
        return $this->level;
    }

    public function matrix(): ModuleMatrix
    {
        return $this->matrix;
    }

    public function placement(): LogoPlacement
    {
        return $this->placement;
    }

    /**
     * Share of the symbol's modules the logo box clears, 0 to 1.
     */
    public function clearedShare(): float
    {
        return $this->placement->clearedModules() / ($this->matrix->size() ** 2);
    }

    /**
     * The share that was allowed — the level's recovery rate times the safety
     * factor.
     */
    public function budget(): float
    {
        return $this->budget;
    }

    /**
     * How much of the allowance is left over, 0 to 1. Higher is more forgiving
     * of print defects, dirt and bad light.
     */
    public function headroom(): float
    {
        if ($this->budget <= 0.0) {
            return 0.0;
        }

        return max(0.0, 1.0 - ($this->clearedShare() / $this->budget));
    }

    /**
     * Whether this configuration gives up an alignment pattern. Unavoidable on
     * the versions where one sits at the centre — 7 to 13, and 21, 23, 25, 27.
     */
    public function compromisesAlignment(): bool
    {
        return $this->placement->compromisesAlignment();
    }
}

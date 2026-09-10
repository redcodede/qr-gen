<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

use Redcodede\QrGen\Qr\Contract\QrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\NoFittingLevel;

/**
 * Finds the error correction level at which a logo box survives.
 *
 * The problem this solves is that **the symbol size is not a setting.** It
 * follows from the payload and the error correction level, so a box that fits
 * at H stops fitting at M — the symbol got smaller, not the logo. Picking the
 * level by hand means knowing that relationship; this class works it out.
 *
 * Raising the level helps twice over. There is more recovery to spend, and the
 * symbol usually moves to a larger version, so the same box covers a smaller
 * share of it. The cost is density: more modules across the same printed width
 * means smaller modules. **That is why the answer is the lowest level that
 * survives, not the highest available** — the lowest keeps the modules as large
 * as the constraints allow, which is what a scanner has an easier time with.
 *
 * On the weighing itself, plainly: the cleared area is a share of **modules**
 * and the recovery rate is a share of **codewords**. Those are not the same
 * unit, so the comparison is a heuristic. The safety factor is what makes it
 * defensible, and it is a constructor argument rather than a constant because
 * whoever has run a print proof knows better than this class does. What is not
 * a heuristic is the function pattern check inside LogoBox.
 */
final class LogoFit
{
    /**
     * Spend at most half the recovery rate on the logo.
     *
     * The other half pays for what happens after printing: ink spread, a
     * scuffed label, a fingerprint, bad light, a phone held at an angle. A logo
     * that eats the whole allowance leaves a code that scans on the screen and
     * fails on the shelf.
     */
    public const DEFAULT_BUDGET_SHARE = 0.5;

    /** @var QrEncoder */
    private $encoder;

    /** @var float */
    private $budgetShare;

    public function __construct(QrEncoder $encoder, float $budgetShare = self::DEFAULT_BUDGET_SHARE)
    {
        if ($budgetShare <= 0.0 || $budgetShare > 1.0) {
            throw InvalidArgument::budgetShareOutOfRange($budgetShare);
        }

        $this->encoder = $encoder;
        $this->budgetShare = $budgetShare;
    }

    /**
     * The lowest level at which this box survives, with the symbol it produced.
     *
     * @throws NoFittingLevel if no level works, with the reason for each
     */
    public function lowestLevelFor(string $data, LogoBox $box): LogoFitResult
    {
        $reasons = [];

        foreach (ErrorCorrection::all() as $letter) {
            $level = ErrorCorrection::fromString($letter);
            $result = $this->tryLevel($data, $box, $level);

            if ($result instanceof LogoFitResult) {
                return $result;
            }

            $reasons[$letter] = $result;
        }

        throw NoFittingLevel::forBox(
            $box->width(),
            $box->height(),
            $reasons,
            $this->suggestion($data, $box)
        );
    }

    /**
     * Largest box that survives at this level, keeping the requested aspect
     * ratio and never growing beyond what was asked for.
     *
     * Returns null when even the smallest sensible box fails, which means the
     * payload and the level leave no room at all.
     */
    public function largestFittingAt(string $data, ErrorCorrection $level, LogoBox $desired): ?LogoBox
    {
        $ratio = $desired->width() / $desired->height();

        for ($width = $desired->width(); $width >= 3; $width -= 2) {
            $candidate = LogoBox::forAspectRatio($ratio, $width, $desired->margin());

            if ($desired->allowsAlignmentPatterns()) {
                $candidate = $candidate->allowingAlignmentPatterns();
            }

            if ($this->tryLevel($data, $candidate, $level) instanceof LogoFitResult) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return LogoFitResult|string The result, or why this level does not work
     */
    private function tryLevel(string $data, LogoBox $box, ErrorCorrection $level)
    {
        $matrix = $this->encoder->encode($data, $level);

        try {
            $placement = $box->placeIn($matrix);
        } catch (InvalidArgument $exception) {
            return sprintf('%dx%d symbol — %s', $matrix->size(), $matrix->size(), $exception->getMessage());
        }

        $budget = $level->recoveryRate() * $this->budgetShare;
        $share = $placement->clearedModules() / ($matrix->size() ** 2);

        if ($share > $budget) {
            return sprintf(
                '%dx%d symbol — the box clears %.1f%% of the modules, above the %.1f%% this level '
                . 'allows (%.0f%% recovery at a %.0f%% safety factor).',
                $matrix->size(),
                $matrix->size(),
                $share * 100,
                $budget * 100,
                $level->recoveryRate() * 100,
                $this->budgetShare * 100
            );
        }

        return new LogoFitResult($level, $matrix, $placement, $budget);
    }

    private function suggestion(string $data, LogoBox $box): ?string
    {
        $strongest = ErrorCorrection::high();
        $largest = $this->largestFittingAt($data, $strongest, $box);

        if ($largest === null) {
            return 'Not even a 3x3 box survives at level H, so there is no room for artwork on '
                . 'this payload at all. Shorten the payload.';
        }

        return sprintf(
            'At level H the largest box that survives is %dx%d modules.',
            $largest->width(),
            $largest->height()
        );
    }
}

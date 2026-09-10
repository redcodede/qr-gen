<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use RuntimeException;

/**
 * No error correction level lets this logo box survive on this payload.
 *
 * Carries the reason for every level it tried, because "it does not fit" is
 * useless on its own: whether to shrink the box, change the payload or accept a
 * compromise depends on *which* wall was hit at which level.
 */
final class NoFittingLevel extends RuntimeException implements QrGenException
{
    /** @var array<string, string> */
    private $reasons;

    /**
     * @param array<string, string> $reasons Level letter to why it failed
     */
    public function __construct(string $message, array $reasons)
    {
        parent::__construct($message);

        $this->reasons = $reasons;
    }

    /**
     * @param array<string, string> $reasons
     */
    public static function forBox(int $width, int $height, array $reasons, ?string $suggestion): self
    {
        $lines = [];

        foreach ($reasons as $level => $reason) {
            $lines[] = sprintf('  %s: %s', $level, $reason);
        }

        $message = sprintf(
            "No error correction level lets a %dx%d logo box survive on this payload.\n%s",
            $width,
            $height,
            implode("\n", $lines)
        );

        if ($suggestion !== null) {
            $message .= "\n" . $suggestion;
        }

        return new self($message, $reasons);
    }

    /**
     * @return array<string, string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}

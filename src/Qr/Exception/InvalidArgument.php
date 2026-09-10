<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use InvalidArgumentException;

final class InvalidArgument extends InvalidArgumentException implements QrGenException
{
    public static function emptyData(): self
    {
        return new self('There is nothing to encode: the data is an empty string.');
    }

    /**
     * @param list<string> $allowed
     */
    public static function unknownErrorCorrectionLevel(string $given, array $allowed): self
    {
        return new self(sprintf(
            'Unknown error correction level "%s". Allowed: %s.',
            $given,
            implode(', ', $allowed)
        ));
    }

    public static function notASquareMatrix(int $rows, int $columns): self
    {
        return new self(sprintf(
            'A QR matrix has to be square, got %d rows and %d columns.',
            $rows,
            $columns
        ));
    }

    public static function emptyMatrix(): self
    {
        return new self('A QR matrix cannot be empty.');
    }

    public static function notABooleanModule(int $x, int $y): self
    {
        return new self(sprintf(
            'Module at %d,%d is not a boolean. Every module is either dark (true) or light (false).',
            $x,
            $y
        ));
    }

    public static function moduleOutOfBounds(int $x, int $y, int $size): self
    {
        return new self(sprintf(
            'Module %d,%d is outside the matrix, which is %dx%d.',
            $x,
            $y,
            $size,
            $size
        ));
    }

    public static function outOfRange(string $name, int $given, int $min, int $max): self
    {
        return new self(sprintf(
            '%s has to be between %d and %d, got %d.',
            $name,
            $min,
            $max,
            $given
        ));
    }

    public static function notAColor(string $name, string $given): self
    {
        return new self(sprintf(
            '%s has to be a hex color such as "#000" or "#1a2b3c", or the keyword "none", got "%s".',
            $name,
            $given
        ));
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use RuntimeException;
use Throwable;

final class EncodingFailed extends RuntimeException implements QrGenException
{
    /**
     * Deliberately reports the payload's length instead of the payload itself.
     *
     * The most common cause of a failure here is data that exceeds the capacity
     * of a QR symbol, and the length is what tells you that. Echoing the data
     * would put it into every log and error report that catches this exception,
     * which is exactly what this package promises not to do.
     */
    public static function forPayloadOfLength(int $bytes, Throwable $previous): self
    {
        return new self(
            sprintf(
                'Encoding a payload of %d bytes failed. It may exceed the capacity of a QR '
                . 'symbol at the requested error correction level. See the previous exception.',
                $bytes
            ),
            0,
            $previous
        );
    }

    public static function notASquareMatrix(int $width, int $height): self
    {
        return new self(sprintf(
            'The encoder returned a %dx%d matrix. A QR symbol is always square, '
            . 'so this is a bug in the encoder rather than in the input.',
            $width,
            $height
        ));
    }
}

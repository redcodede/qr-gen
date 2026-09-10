<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Contract;

use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\EncodingFailed;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * Turns a string into the module grid of a QR symbol.
 *
 * This covers steps 1 to 6 of ISO/IEC 18004 — mode, version and error
 * correction level, bit stream, Reed-Solomon, module placement, masking. It is
 * the only part of this package currently backed by a third-party library, and
 * this interface is why that stays an implementation detail.
 *
 * An implementation MUST NOT choose the encoding mode by assumption. It looks at
 * the data and picks the narrowest mode the data actually fits, because forcing
 * a mode either corrupts content or wastes capacity.
 */
interface QrEncoder
{
    /**
     * @param string $data Encoded as-is. Any string, not only a URL.
     *
     * @throws InvalidArgument if the data is empty
     * @throws EncodingFailed  if the data does not fit a QR symbol at this level
     */
    public function encode(string $data, ErrorCorrection $level): ModuleMatrix;
}

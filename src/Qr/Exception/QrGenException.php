<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use Throwable;

/**
 * Marker interface for every exception this package throws.
 *
 * Consumers can catch this one type instead of enumerating our classes, and it
 * lets them tell our failures apart from those of whatever encoder backs us.
 */
interface QrGenException extends Throwable
{
}

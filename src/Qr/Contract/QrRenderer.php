<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Contract;

use Redcodede\QrGen\Qr\ModuleMatrix;

/**
 * Turns a module grid into a file's worth of bytes.
 *
 * This is step 7, and it is ours. A renderer returns a string and never touches
 * the filesystem: whoever called it decides whether that string becomes an HTTP
 * response, a file, or neither.
 *
 * The renderer carries its own settings, injected on construction, so a caller
 * that renders many symbols configures once and renders in a loop.
 */
interface QrRenderer
{
    public function render(ModuleMatrix $matrix): string;

    /**
     * Media type for a Content-Type header, e.g. "image/svg+xml".
     */
    public function mimeType(): string;

    /**
     * Filename extension without the dot, e.g. "svg".
     */
    public function fileExtension(): string;
}

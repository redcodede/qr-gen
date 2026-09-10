<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Contract;

/**
 * Artwork to place in the middle of a symbol.
 *
 * A logo hands over **markup, not a path**. Reading a file is the caller's job,
 * which keeps the core free of filesystem access and lets the same logo come
 * from a Statamic asset, a Composer package or a test fixture without this
 * package caring which.
 *
 * The intrinsic size is only used to work out the aspect ratio and the scale
 * factor. Its unit is irrelevant as long as both dimensions share it.
 */
interface Logo
{
    /**
     * SVG markup to drop inside a <g>, already safe to embed: no script, no
     * external reference, no identifier that could collide with a second copy
     * on the same page.
     */
    public function markup(): string;

    public function width(): float;

    public function height(): float;
}

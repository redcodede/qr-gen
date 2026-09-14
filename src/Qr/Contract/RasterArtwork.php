<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Contract;

/**
 * Artwork that is already pixels, rather than outlines to be drawn.
 *
 * A logo delivered as a PNG cannot be flattened and filled: there is nothing to
 * flatten. It is resampled to the size it will occupy and composited. This
 * interface is how the renderers tell the two apart, and it sits beside Logo
 * rather than replacing it — a PNG logo is still a Logo, because the SVG
 * renderer embeds it as an <image> and neither knows nor needs to know what is
 * inside.
 *
 * Pixels are **8-bit RGBA in one binary string**, four bytes each, row by row,
 * with straight (not premultiplied) alpha. A string rather than an array
 * because a logo runs to hundreds of thousands of pixels, and the array would
 * cost tens of megabytes to say the same thing.
 */
interface RasterArtwork
{
    /**
     * @return string Four bytes per pixel: red, green, blue, alpha
     */
    public function pixels(): string;

    public function pixelWidth(): int;

    public function pixelHeight(): int;
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Render;

use Redcodede\QrGen\Qr\Exception\InvalidArgument;

/**
 * Settings for the SVG renderer. Immutable; every wither returns a new instance.
 *
 * Colors are validated on the way in rather than escaped on the way out. A color
 * ends up inside an SVG attribute, so accepting an arbitrary string here would
 * let a caller close the attribute and write markup of their own. Restricting
 * the value to hex notation or the keyword "none" makes that impossible instead
 * of merely unlikely.
 */
final class SvgOptions
{
    private const COLOR_PATTERN = '/^(?:#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})|none)$/i';

    private const MIN_MODULE_SIZE = 1;
    private const MAX_MODULE_SIZE = 256;
    private const MAX_QUIET_ZONE = 32;

    /** Modules of quiet zone required by ISO/IEC 18004. */
    public const SPEC_QUIET_ZONE = 4;

    /** @var int */
    private $moduleSize = 8;

    /** @var int */
    private $quietZone = self::SPEC_QUIET_ZONE;

    /** @var string */
    private $darkColor = '#000000';

    /** @var string */
    private $lightColor = '#ffffff';

    /** @var string|null */
    private $title;

    /** @var bool */
    private $xmlDeclaration = false;

    public static function default(): self
    {
        return new self();
    }

    /**
     * Edge length of one module in pixels, used for the width and height
     * attributes. It does not affect the geometry: the viewBox stays in module
     * units, so the same file scales losslessly to any size.
     *
     * @throws InvalidArgument
     */
    public function withModuleSize(int $pixels): self
    {
        if ($pixels < self::MIN_MODULE_SIZE || $pixels > self::MAX_MODULE_SIZE) {
            throw InvalidArgument::outOfRange(
                'Module size',
                $pixels,
                self::MIN_MODULE_SIZE,
                self::MAX_MODULE_SIZE
            );
        }

        $clone = clone $this;
        $clone->moduleSize = $pixels;

        return $clone;
    }

    /**
     * Width of the light border, in modules.
     *
     * The specification requires 4 and scanners rely on it. Zero is allowed for
     * the case where the surrounding layout already provides the margin, but a
     * symbol placed flush against artwork without one may not be readable.
     *
     * @throws InvalidArgument
     */
    public function withQuietZone(int $modules): self
    {
        if ($modules < 0 || $modules > self::MAX_QUIET_ZONE) {
            throw InvalidArgument::outOfRange('Quiet zone', $modules, 0, self::MAX_QUIET_ZONE);
        }

        $clone = clone $this;
        $clone->quietZone = $modules;

        return $clone;
    }

    /**
     * @param string $dark  Hex color, e.g. "#000" or "#1a2b3c".
     * @param string $light Hex color, or "none" to leave the background
     *                      transparent and let whatever is behind show through.
     *
     * @throws InvalidArgument
     */
    public function withColors(string $dark, string $light): self
    {
        if (preg_match(self::COLOR_PATTERN, $dark) !== 1) {
            throw InvalidArgument::notAColor('Dark color', $dark);
        }

        if (preg_match(self::COLOR_PATTERN, $light) !== 1) {
            throw InvalidArgument::notAColor('Light color', $light);
        }

        $clone = clone $this;
        $clone->darkColor = $dark;
        $clone->lightColor = $light;

        return $clone;
    }

    /**
     * Accessible name, rendered as a <title> element. Escaped by the renderer.
     *
     * Note that a title becomes part of the file and travels with it. Do not put
     * anything in here that should not leave the building.
     */
    public function withTitle(?string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    /**
     * Whether to prepend an XML declaration.
     *
     * On for a standalone .svg file, off when the markup is inlined into an HTML
     * document, where a declaration in the middle of the page is not valid.
     */
    public function withXmlDeclaration(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->xmlDeclaration = $enabled;

        return $clone;
    }

    public function moduleSize(): int
    {
        return $this->moduleSize;
    }

    public function quietZone(): int
    {
        return $this->quietZone;
    }

    public function darkColor(): string
    {
        return $this->darkColor;
    }

    public function lightColor(): string
    {
        return $this->lightColor;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function hasBackground(): bool
    {
        return strcasecmp($this->lightColor, 'none') !== 0;
    }

    public function hasXmlDeclaration(): bool
    {
        return $this->xmlDeclaration;
    }
}

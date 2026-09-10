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

    public static function reservedMaskDoesNotMatch(int $size, int $maskSize): self
    {
        return new self(sprintf(
            'The function pattern mask is %d rows for a %dx%d matrix.',
            $maskSize,
            $size,
            $size
        ));
    }

    public static function logoBoxNotOdd(int $width, int $height): self
    {
        return new self(sprintf(
            'A logo box has to be an odd number of modules on both sides, got %dx%d. A QR symbol '
            . 'is always odd (17 + 4 x version), so an even box would sit half a module off the '
            . 'grid and cover parts of modules instead of whole ones.',
            $width,
            $height
        ));
    }

    public static function logoBoxTooLarge(int $width, int $height, int $size): self
    {
        return new self(sprintf(
            'A logo box of %dx%d modules does not fit a %dx%d symbol.',
            $width,
            $height,
            $size,
            $size
        ));
    }

    public static function logoMarginTooLarge(int $margin, int $shorterSide): self
    {
        return new self(sprintf(
            'A margin of %d modules leaves nothing to draw in on a box %d modules across.',
            $margin,
            $shorterSide
        ));
    }

    public static function logoCoversFunctionPattern(
        int $modules,
        int $width,
        int $height,
        int $size
    ): self {
        return new self(sprintf(
            'A logo box of %dx%d modules in a %dx%d symbol would cover %d module(s) of a '
            . 'function pattern — a finder, timing or alignment pattern. Those carry no error '
            . 'correction, so the symbol would lose the geometry a scanner needs. Either use a '
            . 'smaller box, or raise the error correction level: that moves the symbol to a '
            . 'larger version whose alignment patterns sit elsewhere.',
            $width,
            $height,
            $size,
            $size,
            $modules
        ));
    }

    public static function logoCoversAlignmentPattern(
        int $modules,
        int $width,
        int $height,
        int $size
    ): self {
        return new self(sprintf(
            'A logo box of %dx%d modules in a %dx%d symbol would cover %d module(s) of an '
            . 'alignment pattern. Unlike a finder, that is a trade rather than a mistake: a '
            . 'scanner uses alignment patterns to correct perspective and warp, so losing one '
            . 'costs tolerance on a curved or angled surface while the others still locate the '
            . 'grid. Either use a smaller box, change the error correction level so the symbol '
            . 'lands on a version whose alignment patterns sit elsewhere, or accept it with '
            . 'LogoBox::allowingAlignmentPatterns(). Note that on versions 7 to 13, and on 21, '
            . '23, 25 and 27, an alignment pattern sits exactly at the centre, so on those a '
            . 'centred logo cannot avoid one however small it is.',
            $width,
            $height,
            $size,
            $size,
            $modules
        ));
    }

    public static function logoTouchesFinderZone(
        int $width,
        int $height,
        int $size,
        int $largestSide
    ): self {
        return new self(sprintf(
            'A logo box of %dx%d modules leaves no room for the finder patterns in a %dx%d '
            . 'symbol. The three finders and their separators occupy the outer 8 modules of every '
            . 'side, so the largest centred box here is %d modules on either axis. '
            . 'The symbol size is not a setting: it follows from the payload and the error '
            . 'correction level. If the box is the size you want, raise the level — that moves '
            . 'the symbol to a larger version and makes room.',
            $width,
            $height,
            $size,
            $size,
            $largestSide
        ));
    }

    /**
     * @param list<string> $available
     */
    public static function unknownLocale(string $given, array $available): self
    {
        return new self(sprintf(
            'There is no catalogue for the locale "%s". Available: %s.',
            $given,
            implode(', ', $available)
        ));
    }

    public static function missingCatalogue(string $locale, string $path): self
    {
        return new self(sprintf(
            'The catalogue for "%s" is declared available but the file is not there: %s',
            $locale,
            $path
        ));
    }

    public static function budgetShareOutOfRange(float $given): self
    {
        return new self(sprintf(
            'The safety factor has to be greater than 0 and at most 1, got %s. It is the share '
            . 'of the recovery rate a logo may spend; the rest pays for print defects, dirt and '
            . 'bad light.',
            rtrim(rtrim(number_format($given, 4, '.', ''), '0'), '.')
        ));
    }

    public static function logoNeedsOpaqueBackdrop(): self
    {
        return new self(
            'A logo needs an opaque backdrop, but the light color is "none". The cleared area '
            . 'around the logo has to read as light, otherwise whatever is behind the symbol '
            . 'shows through and a scanner sees neither light nor dark. Set a light color.'
        );
    }
}

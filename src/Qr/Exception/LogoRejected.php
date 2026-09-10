<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Exception;

use InvalidArgumentException;

/**
 * The supplied artwork was refused rather than repaired.
 *
 * Rejecting beats stripping. A logo arrives from outside — an upload, a client
 * delivery, a designer's export — and quietly removing what we do not
 * understand would either change the artwork without telling anyone or leave
 * a remnant we did not consider. A refusal that names the offending construct
 * gets the file fixed at the source, which is where it can be fixed properly.
 */
final class LogoRejected extends InvalidArgumentException implements QrGenException
{
    public static function notAnSvg(): self
    {
        return new self('The artwork has no <svg> root element.');
    }

    public static function severalRootElements(): self
    {
        return new self('The artwork holds more than one <svg> element. Supply a single one.');
    }

    public static function withoutDimensions(): self
    {
        return new self(
            'The <svg> has neither a viewBox nor a width and height, so it cannot be scaled. '
            . 'Export it again with a viewBox.'
        );
    }

    public static function emptyArtwork(): self
    {
        return new self('The <svg> holds no drawable element.');
    }

    public static function doctypeWithInternalSubset(): self
    {
        return new self(
            'The artwork carries a DOCTYPE with an internal subset. Entity declarations are '
            . 'refused outright — remove the DOCTYPE.'
        );
    }

    public static function forbiddenElement(string $element): self
    {
        return new self(sprintf(
            'The artwork uses <%s>, which is not allowed. Permitted are only g, path, rect, '
            . 'circle, ellipse, line, polygon and polyline. Flatten the file: convert text to '
            . 'outlines, expand gradients, masks and clipping paths, and remove embedded images.',
            $element
        ));
    }

    public static function forbiddenAttribute(string $attribute, string $element): self
    {
        return new self(sprintf(
            'The attribute "%s" on <%s> is not allowed. Only geometry, fill, stroke and '
            . 'transform attributes are permitted.',
            $attribute,
            $element
        ));
    }

    public static function forbiddenValue(string $attribute, string $reason): self
    {
        return new self(sprintf('The value of "%s" is not allowed: %s', $attribute, $reason));
    }

    public static function nonEmptyDefs(): self
    {
        return new self(
            'The artwork has a non-empty <defs>. Gradients, patterns, masks and clipping paths '
            . 'are not supported — expand them into plain shapes before exporting.'
        );
    }

    public static function unsupportedStyleRule(string $rule): self
    {
        return new self(sprintf(
            'The <style> block holds a rule this package cannot inline: "%s". Only single class '
            . 'selectors with fill, stroke and opacity declarations are understood, which is what '
            . 'Illustrator exports. Anything else has to become presentation attributes.',
            $rule
        ));
    }

    public static function unparsableMarkup(): self
    {
        return new self('The artwork could not be read as markup and was refused.');
    }

    public static function unbalancedMarkup(): self
    {
        return new self(
            'The artwork has unbalanced tags. Embedding it would produce invalid markup, so it '
            . 'is refused rather than repaired.'
        );
    }

    public static function textContent(): self
    {
        return new self(
            'The artwork holds text content. Convert type to outlines — a font that is not on '
            . 'the printing system renders as something else or as nothing.'
        );
    }
}

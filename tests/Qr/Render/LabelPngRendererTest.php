<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\TextRejected;
use Redcodede\QrGen\Qr\Layout\LabelLayout;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\LabelOptions;
use Redcodede\QrGen\Qr\Render\LabelPngRenderer;
use Redcodede\QrGen\Qr\Text\SvgFont;

/**
 * The raster label, decoded back into pixels.
 *
 * A PNG that merely parses proves very little: the header can be right while
 * every pixel is white. So these cases decompress the image and look at
 * particular pixels, because the two things that would actually go wrong here
 * are silent. A module drawn at the wrong scale still produces a plausible
 * symbol, and a colour written in the wrong channel order still produces a
 * coloured one.
 */
final class LabelPngRendererTest extends TestCase
{
    private const SHIPPED = __DIR__ . '/../../../resources/fonts/pt-sans-v18-latin/pt-sans-v18-latin-regular.svg';

    private static function font(): SvgFont
    {
        return SvgFont::fromMarkup((string) file_get_contents(self::SHIPPED));
    }

    private static function renderer(?LabelOptions $options = null): LabelPngRenderer
    {
        return new LabelPngRenderer(LabelLayout::standard(), self::font(), $options);
    }

    /**
     * Dark in the top left corner of the symbol, light beside it, so a pixel
     * lookup can tell the two apart without depending on a real payload.
     */
    private static function matrix(): ModuleMatrix
    {
        return new ModuleMatrix([
            [true, false, true],
            [false, false, false],
            [true, false, true],
        ]);
    }

    /**
     * @return array{width: int, height: int, depth: int, type: int, pixels: string}
     */
    private static function decode(string $png): array
    {
        self::assertSame("\x89PNG\x0d\x0a\x1a\x0a", substr($png, 0, 8), 'Not a PNG.');

        $offset = 8;
        $header = null;
        $data = '';

        while ($offset < strlen($png)) {
            $length = (int) unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $body = substr($png, $offset + 8, $length);

            if ($type === 'IHDR') {
                $header = unpack('Nwidth/Nheight/Cdepth/Ctype', $body);
            }

            if ($type === 'IDAT') {
                $data .= $body;
            }

            $offset += 12 + $length;
        }

        self::assertIsArray($header, 'No IHDR.');

        $raw = (string) gzuncompress($data);
        $stride = $header['width'] * 3;
        $pixels = '';
        $previous = str_repeat("\x00", $stride);

        for ($row = 0; $row < $header['height']; $row++) {
            $filter = ord($raw[$row * ($stride + 1)]);
            $line = substr($raw, ($row * ($stride + 1)) + 1, $stride);

            // Only two filters are ever written: none, and "same as above".
            self::assertContains($filter, [0, 2], 'Unexpected filter type.');

            if ($filter === 2) {
                $restored = '';

                for ($i = 0; $i < $stride; $i++) {
                    $restored .= chr((ord($line[$i]) + ord($previous[$i])) & 0xFF);
                }

                $line = $restored;
            }

            $pixels .= $line;
            $previous = $line;
        }

        return [
            'width' => $header['width'],
            'height' => $header['height'],
            'depth' => $header['depth'],
            'type' => $header['type'],
            'pixels' => $pixels,
        ];
    }

    /**
     * @param array{width: int, pixels: string} $image
     */
    private static function pixel(array $image, int $x, int $y): string
    {
        return '#' . bin2hex(substr($image['pixels'], (($y * $image['width']) + $x) * 3, 3));
    }

    public function testItIsAnEightBitTruecolourImageOfTheStatedSize(): void
    {
        $matrix = self::matrix();
        $image = self::decode(self::renderer()->render($matrix));

        self::assertSame(8, $image['depth']);
        self::assertSame(2, $image['type']);
        self::assertSame(self::renderer()->pixelSize($matrix), [$image['width'], $image['height']]);
    }

    /**
     * The point of choosing the pixel size from the module rather than the
     * other way round. A fractional module would put grey along every edge.
     */
    public function testAModuleIsAWholeNumberOfPixels(): void
    {
        $renderer = self::renderer();
        $matrix = self::matrix();

        self::assertGreaterThan(1, $renderer->pixelsPerModule($matrix));
        self::assertEqualsWithDelta(600.0, $renderer->effectiveDpi($matrix), 60.0);
    }

    public function testDarkModulesCarryTheCodeColourAndLightOnesDoNot(): void
    {
        $renderer = self::renderer(LabelOptions::default()->withCodeColor('#009a7c'));
        $matrix = self::matrix();
        $layout = LabelLayout::standard();

        $image = self::decode($renderer->render($matrix));

        $modulePixels = $renderer->pixelsPerModule($matrix);
        $scale = $modulePixels / $layout->moduleSizeFor($matrix->size());
        $originX = (int) round($layout->codeX() * $scale);
        $originY = (int) round($layout->codeY() * $scale);

        // Middle of module (0,0), which this matrix sets dark.
        $half = intdiv($modulePixels, 2);
        self::assertSame('#009a7c', self::pixel($image, $originX + $half, $originY + $half));

        // Middle of module (1,0), which it does not.
        self::assertSame('#ffffff', self::pixel($image, $originX + $modulePixels + $half, $originY + $half));
    }

    /**
     * The margin between the rule and the symbol is the quiet zone, and it has
     * to be genuinely empty. A frame that had drifted inwards, or a symbol that
     * had drifted outwards, would show up here and nowhere else.
     *
     * Note that the corner pixel itself is *not* light: the rule sits 0.12 mm
     * in with a 0.25 mm stroke, so the band it paints starts just outside the
     * canvas and the corner belongs to it.
     */
    public function testTheMarginBetweenRuleAndSymbolIsLight(): void
    {
        $matrix = self::matrix();
        $renderer = self::renderer();
        $layout = LabelLayout::standard();
        $image = self::decode($renderer->render($matrix));

        $scale = $renderer->pixelsPerModule($matrix) / $layout->moduleSizeFor($matrix->size());

        // 2 mm in: past the rule, which ends at 0.245 mm, and well short of the
        // symbol, which starts at 3.90 mm.
        $at = (int) round(2.0 * $scale);

        self::assertSame('#ffffff', self::pixel($image, $at, $at));
    }

    public function testTheFrameIsDrawn(): void
    {
        $matrix = self::matrix();
        $renderer = self::renderer();
        $layout = LabelLayout::standard();
        $image = self::decode($renderer->render($matrix));

        $scale = $renderer->pixelsPerModule($matrix) / $layout->moduleSizeFor($matrix->size());
        $y = (int) round($layout->frameInset() * $scale);

        // Somewhere along the top rule, well away from the corners.
        $found = false;

        for ($x = 100; $x < 200; $x++) {
            if (self::pixel($image, $x, $y) !== '#ffffff') {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, 'The top rule of the frame is missing.');
    }

    /**
     * The physical size is what survives the resolution being a little off.
     */
    public function testThePhysicalSizeIsTheLabelSize(): void
    {
        $matrix = self::matrix();
        $png = self::renderer()->render($matrix);

        self::assertSame(1, preg_match('/pHYs(.{9})/s', $png, $match));

        $chunk = unpack('Nx/Ny/Cunit', $match[1]);
        self::assertSame(1, $chunk['unit'], 'The unit has to be the metre.');

        [$width] = self::renderer()->pixelSize($matrix);
        self::assertEqualsWithDelta(
            LabelLayout::standard()->width(),
            $width / $chunk['x'] * 1000,
            0.5
        );
    }

    public function testTypeIsRasterisedRatherThanLeftOut(): void
    {
        $matrix = self::matrix();
        $bare = self::decode(self::renderer()->render($matrix));
        $set = self::decode(self::renderer()->render($matrix, 'Rückgabe über das GVÖ-SYSTEM'));

        self::assertNotSame($bare['pixels'], $set['pixels']);
    }

    public function testAColourWithoutSixDigitsIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);

        self::renderer(LabelOptions::default()->withCodeColor('rebeccapurple'))->render(self::matrix());
    }

    public function testTextThatCannotBeMadeToFitIsRefused(): void
    {
        $this->expectException(TextRejected::class);

        self::renderer()->render(self::matrix(), str_repeat('Mineralölwirtschaft', 12));
    }

    public function testAnImpossibleResolutionIsRefused(): void
    {
        $this->expectException(InvalidArgument::class);

        new LabelPngRenderer(LabelLayout::standard(), self::font(), null, 0);
    }
}

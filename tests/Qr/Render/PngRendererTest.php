<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Render\PngOptions;
use Redcodede\QrGen\Qr\Render\PngRenderer;

/**
 * The PNG is written by hand rather than by an image library, so these tests
 * do the job the library's own test suite would otherwise have done: they take
 * the bytes apart and check that what came out is the format it claims to be.
 */
final class PngRendererTest extends TestCase
{
    private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

    private function matrix(string $url = 'https://gvoe.de/return/7K4M2'): ModuleMatrix
    {
        return (new BaconQrEncoder())->encode($url, ErrorCorrection::high());
    }

    public function testItStartsWithThePngSignature(): void
    {
        $png = (new PngRenderer(Preset::pngOptions()))->render($this->matrix());

        self::assertStringStartsWith(self::SIGNATURE, $png);
    }

    public function testItEndsWithTheEndChunk(): void
    {
        $png = (new PngRenderer(Preset::pngOptions()))->render($this->matrix());

        self::assertStringEndsWith('IEND' . pack('N', crc32('IEND')), $png);
    }

    /**
     * getimagesize is part of the standard library, not of gd, so this is a
     * second opinion from code that had no hand in writing the file.
     */
    public function testTheStandardLibraryRecognisesIt(): void
    {
        $renderer = new PngRenderer(Preset::pngOptions());
        $matrix = $this->matrix();

        $file = tempnam(sys_get_temp_dir(), 'qrgen') . '.png';
        file_put_contents($file, $renderer->render($matrix));

        $probe = getimagesize($file);
        unlink($file);

        self::assertIsArray($probe);
        self::assertSame($renderer->pixelWidth($matrix), $probe[0]);
        self::assertSame($renderer->pixelWidth($matrix), $probe[1]);
        self::assertSame('image/png', $probe['mime']);
        self::assertSame(1, $probe['bits'], 'One bit per pixel is the whole point.');
    }

    /**
     * Every chunk carries its own CRC. A wrong one makes a decoder reject the
     * file, and since these are written by hand rather than by a library there
     * is nothing else standing between a typo and a broken deliverable.
     */
    public function testEveryChunkChecksumIsCorrect(): void
    {
        $chunks = $this->chunks((new PngRenderer(Preset::pngOptions()))->render($this->matrix()));

        self::assertNotSame([], $chunks);

        foreach ($chunks as $chunk) {
            self::assertSame(
                crc32($chunk['type'] . $chunk['data']),
                $chunk['crc'],
                "The CRC of {$chunk['type']} does not match its contents."
            );
        }
    }

    public function testTheChunksAppearInTheOrderTheFormatRequires(): void
    {
        $types = array_column(
            $this->chunks((new PngRenderer(Preset::pngOptions()))->render($this->matrix())),
            'type'
        );

        self::assertSame(['IHDR', 'PLTE', 'pHYs', 'IDAT', 'IEND'], $types);
    }

    /**
     * Without pHYs a layout application places the file at its own default,
     * usually 72 dpi, and a code meant for 5 cm lands at 42 and gets scaled by
     * eye. This chunk is the difference between a large image and a print-ready
     * one.
     */
    public function testItDeclaresItsPhysicalResolution(): void
    {
        $chunk = $this->chunk((new PngRenderer(Preset::pngOptions()))->render($this->matrix()), 'pHYs');

        self::assertNotNull($chunk);

        $values = unpack('NppuX/NppuY/Cunit', $chunk);

        self::assertIsArray($values);
        // 600 dpi expressed as pixels per metre.
        self::assertSame(23622, $values['ppuX']);
        self::assertSame(23622, $values['ppuY']);
        self::assertSame(1, $values['unit'], 'Unit 1 is the metre; 0 would mean "unknown".');
    }

    public function testThePaletteHoldsTheTwoColoursLightFirst(): void
    {
        $chunk = $this->chunk((new PngRenderer(Preset::pngOptions()))->render($this->matrix()), 'PLTE');

        self::assertSame(pack('C6', 255, 255, 255, 0, 0, 0), $chunk);
    }

    public function testACustomPaletteEndsUpInTheFile(): void
    {
        $options = Preset::pngOptions()->withColors('#123456', '#fed');
        $chunk = $this->chunk((new PngRenderer($options))->render($this->matrix()), 'PLTE');

        // #fed expands to #ffeedd.
        self::assertSame(pack('C6', 0xff, 0xee, 0xdd, 0x12, 0x34, 0x56), $chunk);
    }

    public function testTransparencyIsAbsentUnlessAskedFor(): void
    {
        $png = (new PngRenderer(Preset::pngOptions()))->render($this->matrix());

        self::assertNull($this->chunk($png, 'tRNS'));
    }

    public function testTransparencyDropsOutTheLightEntryOnly(): void
    {
        $options = Preset::pngOptions()->withTransparentBackground();
        $chunk = $this->chunk((new PngRenderer($options))->render($this->matrix()), 'tRNS');

        self::assertSame(pack('C2', 0, 255), $chunk);
    }

    /**
     * The requirement in plain numbers: the file has to be large enough to
     * print at 5 by 5 centimetres without being scaled up.
     *
     * @dataProvider realPayloads
     */
    public function testTheFileReachesTheOrderedPrintSize(string $url, int $expectedPixels): void
    {
        $renderer = new PngRenderer(Preset::pngOptions());
        $matrix = $this->matrix($url);

        self::assertSame($expectedPixels, $renderer->pixelWidth($matrix));
        self::assertGreaterThanOrEqual(
            Preset::PRINT_SIZE_MM,
            $renderer->printedSizeMm($matrix),
            'Rounding has to go up, so printing at the ordered size never scales up.'
        );
        self::assertLessThan(
            Preset::PRINT_SIZE_MM + 1.0,
            $renderer->printedSizeMm($matrix),
            'Rounding up by a whole millimetre would mean the module count divides badly.'
        );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function realPayloads(): iterable
    {
        // 33 modules + 2x2 quiet zone = 37, at 32 px each.
        yield 'the briefing url' => ['https://gvoe.de/return/7K4M2', 1184];
        // 29 + 4 = 33, at 36 px each.
        yield 'the demo url' => ['https://www.redcode.de/', 1188];
    }

    /**
     * A module boundary that falls between pixels is an edge the raster has to
     * fudge, and a fudged edge is what a scanner reads wrong.
     */
    public function testEveryModuleGetsAWholeNumberOfPixels(): void
    {
        $renderer = new PngRenderer(Preset::pngOptions());
        $matrix = $this->matrix();
        $extent = $matrix->size() + (2 * Preset::QUIET_ZONE);

        self::assertSame(
            $extent * $renderer->pixelsPerModule($matrix),
            $renderer->pixelWidth($matrix)
        );
        self::assertSame(0, $renderer->pixelWidth($matrix) % $extent);
    }

    /**
     * The assertion that matters most: unpack the raster back into modules and
     * compare with the matrix that went in. Everything else checks that the
     * file is well formed; this checks that it is the right picture.
     *
     * @dataProvider roundTripCases
     */
    public function testTheRasterDecodesBackIntoTheMatrix(string $url, int $quietZone): void
    {
        $options = Preset::pngOptions()->withQuietZone($quietZone);
        $renderer = new PngRenderer($options);
        $matrix = $this->matrix($url);

        $pixels = $this->pixelRows($renderer->render($matrix), $renderer->pixelWidth($matrix));
        $scale = $renderer->pixelsPerModule($matrix);
        $size = $matrix->size();
        $rows = [];

        for ($y = 0; $y < $size; $y++) {
            $row = [];

            for ($x = 0; $x < $size; $x++) {
                // Sample the middle of each module rather than a corner, so an
                // off-by-one in the packing shows up instead of hiding on a
                // boundary.
                $row[] = $pixels[(($y + $quietZone) * $scale) + intdiv($scale, 2)]
                    [(($x + $quietZone) * $scale) + intdiv($scale, 2)];
            }

            $rows[] = $row;
        }

        self::assertSame($matrix->rows(), $rows);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function roundTripCases(): iterable
    {
        yield 'preset quiet zone' => ['https://gvoe.de/return/7K4M2', 2];
        yield 'spec quiet zone' => ['https://gvoe.de/return/7K4M2', 4];
        yield 'no quiet zone' => ['https://gvoe.de/return/7K4M2', 0];
        yield 'shorter payload' => ['https://www.redcode.de/', 2];
    }

    /**
     * The light border a scanner needs. Asserted a row at a time rather than a
     * pixel at a time — a border of 72 pixels on a 1184-pixel edge is a third
     * of a million comparisons for one fact, and a count says the same thing.
     */
    public function testTheQuietZoneIsEntirelyLight(): void
    {
        $renderer = new PngRenderer(Preset::pngOptions());
        $matrix = $this->matrix();
        $width = $renderer->pixelWidth($matrix);
        $pixels = $this->pixelRows($renderer->render($matrix), $width);
        $border = Preset::QUIET_ZONE * $renderer->pixelsPerModule($matrix);

        self::assertGreaterThan(0, $border, 'Guard: the preset has a quiet zone to check.');

        foreach ([0, $border - 1, $width - $border, $width - 1] as $row) {
            self::assertSame(
                0,
                count(array_filter($pixels[$row])),
                "Row {$row} lies in the quiet zone and holds dark pixels."
            );
        }

        foreach ([0, intdiv($width, 2), $width - 1] as $row) {
            $left = array_slice($pixels[$row], 0, $border);
            $right = array_slice($pixels[$row], $width - $border);

            self::assertSame(0, count(array_filter($left)), "Row {$row} is dark on the left border.");
            self::assertSame(0, count(array_filter($right)), "Row {$row} is dark on the right border.");
        }
    }

    public function testItAdvertisesWhatItProduces(): void
    {
        $renderer = new PngRenderer();

        self::assertSame('image/png', $renderer->mimeType());
        self::assertSame('png', $renderer->fileExtension());
    }

    /**
     * The reason this class exists rather than a call into an image library.
     */
    public function testItCallsNoImageExtension(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Qr/Render/PngRenderer.php');

        foreach (['imagecreate', 'imagepng', 'imagecolorallocate', 'Imagick', 'gd_info'] as $needle) {
            self::assertStringNotContainsString($needle . '(', $source);
        }

        // What it does use instead, both of which are always available.
        self::assertStringContainsString('gzcompress', $source);
        self::assertStringContainsString('crc32', $source);
    }

    /**
     * @dataProvider refusedColors
     */
    public function testAColourWithAnAlphaComponentIsRefused(string $color): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('three or six digits');

        PngOptions::default()->withColors($color, '#ffffff');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedColors(): iterable
    {
        yield 'four digits' => ['#0008'];
        yield 'eight digits' => ['#1a2b3c80'];
        yield 'named' => ['black'];
        yield 'keyword none' => ['none'];
    }

    /**
     * @dataProvider refusedSettings
     */
    public function testSettingsOutsideTheSupportedRangeAreRefused(callable $mutate): void
    {
        $this->expectException(InvalidArgument::class);

        $mutate(PngOptions::default());
    }

    /**
     * @return iterable<string, array{callable}>
     */
    public static function refusedSettings(): iterable
    {
        yield 'dpi too low' => [static fn (PngOptions $o) => $o->withDpi(71)];
        yield 'dpi absurd' => [static fn (PngOptions $o) => $o->withDpi(9600)];
        yield 'print size too small' => [static fn (PngOptions $o) => $o->withPrintSizeMm(4.0)];
        yield 'print size absurd' => [static fn (PngOptions $o) => $o->withPrintSizeMm(5000.0)];
        yield 'negative quiet zone' => [static fn (PngOptions $o) => $o->withQuietZone(-1)];
    }

    public function testOptionsAreImmutable(): void
    {
        $defaults = PngOptions::default();
        $changed = $defaults->withDpi(1200);

        self::assertSame(600, $defaults->dpi());
        self::assertSame(1200, $changed->dpi());
    }

    /**
     * A higher resolution has to produce a larger file for the same physical
     * size, otherwise the dpi setting is decorative.
     */
    public function testHigherResolutionMeansMorePixelsForTheSameSize(): void
    {
        $matrix = $this->matrix();

        $at600 = (new PngRenderer(Preset::pngOptions()))->pixelWidth($matrix);
        $at1200 = (new PngRenderer(Preset::pngOptions()->withDpi(1200)))->pixelWidth($matrix);

        self::assertGreaterThan($at600, $at1200);
    }

    /**
     * Unpacks a one-bit paletted PNG into a grid of booleans, true where the
     * pixel uses the dark palette entry.
     *
     * @return list<list<bool>>
     */
    private function pixelRows(string $png, int $width): array
    {
        $data = '';

        foreach ($this->chunks($png) as $chunk) {
            if ($chunk['type'] === 'IDAT') {
                $data .= $chunk['data'];
            }
        }

        $raw = gzuncompress($data);

        self::assertIsString($raw, 'The image data is not a valid zlib stream.');

        $bytesPerRow = intdiv($width + 7, 8);
        $rows = [];

        for ($y = 0; $y < $width; $y++) {
            $offset = $y * ($bytesPerRow + 1);

            self::assertSame(
                "\x00",
                $raw[$offset],
                "Row {$y} does not start with filter type 0."
            );

            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $byte = ord($raw[$offset + 1 + ($x >> 3)]);
                $row[] = ($byte & (0x80 >> ($x & 7))) !== 0;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{type: string, data: string, crc: int}>
     */
    private function chunks(string $png): array
    {
        $offset = strlen(self::SIGNATURE);
        $chunks = [];

        while ($offset < strlen($png)) {
            $header = unpack('Nlength/a4type', substr($png, $offset, 8));

            self::assertIsArray($header);

            $length = $header['length'];
            $data = substr($png, $offset + 8, $length);
            $crc = unpack('Ncrc', substr($png, $offset + 8 + $length, 4));

            self::assertIsArray($crc);

            $chunks[] = ['type' => $header['type'], 'data' => $data, 'crc' => $crc['crc']];
            $offset += 8 + $length + 4;
        }

        return $chunks;
    }

    private function chunk(string $png, string $type): ?string
    {
        foreach ($this->chunks($png) as $chunk) {
            if ($chunk['type'] === $type) {
                return $chunk['data'];
            }
        }

        return null;
    }
}

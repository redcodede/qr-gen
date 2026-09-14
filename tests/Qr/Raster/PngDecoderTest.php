<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Raster;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Raster\PngDecoder;

/**
 * The PNG reader, fed files built byte by byte in the test.
 *
 * Building the fixtures here rather than checking in binaries is what makes
 * these tests worth reading: the bytes that go in are written out in the test
 * beside the pixels that should come out, so a failure says which chunk or
 * which filter is wrong instead of "the blob decoded differently". It also
 * covers combinations no artwork in this repository happens to use — four-bit
 * palettes, sixteen-bit samples, grey with a transparent value — which are
 * exactly the ones that will arrive one day from someone's export dialogue.
 */
final class PngDecoderTest extends TestCase
{
    private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    /**
     * @param list<string> $extra Chunks between the header and the image data
     */
    private static function png(
        int $width,
        int $height,
        int $depth,
        int $colour,
        string $scanlines,
        array $extra = [],
        int $interlace = 0
    ): string {
        return self::SIGNATURE
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, $depth, $colour, 0, 0, $interlace))
            . implode('', $extra)
            . self::chunk('IDAT', (string) gzcompress($scanlines, 6))
            . self::chunk('IEND', '');
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int}> One entry per pixel
     */
    private static function pixels(string $rgba): array
    {
        $out = [];

        for ($at = 0; $at < strlen($rgba); $at += 4) {
            $out[] = [ord($rgba[$at]), ord($rgba[$at + 1]), ord($rgba[$at + 2]), ord($rgba[$at + 3])];
        }

        return $out;
    }

    // ------------------------------------------------------ colour types ---

    public function testTruecolourWithAlpha(): void
    {
        // Two pixels: opaque red, half-transparent blue.
        $png = self::png(2, 1, 8, 6, "\x00" . "\xff\x00\x00\xff" . "\x00\x00\xff\x80");

        $decoded = PngDecoder::decode($png);

        self::assertSame(2, $decoded['width']);
        self::assertSame(1, $decoded['height']);
        self::assertSame([[255, 0, 0, 255], [0, 0, 255, 128]], self::pixels($decoded['rgba']));
    }

    public function testTruecolourWithoutAlphaIsOpaque(): void
    {
        $png = self::png(2, 1, 8, 2, "\x00" . "\x11\x22\x33" . "\x44\x55\x66");

        self::assertSame(
            [[0x11, 0x22, 0x33, 255], [0x44, 0x55, 0x66, 255]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    /**
     * On a truecolour image tRNS names one colour that stands for transparent,
     * as two bytes per channel whatever the bit depth.
     */
    public function testTruecolourWithATransparentColour(): void
    {
        $png = self::png(
            2,
            1,
            8,
            2,
            "\x00" . "\x11\x22\x33" . "\x44\x55\x66",
            [self::chunk('tRNS', pack('n3', 0x44, 0x55, 0x66))]
        );

        self::assertSame(
            [[0x11, 0x22, 0x33, 255], [0x44, 0x55, 0x66, 0]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    public function testPaletteWithTransparency(): void
    {
        $palette = self::chunk('PLTE', "\xff\x00\x00" . "\x00\xff\x00" . "\x00\x00\xff");
        // The first entry is clear, the second half-way, the third left out and
        // therefore opaque.
        $transparency = self::chunk('tRNS', "\x00\x80");

        // Four bits per pixel: indices 0, 1, 2, 0 pack into two bytes.
        $png = self::png(4, 1, 4, 3, "\x00" . "\x01\x20", [$palette, $transparency]);

        self::assertSame(
            [[255, 0, 0, 0], [0, 255, 0, 128], [0, 0, 255, 255], [255, 0, 0, 0]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    /**
     * A one-bit grey image stores 0 and 1, not 0 and 255. Reading it literally
     * would give an image that is black and almost black.
     */
    public function testOneBitGreyIsScaledToTheFullRange(): void
    {
        // 1 0 1 1 in the top four bits of a single byte.
        $png = self::png(4, 1, 1, 0, "\x00" . "\xb0");

        self::assertSame(
            [[255, 255, 255, 255], [0, 0, 0, 255], [255, 255, 255, 255], [255, 255, 255, 255]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    public function testTwoBitGreyIsScaledToTheFullRange(): void
    {
        // 0, 1, 2, 3 -> 0, 85, 170, 255.
        $png = self::png(4, 1, 2, 0, "\x00" . "\x1b");

        self::assertSame(
            [[0, 0, 0, 255], [85, 85, 85, 255], [170, 170, 170, 255], [255, 255, 255, 255]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    public function testGreyWithAlpha(): void
    {
        $png = self::png(2, 1, 8, 4, "\x00" . "\x40\xff" . "\xc0\x20");

        self::assertSame(
            [[0x40, 0x40, 0x40, 255], [0xc0, 0xc0, 0xc0, 0x20]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    public function testGreyWithATransparentValue(): void
    {
        $png = self::png(2, 1, 8, 0, "\x00" . "\x40" . "\xc0", [self::chunk('tRNS', pack('n', 0xc0))]);

        self::assertSame(
            [[0x40, 0x40, 0x40, 255], [0xc0, 0xc0, 0xc0, 0]],
            self::pixels(PngDecoder::decode($png)['rgba'])
        );
    }

    /**
     * Sixteen bits go down to eight by keeping the high byte. Nothing
     * downstream can carry more, and a 256-entry palette certainly cannot.
     */
    public function testSixteenBitSamplesComeDownToEight(): void
    {
        $png = self::png(1, 1, 16, 2, "\x00" . "\x12\x34" . "\x56\x78" . "\x9a\xbc");

        self::assertSame([[0x12, 0x56, 0x9a, 255]], self::pixels(PngDecoder::decode($png)['rgba']));
    }

    // ----------------------------------------------------------- filters ---

    /**
     * Each filter predicts a byte from its neighbours and stores the
     * difference. The four fixtures below encode the same two-pixel picture
     * four different ways, so a mistake in one predictor cannot hide behind the
     * others.
     *
     * @dataProvider filteredScanlines
     */
    public function testEveryFilterIsUndone(string $scanlines, string $label): void
    {
        $decoded = PngDecoder::decode(self::png(2, 2, 8, 2, $scanlines));

        self::assertSame(
            [
                [10, 20, 30, 255], [40, 50, 60, 255],
                [70, 80, 90, 255], [100, 110, 120, 255],
            ],
            self::pixels($decoded['rgba']),
            $label
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function filteredScanlines(): iterable
    {
        // The picture, unfiltered: two rows of two RGB pixels.
        yield 'none' => [
            "\x00" . "\x0a\x14\x1e" . "\x28\x32\x3c"
            . "\x00" . "\x46\x50\x5a" . "\x64\x6e\x78",
            'filter 0',
        ];

        // Sub: each byte minus the one three bytes to its left.
        yield 'sub' => [
            "\x01" . "\x0a\x14\x1e" . "\x1e\x1e\x1e"
            . "\x01" . "\x46\x50\x5a" . "\x1e\x1e\x1e",
            'filter 1',
        ];

        // Up: each byte minus the one above. The first row has nothing above.
        yield 'up' => [
            "\x00" . "\x0a\x14\x1e" . "\x28\x32\x3c"
            . "\x02" . "\x3c\x3c\x3c" . "\x3c\x3c\x3c",
            'filter 2',
        ];

        // Average: minus the floor of the mean of left and above.
        yield 'average' => [
            "\x00" . "\x0a\x14\x1e" . "\x28\x32\x3c"
            . "\x03" . "\x41\x46\x4b" . "\x2d\x2d\x2d",
            'filter 3',
        ];

        // Paeth: minus whichever of left, above and upper-left is closest to
        // their linear combination.
        yield 'paeth' => [
            "\x00" . "\x0a\x14\x1e" . "\x28\x32\x3c"
            . "\x04" . "\x3c\x3c\x3c" . "\x1e\x1e\x1e",
            'filter 4',
        ];
    }

    // ---------------------------------------------------------- refusals ---

    public function testSomethingThatIsNotAPngIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/PNG signature/');

        PngDecoder::decode("\xff\xd8\xff\xe0JFIF");
    }

    public function testAnInterlacedFileIsRefusedWithAdvice(): void
    {
        $png = self::png(1, 1, 8, 2, "\x00\x00\x00\x00", [], 1);

        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/interlacing switched off/');

        PngDecoder::decode($png);
    }

    public function testABrokenChecksumIsRefused(): void
    {
        $png = self::png(1, 1, 8, 2, "\x00\x11\x22\x33");
        // Corrupt the last byte of the header chunk's CRC.
        $png[29] = chr(ord($png[29]) ^ 0xff);

        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/checksum/');

        PngDecoder::decode($png);
    }

    public function testAPaletteImageWithNoPaletteIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/no palette/');

        PngDecoder::decode(self::png(1, 1, 8, 3, "\x00\x00"));
    }

    public function testAnUndefinedColourTypeIsRefused(): void
    {
        $this->expectException(LogoRejected::class);

        PngDecoder::decode(self::png(1, 1, 8, 5, "\x00\x00"));
    }

    public function testAnImpossibleDepthForTheColourTypeIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/not allowed with colour type/');

        // One bit per sample is fine for grey and refused for truecolour.
        PngDecoder::decode(self::png(1, 1, 1, 2, "\x00\x00"));
    }

    public function testTruncatedImageDataIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/bytes of image data/');

        // Two rows declared, one supplied.
        PngDecoder::decode(self::png(1, 2, 8, 2, "\x00\x11\x22\x33"));
    }

    public function testAnUndefinedFilterIsRefused(): void
    {
        $this->expectException(LogoRejected::class);
        $this->expectExceptionMessageMatches('/filter 9/');

        PngDecoder::decode(self::png(1, 1, 8, 2, "\x09\x11\x22\x33"));
    }

    // ------------------------------------------------------------- strip ---

    /**
     * A PNG cannot carry a script, but it can carry a designer's name, a
     * camera's coordinates and a colour profile, and those would travel with
     * the symbol.
     */
    public function testStrippingLeavesOnlyThePicture(): void
    {
        $text = self::chunk('tEXt', "Author\x00Someone");
        $png = self::png(1, 1, 8, 2, "\x00\x11\x22\x33", [$text]);

        self::assertStringContainsString('Someone', $png, 'The fixture should carry the metadata.');

        $stripped = PngDecoder::stripToPicture($png);

        self::assertStringNotContainsString('Someone', $stripped);
        self::assertStringNotContainsString('tEXt', $stripped);
        self::assertSame(
            self::pixels(PngDecoder::decode($png)['rgba']),
            self::pixels(PngDecoder::decode($stripped)['rgba']),
            'The picture has to survive unchanged.'
        );
    }

    public function testStrippingKeepsThePaletteAndItsTransparency(): void
    {
        $png = self::png(
            1,
            1,
            8,
            3,
            "\x00\x00",
            [self::chunk('PLTE', "\x01\x02\x03"), self::chunk('tRNS', "\x80"), self::chunk('tEXt', "a\x00b")]
        );

        $stripped = PngDecoder::stripToPicture($png);

        self::assertSame([[1, 2, 3, 128]], self::pixels(PngDecoder::decode($stripped)['rgba']));
    }

    public function testSizeReadsTheHeaderWithoutInflatingAnything(): void
    {
        $png = self::png(7, 3, 8, 2, "\x00" . str_repeat("\x00", 63));

        self::assertSame(['width' => 7, 'height' => 3], PngDecoder::size($png));
    }

    // --------------------------------------------------------- real file ---

    /**
     * A file written by a real tool, which uses whatever mix of filters its
     * encoder chose. The synthetic fixtures above check one predictor at a
     * time; this checks that the combination survives a whole image.
     */
    public function testARealExportDecodes(): void
    {
        $path = __DIR__ . '/../../../demo/logos/qr-gen-demo.png';

        if (!is_file($path)) {
            self::markTestSkipped('No PNG in demo/logos to try.');
        }

        $decoded = PngDecoder::decode((string) file_get_contents($path));

        self::assertGreaterThan(0, $decoded['width']);
        self::assertSame(
            $decoded['width'] * $decoded['height'] * 4,
            strlen($decoded['rgba']),
            'Four bytes per pixel, no more and no fewer.'
        );

        $probe = getimagesize($path);
        self::assertIsArray($probe);
        self::assertSame($probe[0], $decoded['width'], 'Width disagrees with the standard library.');
        self::assertSame($probe[1], $decoded['height'], 'Height disagrees with the standard library.');
    }
}

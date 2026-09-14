<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Contract\Logo;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\Exception\LogoRejected;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Raster\LogoRaster;
use Redcodede\QrGen\Qr\Render\PngOptions;
use Redcodede\QrGen\Qr\Render\PngRenderer;

/**
 * The PNG that carries artwork, taken apart again.
 *
 * The question that matters is not whether a logo appears — that is visible at
 * a glance — but whether the symbol around it is still the symbol. A raster
 * with a logo in it looks right long after it has stopped scanning, so these
 * tests read the file back and compare it to the matrix it was made from,
 * module by module.
 */
final class PngRendererLogoTest extends TestCase
{
    private const URL = 'https://gvoe.de/return/7K4M2';

    private function matrix(): ModuleMatrix
    {
        return (new BaconQrEncoder())->encode(self::URL, ErrorCorrection::high());
    }

    private function logo(?string $markup = null): Logo
    {
        return SvgLogo::fromMarkup($markup ?? <<<'SVG'
            <svg viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" fill="#009879"/>
                <rect x="30" y="30" width="40" height="40" fill="#ffffff"/>
            </svg>
            SVG);
    }

    private function box(): LogoBox
    {
        return Preset::logoBox();
    }

    /**
     * Reads a palette PNG back into pixels, using the format rather than this
     * package's own writer: chunks are walked by length and type, the image data
     * is inflated, and each scanline is unfiltered by the rule its own first byte
     * names.
     *
     * @return array{width: int, bitDepth: int, palette: list<array{0: int, 1: int, 2: int}>, at: callable}
     */
    private function decode(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\x0d\x0a\x1a\x0a", $png, 'Not a PNG at all.');

        $offset = 8;
        $chunks = [];

        while ($offset < strlen($png)) {
            /** @var array{1: int} $header */
            $header = unpack('N', substr($png, $offset, 4));
            $length = $header[1];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);

            /** @var array{1: int} $stored */
            $stored = unpack('N', substr($png, $offset + 8 + $length, 4));
            self::assertSame(crc32($type . $data), $stored[1], "CRC of the $type chunk");

            $chunks[$type] = ($chunks[$type] ?? '') . $data;
            $offset += 12 + $length;
        }

        self::assertArrayHasKey('IHDR', $chunks);
        self::assertArrayHasKey('PLTE', $chunks);
        self::assertArrayHasKey('IDAT', $chunks);
        self::assertArrayHasKey('IEND', $chunks);

        /** @var array{width: int, height: int, depth: int, color: int} $header */
        $header = unpack('Nwidth/Nheight/Cdepth/Ccolor', $chunks['IHDR']);
        self::assertSame(3, $header['color'], 'Colour type 3 is a palette.');
        self::assertSame($header['width'], $header['height'], 'A symbol is square.');

        $palette = [];

        foreach (str_split($chunks['PLTE'], 3) as $entry) {
            $palette[] = [ord($entry[0]), ord($entry[1]), ord($entry[2])];
        }

        $raw = gzuncompress($chunks['IDAT']);
        self::assertIsString($raw, 'The image data did not inflate.');

        $width = $header['width'];
        $depth = $header['depth'];
        $bytesPerRow = intdiv(($width * $depth) + 7, 8);
        $rows = [];

        for ($y = 0; $y < $header['height']; $y++) {
            $start = $y * ($bytesPerRow + 1);
            self::assertSame(0, ord($raw[$start]), "Scanline $y uses a filter this reader does not implement.");
            $rows[] = substr($raw, $start + 1, $bytesPerRow);
        }

        $at = static function (int $x, int $y) use ($rows, $depth, $palette): array {
            $row = $rows[$y];

            if ($depth === 8) {
                return $palette[ord($row[$x])];
            }

            $index = (ord($row[$x >> 3]) >> (7 - ($x & 7))) & 1;

            return $palette[$index];
        };

        return ['width' => $width, 'bitDepth' => $depth, 'palette' => $palette, 'at' => $at];
    }

    public function testAPlainSymbolStaysOneBitPerPixel(): void
    {
        $decoded = $this->decode((new PngRenderer(Preset::pngOptions()))->render($this->matrix()));

        self::assertSame(1, $decoded['bitDepth']);
        self::assertCount(2, $decoded['palette'], 'Two colours, two entries.');
    }

    public function testArtworkMovesTheFileToEightBits(): void
    {
        $png = (new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $this->box())))
            ->render($this->matrix());

        $decoded = $this->decode($png);

        self::assertSame(8, $decoded['bitDepth']);
        self::assertGreaterThan(2, count($decoded['palette']), 'The artwork brings colours of its own.');
        self::assertLessThanOrEqual(256, count($decoded['palette']));
    }

    public function testTheFirstTwoPaletteEntriesStayLightAndDark(): void
    {
        $png = (new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $this->box())))
            ->render($this->matrix());

        $decoded = $this->decode($png);

        self::assertSame([255, 255, 255], $decoded['palette'][0]);
        self::assertSame([0, 0, 0], $decoded['palette'][1]);
    }

    /**
     * The one that matters. Every module outside the cleared box has to survive
     * the artwork untouched, or the file is a picture of a QR code rather than
     * one.
     */
    public function testEveryModuleOutsideTheBoxSurvives(): void
    {
        $matrix = $this->matrix();
        $box = $this->box();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $box));

        $decoded = $this->decode($renderer->render($matrix));
        $at = $decoded['at'];

        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();
        $rows = $matrix->rows();
        $checked = 0;

        for ($y = 0; $y < $matrix->size(); $y++) {
            for ($x = 0; $x < $matrix->size(); $x++) {
                if ($placement->covers($x, $y)) {
                    continue;
                }

                // The centre of the module, so a rounding difference at an edge
                // cannot be mistaken for a wrong module.
                $pixel = $at(
                    (($x + $quietZone) * $scale) + intdiv($scale, 2),
                    (($y + $quietZone) * $scale) + intdiv($scale, 2)
                );

                self::assertSame(
                    (bool) $rows[$y][$x],
                    array_sum($pixel) < 384,
                    sprintf('Module %d,%d came out wrong.', $x, $y)
                );

                $checked++;
            }
        }

        self::assertGreaterThan(500, $checked, 'The sweep should cover most of the symbol.');
    }

    public function testTheQuietZoneStaysClear(): void
    {
        $matrix = $this->matrix();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $this->box()));

        $decoded = $this->decode($renderer->render($matrix));
        $at = $decoded['at'];
        $edge = Preset::pngOptions()->quietZone() * $renderer->pixelsPerModule($matrix);

        for ($p = 0; $p < $edge; $p++) {
            self::assertSame([255, 255, 255], $at($p, $p), "Quiet zone dirty at $p,$p.");
            self::assertSame([255, 255, 255], $at($decoded['width'] - 1 - $p, $p));
        }
    }

    public function testTheArtworkActuallyReachesThePixels(): void
    {
        $matrix = $this->matrix();
        $box = $this->box();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $box));

        $decoded = $this->decode($renderer->render($matrix));
        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();
        $at = $decoded['at'];

        $left = ($placement->x() + $quietZone) * $scale;
        $top = ($placement->y() + $quietZone) * $scale;
        $middleX = (int) ($left + ($placement->width() * $scale / 2));
        $middleY = (int) ($top + ($placement->height() * $scale / 2));

        // Scanning down the middle rather than guessing at one coordinate: the
        // column crosses the green ring, then the white square the fixture puts
        // inside it, then the ring again.
        $green = 0;

        for ($y = $top; $y < $top + ($placement->height() * $scale); $y++) {
            if ($at($middleX, $y) === [0x00, 0x98, 0x79]) {
                $green++;
            }
        }

        self::assertGreaterThan($scale, $green, 'The green ring never appeared.');
        self::assertSame([255, 255, 255], $at($middleX, $middleY), 'The white square should be here.');
    }

    public function testTheSameBoxIsUsedAsForTheSvg(): void
    {
        $matrix = $this->matrix();
        $box = LogoBox::square(9, 1);

        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($this->logo(), $box));
        $decoded = $this->decode($renderer->render($matrix));

        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();
        $at = $decoded['at'];

        // The margin ring of the box is light, whatever the modules under it
        // were. That is what makes the artwork readable and it is the SVG's
        // rule too.
        $left = ($placement->x() + $quietZone) * $scale;
        $top = ($placement->y() + $quietZone) * $scale;

        for ($offset = 0; $offset < $placement->width() * $scale; $offset++) {
            self::assertSame([255, 255, 255], $at($left + $offset, $top + intdiv($scale, 4)));
        }
    }

    public function testArtworkOnATransparentBackgroundIsRefused(): void
    {
        $renderer = new PngRenderer(
            Preset::pngOptions()->withLogo($this->logo(), $this->box())->withTransparentBackground()
        );

        $this->expectException(InvalidArgument::class);

        $renderer->render($this->matrix());
    }

    public function testWithoutLogoUndoesIt(): void
    {
        $options = Preset::pngOptions()->withLogo($this->logo(), $this->box())->withoutLogo();

        self::assertFalse($options->hasLogo());
        self::assertSame(1, $this->decode((new PngRenderer($options))->render($this->matrix()))['bitDepth']);
    }

    public function testOptionsAreImmutable(): void
    {
        $plain = PngOptions::default();
        $withLogo = $plain->withLogo($this->logo(), $this->box());

        self::assertFalse($plain->hasLogo());
        self::assertTrue($withLogo->hasLogo());
    }

    // --------------------------------------------------------- refusals ---

    /**
     * @dataProvider unrasterisableArtwork
     */
    public function testArtworkTheRasteriserCannotDrawIsRefusedByName(string $markup, string $expected): void
    {
        $logo = SvgLogo::fromMarkup($markup);

        $rejection = LogoRaster::rejectionFor($logo);

        self::assertNotNull($rejection, 'This artwork should have been refused.');
        self::assertMatchesRegularExpression($expected, $rejection);

        $this->expectException(LogoRejected::class);

        (new PngRenderer(Preset::pngOptions()->withLogo($logo, $this->box())))->render($this->matrix());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function unrasterisableArtwork(): iterable
    {
        yield 'an elliptical arc' => [
            '<svg viewBox="0 0 10 10"><path d="M1 1A4 4 0 0 1 9 9Z" fill="#000"/></svg>',
            '/elliptical arc/',
        ];

        yield 'a stroke' => [
            '<svg viewBox="0 0 10 10"><rect width="8" height="8" fill="none" stroke="#000" stroke-width="2"/></svg>',
            '/stroke/',
        ];

        yield 'group opacity' => [
            '<svg viewBox="0 0 10 10"><g opacity="0.4"><rect width="8" height="8" fill="#000"/></g></svg>',
            '/[Gg]roup opacity/',
        ];
    }

    public function testArtworkTheRasteriserCanDrawIsNotRefused(): void
    {
        self::assertNull(LogoRaster::rejectionFor($this->logo()));
    }

    /**
     * A stroke set to none, or to a width of zero, paints nothing and must not
     * be treated as a stroke.
     */
    public function testAStrokeThatPaintsNothingIsAllowed(): void
    {
        self::assertNull(LogoRaster::rejectionFor(SvgLogo::fromMarkup(
            '<svg viewBox="0 0 10 10"><rect width="8" height="8" fill="#000" stroke="none"/></svg>'
        )));

        self::assertNull(LogoRaster::rejectionFor(SvgLogo::fromMarkup(
            '<svg viewBox="0 0 10 10"><rect width="8" height="8" fill="#000" stroke="#f00" stroke-width="0"/></svg>'
        )));
    }

    public function testAFullyOpaqueGroupIsAllowed(): void
    {
        self::assertNull(LogoRaster::rejectionFor(SvgLogo::fromMarkup(
            '<svg viewBox="0 0 10 10"><g opacity="1"><rect width="8" height="8" fill="#000"/></g></svg>'
        )));
    }

    /**
     * Paint set on a group reaches the shapes inside it, which is how every
     * Illustrator export with a colour layer is put together.
     */
    public function testShapesInheritPaintFromTheirGroup(): void
    {
        $logo = SvgLogo::fromMarkup(
            '<svg viewBox="0 0 100 100"><g fill="#009879"><rect width="100" height="100"/></g></svg>'
        );

        $matrix = $this->matrix();
        $box = $this->box();
        $renderer = new PngRenderer(Preset::pngOptions()->withLogo($logo, $box));
        $decoded = $this->decode($renderer->render($matrix));

        $placement = $box->placeIn($matrix);
        $scale = $renderer->pixelsPerModule($matrix);
        $quietZone = Preset::pngOptions()->quietZone();

        $at = $decoded['at'];
        $centre = $at(
            (int) ((($placement->x() + $quietZone) * $scale) + ($placement->width() * $scale / 2)),
            (int) ((($placement->y() + $quietZone) * $scale) + ($placement->height() * $scale / 2))
        );

        self::assertSame([0x00, 0x98, 0x79], $centre, 'The group colour should have reached the rect.');
    }
}

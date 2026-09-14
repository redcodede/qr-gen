<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Raster;

use Redcodede\QrGen\Qr\Exception\LogoRejected;

/**
 * Reads a PNG back into pixels, by the specification rather than by an image
 * library.
 *
 * The counterpart to PngRenderer, and here for the same reason: this package
 * promises to need no image extension, and a raster logo has to be decoded
 * before it can be placed in the middle of a symbol. `zlib` inflates the image
 * data, `crc32()` checks the chunks, and the rest is unpacking bytes.
 *
 * Everything comes out as **8-bit RGBA in one binary string**, four bytes per
 * pixel, whatever the file was. A PHP array of a third of a million pixels
 * costs tens of megabytes; the same pixels as a string cost one and a half, and
 * the scaler reads them with `ord()` just as fast.
 *
 * **Sixteen-bit samples are taken down to eight** by keeping the high byte.
 * Nothing downstream can use more: the output palette holds 256 entries, and
 * the artwork ends up a centimetre wide.
 *
 * **Interlaced files are refused.** Adam7 stores the image as seven
 * interleaved passes, each with its own geometry, and no logo delivery has ever
 * arrived that way — it is an option for progressive loading over a slow line,
 * which is not what artwork for print is for. The refusal says to save it
 * again without interlacing, which is one checkbox.
 */
final class PngDecoder
{
    private const SIGNATURE = "\x89PNG\x0d\x0a\x1a\x0a";

    /** Colour types, by their number in the specification. */
    private const GREY = 0;
    private const TRUECOLOUR = 2;
    private const PALETTE = 3;
    private const GREY_ALPHA = 4;
    private const TRUECOLOUR_ALPHA = 6;

    /** Samples per pixel for each colour type. */
    private const CHANNELS = [
        self::GREY => 1,
        self::TRUECOLOUR => 3,
        self::PALETTE => 1,
        self::GREY_ALPHA => 2,
        self::TRUECOLOUR_ALPHA => 4,
    ];

    /** Bit depths each colour type is allowed to use. */
    private const DEPTHS = [
        self::GREY => [1, 2, 4, 8, 16],
        self::TRUECOLOUR => [8, 16],
        self::PALETTE => [1, 2, 4, 8],
        self::GREY_ALPHA => [8, 16],
        self::TRUECOLOUR_ALPHA => [8, 16],
    ];

    /**
     * Guard against a small file that inflates to an enormous raster. 64
     * megapixels is far more than any logo and still only a quarter of a
     * gigabyte once expanded.
     */
    private const MAX_PIXELS = 64000000;

    private function __construct()
    {
    }

    /**
     * @return array{width: int, height: int, rgba: string} Four bytes per pixel, row by row
     *
     * @throws LogoRejected
     */
    public static function decode(string $bytes): array
    {
        $chunks = self::chunks($bytes);
        $header = self::header($chunks);

        $width = $header['width'];
        $height = $header['height'];

        $raw = @gzuncompress($chunks['IDAT']);

        if (!is_string($raw)) {
            throw LogoRejected::undecodablePng('its image data could not be inflated.');
        }

        $channels = self::CHANNELS[$header['colour']];
        $depth = $header['depth'];
        $bytesPerPixel = max(1, intdiv($channels * $depth, 8));
        $bytesPerRow = intdiv(($width * $channels * $depth) + 7, 8);

        if (strlen($raw) < $height * ($bytesPerRow + 1)) {
            throw LogoRejected::undecodablePng(sprintf(
                'it holds %d bytes of image data where %d are needed.',
                strlen($raw),
                $height * ($bytesPerRow + 1)
            ));
        }

        $palette = self::palette($chunks, $header['colour']);
        $transparency = $chunks['tRNS'] ?? null;

        $rgba = '';
        $previous = str_repeat("\x00", $bytesPerRow);
        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$offset]);
            $line = substr($raw, $offset + 1, $bytesPerRow);
            $offset += $bytesPerRow + 1;

            $line = self::unfilter($filter, $line, $previous, $bytesPerPixel, $bytesPerRow);
            $previous = $line;

            $rgba .= self::toRgba($line, $width, $header['colour'], $depth, $palette, $transparency);
        }

        return ['width' => $width, 'height' => $height, 'rgba' => $rgba];
    }

    /**
     * Reads the header alone, without inflating anything.
     *
     * @return array{width: int, height: int} for a caller that only wants the size
     *
     * @throws LogoRejected
     */
    public static function size(string $bytes): array
    {
        $header = self::header(self::chunks($bytes, false));

        return ['width' => $header['width'], 'height' => $header['height']];
    }

    /**
     * Rewrites the file with only the chunks that carry the picture.
     *
     * A PNG cannot hold a script, but it can hold text, EXIF and colour
     * profiles, and those travel: a camera's GPS coordinates or a designer's
     * name inside an artwork file end up wherever the symbol ends up. The
     * critical chunks plus tRNS are the picture; everything else is dropped,
     * which is the same rule the SVG sanitiser follows.
     *
     * @throws LogoRejected
     */
    public static function stripToPicture(string $bytes): string
    {
        $order = ['IHDR', 'PLTE', 'tRNS', 'IDAT'];
        $chunks = self::chunks($bytes);
        $rebuilt = self::SIGNATURE;

        foreach ($order as $type) {
            if (isset($chunks[$type])) {
                $rebuilt .= self::chunk($type, $chunks[$type]);
            }
        }

        return $rebuilt . self::chunk('IEND', '');
    }

    /**
     * Walks the chunk list, checking each CRC.
     *
     * IDAT may be split across any number of chunks and is concatenated; the
     * compressed stream runs across the boundaries, so treating them separately
     * would fail to inflate.
     *
     * @return array<string, string>
     *
     * @throws LogoRejected
     */
    private static function chunks(string $bytes, bool $wholeFile = true): array
    {
        if (strncmp($bytes, self::SIGNATURE, 8) !== 0) {
            throw LogoRejected::notAPng();
        }

        $chunks = [];
        $offset = 8;
        $length = strlen($bytes);

        while ($offset + 12 <= $length) {
            /** @var array{1: int} $size */
            $size = unpack('N', substr($bytes, $offset, 4));
            $bodyLength = $size[1];
            $type = substr($bytes, $offset + 4, 4);

            if ($offset + 12 + $bodyLength > $length) {
                throw LogoRejected::undecodablePng(sprintf('the %s chunk runs past the end of the file.', $type));
            }

            $body = substr($bytes, $offset + 8, $bodyLength);

            /** @var array{1: int} $stored */
            $stored = unpack('N', substr($bytes, $offset + 8 + $bodyLength, 4));

            if ($stored[1] !== crc32($type . $body)) {
                throw LogoRejected::undecodablePng(sprintf('the %s chunk fails its checksum.', $type));
            }

            $chunks[$type] = ($chunks[$type] ?? '') . $body;
            $offset += 12 + $bodyLength;

            if ($type === 'IEND' || (!$wholeFile && $type === 'IHDR')) {
                break;
            }
        }

        if (!isset($chunks['IHDR'])) {
            throw LogoRejected::undecodablePng('it has no header chunk.');
        }

        if ($wholeFile && !isset($chunks['IDAT'])) {
            throw LogoRejected::undecodablePng('it has no image data.');
        }

        return $chunks;
    }

    /**
     * @param array<string, string> $chunks
     *
     * @return array{width: int, height: int, depth: int, colour: int}
     *
     * @throws LogoRejected
     */
    private static function header(array $chunks): array
    {
        if (strlen($chunks['IHDR']) < 13) {
            throw LogoRejected::undecodablePng('its header chunk is too short.');
        }

        /** @var array{width: int, height: int, depth: int, colour: int, compression: int, filter: int, interlace: int} $header */
        $header = unpack(
            'Nwidth/Nheight/Cdepth/Ccolour/Ccompression/Cfilter/Cinterlace',
            substr($chunks['IHDR'], 0, 13)
        );

        if ($header['width'] < 1 || $header['height'] < 1) {
            throw LogoRejected::undecodablePng('it declares no width or no height.');
        }

        if ($header['width'] * $header['height'] > self::MAX_PIXELS) {
            throw LogoRejected::pngTooLarge($header['width'], $header['height']);
        }

        if ($header['interlace'] !== 0) {
            throw LogoRejected::interlacedPng();
        }

        if ($header['compression'] !== 0 || $header['filter'] !== 0) {
            throw LogoRejected::undecodablePng('it uses a compression or filtering method the format does not define.');
        }

        if (!isset(self::CHANNELS[$header['colour']])) {
            throw LogoRejected::undecodablePng(sprintf('colour type %d is not defined.', $header['colour']));
        }

        if (!in_array($header['depth'], self::DEPTHS[$header['colour']], true)) {
            throw LogoRejected::undecodablePng(sprintf(
                '%d bits per sample is not allowed with colour type %d.',
                $header['depth'],
                $header['colour']
            ));
        }

        return $header;
    }

    /**
     * @param array<string, string> $chunks
     *
     * @return list<array{0: int, 1: int, 2: int}>
     *
     * @throws LogoRejected
     */
    private static function palette(array $chunks, int $colour): array
    {
        if ($colour !== self::PALETTE) {
            return [];
        }

        if (!isset($chunks['PLTE'])) {
            throw LogoRejected::undecodablePng('it is a palette image with no palette.');
        }

        $entries = [];

        foreach (str_split($chunks['PLTE'], 3) as $entry) {
            if (strlen($entry) === 3) {
                $entries[] = [ord($entry[0]), ord($entry[1]), ord($entry[2])];
            }
        }

        return $entries;
    }

    /**
     * Undoes one scanline's filter.
     *
     * The five filters predict each byte from the one to its left, the one
     * above, or both, and store the difference; every arithmetic step is modulo
     * 256. Bytes before the start of the line count as zero, which is why the
     * first pixel of a row needs no special case beyond the bounds check.
     *
     * @throws LogoRejected
     */
    private static function unfilter(
        int $filter,
        string $line,
        string $previous,
        int $bytesPerPixel,
        int $bytesPerRow
    ): string {
        if ($filter === 0) {
            return $line;
        }

        if ($filter > 4) {
            throw LogoRejected::undecodablePng(sprintf('a scanline uses filter %d, which is not defined.', $filter));
        }

        $out = [];

        for ($i = 0; $i < $bytesPerRow; $i++) {
            $raw = ord($line[$i]);
            $left = $i >= $bytesPerPixel ? $out[$i - $bytesPerPixel] : 0;
            $up = ord($previous[$i]);

            if ($filter === 1) {
                $out[$i] = ($raw + $left) & 0xFF;

                continue;
            }

            if ($filter === 2) {
                $out[$i] = ($raw + $up) & 0xFF;

                continue;
            }

            if ($filter === 3) {
                $out[$i] = ($raw + intdiv($left + $up, 2)) & 0xFF;

                continue;
            }

            $upperLeft = $i >= $bytesPerPixel ? ord($previous[$i - $bytesPerPixel]) : 0;
            $out[$i] = ($raw + self::paeth($left, $up, $upperLeft)) & 0xFF;
        }

        return pack('C*', ...$out);
    }

    /**
     * The Paeth predictor: of the three neighbours, the one closest to their
     * linear combination.
     */
    private static function paeth(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $toLeft = abs($estimate - $left);
        $toUp = abs($estimate - $up);
        $toUpperLeft = abs($estimate - $upperLeft);

        if ($toLeft <= $toUp && $toLeft <= $toUpperLeft) {
            return $left;
        }

        return $toUp <= $toUpperLeft ? $up : $upperLeft;
    }

    /**
     * One unfiltered scanline to 8-bit RGBA.
     *
     * @param list<array{0: int, 1: int, 2: int}> $palette
     */
    private static function toRgba(
        string $line,
        int $width,
        int $colour,
        int $depth,
        array $palette,
        ?string $transparency
    ): string {
        $samples = self::samples($line, $width * self::CHANNELS[$colour], $depth);
        $out = '';

        if ($colour === self::TRUECOLOUR_ALPHA) {
            for ($x = 0; $x < $width; $x++) {
                $i = $x * 4;
                $out .= chr($samples[$i]) . chr($samples[$i + 1]) . chr($samples[$i + 2]) . chr($samples[$i + 3]);
            }

            return $out;
        }

        if ($colour === self::TRUECOLOUR) {
            $clear = self::transparentTruecolour($transparency, $depth);

            for ($x = 0; $x < $width; $x++) {
                $i = $x * 3;
                $red = $samples[$i];
                $green = $samples[$i + 1];
                $blue = $samples[$i + 2];
                $alpha = $clear !== null && $clear === [$red, $green, $blue] ? 0 : 255;
                $out .= chr($red) . chr($green) . chr($blue) . chr($alpha);
            }

            return $out;
        }

        if ($colour === self::PALETTE) {
            $count = count($palette);

            for ($x = 0; $x < $width; $x++) {
                $index = $samples[$x];

                if ($index >= $count) {
                    throw LogoRejected::undecodablePng('a pixel refers to a palette entry that does not exist.');
                }

                [$red, $green, $blue] = $palette[$index];
                // tRNS on a palette image is one alpha byte per entry, and it
                // may be shorter than the palette; the rest are opaque.
                $alpha = $transparency !== null && $index < strlen($transparency)
                    ? ord($transparency[$index])
                    : 255;
                $out .= chr($red) . chr($green) . chr($blue) . chr($alpha);
            }

            return $out;
        }

        if ($colour === self::GREY_ALPHA) {
            for ($x = 0; $x < $width; $x++) {
                $grey = chr($samples[$x * 2]);
                $out .= $grey . $grey . $grey . chr($samples[($x * 2) + 1]);
            }

            return $out;
        }

        $clear = self::transparentGrey($transparency, $depth);

        for ($x = 0; $x < $width; $x++) {
            $value = $samples[$x];
            // Below eight bits the samples are scaled up so that the deepest
            // value becomes 255 rather than 1, 3 or 15.
            $scaled = $depth < 8 ? (int) round($value * 255 / ((1 << $depth) - 1)) : $value;
            $grey = chr($scaled);
            $out .= $grey . $grey . $grey . chr($clear !== null && $clear === $value ? 0 : 255);
        }

        return $out;
    }

    /**
     * Unpacks a scanline into one integer per sample, whatever the bit depth.
     *
     * @return list<int>
     */
    private static function samples(string $line, int $count, int $depth): array
    {
        if ($depth === 8) {
            /** @var list<int> $values */
            $values = array_values(unpack('C*', $line));

            return $values;
        }

        if ($depth === 16) {
            /** @var list<int> $values */
            $values = array_values(unpack('n*', $line));

            // Sixteen bits down to eight: the high byte. Dividing by 257 would
            // be the exact scaling and rounds to the same value everywhere.
            return array_map(static function (int $value): int {
                return $value >> 8;
            }, array_slice($values, 0, $count));
        }

        $values = [];
        $mask = (1 << $depth) - 1;
        $perByte = intdiv(8, $depth);

        for ($index = 0; $index < $count; $index++) {
            $byte = ord($line[intdiv($index, $perByte)]);
            $shift = 8 - $depth - (($index % $perByte) * $depth);
            $values[] = ($byte >> $shift) & $mask;
        }

        return $values;
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function transparentTruecolour(?string $transparency, int $depth): ?array
    {
        if ($transparency === null || strlen($transparency) < 6) {
            return null;
        }

        /** @var array{1: int, 2: int, 3: int} $values */
        $values = unpack('n3', substr($transparency, 0, 6));
        $shift = $depth === 16 ? 8 : 0;

        return [$values[1] >> $shift, $values[2] >> $shift, $values[3] >> $shift];
    }

    private static function transparentGrey(?string $transparency, int $depth): ?int
    {
        if ($transparency === null || strlen($transparency) < 2) {
            return null;
        }

        /** @var array{1: int} $value */
        $value = unpack('n', substr($transparency, 0, 2));

        return $depth === 16 ? $value[1] >> 8 : $value[1];
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Qr\Encoder;

use BaconQrCode\Common\ErrorCorrectionLevel as BaconLevel;
use BaconQrCode\Common\Version;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use Redcodede\QrGen\Qr\Contract\QrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Exception\EncodingFailed;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Throwable;

/**
 * Encodes via bacon/bacon-qr-code and hands back our own matrix.
 *
 * Only BaconQrCode\Encoder\Encoder::encode() is used. None of the library's
 * renderers are touched, which is what keeps ext-dom, ext-gd and ext-imagick out
 * of this package's requirements — those are needed by its SVG and Imagick
 * back ends, not by its encoder.
 *
 * The library picks the encoding mode from the data on its own. It encodes a
 * string in a single mode and does not mix segments, so a URL with a lowercase
 * host goes out entirely in byte mode even though a short uppercase code inside
 * it would fit the alphanumeric mode. For a payload of a few dozen characters
 * the difference is a fraction of one version.
 *
 * Both the 2.x and the 3.x line are supported, because the part of their API
 * used here is identical. Composer picks 3.x on PHP 8.1 and up, which matters:
 * 2.x still declares parameters as implicitly nullable and therefore emits two
 * deprecation notices per encode on PHP 8.4.
 */
final class BaconQrEncoder implements QrEncoder
{
    /**
     * Latin-1, which covers URLs — they are ASCII by definition.
     *
     * Spelled out rather than taken from the library, whose own constant is
     * misspelled in 2.x and only kept as a deprecated alias in 3.x. Both
     * versions resolve it to this value.
     */
    public const DEFAULT_ENCODING = 'ISO-8859-1';

    /** @var string */
    private $encoding;

    /**
     * @param string $encoding Character set the payload is interpreted as. Pass
     *                         "UTF-8" for payloads with characters beyond
     *                         Latin-1 — note that 3.x then prefixes an ECI
     *                         header, which some older scanners dislike.
     */
    public function __construct(string $encoding = self::DEFAULT_ENCODING)
    {
        $this->encoding = $encoding;
    }

    public function encode(string $data, ErrorCorrection $level): ModuleMatrix
    {
        if ($data === '') {
            throw InvalidArgument::emptyData();
        }

        try {
            $qrCode = BaconEncoder::encode($data, $this->baconLevel($level), $this->encoding);
        } catch (Throwable $exception) {
            throw EncodingFailed::forPayloadOfLength(strlen($data), $exception);
        }

        $matrix = $qrCode->getMatrix();
        $width = $matrix->getWidth();
        $height = $matrix->getHeight();

        if ($width !== $height) {
            throw EncodingFailed::notASquareMatrix($width, $height);
        }

        // Which modules are finders, separators, timing and alignment
        // patterns, format and version information. None of that is covered by
        // error correction, so a logo has to be kept off it — and only the
        // encoder knows where it is, which is why it travels with the matrix.
        $version = Version::getVersionForNumber(intdiv($height - 17, 4));
        $functionPattern = $version->buildFunctionPattern();
        $alignment = self::alignmentModules($version, $height);

        $rows = [];
        $reserved = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];
            $reservedRow = [];

            for ($x = 0; $x < $width; $x++) {
                // The library's ByteMatrix stores 1 for a dark module, 0 for a
                // light one, and -1 for a module it has not written yet. A
                // finished symbol has no -1 left, and treating anything but 1
                // as light keeps a hypothetical leftover from rendering dark.
                $row[] = $matrix->get($x, $y) === 1;
                $reservedRow[] = $functionPattern->get($x, $y);
            }

            $rows[] = $row;
            $reserved[] = $reservedRow;
        }

        return new ModuleMatrix($rows, $reserved, $alignment);
    }

    /**
     * The 5x5 alignment patterns, marked separately from the rest of the
     * function pattern.
     *
     * The centres come from the library's version table rather than from a copy
     * of it here. The three combinations that fall on a finder corner are not
     * drawn as alignment patterns, so they are skipped — matching the
     * specification rather than merely staying out of trouble.
     *
     * @return list<list<bool>>
     */
    private static function alignmentModules(Version $version, int $size): array
    {
        $mask = array_fill(0, $size, array_fill(0, $size, false));
        $centers = $version->getAlignmentPatternCenters();

        if ($centers === []) {
            return $mask;
        }

        $first = $centers[0];
        $last = $centers[count($centers) - 1];

        foreach ($centers as $centerY) {
            foreach ($centers as $centerX) {
                $onFinderCorner = ($centerX === $first && $centerY === $first)
                    || ($centerX === $first && $centerY === $last)
                    || ($centerX === $last && $centerY === $first);

                if ($onFinderCorner) {
                    continue;
                }

                for ($y = $centerY - 2; $y <= $centerY + 2; $y++) {
                    for ($x = $centerX - 2; $x <= $centerX + 2; $x++) {
                        if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                            $mask[$y][$x] = true;
                        }
                    }
                }
            }
        }

        return $mask;
    }

    private function baconLevel(ErrorCorrection $level): BaconLevel
    {
        switch ($level->value()) {
            case ErrorCorrection::LOW:
                return BaconLevel::L();

            case ErrorCorrection::MEDIUM:
                return BaconLevel::M();

            case ErrorCorrection::QUARTILE:
                return BaconLevel::Q();

            default:
                return BaconLevel::H();
        }
    }
}

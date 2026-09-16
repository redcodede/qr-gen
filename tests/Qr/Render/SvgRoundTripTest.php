<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Render;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\ModuleMatrix;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

/**
 * Reads the rendered SVG back into a matrix and compares it with the one that
 * went in.
 *
 * The renderer merges runs of dark modules into single subpaths, which is where
 * an off-by-one would hide: a symbol drawn one module too wide in places still
 * looks like a QR code and still passes every structural check, but no longer
 * decodes. Rebuilding the grid from the path is the one assertion that rules
 * that out, and it needs no decoder installed.
 *
 * This covers step 7 only. Steps 1 to 6 are bacon's, and confirming those means
 * pointing a phone at the demo page or running the output through an
 * independent decoder such as zbarimg.
 */
final class SvgRoundTripTest extends TestCase
{
    /**
     * @dataProvider payloadsAndSettings
     */
    public function testTheRenderedPathDescribesExactlyTheMatrixItWasGiven(
        string $payload,
        string $level,
        int $quietZone
    ): void {
        $matrix = (new BaconQrEncoder())->encode($payload, ErrorCorrection::fromString($level));

        $svg = (new SvgRenderer(
            SvgOptions::default()->withQuietZone($quietZone)->withModuleSize(1)
        ))->render($matrix);

        self::assertSame(
            $matrix->rows(),
            $this->matrixFromSvg($svg, $matrix->size(), $quietZone)->rows()
        );
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function payloadsAndSettings(): iterable
    {
        yield 'demo url, spec quiet zone' => ['https://www.redcode.de/', 'M', 4];
        yield 'demo url, no quiet zone' => ['https://www.redcode.de/', 'M', 0];
        yield 'demo url, highest recovery' => ['https://www.redcode.de/', 'H', 4];
        yield 'the briefing url' => ['https://example.org/qr/7K4M2', 'H', 4];
        yield 'the briefing url uppercased' => ['HTTPS://EXAMPLE.ORG/QR/7K4M2', 'H', 4];
        yield 'smallest symbol' => ['HI', 'L', 4];
        yield 'a long payload, many versions up' => [str_repeat('https://example.org/qr/7K4M2 ', 20), 'L', 4];
    }

    /**
     * Every dark module has to sit inside the symbol. A run that reached into
     * the quiet zone would eat the light border scanners depend on.
     */
    public function testNoDarkModuleLandsInTheQuietZone(): void
    {
        $matrix = (new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::medium());
        $svg = (new SvgRenderer(SvgOptions::default()->withQuietZone(4)))->render($matrix);

        foreach ($this->runsFromSvg($svg) as [$x, $y, $run]) {
            self::assertGreaterThanOrEqual(4, $x, 'A run starts left of the quiet zone.');
            self::assertGreaterThanOrEqual(4, $y, 'A run starts above the quiet zone.');
            self::assertLessThanOrEqual(4 + $matrix->size(), $x + $run, 'A run runs past the right edge.');
            self::assertLessThanOrEqual(4 + $matrix->size(), $y + 1, 'A run sits below the bottom edge.');
        }
    }

    /**
     * Merging must not change how many modules are dark, only how many subpaths
     * describe them.
     */
    public function testMergingPreservesTheNumberOfDarkModules(): void
    {
        $matrix = (new BaconQrEncoder())->encode('https://www.redcode.de/', ErrorCorrection::quartile());
        $svg = (new SvgRenderer(SvgOptions::default()->withQuietZone(0)))->render($matrix);

        $expected = 0;

        foreach ($matrix->rows() as $row) {
            $expected += count(array_filter($row));
        }

        $drawn = 0;
        $runs = $this->runsFromSvg($svg);

        foreach ($runs as [, , $run]) {
            $drawn += $run;
        }

        self::assertSame($expected, $drawn);
        self::assertLessThan($expected, count($runs), 'Merging produced no saving at all, so it is not merging.');
    }

    private function matrixFromSvg(string $svg, int $size, int $quietZone): ModuleMatrix
    {
        $rows = array_fill(0, $size, array_fill(0, $size, false));

        foreach ($this->runsFromSvg($svg) as [$x, $y, $run]) {
            for ($offset = 0; $offset < $run; $offset++) {
                $rows[$y - $quietZone][$x - $quietZone + $offset] = true;
            }
        }

        return new ModuleMatrix($rows);
    }

    /**
     * Parses the path data back into runs of dark modules.
     *
     * The pattern insists on the exact shape the renderer emits, so a change to
     * the path syntax fails here instead of silently matching nothing and
     * reporting an empty symbol as correct — which is why the count is asserted
     * against the number of subpaths.
     *
     * @return list<array{int, int, int}> x, y, length
     */
    private function runsFromSvg(string $svg): array
    {
        if (preg_match('/ d="([^"]*)"/', $svg, $attribute) !== 1) {
            self::fail('The rendered SVG has no path data.');
        }

        $data = $attribute[1];

        preg_match_all('/M(\d+) (\d+)h(\d+)v1h-(\d+)z/', $data, $matches, PREG_SET_ORDER);

        $runs = [];

        foreach ($matches as $match) {
            self::assertSame(
                $match[3],
                $match[4],
                'A subpath closes with a different width than it opened with.'
            );

            $runs[] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        }

        self::assertSame(
            substr_count($data, 'M'),
            count($runs),
            'Some subpaths did not match the expected syntax, so this test is not reading all of them.'
        );

        return $runs;
    }
}

<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr\Logo;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\Qr\Encoder\BaconQrEncoder;
use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Logo\LogoBox;
use Redcodede\QrGen\Qr\Logo\SvgLogo;
use Redcodede\QrGen\Qr\Render\SvgOptions;
use Redcodede\QrGen\Qr\Render\SvgRenderer;

/**
 * Runs the actual files in demo/logos through the whole chain.
 *
 * The unit tests use markup written by hand, which is precise but says nothing
 * about what a designer's export really looks like. This is the counterpart:
 * every file someone dropped in that folder has to survive the sanitiser and
 * come out embedded. When it does not, the message names what to fix in the
 * export — which is the point of refusing rather than stripping.
 */
final class RealWorldLogoTest extends TestCase
{
    private const LOGO_DIR = __DIR__ . '/../../../demo/logos';

    public function testThereIsSomethingToCheck(): void
    {
        self::assertNotSame(
            [],
            self::logoFiles(),
            'No SVG in demo/logos, so this test proves nothing. Put a real export there.'
        );
    }

    /**
     * @dataProvider logoFilesProvider
     */
    public function testARealExportIsAcceptedAndEmbedded(string $path): void
    {
        $logo = SvgLogo::fromMarkup((string) file_get_contents($path));

        self::assertGreaterThan(0.0, $logo->width());
        self::assertGreaterThan(0.0, $logo->height());
        self::assertNotSame('', $logo->markup());

        // Whatever the export carried, none of this may reach the output.
        foreach (['<style', 'class=', ' id=', 'url(', '<script', 'xlink:href', '<!--', '<image'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $logo->markup(),
                sprintf('%s survived the sanitiser in %s.', $forbidden, basename($path))
            );
        }
    }

    /**
     * @dataProvider logoFilesProvider
     */
    public function testARealExportRendersIntoTheBriefingSymbol(string $path): void
    {
        $logo = SvgLogo::fromMarkup((string) file_get_contents($path));
        $matrix = (new BaconQrEncoder())->encode('https://example.org/qr/7K4M2', ErrorCorrection::high());

        $svg = (new SvgRenderer(
            SvgOptions::default()->withLogo(
                $logo,
                LogoBox::forAspectRatio($logo->width() / $logo->height(), 11, 1)
            )
        ))->render($matrix);

        self::assertStringContainsString('<g transform="translate(', $svg);
        self::assertStringContainsString($logo->markup(), $svg);
        self::assertStringNotContainsString('https://', str_replace('http://www.w3.org/2000/svg', '', $svg));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function logoFilesProvider(): iterable
    {
        foreach (self::logoFiles() as $path) {
            yield basename($path) => [$path];
        }
    }

    /**
     * @return list<string>
     */
    private static function logoFiles(): array
    {
        return array_values(glob(self::LOGO_DIR . '/*.svg') ?: []);
    }
}

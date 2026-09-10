<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\Qr;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enforces the one architectural rule this package has.
 *
 * Statamic 3 and Statamic 6 cannot be served by a single release: 3.4 wants
 * Laravel 8 and Vue 2, 6 wants PHP 8.2, Laravel 11 and Vue 3. The plan is that
 * src/Qr stays free of framework references, so porting means writing a new
 * shell rather than doing the work twice.
 *
 * A rule like that decays the moment someone reaches for a facade because it is
 * convenient. The README promises this is checked by a test rather than by
 * agreement, and this is that test.
 */
final class CoreIsFrameworkFreeTest extends TestCase
{
    /**
     * Substrings that betray a framework dependency. Kept as plain strings and
     * matched case-sensitively, because these are all namespace and class names.
     */
    private const FORBIDDEN = [
        'Statamic\\',
        'Illuminate\\',
        'Laravel\\',
        'Statamic::',
        'facade(',
        'Facades\\',
        'config(',
        'app(',
        'resolve(',
        'trans(',
        '__(',
    ];

    public function testTheCoreNamesNoFrameworkClass(): void
    {
        $offences = [];

        foreach ($this->coreFiles() as $path => $source) {
            foreach (self::FORBIDDEN as $needle) {
                if (strpos($source, $needle) !== false) {
                    $offences[] = sprintf('%s references %s', $path, $needle);
                }
            }
        }

        self::assertSame(
            [],
            $offences,
            "src/Qr has to stay free of Laravel and Statamic so it survives the port to Statamic 6.\n"
            . "Move whatever needs the framework into src/Statamic and inject it through an interface."
        );
    }

    /**
     * The core hands back strings and matrices. If it started writing files the
     * caller would lose the choice of whether a symbol becomes a response, a
     * file, or neither — and the promise that nothing is stored would be ours to
     * break rather than theirs to keep.
     */
    public function testTheCoreTouchesNeitherTheFilesystemNorTheNetwork(): void
    {
        $forbidden = [
            'file_put_contents',
            'file_get_contents',
            'fopen',
            'fwrite',
            'unlink',
            'mkdir',
            'curl_init',
            'fsockopen',
            'stream_socket_client',
            'error_log',
            'syslog',
            'getenv',
            'putenv',
            '$_SERVER',
            '$_GET',
            '$_POST',
            'exec(',
            'shell_exec',
            'system(',
            'proc_open',
            'eval(',
        ];

        $offences = [];

        foreach ($this->coreFiles() as $path => $source) {
            foreach ($forbidden as $needle) {
                if (strpos($source, $needle) !== false) {
                    $offences[] = sprintf('%s calls %s', $path, $needle);
                }
            }
        }

        self::assertSame([], $offences);
    }

    public function testTheScanActuallyFoundFiles(): void
    {
        self::assertGreaterThan(5, count($this->coreFiles()), 'The scan found almost nothing, so it is not scanning.');
    }

    /**
     * @return array<string, string> Relative path to source, comments stripped
     */
    private function coreFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/src/Qr';
        $files = [];

        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(
            // Without SKIP_DOTS the iterator yields "." and recurses into it forever.
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // Comments explain the rule and name the very things it forbids, so
            // scanning them would make this test fail on its own documentation.
            $files['src/Qr/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))]
                = $this->stripComments($source);
        }

        return $files;
    }

    private function stripComments(string $source): string
    {
        $kept = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $kept .= is_array($token) ? $token[1] : $token;
        }

        return $kept;
    }
}

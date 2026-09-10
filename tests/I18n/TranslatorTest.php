<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Tests\I18n;

use PHPUnit\Framework\TestCase;
use Redcodede\QrGen\I18n\Translator;
use Redcodede\QrGen\Qr\Exception\InvalidArgument;

final class TranslatorTest extends TestCase
{
    public function testGermanIsTheDefault(): void
    {
        self::assertSame('de', Translator::DEFAULT_LOCALE);
        self::assertSame('de', Translator::forLocale()->locale());
    }

    public function testEnglishIsReachedByAskingForIt(): void
    {
        self::assertSame('en', Translator::forLocale('en')->locale());
    }

    /**
     * @dataProvider sloppyLocales
     */
    public function testALocaleIsNormalizedBeforeItIsLookedUp(string $given): void
    {
        self::assertSame('en', Translator::forLocale($given)->locale());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sloppyLocales(): iterable
    {
        yield 'uppercase' => ['EN'];
        yield 'padded' => ['  en  '];
        yield 'mixed' => ['En'];
    }

    public function testAnUnknownLocaleIsRefusedWhenAskedForDirectly(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('no catalogue for the locale "fr"');

        Translator::forLocale('fr');
    }

    /**
     * The entry point a request uses: a query parameter is whatever someone
     * typed, so an unusable one falls back rather than breaking the page.
     *
     * @dataProvider unusableLocales
     */
    public function testAnUnusableLocaleFallsBackToGerman(?string $given): void
    {
        self::assertSame('de', Translator::forLocaleOrDefault($given)->locale());
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function unusableLocales(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'unknown' => ['fr'];
        yield 'nonsense' => ['../../etc/passwd'];
    }

    public function testItReturnsTheTextForAKey(): void
    {
        self::assertSame('Erzeugen', Translator::forLocale('de')->get('form.submit'));
        self::assertSame('Generate', Translator::forLocale('en')->get('form.submit'));
    }

    public function testPlaceholdersAreSubstituted(): void
    {
        $text = Translator::forLocale('de')->get('facts.payload.value', ['bytes' => 28]);

        self::assertSame('28 Bytes', $text);
    }

    public function testSeveralPlaceholdersAreSubstitutedInOnePass(): void
    {
        $text = Translator::forLocale('en')->get('facts.modules.value', [
            'size' => 33,
            'total' => '1,089',
        ]);

        self::assertSame('33 × 33 = 1,089', $text);
    }

    /**
     * Louder than an empty string and quieter than an exception. A key sitting
     * in a table where a sentence belongs is unmistakable; a blank cell is not.
     */
    public function testAMissingKeyComesBackAsItself(): void
    {
        self::assertSame('nope.not.a.key', Translator::forLocale('de')->get('nope.not.a.key'));
        self::assertFalse(Translator::forLocale('de')->has('nope.not.a.key'));
    }

    public function testHasReportsAKeyThatExists(): void
    {
        self::assertTrue(Translator::forLocale('de')->has('app.title'));
    }

    /**
     * A catalogue that is behind should show German where it has a gap, not a
     * blank. Asserted through has(), which consults the fallback too.
     */
    public function testANonDefaultLocaleFallsBackPerKey(): void
    {
        $english = Translator::forLocale('en');

        foreach (Translator::forLocale('de')->keys() as $key) {
            self::assertTrue($english->has($key), "English cannot resolve {$key}, not even by fallback.");
        }
    }

    /**
     * The test that keeps the two catalogues from drifting. A translation that
     * silently diverges is worse than one that is missing — the missing one is
     * visible.
     */
    public function testBothCataloguesHoldExactlyTheSameKeys(): void
    {
        $german = Translator::forLocale('de')->keys();
        $english = Translator::forLocale('en')->keys();

        sort($german);
        sort($english);

        self::assertSame(
            $german,
            $english,
            "The catalogues have drifted.\nOnly in de: "
            . implode(', ', array_diff($german, $english))
            . "\nOnly in en: "
            . implode(', ', array_diff($english, $german))
        );
    }

    /**
     * Every placeholder a German text uses has to exist in the English one and
     * the other way round, otherwise one locale renders a stray ":headroom"
     * where the other renders a number.
     */
    public function testBothCataloguesUseTheSamePlaceholdersPerKey(): void
    {
        $german = Translator::forLocale('de');
        $english = Translator::forLocale('en');

        foreach ($german->keys() as $key) {
            self::assertSame(
                $this->placeholders($german->get($key)),
                $this->placeholders($english->get($key)),
                "The placeholders for {$key} differ between the catalogues."
            );
        }
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $text, $matches);

        $found = array_unique($matches[1]);
        sort($found);

        return array_values($found);
    }

    public function testNoTextIsEmpty(): void
    {
        foreach (Translator::AVAILABLE_LOCALES as $locale) {
            $translator = Translator::forLocale($locale);

            foreach ($translator->keys() as $key) {
                self::assertNotSame('', trim($translator->get($key)), "{$locale}/{$key} is empty.");
            }
        }
    }

    /**
     * A level hint per error correction level, because the fact table looks
     * them up by letter and a gap would print "level.Q" at a customer.
     */
    public function testThereIsAHintForEveryErrorCorrectionLevel(): void
    {
        foreach (Translator::AVAILABLE_LOCALES as $locale) {
            $translator = Translator::forLocale($locale);

            foreach (['L', 'M', 'Q', 'H'] as $level) {
                self::assertTrue($translator->has('level.' . $level), "{$locale} has no hint for {$level}.");
            }
        }
    }
}

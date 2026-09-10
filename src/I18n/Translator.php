<?php

declare(strict_types=1);

namespace Redcodede\QrGen\I18n;

use Redcodede\QrGen\Qr\Exception\InvalidArgument;

/**
 * Interface texts, looked up by key.
 *
 * Deliberately **outside** `src/Qr/`. Translations are a presentation concern
 * and the core has none: it hands back matrices and strings and throws
 * exceptions whose messages are developer diagnostics — they go into logs,
 * name class names and module counts, and are addressed to whoever reads the
 * code. Putting a translator in the core would also mean reading files there,
 * which is exactly what it promises not to do.
 *
 * German is the default. English exists as a catalogue but nothing offers it;
 * it is reached by asking for the locale, which is what "available on demand"
 * has to mean if it is to be true.
 *
 * The catalogue format is Laravel's — a `return` array with dotted keys and
 * `:name` placeholders — so the same files can later be loaded by Laravel's
 * own translator in the Statamic shell without being touched. One set of
 * texts, not two.
 */
final class Translator
{
    public const DEFAULT_LOCALE = 'de';

    /** @var list<string> */
    public const AVAILABLE_LOCALES = ['de', 'en'];

    /** @var string */
    private $locale;

    /** @var array<string, string> */
    private $messages;

    /** @var array<string, string> */
    private $fallback;

    /**
     * @param array<string, string> $messages
     * @param array<string, string> $fallback
     */
    private function __construct(string $locale, array $messages, array $fallback)
    {
        $this->locale = $locale;
        $this->messages = $messages;
        $this->fallback = $fallback;
    }

    /**
     * @throws InvalidArgument if the locale has no catalogue
     */
    public static function forLocale(string $locale = self::DEFAULT_LOCALE): self
    {
        $normalized = strtolower(trim($locale));

        if (!in_array($normalized, self::AVAILABLE_LOCALES, true)) {
            throw InvalidArgument::unknownLocale($locale, self::AVAILABLE_LOCALES);
        }

        return new self(
            $normalized,
            self::load($normalized),
            $normalized === self::DEFAULT_LOCALE ? [] : self::load(self::DEFAULT_LOCALE)
        );
    }

    /**
     * Falls back to the default locale rather than to nothing, so a catalogue
     * that is behind shows German where it has a gap instead of a blank.
     */
    public static function forLocaleOrDefault(?string $locale): self
    {
        if ($locale === null || !in_array(strtolower(trim($locale)), self::AVAILABLE_LOCALES, true)) {
            return self::forLocale();
        }

        return self::forLocale($locale);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function has(string $key): bool
    {
        return isset($this->messages[$key]) || isset($this->fallback[$key]);
    }

    /**
     * A missing key comes back as the key itself.
     *
     * Louder than an empty string and quieter than an exception: the page still
     * renders, and "facts.allowance" sitting in a table where a sentence
     * belongs is unmistakable to whoever looks at it.
     *
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        $message = $this->messages[$key] ?? $this->fallback[$key] ?? $key;

        if ($replacements === []) {
            return $message;
        }

        $search = [];
        $replace = [];

        foreach ($replacements as $name => $value) {
            $search[] = ':' . $name;
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $message);
    }

    /**
     * Every key in this locale's catalogue. For the test that keeps the
     * catalogues from drifting apart.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $locale): array
    {
        $path = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '.php';

        if (!is_file($path)) {
            throw InvalidArgument::missingCatalogue($locale, $path);
        }

        /** @var array<string, string> $messages */
        $messages = require $path;

        return $messages;
    }
}

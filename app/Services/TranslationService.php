<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LanguageModel;
use Core\Request;
use Throwable;

final class TranslationService
{
    /** @var array<string, string> */
    private array $defaults;

    /** @var array<string, string> */
    private array $translations = [];

    private string $languageCode = 'en';
    private string $defaultLanguageCode = 'en';

    /** @var array<int, array<string, mixed>> */
    private array $activeLanguages = [];

    public function __construct(private readonly LanguageModel $languages)
    {
        $defaults = require BASE_PATH . '/config/translations.php';
        $this->defaults = is_array($defaults) ? array_map('strval', $defaults) : [];
    }

    public function boot(Request $request): void
    {
        try {
            $this->ensureDefaultsSeeded();
            $active = $this->languages->all(true);
            $default = $this->languages->defaultLanguage();
            $this->activeLanguages = $active;
            $this->defaultLanguageCode = is_array($default) ? (string) $default['code'] : 'en';
            $this->languageCode = $this->chooseLanguage($request, $active, $this->defaultLanguageCode);
            $this->translations = array_replace($this->defaults, $this->languages->translationsForCode($this->languageCode));
        } catch (Throwable) {
            $this->languageCode = 'en';
            $this->defaultLanguageCode = 'en';
            $this->activeLanguages = [];
            $this->translations = $this->defaults;
        }

        $GLOBALS['translator'] = $this;
    }

    public function get(string $key, string $fallback = '', array $replace = []): string
    {
        $text = $this->translations[$key] ?? $this->defaults[$key] ?? $fallback;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }

    public function languageCode(): string
    {
        return $this->languageCode;
    }

    public function defaultLanguageCode(): string
    {
        return $this->defaultLanguageCode;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeLanguages(): array
    {
        return $this->activeLanguages;
    }

    private function ensureDefaultsSeeded(): void
    {
        $stamp = $this->defaultsStampPath();
        if (is_file($stamp)) {
            return;
        }

        if (!$this->languages->defaultTranslationsSeeded($this->defaults)) {
            $this->languages->seedDefaults($this->defaults);
        }

        $directory = dirname($stamp);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        @file_put_contents($stamp, date(DATE_ATOM) . PHP_EOL, LOCK_EX);
    }

    private function defaultsStampPath(): string
    {
        $encoded = json_encode($this->defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $encoded);

        return STORAGE_PATH . '/cache/translations/defaults-' . $hash . '.stamp';
    }

    /** @param array<int, array<string, mixed>> $activeLanguages */
    private function chooseLanguage(Request $request, array $activeLanguages, string $default): string
    {
        $available = [];
        foreach ($activeLanguages as $language) {
            $code = strtolower((string) ($language['code'] ?? ''));
            if ($code !== '') {
                $available[$code] = true;
            }
        }
        if ($available === []) {
            return $default;
        }

        $pathLanguage = strtolower(trim($request->locale));
        if ($pathLanguage !== '') {
            if (isset($available[$pathLanguage])) {
                return $pathLanguage;
            }
            $pathPrimary = strtok($pathLanguage, '-');
            if (is_string($pathPrimary) && isset($available[$pathPrimary])) {
                return $pathPrimary;
            }
        }

        $queryLanguage = strtolower((string) ($request->get['lang'] ?? ''));
        if (isset($available[$queryLanguage])) {
            return $queryLanguage;
        }

        $header = (string) ($request->server['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach ($this->parseAcceptLanguage($header) as $candidate) {
            if (isset($available[$candidate])) {
                return $candidate;
            }
            $primary = strtok($candidate, '-');
            if (is_string($primary) && isset($available[$primary])) {
                return $primary;
            }
        }

        return isset($available[$default]) ? $default : (string) array_key_first($available);
    }

    /** @return array<int, string> */
    private function parseAcceptLanguage(string $header): array
    {
        $languages = [];
        foreach (explode(',', $header) as $part) {
            $bits = explode(';q=', trim($part), 2);
            $code = strtolower(str_replace('_', '-', trim($bits[0] ?? '')));
            if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $code)) {
                continue;
            }
            $languages[$code] = isset($bits[1]) ? (float) $bits[1] : 1.0;
        }
        arsort($languages);

        return array_keys($languages);
    }
}

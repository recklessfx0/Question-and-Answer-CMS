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

    public function __construct(private readonly LanguageModel $languages)
    {
        $defaults = require BASE_PATH . '/config/translations.php';
        $this->defaults = is_array($defaults) ? array_map('strval', $defaults) : [];
    }

    public function boot(Request $request): void
    {
        try {
            $this->languages->seedDefaults($this->defaults);
            $active = $this->languages->all(true);
            $default = $this->languages->defaultLanguage();
            $this->languageCode = $this->chooseLanguage($request, $active, is_array($default) ? (string) $default['code'] : 'en');
            $this->translations = array_replace($this->defaults, $this->languages->translationsForCode($this->languageCode));
        } catch (Throwable) {
            $this->languageCode = 'en';
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

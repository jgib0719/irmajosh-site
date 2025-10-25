<?php
declare(strict_types=1);

namespace App\Services;

/**
 * TranslationService
 * 
 * Handles translations with fallback to English
 */
class TranslationService
{
    private static ?array $translations = null;
    private static string $currentLocale = 'en';
    
    /**
     * Load translations for a locale
     */
    private static function loadTranslations(string $locale): array
    {
        $translationFile = __DIR__ . "/../../locales/{$locale}/messages.php";
        
        if (file_exists($translationFile)) {
            try {
                $translations = require $translationFile;
                
                if (!is_array($translations)) {
                    logMessage("Translation file for locale {$locale} did not return an array", 'WARNING');
                    return [];
                }
                
                return $translations;
            } catch (\Exception $e) {
                logMessage("Failed to load translations for locale {$locale}: " . $e->getMessage(), 'ERROR');
                return [];
            }
        }
        
        return [];
    }
    
    /**
     * Set current locale
     */
    public static function setLocale(string $locale): void
    {
        $supportedLocales = ['en', 'id'];
        
        if (!in_array($locale, $supportedLocales)) {
            logMessage("Unsupported locale: {$locale}, falling back to English", 'WARNING');
            $locale = 'en';
        }
        
        self::$currentLocale = $locale;
        self::$translations = null; // Clear cached translations
        
        // Also update session
        setAppLocale($locale);
    }
    
    /**
     * Get current locale
     */
    public static function getLocale(): string
    {
        return self::$currentLocale;
    }
    
    /**
     * Translate a key
     */
    public static function translate(string $key, array $replacements = []): string
    {
        // Load translations if not already loaded
        if (self::$translations === null) {
            self::$translations = self::loadTranslations(self::$currentLocale);
            
            // If translation file is empty or not found, try English fallback
            if (empty(self::$translations) && self::$currentLocale !== 'en') {
                logMessage("Falling back to English translations", 'INFO');
                self::$translations = self::loadTranslations('en');
            }
        }
        
        // Get translation or return key if not found
        $translation = self::$translations[$key] ?? $key;
        
        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace("{{$placeholder}}", $value, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Check if a translation key exists
     */
    public static function has(string $key): bool
    {
        if (self::$translations === null) {
            self::$translations = self::loadTranslations(self::$currentLocale);
        }
        
        return isset(self::$translations[$key]);
    }
    
    /**
     * Get all translations for current locale
     */
    public static function all(): array
    {
        if (self::$translations === null) {
            self::$translations = self::loadTranslations(self::$currentLocale);
        }
        
        return self::$translations;
    }
    
    /**
     * Get available locales
     */
    public static function getAvailableLocales(): array
    {
        return [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ];
    }
    
    /**
     * Format a date according to locale
     */
    public static function formatDate(string $datetime, string $format = 'long'): string
    {
        try {
            $dt = new \DateTime($datetime);
            
            $formats = [
                'en' => [
                    'short' => 'm/d/Y',
                    'medium' => 'M j, Y',
                    'long' => 'F j, Y',
                    'full' => 'l, F j, Y',
                    'time' => 'g:i A',
                    'datetime' => 'M j, Y g:i A',
                ],
                'id' => [
                    'short' => 'd/m/Y',
                    'medium' => 'j M Y',
                    'long' => 'j F Y',
                    'full' => 'l, j F Y',
                    'time' => 'H:i',
                    'datetime' => 'j M Y H:i',
                ],
            ];
            
            $localeFormats = $formats[self::$currentLocale] ?? $formats['en'];
            $dateFormat = $localeFormats[$format] ?? $localeFormats['medium'];
            
            return $dt->format($dateFormat);
        } catch (\Exception $e) {
            logMessage("Date formatting error: " . $e->getMessage(), 'ERROR');
            return $datetime;
        }
    }
    
    /**
     * Format a number according to locale
     */
    public static function formatNumber(float $number, int $decimals = 0): string
    {
        $decimalSeparator = self::$currentLocale === 'id' ? ',' : '.';
        $thousandsSeparator = self::$currentLocale === 'id' ? '.' : ',';
        
        return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
    }
    
    /**
     * Get locale from browser Accept-Language header
     */
    public static function detectLocale(): string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return 'en';
        }
        
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        
        // Parse Accept-Language header
        preg_match_all('/([a-z]{2})(?:-[A-Z]{2})?(;q=([0-9.]+))?/', $acceptLanguage, $matches);
        
        if (empty($matches[1])) {
            return 'en';
        }
        
        $languages = [];
        foreach ($matches[1] as $i => $lang) {
            $quality = isset($matches[3][$i]) && $matches[3][$i] !== '' 
                ? (float)$matches[3][$i] 
                : 1.0;
            $languages[$lang] = $quality;
        }
        
        arsort($languages);
        
        $supportedLocales = ['en', 'id'];
        
        foreach (array_keys($languages) as $lang) {
            if (in_array($lang, $supportedLocales)) {
                return $lang;
            }
        }
        
        return 'en';
    }
}

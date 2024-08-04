<?php

namespace Leantime\Plugins\Watchers\Core;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Leantime\Core\Environment;

class Language
{
    use \Leantime\Core\Eventhelpers;

    /**
     * @var string Default language folder
     * @static
     * @final
     */
    private const DEFAULT_LANG_FOLDER = APP_ROOT . '/app/Language/';

    /**
     * @var string Custom language folder
     * @static
     * @final
     */
    private const CUSTOM_LANG_FOLDER = APP_ROOT . '/custom/Language/';

    /**
     * @var string Plugin language folder
     * @static
     * @final
     */
    private const PLUGIN_LANG_FOLDER = __DIR__ . '/../Language/';

    /**
     * @var string slug for cache keys
     */
    private string $slug = 'watchers';
    /**
     * @var string Default language
     */
    private string $language = 'en-US';

    /**
     * @var array Array which stores translations
     */
    public array $ini_array = [];

    /**
     * @var mixed Language list in the folder
     */
    public mixed $langlist;

    /**
     * @var array|bool Debug value. Will highlight untranslated text
     */
    private array|bool $alert = false;

    /**
     * @var Environment Environment values
     */
    public Environment $config;

    /**
     * Constructor method for initializing an instance of the class
     * @throws Exception If the default English language file en-US.ini cannot be found.
     */
    public function __construct(
        Environment $config,
    )
    {
        $this->config = $config;
        $this->langlist = $this->getLanguageList();

        // Start checking if the user has a language set
        if (!$this->setLanguage($this->language)) {
            $this->setLanguage('en-US');
        }
        $this->readIni();
    }

    /**
     * Set the language for the application
     *
     * @param string $language The language code to be set.
     * @return bool True if the language is valid and successfully set, false otherwise.
     */
    public function setLanguage(string $language): bool
    {
        if (!$this->isValidLanguage($language)) {
            return false;
        }
        $this->language = $language;

        return true;
    }

    /**
     * Get the currently selected language.
     *
     * @return string The currently selected language.
     */
    public function getCurrentLanguage(): string
    {
        return $this->language;
    }

    /**
     * Check if a given language code is valid
     *
     * @param string $languageCode The language code to check
     * @return bool True if the language code is valid, false otherwise.
     */
    public function isValidLanguage(string $languageCode): bool
    {
        return isset($this->langlist[$languageCode]);
    }

    /**
     * Read and load the language resources from all the ini files.
     *
     * @throws Exception If the default English language file en-US.ini cannot be found.
     */
    public function readIni(): array
    {
        $languages = $this->getLanguageList();
        foreach ($languages as $languageCode => $languageWord) {
            $ini_array = $this->readIniFile($languageCode);
            foreach ($ini_array as $key => $value) {
                if (!isset($this->ini_array[$key])) {
                    $this->ini_array[$key] = [];
                }
                $this->ini_array[$key][$languageCode] = $value;
            }
        }

        return $this->ini_array;
    }

    /**
     * Read and load the language resources from the ini file.
     *
     * @param string $language Which language should be loaded from the ini file.
     * @return array the array of language resources loaded from the ini file.
     * @throws Exception If the default English language file en-US.ini cannot be found.
     */
    protected function readIniFile(string $language): array
    {
        $result = [];
        $cache_key = sprintf('cache.%1$s.language_resources_%2$s', $this->slug, $language);
        if (Cache::store('installation')->has($cache_key) && $this->config->debug == 0) {
            $result = self::dispatch_filter(
                'language_resources',
                Cache::store('installation')->get($cache_key),
                [
                    'language' => $language,
                ]
            );
            Cache::store('installation')->set($cache_key, $result);
            return $result;
        }

        if ($language == 'en-US' && !file_exists(static::DEFAULT_LANG_FOLDER . 'en-US.ini')) {
            throw new Exception('Cannot find default English file en-US.ini');
        } else if ($language == 'en-US' && !file_exists(static::PLUGIN_LANG_FOLDER . 'en-US.ini')) {
            throw new Exception('Cannot find default English file en-US.ini');
        } else if (!file_exists(static::DEFAULT_LANG_FOLDER . $language . '.ini')) {
            return $result;
        } else if (!file_exists(static::PLUGIN_LANG_FOLDER . $language . '.ini')) {
            return $result;
        }

        $mainLanguageArray = parse_ini_file(
            static::DEFAULT_LANG_FOLDER . 'en-US.ini',
            false,
            INI_SCANNER_RAW
        );
        $pluginLanguageArray = parse_ini_file(
            static::PLUGIN_LANG_FOLDER . 'en-US.ini',
            false,
            INI_SCANNER_RAW
        );
        $mainLanguageArray = array_merge($mainLanguageArray, $pluginLanguageArray);

        foreach ($languageFiles = self::dispatch_filter('language_files', [
            // Override with non-English customization
            static::CUSTOM_LANG_FOLDER . 'en-US.ini' => false,
            // Overwrite English language by non-English language
            static::DEFAULT_LANG_FOLDER . $language . '.ini' => true,
            // Override with non-English customization
            static::CUSTOM_LANG_FOLDER . $language . '.ini' => true,
            // Override with Plugin non-English language
            static::PLUGIN_LANG_FOLDER . $language . '.ini' => true,
        ], ['language' => $language]) as $language_file => $isForeign) {
            $mainLanguageArray = $this->includeOverrides($language, $mainLanguageArray, $language_file, $isForeign);
        }

        $result = self::dispatch_filter(
            'language_resources',
            $mainLanguageArray,
            [
                'language' => $language,
            ]
        );

        Cache::store('installation')->set($cache_key, $result);

        return $result;
    }

    /**
     * Include language overrides from an ini file.
     *
     * @param array $language The original language array
     * @param string $filepath The path to the ini file.
     * @param bool $foreignLanguage Whether the language is foreign or not. Default to false.
     * @return array The modified language array.
     * @throws Exception
     */
    protected function includeOverrides(
        string $language, array $languageArray, string $filepath, bool $foreignLanguage = false
    ): array
    {
        if ($foreignLanguage && $language == 'en-US') {
            return $languageArray;
        }

        if (!file_exists($filepath)) {
            return $languageArray;
        }

        $ini_overrides = parse_ini_file($filepath, false, INI_SCANNER_RAW);

        if (!is_array($ini_overrides)) {
            throw new Exception("Could not parse ini file $filepath");
        }

        foreach ($ini_overrides as $key => $value) {
            $languageArray[$key] = $value;
        }

        return $languageArray;
    }

    /**
     * Get the list of languages for the plugin.
     *
     * Retrieve the list of languages from a cache or from INI files if the cache is not available.
     * The list of languages is stored in an associative array where the keys represent the language codes
     * and the values represent the language names.
     *
     * @return bool|array The list of languages as an associative array, or false if the list is empty or cannot be retrieved.
     */
    public function getLanguageList(): bool|array
    {
        $cache_key = sprintf('cache.%1$s.langlist', $this->slug);
        if (Cache::store('installation')->has($cache_key)) {
            return Cache::store('installation')->get($cache_key);
        }

        $langlist = false;
        if (file_exists(static::DEFAULT_LANG_FOLDER . '/languagelist.ini')) {
            $langlist = parse_ini_file(
                static::DEFAULT_LANG_FOLDER . '/languagelist.ini',
            false,
            INI_SCANNER_RAW
            );
        }

        if (file_exists(static::PLUGIN_LANG_FOLDER . '/languagelist.ini')) {
            $langlist = parse_ini_file(
                static::PLUGIN_LANG_FOLDER . '/languagelist.ini',
            false,
            INI_SCANNER_RAW
            );
        }

        $parsedLangList = self::dispatch_filter('languages', $langlist);
        Cache::store('installation')->put($cache_key, $parsedLangList);

        return $parsedLangList;
    }

    /**
     * Get a translated string, string in en-US or a default value if the index is not found
     *
     * @param string $index The index of the translated string
     * @param string $default The default value to return if the index is not found. Defaults to an empty string
     * @return string The translated string, string in en-US or a default value if the index is not found.
     */
    public function __(string $index, string $default = ''): string
    {
        if (!isset($this->ini_array[$index])) {
            if (!empty($default)) {
                return $default;
            }
            if ($this->alert) {
                return sprintf('<span style="color: red; font-weight:bold;">%s</span>', $index);
            }
            return $index;
        }
        $language = $this->language;
        if (!isset($this->ini_array[$index][$language])) {
            $language = 'en-US';
        }

        $returnValue = match (trim($index)) {
            'language.dateformat' => $this->ini_array[$index][$language]['language.dateformat'],
            'language.timeformat' => $this->ini_array[$index][$language]['language.timeformat'],
            default => $this->ini_array[$index][$language],
        };

        return (string) $returnValue;
    }
}

<?php

namespace Leantime\Plugins\Watchers\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Leantime\Core\Environment;
use Leantime\Core\IncomingRequest;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Core\Language;

class GetLanguageAssets
{
    private array $supportedLanguages = [
        'en-US', 'ja-JP',
    ];

    public function __construct(
        private Language $language,
        private Environment $config,
    ) {}

    /**
     * Install the custom fields plugin DB if necessary.
     *
     * @param \Closure(IncomingRequest): Response $next
     * @throws BindingResolutionException
     **/
    public function handle(IncomingRequest $request, Closure $next): Response
    {

        $language = $_SESSION["usersettings.language"] ?? $this->config->language;
        $languageArray = self::readIni($language, $this->language->ini_array);
        Cache::put('temporary_notifications.languageArray', $languageArray);

        $this->language->ini_array = array_merge($this->language->ini_array, $languageArray);
        return $next($request);
    }

    static function readIni(string $language): array
    {

        $languageArray = Cache::get('temporary_notifications.languageArray', []);
        if (! empty($languageArray)) {
            return $languageArray;
        }

        if (! Cache::store('installation')->has('temporary_notifications.language.en-US')) {
            $languageArray += parse_ini_file(__DIR__ . '/../Language/en-US.ini', true);
        }

        if ($language !== 'en-US') {
            if (! Cache::store('installation')->has('temporary_notifications.language.' . $language)) {
                Cache::store('installation')->put(
                    'temporary_notifications.language.' . $language,
                    parse_ini_file(__DIR__ . '/../Language/' . $language . '.ini', true)
                );
            }

            $cache = Cache::store('installation')->get('temporary_notifications.language.' . $language);
            $languageArray = $cache ? $cache : [];
        }

        Cache::put('temporary_notifications.languageArray', $languageArray);

        return $languageArray;
    }
}


<?php

namespace Leantime\Plugins\Watchers;

use \Leantime\Core\Events;
use \Leantime\Core\Template;
use \Leantime\Core\Middleware\ApiAuth;
use \Leantime\Plugins\Watchers\Middleware\GetLanguageAssets;
use \Leantime\Plugins\Watchers\Services\Watchers as WatcherService;

class Register {
    private string $slug = 'Watchers';
    private array $session = [];

    function __construct() {
        $this->init();
    }

    /**
     * Initialize plugins
     *
     * @return void
     */
    private function init(): void
    {
        $this->registerEvents();
        $this->registerFilters();
        $this->registerMenus();
        $this->registerTemplate();
    }

    /**
     * Register event listeners
     *
     * @return void
     */
    private function registerEvents(): void
    {
        //Register event listener to receive when notify to project users
        Events::add_event_listener(
            "domain.services.projects.notifyProjectUsers",
            function ($params) {
                return $this->sendNotifications($params);
            }
        );

        Events::add_event_listener(
            "leantime.domain.auth.services.auth.setUserSession.user_session_vars",
            function ($params) {
                error_log('Leantime.Domain.Auth.Services.Auth.setUserSession.user_session_vars');
                error_log(print_r($params, true));
                return $params;
            }
        );
    }

    /**
     * Register filters
     *
     * @return void
     */
    private function registerFilters(): void
    {
        // Events::add_filter_listener(
        //     "filterListenerName",
        //     array($this, 'addDummyFilter')
        // );
        Events::add_filter_listener(
            'leantime.core.httpkernel.handle.plugins_middleware',
            fn(array $middleware) => array_merge(
                $middleware,
                [GetLanguageAssets::class, ApiAuth::class]
            ),
        );
        Events::add_filter_listener(
            'leantime.core.language.readIni.language_resources',
            function (array $mainLanguageArray, array $params) {
                return $this->loadLanguages($mainLanguageArray, $params);
            }
        );
        Events::add_filter_listener(
            'Leantime.Core.Template.getTemplatePath.template_path__Watchers_partials.watchInTicket',
            function ($params) {
                return $this->addTemplatePath($params);
            }
        );
        Events::add_filter_listener(
            'Leantime.Domain.Auth.Services.Auth.setUserSession.user_session_vars',
            function (array $params) {
                return $this->saveSession('user', $params);
            }
        );
    }

    private function saveSession(string $key, array $params): array
    {
        error_log('saveSession: '.$key);
        error_log(print_r($params, true));
        $this->session[$key] = $params;

        return $params;
    }

    /**
     * Add menu point
     *
     * @return void
     */
    private function registerMenus(): void
    {
        Events::add_filter_listener(
            "domain.menu.Repositories.menu.getMenuStructure.menuStructures",
            function (array $menuStructure) {
                return $this->addonMenu($menuStructure);
            }
        );
    }

    /**
     * Register displaying template logic
     *
     * @return void
     */
    private function registerTemplate(): void
    {
        // Register event listener to receive when showing ticket tab contents
        Events::add_event_listener(
            "leantime.core.template.tpl.tickets.showTicketModal.ticketTabs",
            function ($params) {
                return $this->watchTicket($params);
            }
        );
    }

    /**
     * Send notifications to related users
     *
     * @param $params
     * @return bool
     */
    private function sendNotifications($params): bool
    {
        $watcherService = app()->make(
            WatcherService::class
        );
        $values = $watcherService->getTargetUsers($params['type'], $params['module'], $params['moduleId']);
        error_log('params:'.print_r($params, true));
        error_log('values:'.print_r($values, true));
        error_log('merged:'.print_r(array_merge($params, $values), true));
        // Add url
        $values['url'] = $params['url'];

        $watcherService->sendNotifications($params['type'], $params['module'], $values);

        return true;
    }

    /**
     * Load language files into array so that Plugins\Core\Language can translate without session
     *
     * @param array $mainLanguageArray
     * @param array $param
     * @return array
     */
    private function loadLanguages(array $mainLanguageArray, array $params): array
    {
        $languageArray = GetLanguageAssets::readIni(
            $params['language'],
        );
        return array_merge($mainLanguageArray, $languageArray);
    }

    /**
     * Register addon menu
     *
     * @param array $menuStructure
     * @return array
     */
    private function addonMenu(array $menuStructure): array
    {
        $menuStructure['default'][10]['submenu'][] = [
            'type' => 'item',
            'module' => 'SendToWatchers',
            'title' => 'Send Notifications to Watchers',
            'icon' => 'fa fa-fw fa-cogs',
            'tooltip' => 'Send Notifications to Watchers',
            'href' => '/Watchers/settings',
            'active' => ['settings'],
        ];
        return $menuStructure;
    }

    private function watchTicket($params): string
    {
        error_log('watchTicket()');
        error_log(var_export($params, true));

        $watcherService = app()->make(WatcherService::class);

        $template = app()->make(Template::class);
        $templateName = 'Watchers::showTicketWatchers';

        $layout = 'app';
        $layout = Template::dispatch_filter("layout", $layout);
        $layout = Template::dispatch_filter("layout.$templateName", $layout);
        $layout = $template->getTemplatePath('global', "layouts.$layout");

        $loadFile = $template->getTemplatePath("Watchers", "partials.watchTicket");

        error_log('loadFile:');
        error_log(var_export($loadFile, true));

        /** @var View $view */
        $view = $template->viewFactory->make($loadFile);
        $view->with(
            [
                'layout' => $layout,
//                'is_watching' => $watcherService->isWatchingTicket($params['projectId'], $params['ticketId'], \session('userId'))
            ],
        );
        $content = $view->render();
        $content = Template::dispatch_filter('content', $content);
        error_log('content:' . $content);
        echo $content;

        return $content;
    }

    private function addTemplatePath($params): void
    {
        error_log('addTemplatePath()');
        error_log(print_r($params, true));
    }
}

new Register();

//
////Listen to events
//    function sendWatcherListener()
//    {
//        //Do stuff
//        //Call service
//        error_log('sendWatcherListener');
//    }
//
////Register event listener
//    \Leantime\Core\Events::add_event_listener(
//        "core.template.tpl.pageparts.header.afterLinkTags", 'sendWatcherListener'
//    );
//
//    function sendWatcherFilter($payload, $params)
//    {
//
//        //payload is the filterable payload (array)
//        //$params contains additional values as needed
//
//        //Do stuff
//        //Call service
//        error_log('sendWatcherFilter');
//
//        return $payload;
//    }
//
////Register event listener
//    \Leantime\Core\Events::add_filter_listener(
//        "filterListenerName", 'sendWatcherFilter'
//    );
//
//
////Example add menu point
//    function addWatcherMenuItem($menuStructure)
//    {
//        $menuStructure['default'][10]['submenu'][] = [
//            'type' => 'item',
//            'module' => 'SendToWatchers',
//            'title' => 'Send Notifications to Watchers',
//            'icon' => 'fa fa-fw fa-cogs',
//            'tooltip' => 'Send Notifications to Watchers',
//            'href' => '/Watchers/settings',
//            'active' => ['settings'],
//        ];
//        return $menuStructure;
//
//    }
//
////Register event listener
//    \Leantime\Core\Events::add_filter_listener(
//        "domain.menu.Repositories.menu.getMenuStructure.menuStructures", 'addWatcherMenuItem'
//    );
//
//
////Register Language Assets
//    \Leantime\Core\Events::add_filter_listener(
//        'leantime.core.httpkernel.handle.plugins_middleware',
//        fn(array $middleware) => array_merge(
//            $middleware,
//            [Leantime\Plugins\Watchers\Middleware\GetLanguageAssets::class]
//        ),
//    );
//
//    function loadLanguagesForWatchers(array $mainLanguageArray, array $param): array
//    {
//        $languageArray = Leantime\Plugins\Watchers\Middleware\GetLanguageAssets::readIni(
//            $param['language'],
//        );
//        return array_merge($mainLanguageArray, $languageArray);
//    }
//
//    \Leantime\Core\Events::add_filter_listener(
//        'leantime.core.language.readIni.language_resources',
//        'loadLanguagesForWatchers'
//    );
//
//    function sendToWatchers($param): string
//    {
//        $watchersService = app()->make(
//            \Leantime\Plugins\Watchers\Services\Watchers::class
//        );
//        $values = $watchersService->getTargetUsers($param['type'], $param['module'], $param['moduleId']);
//        $values['url'] = $param['url'];
//
//        $watchersService->sendNotificationEmails($param['type'], $param['module'], $values);
//
//        return true;
//    }
//
////Register event listener to receive when notify to project users
//    \Leantime\Core\Events::add_event_listener(
//        "domain.services.projects.notifyProjectUsers",
//        'sendToWatchers'
//    );
//
//    function addTemplatePathForWatchers($params)
//    {
//
//    }
//
//    \Leantime\Core\Events::add_filter_listener(
//        'Leantime.Core.Template.getTemplatePath.template_path__Watchers_partials.watchInTicket',
//        'addTemplatePathForWatchers'
//    );
//
//    function showTicketWatchers($param): string
//    {
//        error_log('showTicketWatchers');
//        error_log(var_export($param, true));
//
//
//        $template = app()->make(\Leantime\Core\Template::class);
//        $templateName = 'Watchers::showTicketWatchers';
//
//        $layout = 'app';
//        $layout = \Leantime\Core\Template::dispatch_filter("layout", $layout);
//        $layout = \Leantime\Core\Template::dispatch_filter("layout.$templateName", $layout);
//        $layout = $template->getTemplatePath('global', "layouts.$layout");
//
//        $loadFile = $template->getTemplatePath("Watchers", "partials.eyeInTicket");
//
//        error_log('loadFile:');
//        error_log(var_export($loadFile, true));
//
//        /** @var View $view */
//        $view = $template->viewFactory->make($loadFile);
//        $view->with(
//            ['layout' => $layout],
//        );
//        $content = $view->render();
//        $content = \Leantime\Core\Template::dispatch_filter('content', $content);
//        error_log('content:' . $content);
//        echo $content;
//
//        return $content;
//
////
////    $loadFile = 'showTicketWatchers.tpl.php';
////    error_log('loadFile: '.$loadFile);
////
////    /** @var View $view */
////    $view = $template->viewFactory->make($loadFile);
////
////    /** @todo this can be reduced to just the 'if' code after removal of php template support */
////    if ($view->getEngine() instanceof CompilerEngine) {
////        $view->with(array_merge(
////            $template->vars,
////            ['layout' => $layout]
////        ));
////    } else {
////        $view = $template->viewFactory->make($layout, array_merge(
////            $template->vars,
////        ));
////    }
//
////    $content = $view->render();
////    $content = \Leantime\Core\Template::dispatch_filter('content', $content);
////    return \Leantime\Core\Template::dispatch_filter("content.$template", $content);
//    }
//
//// Register event listener to receive when showing ticket tab contents
//    \Leantime\Core\Events::add_event_listener(
//        "leantime.core.template.tpl.tickets.showTicketModal.ticketTabs", 'showTicketWatchers'
//    );

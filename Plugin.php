<?php namespace Rd\DynoPages;

use Event;
use Config;
use Backend;
use Session;
use Cms\Classes\Theme;
use Cms\Classes\CmsException;
use System\Classes\PluginBase;
use Rd\DynoPages\Classes\Router;
use Rd\Dynopages\Models\Setting;
use System\Classes\PluginManager;
use RainLab\Pages\Classes\Snippet;
use RainLab\Pages\Classes\SnippetManager;
use Illuminate\Support\Facades\App;
use Rd\DynoPages\Classes\Controller;
use System\Helpers\View as ViewHelper;
use October\Rain\Parse\Bracket as TextParser;
use Rd\DynoPages\Classes\Page as DynoCmsPage;
use Rd\DynoPages\Classes\StaticPage as DynoStaticPage;

/**
 * DynoPages Plugin Class
 * 
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Plugin extends PluginBase
{
    /**
     * @var string Page type
     */
    protected $pageType;

    /**
     * @var string Page url
     */
    protected $url;

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'rd.dynopages::lang.plugin.name',
            'description' => 'rd.dynopages::lang.plugin.description',
            'author'      => 'Rd',
            'icon'        => 'icon-bomb'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        Event::listen('cms.router.beforeRoute', function($url) {
            // We should try to load cmsPage form DB, if not exist loadStaticPage from DB
            $dynoController = new Controller();
            
            if(Setting::get('use_dynopages')){
                if(PluginManager::instance()->exists('RainLab.Pages') && $page = $dynoController->initStaticCmsPageFromDB($url)){
                    $this->pageType = 'staticPage';
                    return $page;
                }elseif($page = $dynoController->initCmsPageFromDB($url)){
                    $this->pageType = 'page';
                    $this->url = $url;
                    return $page;
                }
                return false;
            }
        }, 100);

        Event::listen('cms.page.beforeRenderPage', function($controller, $page) {

            if(Setting::get('use_dynopages')){

                /*
                * Before twig renders
                */
                // Get page type to render CMS or Static page
                if($this->pageType === 'page'){
                    /*
                    * The 'this' variable is reserved for default variables.
                    */
                    $router = new Router($controller->getTheme());
                    $router->findDynoPageByUrl($this->url);

                    $controller->vars['this'] = [
                        'page'        => $page,
                        'layout'      => $controller->getLayout(),
                        'theme'       => $controller->getTheme(),
                        'param'       => $router->getParameters(),
                        'controller'  => $controller,
                        'environment' => App::environment(),
                        'session'     => App::make('session'),
                    ];
                    /*
                    * Check for the presence of validation errors in the session.
                    */
                    $controller->vars['errors'] = (Config::get('session.driver') && Session::has('errors')) ? Session::get('errors') : new \Illuminate\Support\ViewErrorBag;

                    $twig = $controller->getTwig();
                    $loader = $controller->getLoader();

                    CmsException::mask($page, 400);
                    $loader->setObject($page);
                    $template = $twig->loadTemplate($page->getFilePath());
                    $pageContents = $template->render($controller->vars);
                    CmsException::unmask();
                    
                    return $pageContents;
                }else{
                    /*
                    * Before twig renders
                    */
                    $twig = $controller->getTwig();
                    $loader = $controller->getLoader();

                    // Controller::instance()->injectPageTwig($page, $loader, $twig);
                    if (!isset($page->apiBag['staticPage'])) {
                        return;
                    }
            
                    $staticPage = $page->apiBag['staticPage'];

                    CmsException::mask($staticPage, 400);
                    $loader->setObject($staticPage);
                    $template = $twig->loadTemplate($staticPage->getFilePath());
                    $template->render([]);
                    CmsException::unmask();

                    /*
                        Get rendered content process: 
                        1) Controller::instance()->getPageContents($page)
                        2) $page->apiBag['staticPage']->getProcessedMarkup()
                    */
            
                    /*
                    * Process snippets
                    */
                    $markup = Snippet::processPageMarkup(
                        $staticPage->getFileName(),
                        $staticPage->theme,
                        $staticPage->markup
                    );

                    /*
                    * Inject global view variables
                    */
                    $globalVars = ViewHelper::getGlobalVars();
                    
                    if (!empty($globalVars)) {
                        $contents = $page->processedMarkupCache = TextParser::parse($markup, $globalVars);
                    }else{
                        $contents = '';
                    }

                    if (strlen($contents)) {
                        return $contents;
                    }
                }

                // Stop propagation of event listener
                return false;
            }
            
        }, 100);

        Event::listen('cms.page.initComponents', function ($controller, $page, $layout) {
            // Get correct vars[this] for controller
            if(Setting::get('use_dynopages')){
                $router = new Router($controller->getTheme());
                $router->findDynoPageByUrl($this->url);
                $controller->getRouter()->setParameters($router->getParameters());
                
                // Reinit page components to ensure parameters are set
                foreach ($page->settings['components'] as $component => $properties) {
                    list($name, $alias) = strpos($component, ' ')
                        ? explode(' ', $component)
                        : [$component, $component];
        
                        $controller->addComponent($name, $alias, $properties);
                }
            }
        }, 100);

        Event::listen('backend.menu.extendItems', function($manager) {
            if(Setting::get('use_dynopages')){
                if(!Setting::get('show_native_cms')){
                    $manager->removeMainMenuItem('October.Cms', 'cms');
                }
                if(!Setting::get('show_native_static_pages')){
                    $manager->removeMainMenuItem('RainLab.Pages', 'pages');
                }
                if(!PluginManager::instance()->exists('RainLab.Pages')){
                    $manager->removeMainMenuItem('Rd.Dynopages', 'pages');
                }
            }else{
                $manager->removeMainMenuItem('Rd.Dynopages', 'cms');
                $manager->removeMainMenuItem('Rd.Dynopages', 'pages');
            }
        }, 100);

        /*
         * Register menu items for the Dyno static pages
         */
        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'dyno-static-page'      => 'rd.dynopages::lang.menuitem.static_page',
                'all-dyno-static-pages' => 'rd.dynopages::lang.menuitem.all_static_pages'
            ];
        });

        Event::listen('pages.menuitem.getTypeInfo', function($type) {
            if ($type == 'url') {
                return [];
            }

            if ($type == 'dyno-static-page' || $type == 'all-dyno-static-pages') {
                return DynoStaticPage::getMenuTypeInfo($type);
            }
        });
        Event::listen('pages.menuitem.resolveItem', function($type, $item, $url, $theme) {
            if(Setting::get('use_dynopages')){
                if ($type == 'dyno-static-page' || $type == 'all-dyno-static-pages') {
                    return DynoStaticPage::resolveMenuItem($item, $url, $theme);
                }
            }
        }, 100);

        Event::listen('backend.richeditor.listTypes', function () {
            return [
                'dyno-static-page' => 'rd.dynopages::lang.menuitem.static_page',
            ];
        });

        Event::listen('backend.richeditor.getTypeInfo', function ($type) {
            if ($type === 'dyno-static-page') {
                return DynoStaticPage::getRichEditorTypeInfo($type);
            }
        });

        /*
         * Register menu items for the Dyno Cms pages
         */
        Event::listen('pages.menuitem.listTypes', function () {
            return [
                'dyno-cms-page' => 'rd.dynopages::lang.menuitem.cms_page'
            ];
        });

        Event::listen('pages.menuitem.getTypeInfo', function ($type) {
            if ($type === 'dyno-cms-page') {
                return DynoCmsPage::getMenuTypeInfo($type);
            }
        });

        Event::listen('pages.menuitem.resolveItem', function ($type, $item, $url, $theme) {
            if ($type === 'dyno-cms-page') {
                return DynoCmsPage::resolveMenuItem($item, $url, $theme);
            }
        });

        Event::listen('cms.template.save', function($controller, $template, $type) {
            if(Setting::get('use_dynopages')){
                Plugin::clearCache();
            }
        });

        Event::listen('backend.richeditor.listTypes', function () {
            return [
                'dyno-cms-page' => 'cms::lang.page.cms_page'
            ];
        });

        Event::listen('backend.richeditor.getTypeInfo', function ($type) {
            if ($type === 'dyno-cms-page') {
                return DynoCmsPage::getRichEditorTypeInfo($type);
            }
        });

        // BUGFIX LANG
        Event::listen('pages.page.getMenuCacheKey', function (&$key) {
            // Fix wrong locale from Lang::getLocale() method
            $key = $key . '-' . \RainLab\Translate\Classes\Translator::instance()->getLocale();
            return false;
        }, 10);
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        if(PluginManager::instance()->exists('RainLab.Pages')){
            return [
                'Rd\DynoPages\Components\StaticMenu' => 'staticMenu',
            ];
        }
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        // return []; // Remove this line to activate

        return [
            'rd.dynopagesstatic.manage_static_pages' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_static_pages'
            ],
            'rd.dynopagesstatic.manage_menus' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_menus'
                ],
            'rd.dynopagesstatic.manage_content' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_content'
            ],
            'rd.dynopagesstatic.access_snippets' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.access_snippets'
            ],
            'rd.dynopagescms.manage_pages' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_pages'
            ],
            'rd.dynopagescms.manage_partials' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_partials'
            ],
            'rd.dynopagescms.manage_layouts' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_layouts'
                ],
            'rd.dynopagescms.manage_content' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_content'
                ],
            'rd.dynopagescms.manage_assets' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_assets'
                ],
            'rd.dynopagescms.manage_components' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.manage_components'
            ],
            'rd.dynopagescms.access_settings' => [
                'tab'   => 'rd.dynopages::lang.permissions.tab',
                'order' => 200,
                'label' => 'rd.dynopages::lang.permissions.access_settings'
            ]
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        // return []; // Remove this line to activate

        return [
            'cms' => [
                'label'       => 'rd.dynopages::lang.cmspage.menu_label',
                'url'         => Backend::url('rd/dynopages/cmsindex'),
                'iconSvg'     => 'plugins/rd/dynopages/assets/images/cms.svg',
                'permissions' => ['rd.dynopagescms.*'],
                'order'       => 200,

                'sideMenu' => [
                    // CMS Menu
                    'pages' => [
                        'label'        => 'cms::lang.page.menu_label',
                        'icon'         => 'icon-copy',
                        'url'          => 'javascript:;',
                        'attributes'   => ['data-menu-item' => 'pages'],
                        'permissions'  => ['rd.dynopagescms.manage_pages'],
                        'counterLabel' => 'cms::lang.page.unsaved_label'
                    ],
                    'partials' => [
                        'label'        => 'cms::lang.partial.menu_label',
                        'icon'         => 'icon-tags',
                        'url'          => 'javascript:;',
                        'attributes'   => ['data-menu-item' => 'partials'],
                        'permissions'  => ['rd.dynopagescms.manage_partials'],
                        'counterLabel' => 'cms::lang.partial.unsaved_label'
                    ],
                    'layouts' => [
                        'label'        => 'cms::lang.layout.menu_label',
                        'icon'         => 'icon-th-large',
                        'url'          => 'javascript:;',
                        'attributes'   => ['data-menu-item' => 'layouts'],
                        'permissions'  => ['rd.dynopagescms.manage_layouts'],
                        'counterLabel' => 'cms::lang.layout.unsaved_label'
                    ],
                    'content' => [
                        'label'        => 'cms::lang.content.menu_label',
                        'icon'         => 'icon-file-text-o',
                        'url'          => 'javascript:;',
                        'attributes'   => ['data-menu-item' => 'content'],
                        'permissions'  => ['rd.dynopagescms.manage_content'],
                        'counterLabel' => 'cms::lang.content.unsaved_label'
                    ],
                    'assets' => [
                        'label'        => 'cms::lang.asset.menu_label',
                        'icon'         => 'icon-picture-o',
                        'url'          => 'javascript:;',
                        'attributes'   => ['data-menu-item' => 'assets'],
                        'permissions'  => ['rd.dynopagescms.manage_assets'],
                        'counterLabel' => 'cms::lang.asset.unsaved_label'
                    ],
                    'components' => [
                        'label'       => 'cms::lang.component.menu_label',
                        'icon'        => 'icon-puzzle-piece',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item' => 'components'],
                        'permissions' => ['rd.dynopagescms.manage_components']
                    ]
                ]
            ],
            'pages' => [
                'label'       => 'rd.dynopages::lang.staticpage.menu_label',
                'url'         => Backend::url('rd/dynopages/staticindex'),
                'iconSvg'     => 'plugins/rd/dynopages/assets/images/pages.svg',
                'permissions' => ['rd.dynopagesstatic.*'],
                'order'       => 200,

                'sideMenu' => [
                    // Pages plugin menu
                    'pages' => [
                        'label'       => 'rainlab.pages::lang.page.menu_label',
                        'icon'        => 'icon-files-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'pages'],
                        'permissions' => ['rd.dynopagesstatic.manage_static_pages']
                    ],
                    'menus' => [
                        'label'       => 'rainlab.pages::lang.menu.menu_label',
                        'icon'        => 'icon-sitemap',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'menus'],
                        'permissions' => ['rd.dynopagesstatic.manage_menus']
                    ],
                    'content' => [
                        'label'       => 'rainlab.pages::lang.content.menu_label',
                        'icon'        => 'icon-file-text-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'content'],
                        'permissions' => ['rd.dynopagesstatic.manage_content']
                    ],
                    'snippets' => [
                        'label'       => 'rainlab.pages::lang.snippet.menu_label',
                        'icon'        => 'icon-newspaper-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'snippet'],
                        'permissions' => ['rd.dynopagesstatic.access_snippets']
                    ],
                ]
            ],
        ];
    }

    /**
     * Add custom dynopages settings
     *
     * @return array
     */
    public function registerSettings()
    {
        return [
            'migrations' => [
                'label'       => 'rd.dynopages::lang.migrations.label',
                'description' => 'rd.dynopages::lang.migrations.description',
                'category'    => 'rd.dynopages::lang.plugin.name',
                'icon'        => 'icon-clone',
                'url'         => Backend::url('rd/dynopages/migrations'),
                'order'       => 600,
                'permissions' => ['rd.dynopages.access_settings'],
                'keywords'    => 'dynopages cms pages'
            ],
            'settings' => [
                'label'       => 'rd.dynopages::lang.settings.label',
                'description' => 'rd.dynopages::lang.settings.description',
                'category'    => 'rd.dynopages::lang.plugin.name',
                'icon'        => 'icon-cog',
                'url'         => Backend::url('rd/dynopages/settings'),
                'class'       => 'Rd\Dynopages\Models\Setting',
                'order'       => 600,
                'permissions' => ['rd.dynopages.access_settings'],
                'keywords'    => 'dynopages cms pages'
            ]
        ];
    }

    public static function clearCache()
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        DynoStaticPage::clearMenuCache($theme);
        SnippetManager::clearCache($theme);
    }
}

<?php namespace Rd\DynoPages\Classes;

use Lang;
use Cache;
use Event;
use Config;
use Cms\Classes\Theme;
use RainLab\Pages\Classes\Page;
use Rd\DynoPages\Classes\Page as DynoPage;
use Rd\DynoPages\Classes\StaticPage as DynoStaticPage;
use October\Rain\Support\Str;
use October\Rain\Router\Router as RainRouter;
use October\Rain\Router\Helper as RouterHelper;

/**
 * A router for CMS/static pages.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Router
{
    /**
     * @var \Cms\Classes\Theme A reference to the CMS theme containing the object.
     */
    protected $theme;

    /**
     * @var array Contains the URL map - the list of page file names and corresponding URL patterns.
     */
    private static $urlMap = [];

    /**
     * @var array Request-level cache
     */
    private static $cache = [];

    /**
     * October\Rain\Router\Router Router object with routes preloaded.
     */
    protected $routerObj;

    /**
     * @var array A list of parameters names and values extracted from the URL pattern and URL string.
     */
    protected $parameters = [];

    /**
     * Creates the router instance.
     * @param \Cms\Classes\Theme $theme Specifies the theme being processed.
     */
    public function __construct(Theme $theme)
    {
        $this->theme = $theme;
    }

    ################################################
    ################# CMS PAGE #####################
    ################################################

    /**
     * Finds a cms page from DB by its URL.
     * @param string $url The requested URL string.
     * @return \Rd\DynoPages\Classes\Page Returns \Rd\DynoPages\Classes\Page object or null if the page cannot be found.
     */
    public function findDynoPageByUrl($url)
    {
        $this->url = $url;
        $url = RouterHelper::normalizeUrl($url);

        for ($pass = 1; $pass <= 2; $pass++) {
            $fileName = null;
            $urlList = [];

            $cacheable = Config::get('cms.enableRoutesCache');
            if ($cacheable) {
                $fileName = $this->getCachedUrlFileName($url, $urlList);
                if (is_array($fileName)) {
                    list($fileName, $this->parameters) = $fileName;
                }
            }
            
            /*
             * Find the page by URL and cache the route
             */
            if (!$fileName) {
                $router = $this->getRouterObject();

                if ($router->match($url)) {
                    $this->parameters = $router->getParameters();

                    $fileName = $router->matchedRoute();

                    if ($cacheable) {
                        if (!$urlList || !is_array($urlList)) {
                            $urlList = [];
                        }

                        $urlList[$url] = !empty($this->parameters)
                            ? [$fileName, $this->parameters]
                            : $fileName;

                        $key = $this->getUrlListCacheKey();
                        Cache::put(
                            $key,
                            base64_encode(serialize($urlList)),
                            Config::get('cms.urlCacheTtl', 1)
                        );
                    }
                }
            }
            
            /*
             * Return the page
             */
            if ($fileName) {
                if (($page = DynoPage::loadFromDb($this->theme, $fileName, true)) === null) {
                    /*
                     * If the page was not found on the disk, clear the URL cache
                     * and repeat the routing process.
                     */
                    if ($pass == 1) {
                        $this->clearCmsCache();
                        continue;
                    }

                    return null;
                }

                return $page;
            }

            return null;
        }
    }

    /**
     * Autoloads the URL map only allowing a single execution.
     * @return array Returns the URL map.
     */
    protected function getRouterObject()
    {
        if ($this->routerObj !== null) {
            return $this->routerObj;
        }

        /*
         * Load up each route rule
         */
        $router = new RainRouter();
        
        foreach ($this->getCmsUrlMap() as $pageInfo) {
            $router->route($pageInfo['file'], $pageInfo['pattern']);
        }

        /*
         * Sort all the rules
         */
        $router->sortRules();

        return $this->routerObj = $router;
    }

    /**
     * Autoloads the URL map only allowing a single execution.
     * @return array Returns the URL map.
     */
    protected function getCmsUrlMap()
    {
        return $this->loadCmsUrlMap();
    }

    /**
     * Loads the URL map - a list of page file names and corresponding URL patterns.
     * The URL map can is cached. The clearUrlMap() method resets the cache. By default
     * the map is updated every time when a page is saved in the back-end, or
     * when the interval defined with the cms.urlCacheTtl expires.
     * @return boolean Returns true if the URL map was loaded from the cache. Otherwise returns false.
     */
    protected function loadCmsUrlMap()
    {
        $pages = DynoPage::listDbInTheme($this->theme);
        $map = [];
        foreach ($pages as $page) {
            if (!$page || !$page->url) {
                continue;
            }

            $map[] = ['file' => $page->getFileName(), 'pattern' => $page->url];
        }

        return $map;

        $key = $this->getCmsCacheKey('page-url-map');

        $cacheable = Config::get('cms.enableRoutesCache');
        if ($cacheable) {
            $cached = Cache::get($key, false);
        }
        else {
            $cached = false;
        }
        
        if (!$cached || ($unserialized = @unserialize(@base64_decode($cached))) === false) {
                
            /*
             * The item doesn't exist in the cache, create the map
             */
            $pages = DynoPage::listDbInTheme($this->theme);
            $map = [];
            foreach ($pages as $page) {
                if (!$page || !$page->url) {
                    continue;
                }

                $map[] = ['file' => $page->getFileName(), 'pattern' => $page->url];
            }

            self::$urlMap = $map;
            if ($cacheable) {
                Cache::put($key, base64_encode(serialize($map)), Config::get('cms.urlCacheTtl', 1));
            }

            return false;
        }

        self::$urlMap = $unserialized;
        return true;
    }

    /**
     * Clears the router cache.
     */
    public function clearCmsCache()
    {
        Cache::forget($this->getCacheKey('page-url-map'));
        Cache::forget($this->getCacheKey('cms-url-list'));
    }

    /**
     * Returns the caching URL key depending on the theme.
     * @param string $keyName Specifies the base key name.
     * @return string Returns the theme-specific key name.
     */
    protected function getCmsCacheKey($keyName)
    {
        return md5($this->theme->getPath()).$keyName.Lang::getLocale();
    }

    /**
     * Returns the cache key name for the URL list.
     * @return string
     */
    protected function getUrlListCacheKey()
    {
        return $this->getCacheKey('cms-url-list');
    }

    /**
     * Tries to load a page file name corresponding to a specified URL from the cache.
     * @param string $url Specifies the requested URL.
     * @param array &$urlList The URL list loaded from the cache
     * @return mixed Returns the page file name if the URL exists in the cache. Otherwise returns null.
     */
    protected function getCachedUrlFileName($url, &$urlList)
    {
        $key = $this->getUrlListCacheKey();
        $urlList = Cache::get($key, false);

        if ($urlList
            && ($urlList = @unserialize(@base64_decode($urlList)))
            && is_array($urlList)
            && array_key_exists($url, $urlList)
        ) {
            return $urlList[$url];
        }

        return null;
    }

    ####################################################
    ################# END CMS PAGE #####################
    ####################################################


    ###################################################
    ################# STATIC PAGE #####################
    ###################################################

    /**
     * Finds a static page from DB by its URL.
     * @param string $url The requested URL string.
     * @return \RainLab\Pages\Classes\Page Returns \RainLab\Pages\Classes\Page object or null if the page cannot be found.
     */
    public function findDynoStaticPageByUrl($url)
    {
        $url = Str::lower(RouterHelper::normalizeUrl($url));
        
        if (array_key_exists($url, self::$cache)) {
            return self::$cache[$url];
        }
        
        
        $urlMap = $this->getUrlMap();
        $urlMap = array_key_exists('urls', $urlMap) ? $urlMap['urls'] : [];
        
        if (!array_key_exists($url, $urlMap)) {
            return null;
        }
        
        $fileName = $urlMap[$url];

        if (($page = DynoStaticPage::loadFromDb($this->theme, $fileName, true)) === null) {
            /*
             * If the page was not found on the disk, clear the URL cache
             * and try again.
             */
            $this->clearCache();

            return self::$cache[$url] = DynoStaticPage::loadCached($this->theme, $fileName);
        }
        
        return self::$cache[$url] = $page;
    }

    /**
     * Autoloads the URL map only allowing a single execution.
     * @return array Returns the URL map.
     */
    protected function getUrlMap()
    {
        if (!count(self::$urlMap)) {
            $this->loadUrlMap();
        }

        return self::$urlMap;
    }

    /**
     * Loads the URL map - a list of page file names and corresponding URL patterns.
     * The URL map can is cached. The clearUrlMap() method resets the cache. By default
     * the map is updated every time when a page is saved in the back-end, or 
     * when the interval defined with the cms.urlCacheTtl expires.
     * @return boolean Returns true if the URL map was loaded from the cache. Otherwise returns false.
     */
    protected function loadUrlMap()
    {
        $key = $this->getCacheKey('static-page-url-map');

        $cacheable = Config::get('cms.enableRoutesCache');
        $cached = $cacheable ? Cache::get($key, false) : false;

        if (!$cached || ($unserialized = @unserialize($cached)) === false) {
            /*
             * The item doesn't exist in the cache, create the map
             */

            // Get all pages
            $pages = DynoStaticPage::listDbInTheme($this->theme);
            
            $map = [
                'urls'   => [],
                'files'  => [],
                'titles' => []
            ];
            foreach ($pages as $page) {
                $url = trim($page->getViewBag()->property('url'));
                if (!$url) {
                    continue;
                }

                $url = Str::lower(RouterHelper::normalizeUrl($url));
                $file = $page->getBaseFileName();

                $map['urls'][$url] = $file;
                $map['files'][$file] = $url;
                $map['titles'][$file] = $page->getViewBag()->property('title');
            }

            self::$urlMap = $map;

            if ($cacheable) {
                Cache::put($key, serialize($map), Config::get('cms.urlCacheTtl', 1));
            }

            return false;
        }

        self::$urlMap = $unserialized;

        return true;
    }

    /**
     * Returns the caching URL key depending on the theme.
     * @param string $keyName Specifies the base key name.
     * @return string Returns the theme-specific key name.
     */
    protected function getCacheKey($keyName)
    {
        $key = crc32($this->theme->getPath()).$keyName;
        Event::fire('pages.router.getCacheKey', [&$key]);
        return $key;
    }

    /**
     * Clears the router cache.
     */
    public function clearCache()
    {
        Cache::forget($this->getCacheKey('static-page-url-map'));
    }

    #######################################################
    ################# END STATIC PAGE #####################
    #######################################################

    /**
     * Sets the current routing parameters.
     * @param  array $parameters
     * @return array
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns the current routing parameters.
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}

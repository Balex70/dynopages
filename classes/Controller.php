<?php namespace Rd\DynoPages\Classes;

use Cms\Classes\Page as CmsPage;
use Rd\DynoPages\Classes\Router;
use Cms\Classes\Controller as CmsController;

/**
 * Controller
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Controller extends CmsController
{
    /**
     * @var \Cms\Classes\Controller A reference to the CMS controller.
     */
    protected $cmsController;
    
    /**
     * Creates a CMS page form DB and configures it.
     * @param string $url Specifies the page URL.
     * @return \Rd\DynoPages\Classes\Page Returns the extended from CMS page object or NULL of the requested page was not found.
     */
    public function initCmsPageFromDB($url)
    {
        $router = new Router($this->theme);
        $this->cmsController = new CmsController($this->theme);
        // Get native page (dynopages) from DB
        $page = $router->findDynoPageByUrl($url);
        if (!$page) {
            return null;
        }

        return $page;
    }

    /**
     * Creates a CMS page from static page taken from DB and configures it.
     * @param string $url Specifies the page URL.
     * @return \Rd\DynoPages\Classes\StaticPage Returns the extended from CMS page object or NULL of the requested page was not found.
     */
    public function initStaticCmsPageFromDB($url)
    {
        $router = new Router($this->theme);
        $page = $router->findDynoStaticPageByUrl($url);

        if (!$page) {
            return null;
        }

        $viewBag = $page->viewBag;

        $cmsPage = CmsPage::inTheme($this->theme);
        $cmsPage->url = $url;
        $cmsPage->apiBag['staticPage'] = $page;

        /*
         * Transfer specific values from the content view bag to the page settings object.
         */
        $viewBagToSettings = ['title', 'layout', 'meta_title', 'meta_description', 'is_hidden'];

        foreach ($viewBagToSettings as $property) {
            $cmsPage->settings[$property] = array_get($viewBag, $property);
        }

        return $cmsPage;
    }
}
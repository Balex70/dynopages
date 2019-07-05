<?php namespace Rd\DynoPages\Classes;

use Lang;
use SystemException;
use ApplicationException;
use Rd\DynoPages\Classes\StaticPage;
use Rd\DynoPages\Classes\StaticPagesConf;

/**
 * The page list class reads and manages the static page hierarchy.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class PageList extends \RainLab\Pages\Classes\PageList
{
    /**
     * Returns a list of static pages from DB in the specified theme.
     * This method is used internally by the system.
     *
     * @return array Returns an array of static pages.
     */
    public function listStaticPages()
    {
        return StaticPage::listDbInTheme($this->theme);
    }

    /**
     * Returns a list of top-level pages with subpages.
     * The method uses the database instead of origin YAML file to build the hierarchy. The pages are returned
     * in the order defined in configuration. The result of the method is used for building the back-end UI
     * and for generating the menus.
     * 
     * @return array Returns a nested array of objects: object('page': $pageObj, 'subpages'=>[...])
     */
    public function getStaticPageTree()
    {
        // Get all pages from DB
        $pages = $this->listStaticPages();

        // Get config from DB (instead of Yaml file meta/static-pages.yaml)
        $config = $this->getPagesConfig();

        $iterator = function($configPages) use (&$iterator, &$pages) {
            $result = [];

            foreach ($configPages as $fileName => $subpages) {
                $pageObject = null;
                foreach ($pages as $page) {
                    if ($page->getBaseFileName() == $fileName) {
                        $pageObject = $page;
                        break;
                    }
                }

                if ($pageObject === null) {
                    continue;
                }

                $result[] = (object)[
                    'page'     => $pageObject,
                    'subpages' => $iterator($subpages)
                ];
            }

            return $result;
        };
        
        return $iterator($config['static-pages']);
    }

    /**
     * Updates the page hierarchy structure.
     * @param array $structure A nested associative array representing the page structure
     * 
     * @return void
     */
    public function updateStructure($structure)
    {
        $originalData = $this->getPagesConfig();
        $originalData['static-pages'] = $structure;
        
        // Get current configuration from DB
        $currentConf = StaticPagesConf::where('theme', $this->theme->getDirName())
                        ->where('conf', '<>', null)
                        ->first();

        // Save conf to DB
        if (!$currentConf) {
            $newConf = new StaticPagesConf;
            $newConf->conf = $originalData;
            $newConf->theme = $this->theme->getDirName();
            if (!$newConf->save()) {
                throw new ApplicationException(Lang::get('rd.dynopages::lang.rd_dynopages_static_pages.error_creating_conf'));
            }
        }else{
            $currentConf->conf = $originalData;
            $currentConf->theme = $this->theme->getDirName();
            if (!$currentConf->save()) {
                throw new ApplicationException(Lang::get('rd.dynopages::lang.rd_dynopages_static_pages.error_saving_conf'));
            }
        }
    }

    /**
     * Returns the parsed configuration (array).
     * @return mixed
     */
    protected function getPagesConfig()
    {
        $configData = StaticPagesConf::where('theme', $this->theme->getDirName())
                                    ->where('conf', '<>', null)
                                    ->first();

        if (!$configData) {
            return ['static-pages' => []];
        }

        if (!array_key_exists('static-pages', $configData->conf)) {
            throw new SystemException('The content static page config record is invalid: the "static-pages" root element is not found.');
        }

        return $configData->conf;
    }

    /**
     * Returns the parsed configuration (array).
     * @return mixed
     */
    public function getPagesConfigFromDB()
    {
        return $this->getPagesConfig();
    }

}

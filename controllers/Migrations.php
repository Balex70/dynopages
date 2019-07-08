<?php namespace Rd\Dynopages\Controllers;

use Lang;
use Flash;
use BackendMenu;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Rd\DynoPages\Classes\Menu;
use System\Classes\PluginManager;
use Backend\Classes\Controller;
use Illuminate\Support\Facades\Log;
use RainLab\Pages\Classes\PageList;
use System\Classes\SettingsManager;
use Rd\DynoPages\Classes\StaticPage;
use Rd\DynoPages\Services\DBService;
use Rd\DynoPages\Classes\Page as DynoPage;
use Rd\DynoPages\Classes\PageList as StaticPageList;

/**
 * Migrations
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Migrations extends Controller
{
    /**
     * @var Cms\Classes\Theme
     */
    protected $theme;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Rd.Dynopages', 'dynopages');

        if (!($theme = Theme::getEditTheme())) {
            throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));
        }
        $this->theme = $theme;
        $this->addJs('/plugins/rd/dynopages/assets/js/migrations.js');
        $this->addCss('/plugins/rd/dynopages/assets/css/migrations.css');
    }

    public function index()
    {
        $this->prepareTable();
    }

    public function prepareTable()
    {
        $this->vars['defaultLocale'] = $this->getDefaultLang();

        // Get CMS pages data
        $pages = Page::listInTheme($this->theme, true) !== null ? Page::listInTheme($this->theme, true) : [];
        $dbPages = $this->getDBPages() !== null ? $this->getDBPages() : [];
        $this->vars['pages'] = $pages;
        $this->vars['dbPages'] = $dbPages;

        if(PluginManager::instance()->exists('RainLab.Pages')){
            // Get Static pages data
            $pageList = new PageList($this->theme);
            $staticPages = \RainLab\Pages\Classes\Page::listInTheme($this->theme, true) !== null ? \RainLab\Pages\Classes\Page::listInTheme($this->theme, true) : [];
            $staticPagesTree = $pageList->getPageTree(true) !== null ? $pageList->getPageTree(true) : [];
            $dbStaticPages = StaticPage::listDbInTheme($this->theme) !== null ? StaticPage::listDbInTheme($this->theme) : [];
            $this->vars['staticPages'] = $staticPages;
            $this->vars['staticPagesTree'] = $staticPagesTree;
            $this->vars['dbStaticPages'] = $dbStaticPages;

            // Get Static menus data
            $staticMenus = \RainLab\Pages\Classes\Menu::listInTheme($this->theme, true) !== null ? \RainLab\Pages\Classes\Menu::listInTheme($this->theme, true) : [];
            $dbStaticMenus = Menu::listDbInTheme($this->theme) !== null ? Menu::listDbInTheme($this->theme) : [];
            $this->vars['staticMenus'] = \RainLab\Pages\Classes\Menu::listInTheme($this->theme, true);
            $this->vars['dbStaticMenus'] = Menu::listDbInTheme($this->theme);

            $this->vars['rainLabPagePluginExists'] = true;
        }else{
            $this->vars['rainLabPagePluginExists'] = false;
        }
        
    }

    ###################################################
    ### CMS PAGES MIGRATION
    ###################################################
    /**
     * Ajax handler for perform migration
     */
    public function onPerformPageMigrate()
    {
        $recordId = post('recordId');
        $recordFilename = post('recordFilename');

        return $this->performMigrate($recordId, $recordFilename);
    }

    /**
     * Ajax handler for perform bulk migration of cms pages
     * 
     * @return array
     */
    public function onPerformPageBulkMigrate()
    {
        $errors = false;
        $migrationStatus = [];
        $dbPages = $this->getDBPages() !== null ? $this->getDBPages() : [];
        $count = 0;
        foreach (Page::listInTheme($this->theme, true) as $pageKey => $pageItem) {
            if (count($dbPages) > 0 and array_key_exists($pageItem->fileName, $dbPages)){
                // Skip migration of that page
            }else{
                // Migrate page
                $migrate = $this->performMigrate($count, $pageItem->fileName);
                if(!$migrate){
                    Flash::error(Lang::get('rd.dynopages::lang.migrations.error'));
                    $errors = true;
                    break;
                }else{
                    $migrationStatus = array_merge($migrationStatus, $migrate);
                }
            }
            $count++;
        }

        if(!$errors){
            return array_merge(
                [
                    '#migrateStatusBulk' =>
                        '<p class="flash-message static success">'.
                            Lang::get('rd.dynopages::lang.migrations.migrated').
                        '</p>'
                    ],
                $migrationStatus
            );
        }
    }

    /**
     * Perform migration of page
     * @param integer $recordId id of record that should be migrated
     * @param string $recordFilename
     * 
     * @return array|null
     */
    public function performMigrate($recordId, $recordFilename)
    {
        $pages = Page::listInTheme($this->theme, true);
        
        $pageObject = $pages[$recordFilename];

        $locales = $this->getListAvailableLocales();
        foreach ($locales as $lang => $value) {
            $settings[$lang] = $this->getPageSettings($pages[$recordFilename], $locales, $lang);
        }
        
        // Validate before insert page
        $errors = false;
        foreach ($locales as $lang => $value) {
            if(DBService::getRecordByUrl("rd_dynopages_pages", $this->theme->getDirName(), $pageObject->getSettingsUrlAttributeTranslated($lang), $lang) && $pageObject->getSettingsUrlAttributeTranslated($lang) != ''){
                $errors = true;
                Flash::error(Lang::get('rd.dynopages::lang.url_not_unique', ['url' => $pageObject->getSettingsUrlAttributeTranslated($lang), 'lang' => $lang]));
            }
        }

        // Perform insert page if validation passes
        if(!$errors){
            $fields = [
                'file_name' => 'fileName',
                'url' => 'url',
                'layout' => 'layout',
                'title' => 'title',
                'description' => 'description',
                'is_hidden' => 'is_hidden',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'code' => 'code',
                'markup' => 'markup',
                'settings' => 'settings',
                'mtime' => 'mtime',
                'theme' => 'theme',
                'lang' => 'lang'
            ];
            $mtime = time();
            foreach ($locales as $lang => $value) {
                $attributes = $settings[$lang]
                                +['fileName' => $recordFilename]
                                +['layout' => $pageObject->layout]
                                +['is_hidden' => $pageObject->is_hidden]
                                +['markup' => $pageObject->markup];
    
                DBService::insertRecord("rd_dynopages_pages", $fields, $recordFilename, $attributes, $settings[$lang], $this->theme->getDirName(), $mtime, $lang);
            }
            return [
                '#migrateStatus'.$recordId => '<div class="text-success">'.Lang::get('rd.dynopages::lang.migrations.migrated').'</div>'
            ];
        }else{
            return null;
        }
    }

    /**
     * Get settings for CMS page to insert
     * @param Cms\Classes\Page $page page that should be migrated.
     * @param array $locales list of locales
     * @param string $lang current locale
     * 
     * @return array
     */
    protected function getPageSettings($page, $locales, $lang){
        $settings["title"] = $page->getAttributeTranslated('title', $lang);
        $settings["url"] = $page->getSettingsUrlAttributeTranslated($lang);
        $settings["layout"] = $page->layout;
        $settings["description"] = $page->getAttributeTranslated('description', $lang);
        $settings["meta_title"] = $page->getAttributeTranslated('meta_title', $lang);
        $settings["meta_description"] = $page->getAttributeTranslated('meta_description', $lang);
        $settings["is_hidden"] = $page->is_hidden;

        // Fill settings array with components
        if($page->settings['components'] !== null && count($page->settings['components']) > 0){
            foreach ($page->settings['components'] as $componentKey => $component) {
                $settings[$componentKey] = $component;
            }
        }

        $fieldsToFill = [
            'localeUrl' => 'url',
            'localeTitle' => 'title',
            'localeDescription' => 'description',
            'localeMeta_title' => 'meta_title',
            'localeMeta_description' => 'meta_description'
        ];

        foreach ($fieldsToFill as $key => $field) {
            foreach ($locales as $locale => $value) {
                if($field == 'url'){
                    array_set($settings, 'viewBag.'.$key.'.'.$locale, $page->getSettingsUrlAttributeTranslated($locale));
                }else{
                    array_set($settings, 'viewBag.'.$key.'.'.$locale, $page->getAttributeTranslated($field, $locale));
                }
            }
        }

        return $settings;
    }

    /**
     * Get CMS pages from database
     * 
     * @return array|null
     */
    protected function getDBPages()
    {
        // Get records (items) from DB
        $records = DBService::listPages('rd_dynopages_pages', $this->theme, $this->getDefaultLang());

        if(isset($records)){
            foreach ($records as $key => $value) {
                $items[$value] = DynoPage::loadFromDb($this->theme, $value);
            }
        }else{
            return ;
        }
        
        return $items;
    }

    ###################################################
    ### END CMS PAGES MIGRATION
    ###################################################


    ###################################################
    ### STATIC CONTENT MIGRATION
    ###################################################
    /**
     * Ajax handler for perform migration Static page
     * 
     * @return array|null
     */
    public function onPerformStaticPageMigrate()
    {
        $errors = false;
        if(StaticPage::listDbInTheme($this->theme) !== null && count(StaticPage::listDbInTheme($this->theme)) > 0){
            $errors = true;
            Flash::error(Lang::get('rd.dynopages::lang.migrations.db_static_pages_exists'));
        }
        
        if(!$errors){
            $fields = [
                'file_name' => 'fileName',
                'url' => 'url',
                'layout' => 'layout',
                'title' => 'title',
                'is_hidden' => 'is_hidden',
                'navigation_hidden' => 'navigation_hidden',
                'meta_title' => 'meta_title',
                'meta_description' => 'meta_description',
                'settings' => 'settings',
                'code' => 'code',
                'placeholders' => 'placeholders',
                'markup' => 'markup',
                'mtime' => 'mtime',
                'theme' => 'theme',
                'lang' => 'lang'
            ];
            $mtime = time();
    
            // Proceed static pages migration
            foreach (\RainLab\Pages\Classes\Page::listInTheme($this->theme, true) as $fileName => $staticPageValue) {
                $locales = $this->getListAvailableLocales();
                
                $pages = [];
                // Load localized pages
                foreach ($locales as $lang => $value) {
                    if($this->getDefaultLang() != $lang){
                        if($loadedPage = StaticPage::load($this->theme, '../static-pages-'.$lang.'/'.$fileName)){ // get localized page
                            $pages[$lang] = $loadedPage;
                        }
                    }else{
                        $pages[$lang] = $staticPageValue;
                    }
                }
                
                $settings = array();
                // Generate settings for each page
                foreach ($locales as $lang => $value) {
                    if(isset($pages[$lang])){
                        $settings[$lang] = $this->getStaticPageSettings($pages[$this->getDefaultLang()], $pages[$lang], $locales, $lang);
                    }else{
                        $settings[$lang] = $this->getStaticPageSettings($pages[$this->getDefaultLang()], null, $locales, $lang);
                    }
                }

                $attributes = array();
                foreach ($locales as $lang => $value) {
                    $attributes = $settings[$lang]
                                    +['fileName' => preg_replace('/\.htm$/', '', $fileName)];
                    DBService::insertRecord('rd_dynopages_static_pages', $fields, preg_replace('/\.htm$/', '', $fileName), $attributes, $settings[$lang], $this->theme->getDirName(), $mtime, $lang);
                }
            }
    
            // Migrate static pages conf (BE page structure)
            $originPageList = new PageList($this->theme);
            $originStructure = $this->generateStructure($originPageList->getPageTree(true));
            $pageList = new StaticPageList($this->theme);
            $pageList->updateStructure($originStructure);
        }

        return [
            '#staticMigrateStatus' => '<div class="text-success">'.Lang::get('rd.dynopages::lang.migrations.migrated').'</div>'
        ];
    }

    /**
     * Generate structure
     * @param array $pageTree
     *
     * @return array
     */
    protected function generateStructure($pageTree){
        $structure = [];
        foreach($pageTree as $key => $value){
            if(is_array($value->subpages)){
                $subpages = $this->generateStructure($value->subpages);
            }else{
                $subpages = [];
            };
            $structure[preg_replace('/\.htm$/', '', $value->page->fileName)] = $subpages;
        }

        return $structure;
    }

    /**
     * Get settings for Static page to insert
     * @param Cms\Classes\Page $defaultLangPage page that has default language data
     * @param Rd\DynoPages\Classes\StaticPage $page loaded localized|default page
     * @param array $locales list of locales
     * @param string $lang current locale
     * 
     * @return array
     */
    protected function getStaticPageSettings($defaultLangPage, $page, $locales, $lang){
        $settings["title"] = $page ? $page->title : '';
        $settings["url"] = $page ? $defaultLangPage->getViewBagUrlAttributeTranslated($lang) : $defaultLangPage->url;
        $settings["layout"] = $defaultLangPage->layout;
        $settings["is_hidden"] = $defaultLangPage->is_hidden;
        $settings["navigation_hidden"] = $defaultLangPage->navigation_hidden;
        $settings["meta_title"] = $page ? $page->meta_title : '';
        $settings["meta_description"] = $page ? $page->meta_description : '';
        $settings["markup"] = $page ? $page->markup : '';
        $settings["code"] = $page ? $page->code : '';
        $settings["placeholders"] = $page ? $page->placeholders : '';

        $fieldsToFill = [
            'title' => 'title',
            'url' => 'url',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'layout' => 'layout',
            'is_hidden' => 'is_hidden',
            'navigation_hidden' => 'navigation_hidden',
            'markup' => 'markup',
            'code' => 'code',
            'localeUrl' => 'url',
        ];

        if($this->getDefaultLang() == $lang && $page){
            foreach ($fieldsToFill as $key => $field) {
                if($key == 'localeUrl'){
                    foreach ($locales as $locale => $value) {
                        array_set($settings, 'viewBag.'.$key.'.'.$locale, $defaultLangPage->getViewBagUrlAttributeTranslated($locale));
                    }
                }else{
                    array_set($settings, 'viewBag.'.$key, $page->{$field});
                }
            }
        }
        return $settings;
    }

    /**
     * Ajax handler for perform migration Static menu
     * 
     * @return array|null
     */
    public function onPerformStaticMenuMigrate()
    {
        $recordId = post('recordId');
        $menuCode = post('menuCode');
        $menus = \RainLab\Pages\Classes\Menu::listInTheme($this->theme, true);

        $attributes = [
            'code' => $menuCode,
            'fileName' => $menuCode,
            'name' => $menuCode,
            'content' => $menus[$menuCode.'.yaml']->content
        ];

        $fields = [
            'file_name' => 'code',
            'name' => 'name',
            'content' => 'content',
            'theme' => 'theme',
            'mtime' => 'mtime'
        ];

        $mtime = time();

        DBService::insertRecord('rd_dynopages_static_menu', $fields, $menuCode, $attributes, null, $this->theme->getDirName(), $mtime, null);

        return [
            '#migrateMenuStatus'.$recordId => '<div class="text-success">'.Lang::get('rd.dynopages::lang.migrations.migrated').'</div>'
        ];
    }

    ###################################################
    ### END STATIC CONTENT MIGRATION
    ###################################################

    /**
     * Get default language
     * @return string default language
     */
    protected function getDefaultLang()
    {
        if(PluginManager::instance()->exists('RainLab.Translate')){
            $defaultLocale = \RainLab\Translate\Models\Locale::getDefault();
            $defaultLang = $defaultLocale->code ? $defaultLocale->code : 'en';
        }else{
            $defaultLang = config('app.locale') ? config('app.locale') : config('app.fallback_locale');
        }

        return $defaultLang;
    }

    /**
     * Get list of available locales, used in non-static methods
     * @return string default language
     */
    protected function getListAvailableLocales()
    {
        $result = [];
        if(PluginManager::instance()->exists('RainLab.Translate')){
            $result = \RainLab\Translate\Models\Locale::listAvailable();
        }

        return $result;
    }
}

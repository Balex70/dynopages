<?php namespace Rd\DynoPages\Classes;

use Cms;
use Lang;
use Cache;
use Event;
use Config;
use Cms\Classes\Theme;
use ValidationException;
use Cms\Classes\Controller;
use System\Classes\PluginManager;
use Rd\DynoPages\Services\DBService;
use RainLab\Translate\Classes\Translator;

/**
 * The CMS page.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Page extends \Cms\Classes\Page
{
    private static $defaultRecord = null;

    protected static $defaultLang = 'en';
    protected static $listAvailableLocales = [];

    // use private static theme property to be able to use static methods
    private static $theme = '';

    private static $fileNameToSave = null;

    /**
     * @var array The attributes that are mass assignable.
     */
    protected $fillable = [
        'url',
        'layout',
        'title',
        'description',
        'is_hidden',
        'meta_title',
        'meta_description',
        'markup',
        'settings',
        'code'
    ];

    const TABLE = 'rd_dynopages_pages';

    // Used for defining fields that should be saved/updated
    const FIELDS = [
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

    // Used for defining fields that should be loaded to object during FE/BE rendering
    const LOADFIELDS = [
        'fileName' => 'file_name',
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
        'mtime' => 'mtime'
    ];

    
    /**
     * Loads the object from a DB.
     * This method is used in the CMS back-end. It doesn't use any caching.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name. The file name can contain only alphanumeric symbols, dashes and dots.
     * @param boolean $feRender define if object should be loaded for FE|BE
     * 
     * @return mixed Returns a CMS object instance or null if the object wasn't found.
     */
    public static function loadFromDb($theme, $fileName, $feRender = false)
    {
        self::$theme = $theme->getDirName();

        // Get default locale and available locales for static methods
        if(PluginManager::instance()->exists('RainLab.Translate')){
            $defaultLocale = \RainLab\Translate\Models\Locale::getDefault();
            self::$defaultLang = $defaultLocale->code ? $defaultLocale->code : 'en';
            self::$listAvailableLocales = \RainLab\Translate\Models\Locale::listAvailable();
        }else{
            self::$defaultLang = config('app.locale') ? config('app.locale') : config('app.fallback_locale');
        }

        $record = DBService::getRecordByFileName(self::TABLE, $theme->getDirName(), $fileName, self::$defaultLang);
        
        if(!$record){
            return ;
        }

        self::$defaultRecord = $record;

        // I can get needed object by calling static::inTheme($theme) where $theme its theme object
        // $this->model->hydrate($results, $datasource);
        // $result - array of attributes from db,
        // $datasource - actually name of theme (maybe partial, or page etc) ($themeName = $this->getDatasourceName())
        foreach (self::LOADFIELDS as $key => $field) {
            switch ($key) {
                // fill settings from DB
                case 'settings':
                    $settings = json_decode($record->settings, true);
                    if($settings){
                        foreach ($settings as $settingsKey => $value) {
                            $results[strval($fileName)][$settingsKey] = $value;
                        }
                    }
                    break;
                
                case '_PARSER_ERROR_INI':
                    $results[strval($fileName)][$key] = "";
                    break;

                default:
                    $results[strval($fileName)][$key] = $record->$field;
                    break;
            }
        }
        
        $object = static::inTheme($theme)->hydrate($results, $theme->getDirName());

        $result = $object->first();
        
        // Load TranslatableAttributes
        if(count(self::$listAvailableLocales) > 0){
            self::loadTranslatableAttributes($result);
        };

        return $result;
    }

    /**
     * Load TranslatableAttributes with correct data from database
     * @param Rd\DynoPages\Classes\Pages $object
     * 
     * @return void
     */
    public static function loadTranslatableAttributes($object)
    {
        $locales = self::$listAvailableLocales;
        // Unset default locale, no need to fill default language with translatables
        unset($locales[self::$defaultLang]);
        $translator = Translator::instance();
        $translateContext = $translator->getLocale();

        foreach ($locales as $key => $locale) {
            $record = DBService::getRecordByFileName(self::TABLE, self::$theme, self::$defaultRecord->file_name, $key);
            if(!$record) return false;
            
            // Set translatable attributes (no need to set translatableOriginals I think?)
            $settings = $record->settings ? json_decode($record->settings, true) : null;
            
            $object->setAttributeTranslated('title', isset($settings['title']) ? $settings['title'] : null, $key);
            $object->setAttributeTranslated('description', isset($settings['description']) ? $settings['description'] : null, $key);
            $object->setAttributeTranslated('meta_title', isset($settings['meta_title']) ? $settings['meta_title'] : null, $key);
            $object->setAttributeTranslated('meta_description', isset($settings['meta_description']) ? $settings['meta_description'] : null, $key);
        }
        
    }

    /**
     * Delete the model from the database.
     *
     * @return void
     */
    public function dbDelete()
    {
        // Delete object itself form DB within all localized records
        $recordsToDelete = DBService::getRecordIdsByFileName(self::TABLE, self::$theme, $this->fileName);
        foreach ($recordsToDelete as $id) {
            DBService::deleteRecord(self::TABLE, $id);
        }
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return void
     */
    public function dbSave(array $options = null)
    {
        $mtime = time();
        $defaultRecord = isset(self::$defaultRecord) ? self::$defaultRecord : null;

        // Get locales
        $locales = count($this->getListAvailableLocales()) > 0 ? $this->getListAvailableLocales() : [$this->getDefaultLang()];

        $fileNameToSave = '';
        if($defaultRecord){
            // Perform update existing page
            // Get all data for updates
            $dataToUpdate = [];
            $defaultAttributes = $this->getAttributes();
            $fileNameToSave = isset($defaultAttributes['fileName']) ? $defaultAttributes['fileName'] : null;
            foreach ($locales as $key => $value) {
                if($key === $this->getDefaultLang()){
                    // Get correct attributes for default language
                    $dataToUpdate[$key]['url'] = post('settings')['url'];
                    $dataToUpdate[$key]['attributes'] = $defaultAttributes;
                    $settings = $this->getAttribute('settings');
                    $dataToUpdate[$key]['settings'] = $this->fillViewBagFromRLTranslate($settings);
                }else{
                    // Get correct attributes for other languages
                    $dataToUpdate[$key]['url'] = post('RLTranslate')[$key]['settings']['url'];
                    $dataToUpdate[$key]['attributes'] = post('RLTranslate')[$key]['settings']
                                    +['fileName' => $fileNameToSave]
                                    +['layout' => isset($defaultAttributes['layout']) ? $defaultAttributes['layout'] : null]
                                    +['is_hidden' => isset($defaultAttributes['is_hidden']) ? $defaultAttributes['is_hidden'] : null]
                                    +['markup' => isset($defaultAttributes['markup']) ? $defaultAttributes['markup'] : null];
                    $settings = post('RLTranslate')[$key]['settings'];
                    $dataToUpdate[$key]['settings'] = $this->fillViewBagFromRLTranslate($settings);
                }
            }
            
            // Validate data for all languages before actual updating
            foreach ($dataToUpdate as $key => $valueToUpdate) {
                $this->validateBeforeUpdate($valueToUpdate['attributes'], $valueToUpdate['settings'], $valueToUpdate['url'], $defaultRecord, $mtime, $key);
            }

            // Perform update
            foreach ($dataToUpdate as $key => $valueToUpdate) {
                $this->performDBUpdate($valueToUpdate['attributes'], $valueToUpdate['settings'], $valueToUpdate['url'], $defaultRecord, $mtime, $key);
            }

            // Get filename to be able to correctly save it on Page object and render it in BE
            $fileNameToSaveInObject = $fileNameToSave;
        }else{
            // Perform create new page
            $defaultAttributes = $this->getAttributes();
            $fileNameToSave = isset($defaultAttributes['fileName']) ? $defaultAttributes['fileName'] : null;
            foreach ($locales as $key => $value) {
                if($key === $this->getDefaultLang()){
                    // Get correct attributes for default language
                    $dataToCreate[$key]['url'] = post('settings')['url'];
                    $dataToCreate[$key]['attributes'] = $this->getAttributes();
                    $settings = $this->getAttribute('settings');
                    $dataToCreate[$key]['settings'] = $this->fillViewBagFromRLTranslate($settings);
                }else{
                    // Get correct attributes for other languages
                    $dataToCreate[$key]['url'] = post('RLTranslate')[$key]['settings']['url'];
                    $dataToCreate[$key]['attributes'] = post('RLTranslate')[$key]['settings']
                                    +['fileName' => $fileNameToSave]
                                    +['layout' => isset($defaultAttributes['layout']) ? $defaultAttributes['layout'] : null]
                                    +['is_hidden' => isset($defaultAttributes['is_hidden']) ? $defaultAttributes['is_hidden'] : null]
                                    +['markup' => isset($defaultAttributes['markup']) ? $defaultAttributes['markup'] : null];
                    $settings = post('RLTranslate')[$key]['settings'];
                    $dataToCreate[$key]['settings'] = $this->fillViewBagFromRLTranslate($settings);
                }
                
            }

            // Validate data for all languages before actual creating
            foreach ($dataToCreate as $key => $valueToCreate) {
                $this->validateBeforeCreate($valueToCreate['attributes'], $valueToCreate['settings'], $valueToCreate['url'], $mtime, $key);
            }

            // Perform create
            foreach ($dataToCreate as $key => $valueToCreate) {
                $this->performDBCreate($valueToCreate['attributes'], $valueToCreate['settings'], $valueToCreate['url'], $mtime, $key);
            }

            // Get filename to be able to correctly save it on Page object and render it in BE
            $fileNameToSaveInObject = $fileNameToSave;
        }

        // Finish processing on a successful save operation.
        $this->FinishSaveModel($mtime, $fileNameToSaveInObject);
    }

    /**
     * Validate before create new record
     * @param array $attributes Attributes to be saved
     * @param array $settings Settings to be saved in separate field of record
     * @param string $url to be saved
     * @param integer $mtime
     * @param string $lang Language code to be saved
     * 
     * @return void
     */
    public function validateBeforeCreate($attributes, $settings, $url, $mtime, $lang)
    {
        // Check if url default language is exist and not empty, url for other language can be empty
        if($lang == $this->getDefaultLang() && (!$url || $url == '' || $url == "\r\n")){
            throw new ValidationException([
                'fileName' => Lang::get('rd.dynopages::lang.url_required', ['lang' => $lang])
            ]);
        }
        else{
            // Check if record with same url (exclude records with same names) not exist otherwise throw an error
            if(DBService::getDuplicateRecordByUrl(self::TABLE, self::$theme, null, $url, $lang)){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.url_not_unique', ['url' => $url, 'lang' => $lang])
                ]);
            }

            // Check if fileName field is filled
            if(!$attributes['fileName'] || $attributes['fileName'] == '' || $attributes['fileName'] == "\r\n"){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.file_name_required')
                ]);
            }

            // Check if record with same file name not exist for default language otherwise throw an error
            if($lang == $this->getDefaultLang() && DBService::getRecordByFileName(self::TABLE, self::$theme, $attributes['fileName'], $lang)){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.file_name_not_unique', ['fileName' => $attributes['fileName']])
                ]);
            }
        }
    }

    /**
     * Add new record 
     * @param array $attributes Attributes to be saved
     * @param array $settings Settings to be saved in separate field of record
     * @param string $url to be saved
     * @param integer $mtime
     * @param string $lang Language code to be saved
     * 
     * @return void
     */
    public function performDBCreate($attributes, $settings, $url, $mtime, $lang)
    {
        DBService::insertRecord(self::TABLE, self::FIELDS, $attributes['fileName'], $attributes, $settings, self::$theme, $mtime, $lang);
    }

    /**
     * Validate before Update record
     * @param array $attributes Attributes to be updated
     * @param array $settings Settings to be updated in separate field of record
     * @param string $url to be updated
     * @param integer $mtime
     * @param string $lang Language code to be updated
     * 
     * @return void
     */
    public function validateBeforeUpdate($attributes, $settings, $url, $defaultRecord, $mtime, $lang)
    {
        // Get record to update by fileName and lang
        $record = DBService::getRecordByFileName(self::TABLE, self::$theme, $defaultRecord->file_name, $lang);
        
        // Check if url for default language is exist and not empty, url for other language can be empty
        if($lang == $this->getDefaultLang() && (!$url || $url == '' || $url == "\r\n")){
            throw new ValidationException([
                'fileName' => Lang::get('rainlab.pages::lang.menuitem.url_required')
            ]);            
        }
        else{
            // Check if record with same url (exclude records with same ids) not exist otherwise throw an error
            $duplicateRecordByUrl = DBService::getRecordByUrl(self::TABLE, self::$theme, $url, $lang);
            if($record && $duplicateRecordByUrl && $record->id != $duplicateRecordByUrl->id){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.url_not_unique', ['url' => $url, 'lang' => $lang])
                ]);
            }

            // Check if fileName field is filled
            if(!$attributes['fileName'] || $attributes['fileName'] == '' || $attributes['fileName'] == "\r\n"){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.file_name_required')
                ]);
            }

            // Check if record with same file name not exist (exclude same record) otherwise throw an error
            $duplicateRecordByFileName = DBService::getRecordByFileName(self::TABLE, self::$theme, $attributes['fileName'], $lang);
            if($record && $duplicateRecordByFileName && $record->id != $duplicateRecordByFileName->id){ 
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.file_name_not_unique', ['fileName' => $attributes['fileName']])
                ]);
            }
        }
    }

    /**
     * Update record
     * @param array $attributes Attributes to be updated
     * @param array $settings Settings to be updated in separate field of record
     * @param string $url to be updated
     * @param integer $mtime
     * @param string $lang Language code to be updated
     * 
     * @return void
     */
    public function performDBUpdate($attributes, $settings, $url, $defaultRecord, $mtime, $lang)
    {
        // Get record to update by fileName and lang
        $record = DBService::getRecordByFileName(self::TABLE, self::$theme, $defaultRecord->file_name, $lang);

        if ($record) {
            DBService::updateRecord(self::TABLE, self::FIELDS, $record->id, $attributes, $settings, self::$theme, $mtime, $lang);
        }
        else{
            DBService::insertRecord(self::TABLE, self::FIELDS, $attributes['fileName'], $attributes, $settings, self::$theme, $mtime, $lang);
        }
    }

    /**
     * Fill viewBag from post(RLTranslate)
     * @param array $settings Specifies the attributes.
     * 
     * @return array
     */
    protected function fillViewBagFromRLTranslate($settings){
        $fieldsToFill = [
            'localeUrl' => 'url',
            'localeTitle' => 'title',
            'localeDescription' => 'description',
            'localeMeta_title' => 'meta_title',
            'localeMeta_description' => 'meta_description'
        ];

        if(post('RLTranslate') !== null && count(post('RLTranslate')) > 0){
            foreach ($fieldsToFill as $key => $field) {
                foreach (post('RLTranslate') as $locale => $value) {
                    array_set($settings, 'viewBag.'.$key.'.'.$locale, array_get($value, 'settings.'.$field));
                }
            }
        }

        return $settings;
    }

    /**
     * Set active theme
     *
     * @param \Cms\Classes\Theme
     * @return void
     */
    public function setTheme($theme)
    {
        self::$theme = $theme->getDirName();
    }

    /**
     * Finish processing on a successful save operation.
     * @param integer $mtime
     * @param string $fileName Specifies the file name.
     * 
     * @return void
     */
    protected function FinishSaveModel($mtime, $fileName)
    {
        $this->fireModelEvent('saved', false);

        $this->mtime = $mtime;

        $this->syncOriginal();

        $this->attributes['fileName'] = $fileName;
    }

    /**
     * Returns the list of objects from DB in the specified theme.
     * This method is used internally by the system.
     * @param \Cms\Classes\Theme $theme Specifies a parent theme.
     *
     * @return Collection Returns a collection of CMS objects.
     */
    public static function listDbInTheme($theme)
    {
        $result = [];
        // new instance of this Model
        $instance = new static;

        // Override static::inTheme($theme) method
        $instance = static::on($theme->getDirName());

        // Get pages (array of fileNames) listed in theme
        $items = DBService::listPages(self::TABLE, $theme, self::$defaultLang);
        $loadedItems = [];

        // Load objects
        if($items){
            foreach ($items as $item) {
                $loadedItems[] = self::loadFromDb($theme, $item, true);
            }
        }
        $result = $instance->newCollection($loadedItems);
        
        return $result;
    }

    /**
     * Get default language, used in non-static methods
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
     * @return array list of locales
     */
    protected function getListAvailableLocales()
    {
        $result = [];
        if(PluginManager::instance()->exists('RainLab.Translate')){
            $result = \RainLab\Translate\Models\Locale::listAvailable();
        }

        return $result;
    }

    /**
     * Handler for the pages.menuitem.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * with the following elements:
     * - references - a list of the item type reference options. The options are returned in the
     *   ["key"] => "title" format for options that don't have sub-options, and in the format
     *   ["key"] => ["title"=>"Option title", "items"=>[...]] for options that have sub-options. Optional,
     *   required only if the menu item type requires references.
     * - nesting - Boolean value indicating whether the item type supports nested items. Optional,
     *   false if omitted.
     * - dynamicItems - Boolean value indicating whether the item type could generate new menu items.
     *   Optional, false if omitted.
     * - cmsPages - a list of CMS pages (objects of the Cms\Classes\Page class), if the item type requires
     *   a CMS page reference to resolve the item URL.
     * @param string $type Specifies the menu item type
     * @return array Returns an array
     */
    public static function getMenuTypeInfo(string $type)
    {
        $result = [];

        if ($type === 'dyno-cms-page') {
            $theme = Theme::getActiveTheme();
            $pages = self::listDbInTheme($theme, true);
            $references = [];

            foreach ($pages as $page) {
                $references[$page->getBaseFileName()] = $page->title . ' [' . $page->getBaseFileName() . ']';
            }

            $result = [
                'references'   => $references,
                'nesting'      => false,
                'dynamicItems' => false
            ];
        }

        return $result;
    }

    /**
     * Handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     *   The URL should be returned relative to the website root and include the subdirectory, if any.
     *   Use the Url::to() helper to generate the URLs.
     * - isActive - determines whether the menu item is active. Not required for menu item types that
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key.
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     * @param \RainLab\Pages\Classes\MenuItem $item Specifies the menu item.
     * @param string $url Specifies the current page URL, normalized, in lower case
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * The URL is specified relative to the website root, it includes the subdirectory name, if any.
     * @return mixed Returns an array. Returns null if the item cannot be resolved.
     */
    public static function resolveMenuItem($item, string $url, Theme $theme)
    {
        $result = null;
        
        if ($item->type === 'dyno-cms-page') {
            if (!$item->reference) {
                return;
            }

            $page = self::loadFromDb($theme, $item->reference.'.htm', false);
            
            $result = [];
            $result['url'] = $page->url;
            $result['isActive'] = $page->url == $url;
            $result['mtime'] = $page ? $page->mtime : null;
        }

        return $result;
    }

    /**
     * Handler for the backend.richeditor.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * @param string $type Specifies the page link type
     * @return array
     */
    public static function getRichEditorTypeInfo(string $type)
    {
        $result = [];

        if ($type === 'dyno-cms-page') {
            $theme = Theme::getActiveTheme();
            $pages = self::listDbInTheme($theme, true);

            foreach ($pages as $page) {
                $url = self::url($page->getBaseFileName());
                $result[$url] = $page->title;
            }
        }

        return $result;
    }
}

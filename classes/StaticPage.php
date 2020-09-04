<?php namespace Rd\DynoPages\Classes;

use Cms;
use Lang;
use Cache;
use Event;
use Config;
use Cms\Classes\Theme;
use ValidationException;
use October\Rain\Support\Str;
use October\Rain\Router\Helper as RouterHelper;
use System\Classes\PluginManager;
use Rd\DynoPages\Classes\PageList;
use Rd\DynoPages\Services\DBService;
use RainLab\Translate\Classes\Translator;

/**
 * The static page class.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class StaticPage extends \RainLab\Pages\Classes\Page
{
    protected static $dynoMenuTreeCache = null;

    private static $defaultRecord = null;

    protected static $defaultLang = 'en';
    protected static $listAvailableLocales = [];

    // use private static theme property to be able to use static methods
    private static $theme = '';

    private static $fileNameToSave = null;

    const TABLE = 'rd_dynopages_static_pages';

    // Used for defining fields that should be saved/updated
    const FIELDS = [
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

    // Used for defining fields that should be loaded to object during FE/BE rendering
    const LOADFIELDS = [
        'fileName' => 'file_name',
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

        $translator = Translator::instance();
        $translateContext = $translator->getLocale();

        $localRecord = DBService::getRecordByFileName(self::TABLE, $theme->getDirName(), $fileName, $translateContext);
        if($feRender && self::$defaultLang != $translateContext){
            if(!$localRecord){
                return null;
            }
        }

        self::$defaultRecord = $record;

        // I can get needed object by calling static::inTheme($theme) where $theme its theme object
        // $this->model->hydrate($results, $datasource);
        // $result - array of attributes from db,
        // $datasource - actually name of theme (maybe partial, or page etc) ($themeName = $this->getDatasourceName())
        foreach (self::LOADFIELDS as $key => $field) {
            switch ($key) {
                // Fill settings from DB
                case 'settings':
                    $settings = json_decode($record->settings, true);
                    if($settings){
                        foreach ($settings as $settingsKey => $value) {
                            $results[strval($fileName)][$settingsKey] = $value;
                            // Fill object's viewBag from settings
                            $allowedFields = [
                                'title',
                                'url',
                                'layout',
                                'is_hidden',
                                'navigation_hidden',
                                'meta_title',
                                'meta_description',
                                'localeUrl'
                            ];
                            
                            if(in_array($settingsKey, $allowedFields)){
                                $results[strval($fileName)]['viewBag'][$settingsKey] = $value."\r\n";
                            }
                        }
                    }
                    break;

                // fill placeholders from DB
                case 'placeholders':
                    $placeholders = json_decode($record->placeholders, true);
                    break;
                
                case '_PARSER_ERROR_INI':
                    $results[strval($fileName)][$key] = "";
                    break;

                default:
                    $results[strval($fileName)][$key] = $record->$field;
                    $allowedFields = [
                        'title',
                        'url',
                        'layout',
                        'is_hidden',
                        'navigation_hidden',
                        'meta_title',
                        'meta_description',
                        'localeUrl'
                    ];

                    // Actually not sure if content property is needed for FE before hydrate at all
                    if(in_array($key, $allowedFields) && $feRender){
                        if(!isset($results[strval($fileName)]['content'])){
                            $results[strval($fileName)]['content'] = "[viewBag]\n";
                        }else{
                            $content = $results[strval($fileName)]['content'];
                            $valueToAdd = $key.' = '.$record->$field."\n";
                            unset($results[strval($fileName)]['content']);
                            $results[strval($fileName)]['content'] = $content.$valueToAdd;
                        }
                    }
                    
                    break;
            }
        }

        if($feRender){
            $results[strval($fileName)]['content'] = $results[strval($fileName)]['content']."==\n".$record->{'code'};
        }

        $object = static::inTheme($theme)->hydrate($results, $theme->getDirName());

        $result = $object->first();

        // Load TranslatableAttributes
        if(count(self::$listAvailableLocales) > 0){
            self::loadTranslatableAttributes($result, $feRender);
        };

        return $result;
    }

    /**
     * Load TranslatableAttributes with correct data from database
     * @param Rd\DynoPages\Classes\StaticPages $object
     * 
     * @return void
     */
    public static function loadTranslatableAttributes($object, $feRender)
    {
        $locales = self::$listAvailableLocales;
        // Unset default locale, no need to fill default language with translatables
        unset($locales[self::$defaultLang]);
        $translator = Translator::instance();
        $translateContext = $translator->getLocale();

        foreach ($locales as $key => $locale) {
            $record = DBService::getRecordByFileName(self::TABLE, self::$theme, self::$defaultRecord->file_name, $key);
            if(!$record) return false;
            if($feRender && $translateContext != self::$defaultLang){
                foreach (self::LOADFIELDS as $contentKey => $field) {
                    $allowedFields = [
                        'title',
                        'url',
                        'layout',
                        'is_hidden',
                        'navigation_hidden',
                        'meta_title',
                        'meta_description',
                        'localeUrl',
                        'placeholders'
                    ];
                    if(in_array($contentKey, $allowedFields) && $feRender){
                        if(!isset($result['content'])){
                            $result['content'] = "[viewBag]\n";
                        }else{
                            $content = $result['content'];
                            if($contentKey == 'placeholders'){
                                $valueToAdd = "==\n".$contentKey.' = '.$record->$field."\n==\n";
                            }else{
                                $valueToAdd = $contentKey.' = '.$record->$field."\n";
                            }
                            unset($result['content']);
                            $result['content'] = $content.$valueToAdd;
                        }
                    }
                }

                // Set translatable attribute content
                $object->setAttributeTranslated('content', $result["content"], $key);
            }
            
            $result['markup'] = $record->markup;
            $result['viewBag'] = $record->settings ? json_decode($record->settings, true) : null;
            $result['placeholders'] = $record->placeholders ? json_decode($record->placeholders, true) : null;
            $result['code'] = self::getMyPlaceholdersAttribute($result['placeholders']);
            
            // Set translatable attributes (no need to set translatableOriginals I think?)
            $object->setAttributeTranslated('markup', $result['markup'], $key);
            $object->setAttributeTranslated('viewBag', $result['viewBag'], $key);
            $object->setAttributeTranslated('placeholders', $result['placeholders'], $key);
            $object->setAttributeTranslated('code', self::getMyPlaceholdersAttribute($result['placeholders']), $key);

            // Override viewbag so it can be correctly used in TranslatableCmsObject->mergeViewBagAttributes method
            // this implementation allow us to fill viewBag with translated data
            // we can't use set TranslatableCmsObject->translatableViewBag property because its protected
            if($feRender && $translateContext != self::$defaultLang && $translateContext == $key){
                $object->viewBag = array_merge(
                    $object->viewBag,
                    $result['viewBag']
                );
            }
        }
        
    }

    /**
     * Takes an array of placeholder data (key: code, value: content) and renders
     * it as a single string of Twig markup against the "code" attribute.
     * @param array  $value
     * @return void
     */
    public static function getMyPlaceholdersAttribute($value)
    {
        if (!is_array($value)) {
            return;
        }

        $placeholders = $value;
        $result = '';

        foreach ($placeholders as $code => $content) {
            if (!strlen($content)) {
                continue;
            }

            $result .= '{% put '.$code.' %}'.PHP_EOL;
            $result .= $content.PHP_EOL;
            $result .= '{% endput %}'.PHP_EOL;
            $result .= PHP_EOL;
        }

        return trim($result);
    }

    /**
     * Trigger delete.
     *
     * @return void
     */
    public function delete()
    {
        $this->dbDelete();
        $this->removeFromMeta();
    }

    /**
     * Delete the model from the database.
     *
     * @return void
     */
    public function dbDelete()
    {
        // Get all pages within theme
        $items = DBService::listPages(self::TABLE, $this->theme, $this->getDefaultLang());

        // Get static pages menu array
        $pageList = new PageList($this->theme);
        $configArray = $pageList->getPagesConfigFromDB();
        
        // Get array of elements to delete
        $deleteArray = $this->getSubTreeConfArray($configArray['static-pages'], $this->fileName);

        // Cascade delete (delete all subpages of given object)
        $this->cascadeDelete($deleteArray);

        // Delete object itself form DB within all localized records
        $recordsToDelete = DBService::getRecordIdsByFileName(self::TABLE, self::$theme, $this->fileName);
        foreach ($recordsToDelete as $id) {
            DBService::deleteRecord(self::TABLE, $id);
        }
        Event::fire('dynopages.staticPage.afterDelete', [$this->fileName, $this->layout]);
    }

    /**
     * Get array of pages filenames which should be deleted
     *
     * @return array
     */
    protected function getSubTreeConfArray($array, $needle){
        foreach($array as $key => $value){
            if($key == $needle){
                return $value;
            }elseif(is_array($value) && !empty($value)){
                // Break recursive loop
                $r = $this->getSubTreeConfArray($value, $needle);
                if(!empty($r)){
                    return $r;
                }
            }
        }
        return [];
    }

    /**
     * Delete sub pages according to array of sub pages
     *
     * @return array
     */
    protected function cascadeDelete($array){
        foreach($array as $key => $value){
            // Get correct records to delete
            $recordsToDelete = DBService::getRecordIdsByFileName(self::TABLE, self::$theme, $key);
            foreach ($recordsToDelete as $id) {
                DBService::deleteRecord(self::TABLE, $id);
            }
            
            if(is_array($value)){
                $this->cascadeDelete($value);
            }
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
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $settings = $this->getAttribute('settings');
        $mtime = time();
        $defaultRecord = isset(self::$defaultRecord) ? self::$defaultRecord : null;

        // Get locales
        $locales = count($this->getListAvailableLocales()) > 0 ? $this->getListAvailableLocales() : [$this->getDefaultLang()];

        if($defaultRecord){
            // Perform update existing page
            foreach ($locales as $key => $value) {
                if($key === $this->getDefaultLang()){
                    // Get correct attributes for default language
                    $dataToUpdate[$key]['url'] = $this->url;
                    $dataToUpdate[$key]['attributes'] = $this->getAttributes();
                    $dataToUpdate[$key]['settings'] = $this->getAttribute('settings');
                    $dataToUpdate[$key]['settings']['url'] = post('RLTranslate')[$key]['viewBag']['url'];
                    $dataToUpdate[$key]['settings']['title'] = post('RLTranslate')[$key]['viewBag']['title'];
                    $dataToUpdate[$key]['settings']['layout'] = $this->getAttribute('settings')['viewBag']['layout'];
                }else{
                    // Get correct attributes for other languages
                    $attributes = $this->getTranslateAttributes($key);
                    if(isset(post('RLTranslate')[$key]['placeholders'])){
                        $attributes['placeholders'] = post('RLTranslate')[$key]['placeholders'];
                    }
                    
                    $dataToUpdate[$key]['url'] = post('RLTranslate')[$key]['viewBag']['url'];
                    array_set($attributes, 'viewBag.url', post('RLTranslate')[$key]['viewBag']['url']);
                    $dataToUpdate[$key]['attributes'] = $attributes;
                    $dataToUpdate[$key]['settings'] = post('RLTranslate')[$key]['viewBag'];
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
        }else{
            // Perform create new page
            foreach ($locales as $key => $value) {
                if($key === $this->getDefaultLang()){
                    // Get correct attributes for default language
                    $dataToCreate[$key]['url'] = $this->url;
                    $dataToCreate[$key]['attributes'] = $this->getAttributes();
                    $dataToCreate[$key]['settings'] = $this->getAttribute('settings');
                    $dataToCreate[$key]['settings']['url'] = post('RLTranslate')[$key]['viewBag']['url'];
                    $dataToCreate[$key]['settings']['title'] = post('RLTranslate')[$key]['viewBag']['title'];
                }else{
                    // Get correct attributes for other languages
                    $attributes = $this->getTranslateAttributes($key);

                    $dataToCreate[$key]['url'] = post('RLTranslate')[$key]['viewBag']['url'];
                    array_set($attributes, 'viewBag.url', post('RLTranslate')[$key]['viewBag']['url']);
                    $dataToCreate[$key]['attributes'] = $attributes;
                    $dataToCreate[$key]['settings'] = post('RLTranslate')[$key]['viewBag'];
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
        }

        // Finish processing on a successful save operation.
        $this->FinishSaveModel($mtime);
    }

    /**
     * Validate before add new record
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
        if((!$url || $url == '' || $url == "\r\n")){
            // Check if url default language is exist and not empty, url for other language can be empty
            if($lang == $this->getDefaultLang()){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.url_required', ['lang' => $lang])
                ]);
            }
        }else{
            // Check if record with same url (exclude records with same names) not exist otherwise throw an error
            $duplicateUrlRecord = DBService::getDuplicateRecordByUrl(self::TABLE, self::$theme, null, $url, $lang);
            if($duplicateUrlRecord){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.url_not_unique', ['url' => $url, 'lang' => $lang])
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
        // Use one file name for all records of object to be saved
        if(!self::$fileNameToSave){
            self::$fileNameToSave = $this->generateFilename($attributes, self::$theme, null, $lang);
        }

        $this->beforeStaticPageCreate($this->getAttributes(), self::$theme, self::$fileNameToSave, $lang);
        $attributes = $this->fillAttributesFromViewBag($attributes, self::$theme, self::$fileNameToSave, $lang);
        DBService::insertRecord(self::TABLE, self::FIELDS, self::$fileNameToSave, $attributes, $settings, self::$theme, $mtime, $lang);
        $this->afterCreate();
    }

    /**
     * Validate before update record
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
        $defaultFileName = $defaultRecord->file_name;

        if(!$url || $url == '' || $url == "\r\n"){
            // Check if url for default language is exist and not empty, url for other language can be empty
            if($lang == $this->getDefaultLang()){
                throw new ValidationException([
                    'fileName' => Lang::get('rainlab.pages::lang.menuitem.url_required')
                ]);            
            }    
        }else{
            // Check if record with same url (exclude records with same names) not exist otherwise throw an error
            if($defaultFileName){
                $duplicateUrlRecord = DBService::getDuplicateRecordByUrl(self::TABLE, self::$theme, $defaultFileName, $url, $lang);
                if($duplicateUrlRecord){
                    throw new ValidationException([
                        'fileName' => Lang::get('rd.dynopages::lang.url_not_unique', ['url' => $url, 'lang' => $lang])
                    ]);
                }
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
        $defaultFileName = $defaultRecord->file_name;
        
        if ($record) {
            $attributes = $this->fillAttributesFromViewBag($attributes, self::$theme, $defaultFileName, $lang);
            DBService::updateRecord(self::TABLE, self::FIELDS, $record->id, $attributes, $settings, self::$theme, $mtime, $lang);
        }
        else{
            $this->beforeStaticPageCreate($this->getAttributes(), self::$theme, $defaultFileName, $lang);
            $attributes = $this->fillAttributesFromViewBag($attributes, self::$theme, $defaultFileName, $lang);
            DBService::insertRecord(self::TABLE, self::FIELDS, $defaultFileName, $attributes, $settings, self::$theme, $mtime, $lang);
            $this->afterCreate();
        }
    }

    /**
     * Fill attributes from viewBag
     * @param array $attributes Specifies the attributes.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name. The file name can contain only alphanumeric symbols, dashes and dots.
     * @param string $lang Language code
     * 
     * @return array
     */
    protected function fillAttributesFromViewBag($attributes, $theme, $fileName, $lang){
        $fieldsToFill = [
            'fileName',
            'title',
            'url',
            'layout',
            'is_hidden',
            'navigation_hidden',
            'meta_title',
            'meta_description',
            'localeUrl'
        ];

        foreach ($fieldsToFill as $field) {
            if($field == 'fileName'){
                $attributes[$field] = $fileName;
            }else{
                $attributes[$field] = array_get($attributes, 'viewBag.'.$field);
            }
        }

        return $attributes;
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
     * 
     * @return void
     */
    protected function FinishSaveModel($mtime)
    {
        $this->fireModelEvent('saved', false);

        $this->mtime = $mtime;

        $this->syncOriginal();
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

        // Get default locale and available locales for static methods
        if(PluginManager::instance()->exists('RainLab.Translate')){
            $defaultLocale = \RainLab\Translate\Models\Locale::getDefault();
            self::$defaultLang = $defaultLocale->code ? $defaultLocale->code : 'en';
            self::$listAvailableLocales = \RainLab\Translate\Models\Locale::listAvailable();
        }else{
            self::$defaultLang = config('app.locale') ? config('app.locale') : config('app.fallback_locale');
        }

        // Get pages (array of fileNames) listed in theme
        $items = DBService::listPages(self::TABLE, $theme, self::$defaultLang);
        $loadedItems = [];

        // I guess get actual object of page and generate result array
        if($items){
            foreach ($items as $item) {
                if($loadedItem = self::loadFromDb($theme, $item, true)){
                    $loadedItems[$item] = $loadedItem;
                }
            }
        }

        $result = $instance->newCollection($loadedItems);
        
        return $result;
    }

    /**
     * Generate a file name based on the URL on default language, use default language fileName for other languages
     * @param array $attributes Attributes of object
     * @param @param \Cms\Classes\Theme $theme Specifies the theme.
     * @param string $fileName of record with default language
     * @param string $lang language
     * 
     * @return string
     */
    protected function generateFilename($attributes, $theme, $fileName, $lang)
    {
        // If not default language use default record filename
        if($fileName){
            return $fileName;
        }

        $fileName = trim(str_replace('/', '-', $attributes['viewBag']['url']), '-');
        
        if (strlen($fileName) > 200) {
            $fileName = substr($fileName, 0, 200);
        }

        if (!strlen($fileName)) {
            $fileName = 'index';
        }
        $curName = $fileName;
        $counter = 2;

        while (DBService::getDuplicateRecordByFileName(self::TABLE, $theme, $curName, $lang)) {
            $curName = $fileName.'-'.$counter;
            $counter++;
        }

        return $curName;
    }

    /**
     * Triggered before a new object is saved.
     */
    public function beforeStaticPageCreate($attributes, $theme, $fileName, $lang)
    {
        $this->fileName = $this->generateFilename($attributes, $theme, $fileName, $lang);
    }

    /**
     * Triggered after a new object is saved.
     */
    public function afterCreate()
    {
        $this->appendToMeta();
    }

    /**
     * Adds this page to the meta index.
     */
    protected function appendToMeta()
    {
        $pageList = new PageList($this->theme);
        $pageList->appendPage($this);
    }

    /**
     * Removes this page to the meta index.
     */
    protected function removeFromMeta()
    {
        $pageList = new PageList($this->theme);
        $pageList->removeSubtree($this);
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

    //
    // Static Menu API
    //

        /**
     * Returns a cache key for this record.
     */
    protected static function getMenuCacheKey($theme)
    {
        $key = crc32($theme->getPath()).'dyno-static-page-menu';
        Event::fire('pages.page.getMenuCacheKey', [&$key]);
        return $key;
    }

    /**
     * Returns whether the specified URLs are equal.
     */
    protected static function urlsAreEqual($url, $other)
    {
        return rawurldecode($url) === rawurldecode($other);
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
     * - cmsPages - a list of CMS pages (objects of the Cms\Classes\Page class), if the item type requires a CMS page reference to
     *   resolve the item URL.
     * @param string $type Specifies the menu item type
     * @return array Returns an array
     */
    public static function getMenuTypeInfo($type)
    {
        if ($type == 'all-dyno-static-pages') {
            return [
                'dynamicItems' => true
            ];
        }

        if ($type == 'dyno-static-page') {
            return [
                'references'   => self::listStaticPageMenuOptions(),
                'nesting'      => true,
                'dynamicItems' => true
            ];
        }
    }

    /**
     * Handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     *   The URL should be returned relative to the website root and include the subdirectory, if any.
     *   Use the Cms::url() helper to generate the URLs.
     * - isActive - determines whether the menu item is active. Not required for menu item types that
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key.
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     * @param \RainLab\Pages\Classes\MenuItem $item Specifies the menu item.
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * @param string $url Specifies the current page URL, normalized, in lower case
     * The URL is specified relative to the website root, it includes the subdirectory name, if any.
     * @return mixed Returns an array. Returns null if the item cannot be resolved.
     */
    public static function resolveMenuItem($item, $url, $theme)
    {
        $tree = self::buildMenuTree($theme);

        if ($item->type == 'dyno-static-page' && !isset($tree[$item->reference])) {
            return;
        }

        $result = [];
        
        if ($item->type == 'dyno-static-page') {
            $pageInfo = $tree[$item->reference];
            $result['url'] = Cms::url(trim($pageInfo['url']));
            $result['pageTitle'] = $pageInfo['title'];
            $result['mtime'] = $pageInfo['mtime'];
            $result['isActive'] = self::urlsAreEqual($result['url'], $url);
        }

        if ($item->nesting || $item->type == 'all-dyno-static-pages') {
            $iterator = function($items) use (&$iterator, &$tree, $url) {
                $branch = [];

                foreach ($items as $itemName) {
                    if (!isset($tree[$itemName])) {
                        continue;
                    }

                    $itemInfo = $tree[$itemName];

                    if ($itemInfo['navigation_hidden']) {
                        continue;
                    }
                    
                    $branchItem = [];
                    $branchItem['url'] = Cms::url($itemInfo['url']);
                    // No need to use pageTitle, as by default it uses title of page for all-static-pages (in our case all-dyno-static-page)
                    $branchItem['isActive'] = self::urlsAreEqual($branchItem['url'], $url);
                    $branchItem['title'] = $itemInfo['title'];
                    $branchItem['mtime'] = $itemInfo['mtime'];

                    if ($itemInfo['items']) {
                        $branchItem['items'] = $iterator($itemInfo['items']);
                    }

                    $branch[] = $branchItem;
                }

                return $branch;
            };

            $result['items'] = $iterator($item->type == 'dyno-static-page' ? $pageInfo['items'] : $tree['--root-pages--']);
        }

        return $result;
    }

    /**
     * Handler for the backend.richeditor.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * @param string $type Specifies the page link type
     * @return array
     */
    public static function getRichEditorTypeInfo($type)
    {
        if ($type == 'dyno-static-page') {

            $pages = self::listStaticPageMenuOptions();

            $iterator = function($pages) use (&$iterator) {
                $result = [];
                foreach ($pages as $pageFile => $page) {
                    $url = self::url($pageFile);

                    if (is_array($page)) {
                        $result[$url] = [
                            'title' => array_get($page, 'title', []),
                            'links' => $iterator(array_get($page, 'items', []))
                        ];
                    }
                    else {
                        $result[$url] = $page;
                    }
                }

                return $result;
            };

            return $iterator($pages);
        }

        return [];
    }

    /**
     * Builds and caches a menu item tree.
     * This method is used internally for menu items and breadcrumbs.
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * @return array Returns an array containing the page information
     */
    public static function buildMenuTree($theme)
    {
        if (self::$dynoMenuTreeCache !== null) {
            return self::$dynoMenuTreeCache;
        }

        $key = self::getMenuCacheKey($theme);
        
        
        $cached = Cache::get($key, false);
        $unserialized = $cached ? @unserialize($cached) : false;

        if ($unserialized !== false) {
            return self::$dynoMenuTreeCache = $unserialized;
        }

        $menuTree = [
            '--root-pages--' => []
        ];

        $iterator = function($items, $parent, $level) use (&$menuTree, &$iterator) {
            $result = [];

            foreach ($items as $item) {
                $viewBag = $item->page->viewBag;
                $pageCode = $item->page->getBaseFileName();
                $pageUrl = Str::lower(RouterHelper::normalizeUrl(array_get($viewBag, 'url')));

                $itemData = [
                    'url'    => $pageUrl,
                    'title'  => array_get($viewBag, 'title'),
                    'mtime'  => $item->page->mtime,
                    'items'  => $iterator($item->subpages, $pageCode, $level+1),
                    'parent' => $parent,
                    'navigation_hidden' => array_get($viewBag, 'navigation_hidden')
                ];

                if ($level == 0) {
                    $menuTree['--root-pages--'][] = $pageCode;
                }

                $result[] = $pageCode;
                $menuTree[$pageCode] = $itemData;
            }

            return $result;
        };

        $pageList = new PageList($theme);
        $iterator($pageList->getStaticPageTree(), null, 0);

        self::$dynoMenuTreeCache = $menuTree;
        $expiresAt = now()->addMinutes(Config::get('cms.parsedPageCacheTTL', 10));
        Cache::put($key, serialize($menuTree), $expiresAt);
        
        return self::$dynoMenuTreeCache;
    }

    /**
     * Returns a list of options for the Reference drop-down menu in the
     * menu item configuration form, when the Static Page item type is selected.
     * @return array Returns an array
     */
    protected static function listStaticPageMenuOptions()
    {
        $theme = Theme::getEditTheme();

        $pageList = new PageList($theme);
        $pageTree = $pageList->getStaticPageTree(true);
        
        $iterator = function($pages) use (&$iterator) {
            $result = [];

            foreach ($pages as $pageInfo) {
                $pageName = $pageInfo->page->getViewBag()->property('title');
                $fileName = $pageInfo->page->getBaseFileName();

                if (!$pageInfo->subpages) {
                    $result[$fileName] = $pageName;
                }
                else {
                    $result[$fileName] = [
                        'title' => $pageName,
                        'items' => $iterator($pageInfo->subpages)
                    ];
                }
            }

            return $result;
        };

        return $iterator($pageTree);
    }
}

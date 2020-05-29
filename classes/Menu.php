<?php namespace Rd\DynoPages\Classes;

use Url;
use Lang;
use Event;
use Request;
use ValidationException;
use October\Rain\Support\Str;
use Rd\DynoPages\Services\DBService;
use RainLab\Pages\Classes\MenuItemReference;

/**
 * Represents a front-end menu.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Menu extends \RainLab\Pages\Classes\Menu
{
    private static $defaultRecord = null;

    // use private static theme property to be able to use static methods
    private static $theme = '';

    const TABLE = 'rd_dynopages_static_menu';
    const FIELDS = [
        'file_name' => 'code',
        'name' => 'name',
        'content' => 'content',
        'theme' => 'theme',
        'mtime' => 'mtime'
    ];

    const LOADFIELDS = [
        'code' => 'file_name',
        'fileName' => 'file_name',
        'name' => 'name',
        'content' => 'content',
        'mtime' => 'mtime'
    ];

    /**
     * Loads the object from a DB.
     * This method is used in the CMS back-end. It doesn't use any caching.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name, with the extension. The file name can contain only alphanumeric symbols, dashes and dots.
     * @param boolean $feRender define if object should be loaded for FE|BE
     *
     * @return mixed Returns a CMS object instance or null if the object wasn't found.
     */
    public static function loadFromDb($theme, $fileName, $feRender = false)
    {
        self::$theme = $theme->getDirName();

        $record = DBService::getRecordByFileName(self::TABLE, $theme->getDirName(), $fileName, null);
        
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
                case 'content':
                    $results[strval($fileName)][$key] = $record->content;
                    break;
                
                default:
                    $results[strval($fileName)][$key] = $record->$field;
                   
                    break;
            }
        }

        $object = static::inTheme($theme)->hydrate($results, $theme->getDirName());

        $result = $object->first();
        
        return $result;
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

        // Get array of menus listed in theme (actually taken from DB)
        $items = DBService::listPages(self::TABLE, $theme, null);
        $loadedItems = [];

        // Load objects
        if($items){
            foreach ($items as $item) {
                $loadedItems[$item] = self::loadFromDb($theme, $item, false);
            }
        }

        $result = $instance->newCollection($loadedItems);
        
        return $result;
    }

    /**
     * Delete the model from the database.
     *
     * @return void
     */
    public function delete()
    {
        // Delete menu record from DB
        $recordToDelete = DBService::getRecordByFileName(self::TABLE, self::$theme, $this->fileName, null);
        DBService::deleteRecord(self::TABLE, $recordToDelete->id);
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
     *
     * @param integer $mtime
     * @return void
     */
    protected function FinishSaveModel($mtime)
    {
        $this->fireModelEvent('saved', false);

        $this->mtime = $mtime;

        $this->syncOriginal();
    }

    /**
     * Save the menu item to the database.
     *
     * @param array $options
     * @return void
     */
    public function dbSave(array $options = null)
    {
        $mtime = time();
        $defaultRecord = isset(self::$defaultRecord) ? self::$defaultRecord : null;

        if($defaultRecord){
            // Perform update existing menu item
            // $this->beforeSave();
            $this->content = $this->renderContent();
            
            // Get correct attributes
            $attributes = $this->getAttributes();

            $attributes = [
                'code' => $attributes['code'],
                'fileName' => $attributes['code'],
                'name' => $attributes['name'],
                'content' => $attributes['content']
            ];

            $this->performDBUpdate($attributes, $attributes['code'], $defaultRecord, $mtime);
            
        }else{
            // Perform add new item
            // $this->beforeSave();
            $this->content = $this->renderContent();

            // Get correct attributes
            $attributes = $this->getAttributes();

            $attributes = [
                'code' => $attributes['code'],
                'fileName' => $attributes['code'],
                'name' => $attributes['name'],
                'content' => $attributes['content']
            ];

            $this->performDBCreate($attributes, $attributes['code'], $mtime);
        }

        // Finish processing on a successful save operation.
        $this->FinishSaveModel($mtime);
    }

    /**
     * Add new record
     * @param array $attributes Attributes to be saved
     * @param string $code code/fileName of record to be saved
     * @param integer $mtime
     *
     * @return void
     */
    public function performDBCreate($attributes, $code, $mtime)
    {
        // Check if code is exist and not empty
        if(!$code || $code == ''){
            throw new ValidationException([
                'fileName' => Lang::get('rainlab.pages::lang.menu.code_required')
            ]);
        }
        else{
            // Check if record with same fileName exist
            $duplicateFileNameRecord = DBService::getDuplicateRecordByFileName(self::TABLE, self::$theme, $code, null);

            if($duplicateFileNameRecord){
                throw new ValidationException([
                    'fileName' => Lang::get('rd.dynopages::lang.code_not_unique', ['code' => $code])
                ]);
            }

            DBService::insertRecord(self::TABLE, self::FIELDS, $code, $attributes, null, self::$theme, $mtime, null);
        }
    }

    /**
     * Update record 
     * @param array $attributes Attributes to be updated
     * @param string $code code/fileName of record to be updated
     * @param mixed $defaultRecord stdClass object, default record for updated object
     * @param integer $mtime
     * @return void
     */
    public function performDBUpdate($attributes, $code, $defaultRecord, $mtime)
    {
        // Check if code is exist and not empty
        if(!$code || $code == ''){
            throw new ValidationException([
                'fileName' => Lang::get('rainlab.pages::lang.menu.code_required')
            ]);
        }
        else{
            // Check if record with same fileName exist (exclude updated record)
            if($defaultRecord->file_name){
                $duplicateFileNameRecord = DBService::getDuplicateRecordByFileName(self::TABLE, self::$theme, $code, null);

                if($duplicateFileNameRecord && ($duplicateFileNameRecord->id != $defaultRecord->id)){
                    throw new ValidationException([
                        'fileName' => Lang::get('rd.dynopages::lang.code_not_unique', ['code' => $code])
                    ]);
                }
            }

            if ($defaultRecord) {
                DBService::updateRecord(self::TABLE, self::FIELDS, $defaultRecord->id, $attributes, null, self::$theme, $mtime, null);
            }
        }
    }

    /**
     * Returns the menu item references.
     * This function is used on the front-end.
     * @param Cms\Classes\Page $page The current page object.
     * @return array Returns an array of the \RainLab\Pages\Classes\MenuItemReference objects.
     */
    public function generateReferences($page)
    {
        $currentUrl = Request::path();

        if (!strlen($currentUrl)) {
            $currentUrl = '/';
        }

        $currentUrl = Str::lower(Url::to($currentUrl));
        
        $activeMenuItem = $page->activeMenuItem ?: false;
        $iterator = function($items) use ($currentUrl, &$iterator, $activeMenuItem) {
            $result = [];

            foreach ($items as $item) {
                $parentReference = new MenuItemReference;
                $parentReference->title = $item->title;
                $parentReference->code = $item->code;
                $parentReference->viewBag = $item->viewBag;

                /*
                 * If the item type is URL, assign the reference the item's URL and compare the current URL with the item URL
                 * to determine whether the item is active.
                 */
                if ($item->type == 'url') {
                    $parentReference->url = $item->url;
                    $parentReference->isActive = $currentUrl == Str::lower(Url::to($item->url)) || $activeMenuItem === $item->code;
                }
                else {
                    /*
                     * If the item type is not URL, use the API to request the item type's provider to
                     * return the item URL, subitems and determine whether the item is active.
                     */
                    $apiResult = Event::fire('pages.menuitem.resolveItem', [$item->type, $item, $currentUrl, $this->theme]);

                    $emptyApiResult = true;
                    if (is_array($apiResult)) {
                        foreach ($apiResult as $itemInfo) {
                            if (!is_array($itemInfo)) {
                                continue;
                            }
                            $emptyApiResult = false;

                            if (!$item->replace && isset($itemInfo['url'])) {
                                $parentReference->url = $itemInfo['url'];
                                $parentReference->pageTitle = $itemInfo['pageTitle'];
                                $parentReference->isActive = $currentUrl == Str::lower(Url::to($itemInfo['url'])) || $activeMenuItem === $item->code;
                            }

                            if (isset($itemInfo['items'])) {
                                $itemIterator = function($items) use (&$itemIterator, $parentReference, $currentUrl) {
                                    $result = [];

                                    foreach ($items as $item) {
                                        $reference = new MenuItemReference;
                                        $reference->title = isset($item['title']) ? $item['title'] : '--no title--';
                                        $reference->url = isset($item['url']) ? $item['url'] : '#';
                                        $reference->isActive = isset($item['url']) ? $currentUrl == Str::lower(Url::to($item['url'])) : false;
                                        $reference->viewBag = isset($item['viewBag']) ? $item['viewBag'] : [];
                                        $reference->code = isset($item['code']) ? $item['code'] : null;

                                        // No need to use pageTitle, as by default it uses title of page for all-static-pages (in our case all-dyno-static-page)
                                        if (!strlen($parentReference->url)) {
                                            $parentReference->url = $reference->url;
                                            $parentReference->isActive = $reference->isActive;
                                        }

                                        if (isset($item['items'])) {
                                            $reference->items = $itemIterator($item['items']);
                                        }

                                        $result[] = $reference;
                                    }

                                    return $result;
                                };

                                $parentReference->items = $itemIterator($itemInfo['items']);
                            }
                        }
                    }

                    // If page doesn't exist in system, remove it from menu
                    if($emptyApiResult){
                        continue;
                    }
                }

                
                if ($item->items) {
                    $parentReference->items = $iterator($item->items);
                }

                if (!$item->replace) {
                    $result[] = $parentReference;
                }
                else {
                    foreach ($parentReference->items as $subItem) {
                        $result[] = $subItem;
                    }
                }
            }

            return $result;
        };

        $items = $iterator($this->items);

        /*
         * Populate the isChildActive property
         */
        $hasActiveChild = function($items) use (&$hasActiveChild) {
            foreach ($items as $item) {
                if ($item->isActive) {
                    return true;
                }

                $result = $hasActiveChild($item->items);
                if ($result) {
                    return $result;
                }
            }
        };

        $iterator = function($items) use (&$iterator, &$hasActiveChild) {
            foreach ($items as $item) {
                $item->isChildActive = $hasActiveChild($item->items);

                $iterator($item->items);
            }
        };

        $iterator($items);

        /*
         * @event pages.menu.referencesGenerated
         * Provides opportunity to dynamically change menu entries right after reference generation.
         *
         * For example you can use it to filter menu entries for user groups from RainLab.User
         * Before doing so you have to add custom field 'group' to menu viewBag using backend.form.extendFields event
         * where the group can be selected by the user. See how to do this here:
         * https://octobercms.com/docs/backend/forms#extend-form-fields
         *
         * Parameter provided is `$items` - a collection of the MenuItemReference objects passed by reference
         *
         * For example to hide entries where group is not 'registered' you can use the following code. It can
         * be used to show different menus for different user groups.
         *
         * Event::listen('pages.menu.referencesGenerated', function (&$items) {
         *     $iterator = function ($menuItems) use (&$iterator, $clusterRepository) {
         *         $result = [];
         *         foreach ($menuItems as $item) {
         *             if (isset($item->viewBag['group']) && $item->viewBag['group'] !== "registered") {
         *                 $item->viewBag['isHidden'] = "1";
         *             }
         *             if ($item->items) {
         *                 $item->items = $iterator($item->items);
         *             }
         *             $result[] = $item;
         *         }
         *         return $result;
         *     };
         *     $items = $iterator($items);
         * });
         */

        Event::fire('pages.menu.referencesGenerated', [&$items]);

        return $items;
    }

}

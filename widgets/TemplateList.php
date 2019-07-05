<?php namespace Rd\DynoPages\Widgets;

use Str;
use Rd\DynoPages\Classes\Page;
use Rd\DynoPages\Classes\Layout;
use Rd\DynoPages\Classes\Partial;
use System\Classes\PluginManager;
use Rd\DynoPages\Services\DBService;

/**
 * Template list widget.
 * This widget displays templates of different types.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class TemplateList extends \Cms\Widgets\TemplateList
{
    /*
     * Public methods
     */
    public function __construct($controller, $alias)
    {
        $this->alias = $alias;
        $this->dataSource = function () {
            return;
        };
        
        parent::__construct($controller, $this->alias, $this->dataSource);
    }

    /**
     * Renders the widget.
     * @return string
     */
    public function render()
    {
        $toolbarClass = Str::contains($this->controlClass, 'hero') ? 'separator' : null;
        $this->vars['toolbarClass'] = $toolbarClass;
        return $this->makePartial('body', [
            'data' => $this->getDbData()
        ]);
    }

    /**
     * Get data form DB
     * 
     */
    protected function getDbData()
    {
        // this->alias can be used for switch between models (tables)
        // can be used if layout and partial migrated to DB too
        $type = preg_replace('/List$/', '', $this->alias);

        $tableMapping = [
            'page' => 'rd_dynopages_pages',
            // Can be used if layout and partial migrated to DB too
            // 'layout' => 'rd_dynopages_layouts',
            // 'partial' => 'rd_dynopages_partials',
        ];

        $table = $tableMapping[$type];

        // Get records (items) from DB
        $records = DBService::listPages($table, $this->theme, $this->getDefaultLang());

        if(isset($records)){
            foreach ($records as $key => $value) {
                switch ($type) {
                    case 'page':
                        $items[$value] = Page::loadFromDb($this->theme, $value);
                        break;
                    // Can be used if layout and partial migrated to DB too
                    // case 'layout':
                    //     $items[$value] = Layout::loadFromDb($this->theme, $value);
                    //     break;
                    
                    // case 'partial':
                    //     $items[$value] = Partial::loadFromDb($this->theme, $value);
                    //     break;
    
                    default:
                        $items[$value] = Page::loadFromDb($this->theme, $value);
                        break;
                }
            }
        }else{
            return ;
        }
        
        if(isset($items)){
            if ($items instanceof \October\Rain\Support\Collection) {
                $items = $items->all();
            }
        }else{
            return ;
        }

        $items = $this->removeIgnoredDirectories($items);

        $items = array_map([$this, 'normalizeItem'], $items);

        $this->sortItems($items);

        /*
         * Apply the search
         */
        $filteredItems = [];
        $searchTerm = Str::lower($this->getSearchTerm());

        if (strlen($searchTerm)) {
            /*
             * Exact
             */
            foreach ($items as $index => $item) {
                if ($this->itemContainsWord($searchTerm, $item, true)) {
                    $filteredItems[] = $item;
                    unset($items[$index]);
                }
            }

            /*
             * Fuzzy
             */
            $words = explode(' ', $searchTerm);
            foreach ($items as $item) {
                if ($this->itemMatchesSearch($words, $item)) {
                    $filteredItems[] = $item;
                }
            }
        }
        else {
            $filteredItems = $items;
        }

        /*
         * Group the items
         */
        $result = [];
        $foundGroups = [];
        foreach ($filteredItems as $itemData) {
            $pos = strpos($itemData->fileName, '/');

            if ($pos !== false) {
                $group = substr($itemData->fileName, 0, $pos);
                if (!array_key_exists($group, $foundGroups)) {
                    $newGroup = (object)[
                        'title' => $group,
                        'items' => []
                    ];

                    $foundGroups[$group] = $newGroup;
                }

                $foundGroups[$group]->items[] = $itemData;
            }
            else {
                $result[] = $itemData;
            }
        }

        // Sort folders by name regardless of the
        // selected sorting options.
        ksort($foundGroups);

        foreach ($foundGroups as $group) {
            $result[] = $group;
        }

        return $result;
    }
    
    /*
     * Update side menu with correct data from DB
     */
    protected function updateList()
    {
        return [
            '#'.$this->getId('template-list') => $this->makePartial('items', ['items' => $this->getDbData()])
        ];
    }

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
}

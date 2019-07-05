<?php namespace Rd\DynoPages\Widgets;

use Str;
use Rd\DynoPages\Classes\Menu as StaticMenu;

/**
 * Menu list widget.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class MenuList extends \RainLab\Pages\Widgets\MenuList
{

    public function __construct($controller, $alias)
    {
        parent::__construct($controller, $alias);
    }

    /**
     * Renders the widget.
     * @return string
     */
    public function render()
    {
        return $this->makePartial('body', [
            'data' => $this->getDBData()
        ]);
    }

    /**
     * Methods for the internal use
     * 
     * @return array of menu items
     */
    protected function getDBData()
    {
        $menus = StaticMenu::listDbInTheme($this->theme);

        $searchTerm = Str::lower($this->getSearchTerm());

        if (strlen($searchTerm)) {
            $words = explode(' ', $searchTerm);
            $filteredMenus = [];

            foreach ($menus as $menu) {
                if ($this->textMatchesSearch($words, $menu->name.' '.$menu->fileName)) {
                    $filteredMenus[] = $menu;
                }
            }

            $menus = $filteredMenus;
        }

        return $menus;
    }

    protected function updateList()
    {
        $vars = ['items' => $this->getDBData()];
        return ['#'.$this->getId('menu-list') => $this->makePartial('items', $vars)];
    }
}

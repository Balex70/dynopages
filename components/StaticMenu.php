<?php namespace Rd\DynoPages\Components;

use Cms\Classes\Theme;
use Rd\DynoPages\Classes\Menu;

/**
 * The menu component.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class StaticMenu extends \RainLab\Pages\Components\StaticMenu
{
    /**
     * @var array A list of items generated by the menu.
     * Each item is an object of the RainLab\Pages\Classes\MenuItemReference class.
     */
    protected $menuItems;

    public function componentDetails()
    {
        return [
            'name'        => 'rd.dynopages::lang.component.static_menu_name',
            'description' => 'rd.dynopages::lang.component.static_menu_description'
        ];
    }

    public function defineProperties()
    {
        return [
            'code' => [
                'title'       => 'rd.dynopages::lang.component.static_menu_code_name',
                'description' => 'rd.dynopages::lang.component.static_menu_code_description',
                'type'        => 'dropdown'
            ]
        ];
    }

    public function getCodeOptions()
    {
        $result = [];

        $theme = Theme::getEditTheme();
        $menus = Menu::listDbInTheme($theme);

        foreach ($menus as $menu) {
            $result[$menu->code] = $menu->name;
        }

        return $result;
    }

    public function menuItems()
    {
        if ($this->menuItems !== null) {
            return $this->menuItems;
        }

        if (!strlen($this->property('code'))) {
            return;
        }

        $theme = Theme::getActiveTheme();
        $menu = Menu::loadFromDb($theme, $this->property('code'));

        if ($menu) {
            $this->menuItems = $menu->generateReferences($this->page);
            $this->menuName = $menu->name;
        }

        return $this->menuItems;
    }
}

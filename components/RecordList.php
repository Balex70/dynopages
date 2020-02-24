<?php namespace Rd\DynoPages\Components;

use Lang;
use Cms\Classes\Theme;
use Rd\DynoPages\Classes\Page as PageClass;

/**
 * The RecordList component.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class RecordList extends \RainLab\Builder\Components\RecordList
{
    public function getDetailsPageOptions()
    {
        $lang = PageClass::getDefaultLang();
        $theme = Theme::getActiveTheme();
        $pages = PageClass::listDbInTheme($theme, true);
        $result['-'] = Lang::get('rainlab.builder::lang.components.list_details_page_no');
        
        if($pages->count() > 0){
            foreach ($pages as $page) {
                $result[$page->getBaseFileName()] = $page->getAttributeTranslated('url', $lang);
            }
        }

        return $result;
    }
}

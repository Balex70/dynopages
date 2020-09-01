<?php namespace Rd\DynoPages\Controllers;

use Url;
use Lang;
use Flash;
use Request;
use BackendMenu;
use Cms\Classes\Theme;
use Cms\Widgets\TemplateList;
use RainLab\Pages\Widgets\SnippetList;
use RainLab\Pages\Classes\Content;
use RainLab\Pages\Plugin as PagesPlugin;
use ApplicationException;
use Exception;
use Rd\DynoPages\Classes\StaticPage as DynoStaticPage;
use Rd\DynoPages\Classes\Menu as DynoStaticMenu;
use Rd\DynoPages\Widgets\PageList as StaticPageList;
use Rd\DynoPages\Widgets\MenuList as StaticMenuList;

/**
 * Pages and Menus index
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class StaticIndex extends \RainLab\Pages\Controllers\Index
{
    public $requiredPermissions = ['rd.dynopagesstatic.*'];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Rd.DynoPages', 'pages', 'staticindex');

        try {
            if (!($theme = Theme::getEditTheme())) {
                throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));
            }

            new StaticPageList($this, 'pageList');
            new StaticMenuList($this, 'menuList');
            new SnippetList($this, 'snippetList');

            new TemplateList($this, 'contentList', function() {
                return $this->getContentTemplateList();
            });
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    public function onSave()
    {
        $this->validateRequestTheme();
        $type = Request::input('objectType');
        $object = $this->fillObjectFromPost($type);
        
        if ($type == 'page' || $type == 'menu') {
            $object->setTheme($this->theme);
            $object->dbSave();
        }else{
            $object->save();
        }

        $result = [
            'objectPath'  => $type != 'content' ? $object->getBaseFileName() : $object->fileName,
            'objectMtime' => $object->mtime,
            'tabTitle'    => $this->getTabTitle($type, $object)
        ];

        if ($type == 'page') {
            $result['pageUrl'] = Url::to($object->getViewBag()->property('url'));

            PagesPlugin::clearCache();
        }

        $successMessages = [
            'page' => 'rainlab.pages::lang.page.saved',
            'menu' => 'rainlab.pages::lang.menu.saved'
        ];

        $successMessage = isset($successMessages[$type])
            ? $successMessages[$type]
            : $successMessages['page'];

        Flash::success(Lang::get($successMessage));

        return $result;
    }

    public function onCreateObject()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $object = $this->createObject($type);
        $parent = Request::input('parent');
        $parentPage = null;
        
        if ($type == 'page') {
            if (strlen($parent)) {
                $parentPage = DynoStaticPage::loadFromDb($this->theme, $parent);
            }

            $object->setDefaultLayout($parentPage);
        }

        $widget = $this->makeObjectFormWidget($type, $object);
        $this->vars['objectPath'] = '';

        $result = [
            'tabTitle' => $this->getTabTitle($type, $object),
            'tab'      => $this->makePartial('form_page', [
                'form'         => $widget,
                'objectType'   => $type,
                'objectTheme'  => $this->theme->getDirName(),
                'objectMtime'  => null,
                'objectParent' => $parent,
                'parentPage'   => $parentPage
            ])
        ];

        return $result;
    }

    public function onDelete()
    {
        $this->validateRequestTheme();

        $type = Request::input('objectType');

        $deletedObjects = $this->loadObject($type, trim(Request::input('objectPath')))->dbDelete();

        $result = [
            'deletedObjects' => $deletedObjects,
            'theme' => $this->theme->getDirName()
        ];

        return $result;
    }

    protected function loadObject($type, $path, $ignoreNotFound = false)
    {
        $class = $this->resolveTypeClassName($type);
        
        if ($type == 'page' || $type == 'menu') {
            if (!($object = call_user_func(array($class, 'loadFromDb'), $this->theme, $path))) {
                if (!$ignoreNotFound) {
                    throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));
                }

                return null;
            }
        }else{
            if (!($object = call_user_func(array($class, 'load'), $this->theme, $path))) {
                if (!$ignoreNotFound) {
                    throw new ApplicationException(trans('rainlab.pages::lang.object.not_found'));
                }
    
                return null;
            }
        }
 
        return $object;
    }

    protected function resolveTypeClassName($type)
    {
        $types = [
            'page'    => DynoStaticPage::class,
            'menu'    => DynoStaticMenu::class,
            'content' => Content::class,
        ];

        if (!array_key_exists($type, $types)) {
            throw new ApplicationException(trans('rainlab.pages::lang.object.invalid_type'));
        }

        return $types[$type];
    }

}
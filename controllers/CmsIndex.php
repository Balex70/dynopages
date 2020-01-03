<?php namespace Rd\DynoPages\Controllers;

use Url;
use Lang;
use Flash;
use Config;
use Request;
use Exception;
use BackendMenu;
use Cms\Widgets\AssetList;
use Cms\Widgets\TemplateList;
use Cms\Widgets\ComponentList;
use Cms\Classes\Theme;
use Cms\Classes\Router;
use Cms\Classes\Layout;
use Cms\Classes\Partial;
use Cms\Classes\Content;
use Cms\Classes\CmsCompoundObject;
use ApplicationException;
use Cms\Classes\Asset;
use Rd\DynoPages\Widgets\TemplateList as DynoTemplateList;
use Rd\DynoPages\Classes\Page as DynoPage;
use Rd\Dynopages\Models\Setting;

/**
 * CMS
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class CmsIndex extends \Cms\Controllers\Index
{

    public $requiredPermissions = ['rd.dynopagescms.*'];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Rd.DynoPages', 'cms', 'cmsindex');

        try {
            if (!($theme = Theme::getEditTheme())) {
                throw new ApplicationException(Lang::get('cms::lang.theme.edit.not_found'));
            }

            $this->theme = $theme;

            new DynoTemplateList($this, 'pageList');

            new TemplateList($this, 'partialList', function () use ($theme) {
                return Partial::listInTheme($theme, true);
            });

            new TemplateList($this, 'layoutList', function () use ($theme) {
                return Layout::listInTheme($theme, true);
            });

            new TemplateList($this, 'contentList', function () use ($theme) {
                return Content::listInTheme($theme, true);
            });
            
            new ComponentList($this, 'componentList');

            new AssetList($this, 'assetList');
        }
        catch (Exception $ex) {
            $this->handleError($ex);
        }
    }

    protected function resolveTypeClassName($type)
    {
        $types = [
            'page'  => DynoPage::class,
            'partial' => Partial::class,
            'layout'  => Layout::class,
            'content' => Content::class,
            'asset'   => Asset::class
        ];

        if (!array_key_exists($type, $types)) {
            throw new ApplicationException(trans('cms::lang.template.invalid_type'));
        }

        return $types[$type];
    }

    /**
     * Returns a form widget for a specified template type.
     * @param string $type
     * @param string $template
     * @param string $alias
     * @return Backend\Widgets\Form
     */
    protected function makeTemplateFormWidget($type, $template, $alias = null)
    {
        $formConfigs = [
            // Use custom field.yaml for page
            'page'  => '~/plugins/rd/dynopages/classes/page/fields.yaml',
            'layout'  => '~/modules/cms/classes/layout/fields.yaml',
            'partial' => '~/modules/cms/classes/partial/fields.yaml',
            'content' => '~/modules/cms/classes/content/fields.yaml',
            'asset'   => '~/modules/cms/classes/asset/fields.yaml'
        ];

        if (!array_key_exists($type, $formConfigs)) {
            throw new ApplicationException(trans('cms::lang.template.not_found'));
        }

        $widgetConfig = $this->makeConfig($formConfigs[$type]);
        $widgetConfig->model = $template;
        $widgetConfig->alias = $alias ?: 'form'.studly_case($type).md5($template->getFileName()).uniqid();

        return $this->makeWidget('Backend\Widgets\Form', $widgetConfig);
    }

    /**
     * Saves the template currently open
     * @return array
     */
    public function onSave()
    {
        $this->validateRequestTheme();
        $type = Request::input('templateType');
        $templatePath = trim(Request::input('templatePath'));
        $template = $templatePath ? $this->loadTemplate($type, $templatePath) : $this->createTemplate($type);
        $formWidget = $this->makeTemplateFormWidget($type, $template);
        
        $saveData = $formWidget->getSaveData();
        $postData = post();
        $templateData = [];

        $settings = array_get($saveData, 'settings', []) + Request::input('settings', []);

        // Check if should be used custom behaviour on viewBag
        if($type == 'layout' || $type == 'partial' || $type == 'content' || $type == 'asset'){
            // skip
        }else{
            if ($viewBag = array_get($saveData, 'viewBag')) {
                $objectData['settings'] = ['viewBag' => $viewBag];
            }
        }
        
        $settings = $this->upgradeSettings($settings);

        if ($settings) {
            $templateData['settings'] = $settings;
        }

        $fields = ['markup', 'code', 'fileName', 'content'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $saveData)) {
                $templateData[$field] = $saveData[$field];
            }
            elseif (array_key_exists($field, $postData)) {
                $templateData[$field] = $postData[$field];
            }
        }

        if (!empty($templateData['markup']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['markup'] = $this->convertLineEndings($templateData['markup']);
        }

        if (!empty($templateData['code']) && Config::get('cms.convertLineEndings', false) === true) {
            $templateData['code'] = $this->convertLineEndings($templateData['code']);
        }

        if (
            !Request::input('templateForceSave') && $template->mtime
            && Request::input('templateMtime') != $template->mtime
        ) {
            throw new ApplicationException('mtime-mismatch');
        }

        $template->attributes = [];
        $template->fill($templateData);

        // Check if should use DB (dbSave()) or native save method (in case of layout, partial, content and asset)
        if($type == 'layout' || $type == 'partial' || $type == 'content' || $type == 'asset'){
            $template->save();
        }else{
            $template->setTheme($this->theme);
            $template->dbSave();
        }

        $this->fireSystemEvent('cms.template.save', [$template, $type]);
        Flash::success(Lang::get('cms::lang.template.saved'));

        $result = [
            'templatePath'  => $template->fileName,
            'templateMtime' => $template->mtime,
            'tabTitle'      => $this->getTabTitle($type, $template)
        ];

        if ($type === 'page') {
            $result['pageUrl'] = Url::to($template->url);
            $router = new Router($this->theme);
            $router->clearCache();
            CmsCompoundObject::clearCache($this->theme);
        }

        return $result;
    }

    /**
     * Returns an existing template of a given type
     * @param string $type
     * @param string $path
     * @return mixed
     */
    protected function loadTemplate($type, $path, $db = true)
    {
        $class = $this->resolveTypeClassName($type);

        if(!$db || $type == 'layout' || $type == 'partial' || $type == 'content' || $type == 'asset'){
            if (!($template = call_user_func([$class, 'load'], $this->theme, $path))) {
                throw new ApplicationException(trans('cms::lang.template.not_found'));
            }
        }else{
            // $this->theme = name of theme
            // $path = file name
            if (!($template = call_user_func([$class, 'loadFromDb'], $this->theme, $path))) {
                throw new ApplicationException(trans('cms::lang.template.not_found'));
            }
        }

        return $template;
    }

    /**
     * Deletes multiple templates at the same time
     * @return array
     */
    public function onDeleteTemplates()
    {
        $this->validateRequestTheme();

        $type = Request::input('type');
        $templates = Request::input('template');
        $error = null;
        $deleted = [];

        try {
            foreach ($templates as $path => $selected) {
                if ($selected) {
                    // Check if should use DB or native delete method (in case of layout, partial, content and asset)
                    if($type == 'layout' || $type == 'partial' || $type == 'content' || $type == 'asset'){
                        $this->loadTemplate($type, $path)->delete();
                    }else{
                        $this->loadTemplate($type, $path)->dbDelete();
                    }
                    
                    $deleted[] = $path;
                }
            }
        }
        catch (Exception $ex) {
            $error = $ex->getMessage();
        }

        return [
            'deleted' => $deleted,
            'error'   => $error,
            'theme'   => Request::input('theme')
        ];
    }

    public function onDelete()
    {
        $this->validateRequestTheme();

        $type = Request::input('templateType');

        // Check if should use DB or native delete method (in case of layout, partial, content and asset)
        if($type == 'layout' || $type == 'partial' || $type == 'content' || $type == 'asset'){
            $this->loadTemplate($type, trim(Request::input('templatePath')))->delete();
        }else{
            $this->loadTemplate($type, trim(Request::input('templatePath')))->dbDelete();
        }
    }
}
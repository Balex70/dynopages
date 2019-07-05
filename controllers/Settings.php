<?php namespace Rd\Dynopages\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use System\Controllers\Settings as SettingsController;

/**
 * Settings
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Settings extends Controller
{
    public $instance;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'RD Dynopages Settings';
        $this->instance = new SettingsController();
        $this->instance->update("rd","dynopages","settings");
        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Rd.Dynopages', 'dynopages');
    }

    public function index() {}

    public function onSave()
    {
        $this->instance->update_onSave("rd","dynopages","settings");
    }

    public function onResetDefault()
    {
        $this->instance->update_onResetDefault("rd","dynopages","settings");
    }
}

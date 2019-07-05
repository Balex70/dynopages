<?php namespace Rd\Dynopages\Models;

use Model;

/**
 * Setting Model
 * 
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Setting extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'rd_dynopages_settings';

    public $settingsFields = 'fields.yaml';
}

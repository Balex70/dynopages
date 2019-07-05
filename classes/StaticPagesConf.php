<?php namespace Rd\DynoPages\Classes;

use Model;

/**
 * Static Pages Conf class
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class StaticPagesConf extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'rd_dynopages_static_pages_conf';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'conf' => 'json',
    ];
}

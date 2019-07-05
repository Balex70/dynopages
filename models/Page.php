<?php namespace Rd\DynoPages\Models;

use Model;

/**
 * Page Model
 * 
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class Page extends Model
{
    /**
     * @var string The database table used by the model.
     */
    public $table = 'rd_dynopages_pages';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}

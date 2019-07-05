<?php namespace Rd\DynoPages\Services;

use Illuminate\Support\Facades\DB;
use ApplicationException;
use Lang;

/**
 * The CMS partial class.
 *
 * @package rd\dynopages
 * @author Alex Bachynskyi
 */
class DBService
{
    /**
     * Get record from Database by file name
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name.
     * @param string $lang Language code to be used.
     * The file name can contain only alphanumeric symbols, dashes and dots.
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getRecordByFileName($table, $theme, $fileName, $lang)
    {
        $record = DB::table($table)
                ->where('file_name', $fileName)
                ->where('theme', $theme)
                ->when($lang, function ($query, $lang) {
                    return $query->where('lang', $lang);
                })
                ->where('deleted', 0)
                ->first();
        if(!$record){
            return null;
        }

        return $record;
    }

    /**
     * Get record from Database by url
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $url Specifies the url.
     * @param string $lang Language code to be used.
     * 
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getRecordByUrl($table, $theme, $url, $lang)
    {
        $record = DB::table($table)
                ->where('url', $url)
                ->where('theme', $theme)
                ->when($lang, function ($query, $lang) {
                    return $query->where('lang', $lang);
                })
                ->where('deleted', 0)
                ->first();
        if(!$record){
            return null;
        }

        return $record;
    }

    /**
     * Get duplicate record from Database by file name
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name.
     * @param string $lang Language code to be used.
     * The file name can contain only alphanumeric symbols, dashes and dots.
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getDuplicateRecordByFileName($table, $theme, $fileName, $lang)
    {
        $record = DB::table($table)
                ->where('file_name', $fileName)
                ->where('theme', $theme)
                ->when($lang, function ($query, $lang) {
                    return $query->where('lang', '<>', $lang);
                })
                ->where('deleted', 0)
                ->first();
        if(!$record){
            return null;
        }
        
        return $record;
    }

    /**
     * Get record from Database by id
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $id identifier.
     * @param string $lang Language code to be used.
     *
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getRecordById($table, $theme, $id, $lang)
    {
        $record = DB::table($table)
                ->where('id', $id)
                ->where('theme', $theme->getDirName())
                ->when($lang, function ($query, $lang) {
                    return $query->where('lang', $lang);
                })
                ->where('deleted', 0)
                ->first();
        if(!$record){
            return null;
        }
        
        return $record;
    }

    /**
     * Get duplicate record from Database by url (exclude records with same file name, to get real duplication)
     * 
     * @param string $table Specifies the table.
     * @param string $theme Specifies the theme name.
     * @param string $fileName Specifies the default file name (file name to be ignored).
     * @param string $url Specifies the url.
     * @param string $lang Language code to be used.
     * 
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getDuplicateRecordByUrl($table, $theme, $fileName, $url, $lang)
    {
        $record = DB::table($table)
                ->where('file_name', '<>', $fileName)
                ->where('url', $url)
                ->where('theme', $theme)
                ->where('lang', $lang)
                ->where('deleted', 0)
                ->first();
        if(!$record){
            return null;
        }
        
        return $record;
    }

    /**
     * Get all records
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $lang Language code to be used.
     * 
     * @return array Returns a array of records in theme
     */
    public static function listPages($table, $theme, $lang)
    {

        $records = DB::table($table)
                ->where('theme', $theme->getDirName())
                ->where('deleted', 0)
                ->when($lang, function ($query, $lang) {
                    return $query->where('lang', $lang);
                })
                ->pluck('file_name')
                ->toArray();
        if(!$records){
            return null;
        }
        
        return $records;
    }

    /**
     * Get ids of record by fileName
     * 
     * @param string $table Specifies the table.
     * @param mixed $theme Specifies the theme the object belongs to.
     * @param string $fileName Specifies the file name.
     * 
     * @return array Returns a array of records in theme
     */
    public static function getRecordIdsByFileName($table, $theme, $fileName)
    {
        $records = DB::table($table)
                ->where('file_name', $fileName)
                ->where('theme', $theme)
                ->where('deleted', 0)
                ->pluck('id')
                ->toArray();
        if(!$records){
            return null;
        }
        
        return $records;
    }

    /**
     * Delete the model from the database.
     * @param string $table Specifies the table.
     * @param integer $id of record to delete.
     * @return void
     */
    public static function deleteRecord($table, $id)
    {
        // Use soft delete
        DB::table($table)
                ->where('id', $id)
                ->update(
                    [
                        'deleted' => 1
                    ]);
    }

    /**
     * Get record to update from Database or return null
     * 
     * @param string $table Specifies the table.
     * @param integer $id id of record to update.
     * 
     * @return mixed Returns a stdClass object or null if the record wasn't found.
     */
    public static function getRecordToUpdate($table, $id)
    {
        $record = DB::table($table)
                ->where('id', $id)
                ->first();
        if(!$record){
            return null;
        }
        
        return $record;
    }

    /**
     * Update record
     * 
     * @param string $table Specifies the table.
     * @param array $fields Specifies fields.
     * @param integer $id id of record to update.
     * @param array $attributes Specifies the attributes.
     * @param string $settings Specifies the settings (json).
     * @param string $theme theme name.
     * @param integer $mtime
     * @param string $locale Language code to be used.
     * 
     * @return void
     */
    public static function updateRecord($table, $fields, $id, $attributes, $settings, $theme, $mtime, $locale)
    {
        $update = self::getFieldsArray($fields, $attributes, $settings, $theme, $mtime, $locale);

        try {
            // Get the updated rows count here. Keep in mind that zero is a
            // valid value (not failure) if there were no updates needed
            DB::table($table)
            ->where('id', $id)
            ->update($update);
        } catch (\Illuminate\Database\QueryException $e) {
            throw new ApplicationException(
                Lang::get('rd.dynopages::lang.'.$table.'.error_saving')
            );
        }
    }

    /**
     * Insert record
     * 
     * @param string $table Specifies the table.
     * @param array $fields Specifies fields.
     * @param integer $id id of record to update.
     * @param array $attributes Specifies the attributes.
     * @param string $settings Specifies the settings (json).
     * @param string $theme theme name.
     * @param integer $mtime
     * @param string $locale Language code to be used.
     * 
     * @return void
     */
    public static function insertRecord($table, $fields, $fileName, $attributes, $settings, $theme, $mtime, $locale)
    {
        $insert = self::getFieldsArray($fields, $attributes, $settings, $theme, $mtime, $locale);
        
        try {
            // Get the updated rows count here. Keep in mind that zero is a
            // valid value (not failure) if there were no updates needed
            DB::table($table)->insert($insert);
        } catch (\Illuminate\Database\QueryException $e) {
            throw new ApplicationException(
                Lang::get('rd.dynopages::lang.'.$table.'.error_saving')
            );
        }
    }

    /**
     * Get correct formatted array of fields which should be inserted/updated
     * 
     * @param array $fields Specifies the table.
     * @param array $attributes Specifies the attributes.
     * @param string $settings Specifies the settings (json).
     * @param string $theme theme name.
     * @param integer $mtime
     * @param string $locale Language code to be used.
     * 
     * @return array
     */
    public static function getFieldsArray($fields, $attributes, $settings, $theme, $mtime, $locale){
        foreach ($fields as $key => $field) {
            switch ($key) {
                case 'settings':
                    if(isset($settings) && json_encode($settings)){
                        $array[$key] = json_encode($settings);
                    }
                    break;
                
                case 'mtime':
                    $array[$key] = $mtime;
                    break;

                case 'theme':
                    $array[$key] = $theme;
                    break;

                case 'lang':
                    $array[$key] = $locale;
                    break;

                case 'url':
                    if(isset($attributes[$field])){
                        $array[$key] = $attributes[$field];
                    } 
                    break;

                case 'placeholders':
                    if(isset($attributes[$field]) && json_encode($attributes[$field])){
                        $array[$key] = json_encode($attributes[$field]);
                    }    
                    break;

                default:
                    if(isset($attributes[$field]) && $attributes[$field] != ''){
                        $array[$key] = $attributes[$field];
                    }                    
                    break;
            }
        }

        return $array;
    }
}

<?php namespace Rd\DynoPages\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRdDynopagesStaticMenu extends Migration
{
    public function up()
    {
        Schema::create('rd_dynopages_static_menu', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('file_name');
            $table->text('content');
            $table->string('theme');
            $table->string('name');
            $table->boolean('deleted')->default(0);
            $table->integer('mtime');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('rd_dynopages_static_menu');
    }
}
<?php namespace Rd\DynoPages\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRdDynopagesStaticPages extends Migration
{
    public function up()
    {
        Schema::create('rd_dynopages_static_pages', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('file_name');
            $table->string('url')->nullable();
            $table->string('title')->nullable();
            $table->longText('settings');
            $table->longText('placeholders')->nullable();
            $table->string('layout')->nullable();
            $table->longText('markup')->nullable();
            $table->string('theme');
            $table->boolean('navigation_hidden')->nullable(false)->default(0);
            $table->string('lang')->default('en');
            $table->boolean('deleted')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('code')->nullable();
            $table->boolean('is_hidden')->default(0);
            $table->integer('mtime');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('rd_dynopages_static_pages');
    }
}

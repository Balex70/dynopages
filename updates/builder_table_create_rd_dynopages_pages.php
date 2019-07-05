<?php namespace Rd\DynoPages\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreatePagesTable extends Migration
{
    public function up()
    {
        Schema::create('rd_dynopages_pages', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('file_name');
            $table->string('url')->nullable();
            $table->string('title')->nullable();
            $table->longText('settings');
            $table->string('layout')->nullable();
            $table->longText('markup')->nullable();
            $table->string('theme');
            $table->string('lang')->default('en');
            $table->boolean('deleted')->default(0);
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('code')->nullable();
            $table->string('is_hidden')->default('0');
            $table->integer('mtime')->unsigned();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rd_dynopages_pages');
    }
}

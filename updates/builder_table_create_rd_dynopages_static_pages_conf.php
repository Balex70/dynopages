<?php namespace Rd\DynoPages\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateRdDynopagesStaticPagesConf extends Migration
{
    public function up()
    {
        Schema::create('rd_dynopages_static_pages_conf', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('conf')->nullable();
            $table->string('theme');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('rd_dynopages_static_pages_conf');
    }
}

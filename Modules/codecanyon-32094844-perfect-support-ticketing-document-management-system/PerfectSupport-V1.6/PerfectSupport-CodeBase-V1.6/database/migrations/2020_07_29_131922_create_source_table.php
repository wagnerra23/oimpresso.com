<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSourceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->string('source_type');
            $table->index('source_type');

            $table->string('web_url')->nullable();
            $table->string('woo_consumer_key')->nullable();
            $table->string('woo_consumer_secret')->nullable();
            
            $table->string('envato_token')->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);

            $table->text('source_other_info')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sources');
    }
}

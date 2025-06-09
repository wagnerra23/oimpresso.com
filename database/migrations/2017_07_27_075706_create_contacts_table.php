<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');

            $table->integer('city_id')->unsigned();
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('ie_rg', 18)->nullable();

            $table->integer('consumidor_final')->default(1);
            $table->integer('contribuinte')->default(1);

            $table->string('rua', 80)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('bairro', 40)->nullable();
            $table->string('cep', 10)->nullable();


            $table->string('type')->index();
            $table->string('supplier_business_name')->nullable();
            $table->string('name');
            $table->string('tax_number')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('landmark')->nullable();
            $table->string('mobile');
            $table->string('landline')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('pay_term_number')->nullable();
            $table->enum('pay_term_type', ['days', 'months'])->nullable();
            $table->integer('created_by')->unsigned();
            $table->boolean('is_default')->default(0);
            $table->softDeletes();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('contacts');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rb_boleto_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->enum('banco', ['inter', 'c6', 'asaas']);
            $table->enum('ambiente', ['production', 'sandbox'])->default('production');
            $table->boolean('ativo')->default(true)->index();
            $table->string('nome_display')->nullable();
            $table->json('config_json');  // campos sensíveis criptografados via Crypt::encryptString
            $table->timestamps();

            $table->unique(['business_id', 'banco'], 'rb_boleto_cred_biz_banco_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_boleto_credentials');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_certificados')) {
            return; // idempotente — ADR tech/0008
        }

        Schema::create('nfe_certificados', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->uuid('uuid')->unique()
                ->comment('Path do .pfx encrypted: storage/app/nfe-brasil/{biz}/cert/{uuid}.pfx.enc');
            $table->string('cnpj_titular', 14)->index();
            $table->date('valido_ate')->index();
            $table->text('encrypted_password')
                ->comment('Crypt::encryptString da senha do .pfx — NUNCA em texto');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfe_certificados');
    }
};

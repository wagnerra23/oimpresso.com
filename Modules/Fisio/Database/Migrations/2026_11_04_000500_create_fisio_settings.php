<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração da clínica no Fisio (canvas Prescreve Pro, telas ConfigWhatsApp e Onboarding).
 *
 * Chave-valor por empresa, com `user_id` opcional para preferência do profissional (perfil).
 * Uma tabela só para o módulo inteiro: cada tela prefixa a chave (`whatsapp.*`, `onboarding.*`,
 * `agenda.*`). Quem lê e grava é `Modules\Fisio\Services\ConfiguracaoClinica`, sempre com
 * `business_id` explícito (ADR 0093).
 *
 * `user_key` existe porque UNIQUE com coluna NULL não impede duplicata no MySQL: é `user_id`
 * ou 0, e o unique vai nele.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_settings')) {
            return;
        }

        Schema::create('fisio_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedInteger('user_key')->default(0);
            $t->string('key', 80);
            $t->json('value')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'user_key', 'key'], 'fisio_settings_biz_user_key_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_settings');
    }
};

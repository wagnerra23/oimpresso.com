<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operadores de cada posto (UC-FLX-08 · US-PCP-030). Decisão [W] 2026-10-20: o operador do setor da
 * etapa é quem a começa. No legado, SETOR_FUNCIONARIO ligava o funcionário ao setor; aqui a ligação é
 * ao posto, que já carrega o setor (`pcp_postos.setor`).
 *
 * Posto sem nenhuma linha aqui segue como antes: qualquer pessoa da empresa. Tier 0 (ADR 0093):
 * `business_id` indexado + FK, e a chave única inclui a empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_posto_operadores')) {
            return;
        }

        Schema::create('pcp_posto_operadores', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->foreignId('posto_id')->constrained('pcp_postos')->cascadeOnDelete();
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'posto_id', 'user_id'], 'pcp_posto_operadores_biz_posto_user_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_posto_operadores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régua de cobrança do título vencido (US-FIN-071 · UC-FLX-29).
 *
 * Uma linha por (título, etapa) — o UNIQUE é a idempotência: rodar o job duas vezes no mesmo
 * dia não manda o lembrete de novo. `status` = enviado | nao_enviado (com `motivo`: opt-out,
 * sem e-mail, sem contato, falha). `tarefa_id` aponta para o `crm_schedules` criado na última
 * etapa para o vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_titulo_lembretes')) {
            return;
        }

        Schema::create('fin_titulo_lembretes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->unsignedInteger('titulo_id');
            $t->unsignedSmallInteger('etapa_dias');
            $t->string('canal', 20);
            $t->string('status', 20);
            $t->string('motivo', 60)->nullable();
            $t->unsignedBigInteger('tarefa_id')->nullable();
            $t->dateTime('enviado_em')->nullable();
            $t->timestamps();
            $t->unique(['titulo_id', 'etapa_dias'], 'fin_tit_lembretes_titulo_etapa_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('titulo_id')->references('id')->on('fin_titulos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_titulo_lembretes');
    }
};

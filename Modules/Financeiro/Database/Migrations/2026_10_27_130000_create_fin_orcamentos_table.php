<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orçamento mensal por conta do plano de contas, opcionalmente por centro de custo (US-FIN-076).
 * Uma linha por (empresa, mês, conta, centro). `centro_custo_id` NULL = orçamento geral da conta.
 * `centro_chave` existe só para o UNIQUE: no MySQL, NULL não colide com NULL num índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_orcamentos')) {
            return;
        }

        Schema::create('fin_orcamentos', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('business_id')->index();
            $t->char('competencia_mes', 7)->comment('YYYY-MM');
            $t->unsignedInteger('plano_conta_id');
            $t->unsignedInteger('centro_custo_id')->nullable();
            $t->unsignedInteger('centro_chave')->storedAs('IFNULL(centro_custo_id, 0)');
            $t->decimal('valor', 22, 4);
            $t->unsignedInteger('updated_by')->nullable();
            $t->timestamps();

            $t->unique(['business_id', 'competencia_mes', 'plano_conta_id', 'centro_chave'], 'fin_orc_mes_conta_centro_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('plano_conta_id')->references('id')->on('fin_planos_conta')->cascadeOnDelete();
            $t->foreign('centro_custo_id')->references('id')->on('fin_centros_custo')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_orcamentos');
    }
};

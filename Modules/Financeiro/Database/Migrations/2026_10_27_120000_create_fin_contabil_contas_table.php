<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para de conta contábil por empresa (US-FIN-077) — o código reduzido que o sistema do
 * contador (Domínio, Alterdata, Questor) usa para cada origem do Financeiro.
 *
 * `origem_tipo`: plano_conta | conta_bancaria (origem_id = id da linha) · juros | desconto
 * (origem_id = 0, uma conta por empresa). `origem_id` é 0 em vez de NULL porque o UNIQUE do
 * MySQL deixa passar NULL repetido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fin_contabil_contas')) {
            return;
        }

        Schema::create('fin_contabil_contas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('origem_tipo', 20);
            $t->unsignedInteger('origem_id')->default(0);
            $t->string('codigo', 30);
            $t->timestamps();
            $t->unique(['business_id', 'origem_tipo', 'origem_id'], 'fin_contabil_contas_origem_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_contabil_contas');
    }
};

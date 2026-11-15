<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-023 — o aceite guarda o que foi aceito.
 *
 * `retrato_json`: número, valores e itens do orçamento no momento da decisão, nunca custo nem margem.
 * `ip` e `user_agent`: prova de quem aceitou, gravados só no aceite (dado pessoal, LGPD Art. 7º, IX).
 * As três nascem nulas: as decisões anteriores não têm retrato e nenhuma linha existente muda.
 *
 * A tabela já tem `business_id` com índice e FK (2026_10_26_200000). Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comvis_orcamento_aceites')) {
            return;
        }

        Schema::table('comvis_orcamento_aceites', function (Blueprint $t) {
            if (! Schema::hasColumn('comvis_orcamento_aceites', 'retrato_json')) {
                $t->json('retrato_json')->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_aceites', 'ip')) {
                $t->string('ip', 45)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_aceites', 'user_agent')) {
                $t->string('user_agent', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comvis_orcamento_aceites')) {
            return;
        }

        Schema::table('comvis_orcamento_aceites', function (Blueprint $t) {
            foreach (['retrato_json', 'ip', 'user_agent'] as $coluna) {
                if (Schema::hasColumn('comvis_orcamento_aceites', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};

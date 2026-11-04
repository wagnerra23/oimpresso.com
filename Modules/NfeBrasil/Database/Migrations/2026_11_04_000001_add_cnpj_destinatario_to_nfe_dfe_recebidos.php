<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR ARQ-0007 (aceita 2026-11-04) · princípio 6 — DF-e por CNPJ.
 *
 * Uma empresa com mais de um CNPJ (Sabor Brasil: grupo no mesmo banco Delphi) recebe notas em
 * cada CNPJ. `cnpj_destinatario` diz a qual CNPJ da empresa a nota foi destinada — é o que separa
 * o manifesto de um CNPJ do de outro. Nulo = como hoje (o CNPJ do business).
 *
 * Só acrescenta (ADR 0093 G8): coluna nula, nenhum fluxo existente lê. Tier 0: a unicidade
 * continua (business_id, chave_44).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nfe_dfe_recebidos') && ! Schema::hasColumn('nfe_dfe_recebidos', 'cnpj_destinatario')) {
            Schema::table('nfe_dfe_recebidos', function (Blueprint $t) {
                $t->string('cnpj_destinatario', 14)->nullable()->after('cnpj_emitente')
                    ->comment('CNPJ da empresa a quem a nota foi destinada (multi-CNPJ, ADR ARQ-0007)');
                $t->index(['business_id', 'cnpj_destinatario'], 'nfe_dfe_recebidos_biz_dest_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nfe_dfe_recebidos', 'cnpj_destinatario')) {
            Schema::table('nfe_dfe_recebidos', function (Blueprint $t) {
                $t->dropIndex('nfe_dfe_recebidos_biz_dest_idx');
                $t->dropColumn('cnpj_destinatario');
            });
        }
    }
};

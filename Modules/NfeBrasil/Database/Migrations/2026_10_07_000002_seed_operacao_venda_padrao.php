<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 07 · R-NFE-020 — toda empresa que já emite ganha a operação padrão
 * "Venda".
 *
 * A regra geral dela fica NULL de propósito: NULL quer dizer "usa o `tributacao_default` da
 * empresa", que continua sendo editado na tela ConfigDefault. Copiar o JSON aqui criaria duas
 * fontes para o mesmo número, e a tela passaria a editar uma cópia que o motor não lê. Com isso
 * nenhum valor é copiado, e o cálculo de hoje não muda.
 *
 * Idempotente: `insertOrIgnore` contra o índice único (business_id, slug). Rodar duas vezes não
 * duplica. Só insere — nenhuma linha existente é tocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_operacoes_fiscais') || ! Schema::hasTable('nfe_business_configs')) {
            return;
        }

        $agora = now();

        DB::table('nfe_business_configs')
            ->join('business', 'business.id', '=', 'nfe_business_configs.business_id')
            ->orderBy('nfe_business_configs.business_id')
            ->pluck('nfe_business_configs.business_id')
            ->chunk(200)
            ->each(function ($ids) use ($agora) {
                DB::table('nfe_operacoes_fiscais')->insertOrIgnore($ids->map(fn ($id) => [
                    'business_id' => (int) $id,
                    'slug'        => 'venda',
                    'nome'        => 'Venda',
                    'finalidade'  => 1,
                    'cfop'        => null,
                    'regra_geral' => null,
                    'padrao'      => true,
                    'created_at'  => $agora,
                    'updated_at'  => $agora,
                ])->all());
            });
    }

    public function down(): void
    {
        // Sem down destrutivo: a operação pode já ter regras vinculadas.
    }
};

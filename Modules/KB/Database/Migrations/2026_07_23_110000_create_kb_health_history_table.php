<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kb_health_history — série temporal do kb:health-check (régua doc↔código).
 *
 * Responde "cada métrica fica gravada pra verificar a evolução?" (pedido [W]
 * 2026-07-23) do jeito canônico do sistema (ADR 0256: derivado+enforçado
 * sobrevive): o `kb:health-check --snapshot` persiste 1 row por (business, dia)
 * com o JSON dos checks — incl. os 3 novos do trilho doc↔código
 * (code_drift_flagged / code_nodes / code_edges). Evolução consultável por SQL,
 * nunca editada à mão (re-roda o comando; não edita o número).
 *
 * Precedente: mcp_sdd_scorecard_history (ADR 0275 GT-G7 — snapshot diário do
 * scorecard SDD). Invocador real: schedule weekly no KBServiceProvider
 * (domingo 04:00 BRT, 1h após o kb:drift-detector das 03:00).
 *
 * Idempotência de escrita: UNIQUE (business_id, snapshot_date) + updateOrInsert
 * — re-run no mesmo dia atualiza a row, não duplica.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_health_history')) {
            return;
        }

        Schema::create('kb_health_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->date('snapshot_date');
            $table->string('overall', 10)->comment('ok|warn|fail agregado do run');
            $table->json('checks')->comment('JSON completo dos checks do kb:health-check');
            $table->timestamps();

            $table->unique(['business_id', 'snapshot_date'], 'uq_kb_health_hist_biz_date');
            $table->index('business_id', 'idx_kb_health_hist_biz');

            $table->foreign('business_id', 'fk_kb_health_hist_business')
                ->references('id')->on('business')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_health_history');
    }
};

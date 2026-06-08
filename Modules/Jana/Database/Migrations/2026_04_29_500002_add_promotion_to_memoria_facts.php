<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-S8-4 — Auto-promotion de facts úteis (proxy de recall_quality).
 *
 * Hits = quantas vezes esse fato apareceu no recall e foi USADO em uma resposta
 * subsequente (não só retornado, mas referenciado no LLM output).
 *
 * Quando hits >= 5: vira "core_memory" → injetado direto no system prompt
 * (sem precisar passar pelo recall — economiza tempo + tokens).
 *
 * Quando hits == 0 e fato tem > 30 dias: candidato a soft-delete (bloat reducer
 * — métrica memory_bloat_ratio do ADR 0050).
 */
class AddPromotionToMemoriaFacts extends Migration
{
    public function up(): void
    {
        Schema::table('copiloto_memoria_facts', function (Blueprint $t) {
            $t->unsignedInteger('hits_count')->default(0)
                ->after('valid_until')
                ->comment('Quantas vezes esse fato foi usado em resposta');
            $t->timestamp('ultimo_hit_em')->nullable()
                ->after('hits_count')
                ->comment('Última vez que foi referenciado pelo agent');
            $t->boolean('core_memory')->default(false)
                ->after('ultimo_hit_em')
                ->comment('hits >= 5: promovido a core_memory (injetado direto no prompt)');

            $t->index(['business_id', 'core_memory'], 'cmf_biz_core_idx');
            $t->index('hits_count', 'cmf_hits_idx');
        });
    }

    public function down(): void
    {
        Schema::table('copiloto_memoria_facts', function (Blueprint $t) {
            $t->dropColumn(['hits_count', 'ultimo_hit_em', 'core_memory']);
        });
    }
}

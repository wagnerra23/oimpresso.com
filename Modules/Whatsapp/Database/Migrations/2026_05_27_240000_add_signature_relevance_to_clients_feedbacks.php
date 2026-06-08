<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clients_feedbacks — sistema de relevância adaptativa
 *
 * Wagner 2026-05-27: índice de feedback com checksum/signature pra dedup +
 * ranking por relevância. Mais importantes ficam em memória (INDEX.md auto),
 * menos importantes vão pra archive trimestral.
 *
 * Refs: ADR 0195-feedback-relevance-scoring-decay-adaptativo, ADR 0131 (tiering canon/local/segredo).
 *
 * Campos:
 *   - signature (sha1 40c): hash de identidade pra dedup
 *     persona + módulo + ação + literal_normalized
 *     Mesma signature em 90d → recorrente_count++ no existente, NÃO cria novo
 *   - relevance_score (0-100 decimal 5,2): score calculado pela formula em
 *     FeedbackRelevanceService. Recomputado semanalmente.
 *   - relevance_score_at: quando foi último recompute
 *   - last_seen_at: última ocorrência (atualizado em dedup)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients_feedbacks')) {
            return;
        }

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            if (! Schema::hasColumn('clients_feedbacks', 'signature')) {
                $table->string('signature', 40)->nullable()->after('cliente_slug');
            }
            if (! Schema::hasColumn('clients_feedbacks', 'relevance_score')) {
                $table->decimal('relevance_score', 5, 2)->default(0)->after('signature');
            }
            if (! Schema::hasColumn('clients_feedbacks', 'relevance_score_at')) {
                $table->timestamp('relevance_score_at')->nullable()->after('relevance_score');
            }
            if (! Schema::hasColumn('clients_feedbacks', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('relevance_score_at');
            }
        });

        // Indexes em call separada (alguns drivers SQLite reclamam se misturado)
        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->index(['business_id', 'signature'], 'idx_biz_signature');
            $table->index(['business_id', 'relevance_score'], 'idx_biz_relevance');
            $table->index(['business_id', 'last_seen_at'], 'idx_biz_last_seen');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients_feedbacks')) {
            return;
        }

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->dropIndex('idx_biz_signature');
            $table->dropIndex('idx_biz_relevance');
            $table->dropIndex('idx_biz_last_seen');
        });

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->dropColumn(['signature', 'relevance_score', 'relevance_score_at', 'last_seen_at']);
        });
    }
};

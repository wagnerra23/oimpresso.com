<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-VOZ-002 — Extensão customer_memory: funcionário + reclamações + fontes externas.
 *
 * Wagner 2026-05-15: "coloque junto com o perfil do cliente, funcionário,
 * reclamação algo do tipo? nem todo cliente está cadastrado, pesquise no
 * firebird."
 *
 * Adiciona 6 colunas:
 *   - assigned_user_id           — último funcionário que respondeu (outbound)
 *   - most_active_user_id        — funcionário com mais msgs outbound histórico
 *   - most_active_user_count     — n msgs outbound desse funcionário
 *   - reclamacoes_recentes JSON  — top 5 reclamações (heurística keywords, sem IA)
 *   - total_reclamacoes          — count msgs flagged "reclamacao" 30d
 *   - external_sources JSON      — [{source:"firebird_office",codigo,nome,fone}]
 *   - external_sources_enriched_at — last lookup timestamp
 *
 * Idempotente. Tier 0 preservado (sem novos business_id — usa o existente).
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 * @see Modules/Whatsapp/Services/CustomerMemory/OfficeimpressoEnrichService.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_memory')) {
            return; // base migration ainda não rodou
        }

        Schema::table('customer_memory', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_memory', 'assigned_user_id')) {
                $table->unsignedInteger('assigned_user_id')->nullable()
                    ->comment('User.id do último funcionário que respondeu (outbound mais recente)');
            }
            if (! Schema::hasColumn('customer_memory', 'most_active_user_id')) {
                $table->unsignedInteger('most_active_user_id')->nullable()
                    ->comment('User.id do funcionário com mais msgs outbound histórico');
            }
            if (! Schema::hasColumn('customer_memory', 'most_active_user_count')) {
                $table->unsignedInteger('most_active_user_count')->nullable()
                    ->comment('N msgs outbound do most_active_user_id (pra ranking)');
            }
            if (! Schema::hasColumn('customer_memory', 'reclamacoes_recentes')) {
                $table->json('reclamacoes_recentes')->nullable()
                    ->comment('[{date,msg_id,severity,preview}] — top 5 reclamações heurística 30d');
            }
            if (! Schema::hasColumn('customer_memory', 'total_reclamacoes')) {
                $table->unsignedInteger('total_reclamacoes')->default(0)
                    ->comment('Count msgs flagged reclamação (heurística keywords) últimos 30d');
            }
            if (! Schema::hasColumn('customer_memory', 'external_sources')) {
                $table->json('external_sources')->nullable()
                    ->comment('[{source:"firebird_office",cliente_id,name,fone1,fone2,email,bloqueado}]');
            }
            if (! Schema::hasColumn('customer_memory', 'external_sources_enriched_at')) {
                $table->timestamp('external_sources_enriched_at')->nullable();
            }
        });

        // Index pra "top atendentes" + "clientes com reclamações"
        Schema::table('customer_memory', function (Blueprint $table): void {
            $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM customer_memory'))
                ->pluck('Key_name')->unique()->all();

            if (! in_array('cm_biz_assigned_idx', $indexes, true)) {
                $table->index(['business_id', 'assigned_user_id'], 'cm_biz_assigned_idx');
            }
            if (! in_array('cm_biz_reclamacoes_idx', $indexes, true)) {
                $table->index(['business_id', 'total_reclamacoes'], 'cm_biz_reclamacoes_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_memory', function (Blueprint $table): void {
            $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM customer_memory'))
                ->pluck('Key_name')->unique()->all();

            foreach (['cm_biz_assigned_idx', 'cm_biz_reclamacoes_idx'] as $idx) {
                if (in_array($idx, $indexes, true)) {
                    $table->dropIndex($idx);
                }
            }

            foreach ([
                'assigned_user_id', 'most_active_user_id', 'most_active_user_count',
                'reclamacoes_recentes', 'total_reclamacoes',
                'external_sources', 'external_sources_enriched_at',
            ] as $col) {
                if (Schema::hasColumn('customer_memory', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

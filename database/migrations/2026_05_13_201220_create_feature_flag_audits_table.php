<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log append-only de toda mudança em feature flag (GrowthBook).
 *
 * Captura escritas dos 3 canais (Artisan command, Tool MCP, painel admin
 * Inertia) num lugar único. Complementa `mcp_audit_log` (que cobre chamadas
 * MCP genéricas) com payload diff específico de flag.
 *
 * Sem UPDATE jamais — só INSERT (append-only enforced culturalmente).
 *
 * Refs: ADR 0094 (Constituição §princípio 7 transparência),
 *       US-INFRA-001 (GrowthBook self-hosted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flag_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('created_at')->useCurrent()->index();

            $table->unsignedInteger('actor_id')->nullable()
                ->comment('users.id quando ação veio do painel web; null pra CLI/MCP server-side');
            $table->string('actor_label', 80)
                ->comment('Identificador human-readable: web:email@x.com, cli:flag:set, mcp:flag-set');

            $table->string('flag_key', 100)
                ->comment('GrowthBook feature key (ex: useV2SellsCreate)');

            $table->enum('action', [
                'rule_upsert',
                'rule_remove',
                'env_toggle',
                'feature_create',
                'feature_delete',
                'default_value_change',
            ]);
            $table->string('environment', 50)->nullable()
                ->comment('production | dev | staging — null se ação não tem escopo de env');

            $table->json('payload_before')->nullable()
                ->comment('Snapshot relevante antes da mudança (ex: rules antigas)');
            $table->json('payload_after')->nullable()
                ->comment('Snapshot relevante depois da mudança');
            $table->text('diff_summary')->nullable()
                ->comment('Resumo human-readable da mudança (1 linha)');

            $table->index(['flag_key', 'created_at'], 'ffa_flag_ts_idx');
            $table->index(['actor_id', 'created_at'], 'ffa_actor_ts_idx');
            $table->index(['action', 'created_at'], 'ffa_action_ts_idx');

            // FK opcional: deixar nullable + onDelete set null pra preservar audit
            // mesmo se user for excluído (LGPD: registro de quem fez O QUE permanece).
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_audits');
    }
};

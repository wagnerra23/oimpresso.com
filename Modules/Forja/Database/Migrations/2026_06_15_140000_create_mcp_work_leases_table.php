<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mcp_work_leases — lease de coordenação "no máx 1 sessão/agente por task ATIVA".
 *
 * D1 da arquitetura durável anti-vazamento (ADR 0278, proposal #2766). Ativa o
 * Tier 2 do ADR 0119 (lease formal com TTL) que estava dormente: o `whats-active`
 * (Tier 1) é alerta passivo; este é o compare-and-set real (R3 anti-colisão + R4
 * estado canônico de quem-faz-o-quê).
 *
 * INVARIANTE: no máx 1 lease ATIVO por task_id. Como MySQL/MariaDB/SQLite tratam
 * NULL como DISTINTO num índice UNIQUE, usa-se coluna gerada VIRTUAL `active_marker`
 * (1=ativo quando released_at NULL, NULL=liberado) + UNIQUE(task_id, active_marker):
 *   - ativos    → marker=1    → no máx 1 por task_id [colidem → claim concorrente falha]
 *   - liberados → marker=NULL → coexistem N (history) + permite re-claim após release
 *
 * Por que VIRTUAL e não STORED: SQLite só aceita coluna gerada VIRTUAL via
 * `ALTER TABLE ADD COLUMN` (STORED proibido em ALTER) — e a lane per-PR roda todas
 * as migrations contra SQLite :memory: (lição-mãe do floor SDD). VIRTUAL+UNIQUE é
 * suportado em MySQL 5.7.8+, MariaDB 10.2+ e SQLite 3.31+. Padrão provado em
 * 2026_06_13_120000_enforce_single_active_channel_user_access (US-WA-068).
 *
 * EXPIRAÇÃO (TTL/heartbeat) é responsabilidade do WorkLeaseService (seta released_at
 * em leases com expires_at<now ANTES de cada claim) — NUNCA do schema: coluna gerada
 * não pode referenciar now() (função não-determinística).
 *
 * SEM business_id: espelha mcp_tasks (cache git-synced de governança global, sem
 * tenant — coordenação do time é cross-business por natureza). Precedente: mcp_tasks
 * também não tem business_id. Exceção a .claude/rules/migrations.md justificada aqui.
 *
 * SEM FK pra mcp_tasks: aquela tabela é CACHE reconstruído pelo parser idempotente
 * → uma FK cascatearia/quebraria no re-sync. task_id é validado em RUNTIME pelo
 * WorkLeaseService (não pelo schema).
 *
 * NÃO confundir com mcp_file_locks (Modules/ADS) — aquele é mutex POR-ARQUIVO entre
 * Brain A/B do daemon ADS; este é lease POR-TASK entre sessões Claude/IA. O
 * lock-por-path foi conscientemente CORTADO do D1 (proposal #2766, "o que cortar":
 * overlap de path vira alerta no whats-active, não bloqueio).
 *
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md
 * @see memory/decisions/0119-whats-active-tier-1-alerta-passivo.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_work_leases')) {
            Schema::create('mcp_work_leases', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->string('task_id', 40)
                    ->comment('US-* canônico (espelha mcp_tasks.task_id; sem FK — cache git-synced)');

                // Ator (R4). human_principal = dono do token MCP (NÃO-spoofável,
                // resolvido por McpAuthMiddleware). agent_id/session = hints de correlação.
                $table->string('human_principal', 60)
                    ->comment('Dono do token MCP (username) — confiável, não auto-declarado');
                $table->string('agent_id', 80)->nullable()
                    ->comment('Hint auto-declarado do agente (claude-code/cursor/...) — NÃO-confiável');
                $table->string('claude_code_session', 64)->nullable()
                    ->comment('X-Claude-Code-Session — correlaciona com mcp_audit_log/mcp_cc_sessions');

                $table->timestamp('acquired_at')->useCurrent();
                $table->timestamp('heartbeat_at')->useCurrent()
                    ->comment('Último heartbeat; Service estende expires_at a partir daqui');
                $table->timestamp('expires_at')
                    ->comment('TTL (acquired_at + 30min, ADR 0119); Service expira stale antes do claim');
                $table->timestamp('released_at')->nullable()
                    ->comment('NULL=ativo. Setado no release OU pelo Service ao expirar (TTL)');

                $table->timestamps();

                $table->index('task_id', 'mwl_task_idx');
                $table->index('expires_at', 'mwl_expires_idx');
            });
        }

        // Coluna gerada VIRTUAL + UNIQUE via ALTER — padrão provado sqlite+mysql
        // (US-WA-068). Idempotente: guards hasColumn/hasIndex.
        if (! Schema::hasColumn('mcp_work_leases', 'active_marker')) {
            Schema::table('mcp_work_leases', function (Blueprint $table) {
                $table->integer('active_marker')
                    ->virtualAs('case when released_at is null then 1 else null end')
                    ->nullable()
                    ->comment('GERADA: 1=ativo (released_at NULL), NULL=liberado. Enforce 1 lease ativo/task.');
            });
        }

        if (! Schema::hasIndex('mcp_work_leases', 'mwl_active_lease_unq')) {
            Schema::table('mcp_work_leases', function (Blueprint $table) {
                $table->unique(['task_id', 'active_marker'], 'mwl_active_lease_unq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_work_leases');
    }
};

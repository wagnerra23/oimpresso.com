<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mcp_tasks.acceptance_ref — prova de DoD pra fechar uma task (Fase 2, ADR 0278).
 *
 * R1 (estado explícito, anti-vazamento): "done" passa a poder carregar a evidência
 * que a satisfez (URL do PR / commit SHA / path de teste Pest / smoke). O consumer
 * SOFT no TaskCrudService registra um evento de AVISO quando uma task vira `done`
 * SEM esta referência — auditável/ruidoso, **não bloqueante**. O hard-gate (throw)
 * é a Fase 3, sequenciada para DEPOIS do lease (D1) provar valor (ADR 0105).
 *
 * SEM business_id: mcp_tasks é cache git-synced repo-wide (ADR 0070, sem tenant) —
 * a coluna nova espelha a tabela. String simples (não-gerada, sem now()).
 * Idempotente (hasColumn guard) porque a lane per-PR roda contra sqlite :memory:.
 *
 * @see memory/decisions/0278-arquitetura-rede-ia-duravel-anti-vazamento.md (Fase 2)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_tasks') && ! Schema::hasColumn('mcp_tasks', 'acceptance_ref')) {
            Schema::table('mcp_tasks', function (Blueprint $table) {
                $table->string('acceptance_ref', 500)->nullable()->after('blocked_by')
                    ->comment('Prova de DoD pra fechar (PR url / commit sha / test path / smoke). Fase 2 ADR 0278 — R1. Hard-gate = Fase 3.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcp_tasks') && Schema::hasColumn('mcp_tasks', 'acceptance_ref')) {
            Schema::table('mcp_tasks', function (Blueprint $table) {
                $table->dropColumn('acceptance_ref');
            });
        }
    }
};

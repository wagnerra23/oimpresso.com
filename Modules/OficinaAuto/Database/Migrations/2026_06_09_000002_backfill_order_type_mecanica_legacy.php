<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: OS legadas sem tipo de reparo → `order_type = 'mecanica'` (auditoria [CC]
 * 2026-06-09, aprovada [W]).
 *
 * Origem: na Lista/Kanban da Oficina, OS antigas (importadas/seed pré-Wave 5-A) ficavam
 * com `order_type` nulo ou no resíduo legado `'locacao'` — renderizando badge "—" e
 * ficando FORA do quadro de mecânica (`oficina_mecanica_os` só lista `order_type='mecanica'`
 * ou quem já está numa stage de board). [W] (dono do domínio) decidiu: a Oficina é REPARO,
 * então essas OS órfãs nascem `mecanica`.
 *
 * Driver-agnóstico de propósito (≠ migration 000001 erradica_locacao, que é MySQL-only e
 * já estreitou o enum): aqui é um UPDATE puro de dados que roda igual em SQLite (testes) e
 * MySQL (prod). Por isso é complementar, não duplicada — no MySQL a 000001 já reclassificou
 * 'locacao' antes; este passo fecha qualquer NULL remanescente. No SQLite (enum = TEXT) é
 * quem efetivamente faz o backfill.
 *
 * Escopo GLOBAL (cross-business) por desenho: `order_type` é coluna por-linha (não há eixo
 * tenant a respeitar — cada linha já pertence ao seu business via business_id). Idempotente:
 * re-rodar não tem efeito (após o 1º run não sobra 'locacao'/NULL).
 *
 * down(): NÃO é reversível com fidelidade — não há como saber quais linhas eram NULL/'locacao'
 * originalmente. Mantido como no-op explícito (documentado) pra não reintroduzir o badge "—".
 *
 * Preserva Tier 0: multi-tenant global scope (ADR 0093) intacto; FSM ServiceOrder (ADR 0143)
 * intacto; NÃO toca FSM keys das colunas do kanban (charter v4).
 *
 * @see memory/decisions/0265-oficina-reparo-erradica-locacao.md
 * @see memory/sessions/2026-06-09-auditoria-lista-kanban-fechamento.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'order_type')) {
            return;
        }

        DB::table('service_orders')
            ->where('order_type', 'locacao')
            ->orWhereNull('order_type')
            ->update(['order_type' => 'mecanica']);
    }

    public function down(): void
    {
        // Irreversível por desenho: o dado de origem (NULL vs 'locacao') foi perdido no up().
        // No-op explícito — reverter pra NULL reintroduziria o badge "—" que esta migration
        // veio justamente apagar. Sem rollback de dados aqui (documentado p/ a catraca de schema).
    }
};

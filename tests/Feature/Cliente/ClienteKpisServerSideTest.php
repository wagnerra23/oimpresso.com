<?php

declare(strict_types=1);
// Cobre UC-CIDX-04 (Index.casos.md) - G-2 rastreabilidade caso-teste.

/**
 * P0 — KPIs do placar Cliente: número que veio de query, não de amostra.
 *
 * Antes, 3 dos 5 cards (VIPs · Sem compra 90d · Novos) eram estimados client-side
 * sobre as 50 rows da página — "número sem prova" (o mesmo pecado que o Ledger DS
 * combate). Agora os 5 vêm reais de ContactController::buildClienteIndexKpis,
 * scoped business_id (Tier 0 · ADR 0093).
 *
 * Estratégia (canon ClienteListagemTurbinadaTest): structural guards do query exato
 * (a semântica É a corretude) + wiring frontend. Prova do NÚMERO real = smoke
 * pós-deploy (Wagner confere o placar renderizado).
 *
 * Refs: charter Cliente/Index "Onda 3 plug backend" · ADR 0093 · FrescorPill (last_purchase_at).
 */

$controller = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
$index = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
$strip = __DIR__ . '/../../../resources/js/Pages/Cliente/_components/KpiStripClickable.tsx';

test('P0 — buildClienteIndexKpis devolve os 3 counts reais (vips/sem_compra_90d/novos_mes)', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)
        ->toContain("'vips' => (int) \$vips")
        ->toContain("'sem_compra_90d' => (int) \$sem_compra_90d")
        ->toContain("'novos_mes' => (int) \$novos_mes");
});

test('P0 — VIPs conta contacts.vip=1 com guard de coluna (compat pré-migration Wave B)', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)
        ->toContain("Schema::hasColumn('contacts', 'vip')")
        ->toContain("->where('contacts.vip', 1)");
});

test('P0 — Novos do mês = created_at >= início do mês', function () use ($controller) {
    $src = file_get_contents($controller);
    expect($src)->toContain("->where('contacts.created_at', '>=', now()->startOfMonth())");
});

test('P0 — Sem compra 90d (risco churn) = já comprou (não-draft) mas nada nos últimos 90d', function () use ($controller) {
    $src = file_get_contents($controller);
    // Bloco sem_compra_90d: whereExists (comprou) + whereNotExists (não nos 90d).
    expect($src)
        ->toContain('$sem_compra_90d = (clone $base)')
        ->toContain('->whereExists(function ($q) use ($business_id) {')
        ->toContain('->whereNotExists(function ($q) use ($business_id) {')
        ->toContain("->where('transactions.status', '!=', 'draft')")
        ->toContain("->where('transactions.transaction_date', '>=', now()->subDays(90))");
});

test('P0 — Tier 0: subqueries do sem_compra_90d scoped por business_id (ADR 0093)', function () use ($controller) {
    $src = file_get_contents($controller);
    // A subquery em transactions filtra business_id explícito (não só via join contact).
    expect(substr_count($src, "->where('transactions.business_id', \$business_id)"))
        ->toBeGreaterThanOrEqual(2); // exists + notExists do sem_compra_90d
});

test('P0 — frontend usa os counts reais do backend (kpis.*), não estimativa client-side', function () use ($index) {
    $src = file_get_contents($index);
    expect($src)
        ->toContain('vips={kpis?.vips ?? 0}')
        ->toContain('sem90={kpis?.sem_compra_90d ?? 0}')
        ->toContain('novos={kpis?.novos_mes ?? 0}')
        // interface ClienteKpis expandida.
        ->toContain('sem_compra_90d: number;')
        ->toContain('novos_mes: number;')
        // o estimado client-side (kpiCounts/vipsCount sobre rows) foi REMOVIDO.
        ->not->toContain('kpiCounts')
        ->not->toContain('vipsCount');
});

test('P0 — KpiStripClickable não anuncia mais "estimado client-side"', function () use ($strip) {
    $src = file_get_contents($strip);
    expect($src)
        ->not->toContain('Estimado client-side')
        ->toContain('Total real');
});

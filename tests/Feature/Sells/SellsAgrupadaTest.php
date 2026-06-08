<?php

declare(strict_types=1);

/**
 * US-SELL-024 — Campo "venda agrupada" explícito (boolean is_grouped_invoice).
 *
 * Pattern canon US-SELL-008/017/021/023: testes ESTRUTURAIS (file_get_contents
 * + regex) — auto-mem feedback_tenancy_changes_require_pest_local dispensa banco
 * real pra mudanças que adicionam coluna nullable + select scalar (sem mexer em
 * scope/Model multi-tenant).
 *
 * Anti-regressão Tier 0:
 *   - Migration adiciona coluna boolean default false (preserva vendas legadas)
 *   - Index composto (business_id, is_grouped_invoice) — multi-tenant Tier 0 (ADR 0093)
 *   - Migration idempotente (Schema::hasColumn check)
 *   - Backend retorna boolean cast (defesa contra 0/1 string SQLite Pest)
 *   - Backend usa COALESCE pra schemas sem a coluna (compat Pest in-memory)
 *   - Frontend badge só renderiza quando true (silent quando false — não polui Lista)
 *   - Substitui inferência ambígua "ATIVO CRIADO" do Delphi por flag explícita
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), SPEC US-SELL-024,
 *       memory/research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md
 *       (CODFINANCEIRO_GRUPO 43-65% das linhas em todos clientes legacy)
 */

const SELL_CONTROLLER_PATH_024 = 'app/Http/Controllers/SellController.php';
const GRADE_PATH_024 = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';
const MIGRATION_PATH_024 = 'database/migrations/2026_05_12_140001_add_is_grouped_invoice_to_transactions.php';

function readController024(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_024));
}

function readGrade024(): string
{
    return file_get_contents(base_path(GRADE_PATH_024));
}

function readMigration024(): string
{
    return file_get_contents(base_path(MIGRATION_PATH_024));
}

// ─── Migration ──────────────────────────────────────────────────────────────

it('migration add_is_grouped_invoice_to_transactions existe', function () {
    expect(file_exists(base_path(MIGRATION_PATH_024)))->toBeTrue();
});

it('migration adiciona coluna boolean default false (preserva vendas legacy)', function () {
    $src = readMigration024();
    expect($src)->toMatch('/boolean\\([\'"]is_grouped_invoice[\'"]\\)[\\s\\S]*?->default\\(false\\)/');
});

it('migration adiciona INDEX composto (business_id, is_grouped_invoice) — Tier 0 multi-tenant', function () {
    $src = readMigration024();
    expect($src)->toMatch('/index\\(\\[[\'"]business_id[\'"]\\s*,\\s*[\'"]is_grouped_invoice[\'"]\\]/');
});

it('migration é idempotente (Schema::hasColumn check antes de criar)', function () {
    $src = readMigration024();
    expect($src)->toMatch('/Schema::hasColumn\\([\'"]transactions[\'"]\\s*,\\s*[\'"]is_grouped_invoice[\'"]\\)/');
});

it('migration tem down() reversível (drop index + drop column)', function () {
    $src = readMigration024();
    expect($src)->toContain('public function down');
    expect($src)->toContain('dropColumn');
    expect($src)->toContain('dropIndex');
});

// ─── Backend: SellController@inertiaList ────────────────────────────────────

it('inertiaList retorna is_grouped_invoice no payload (cast bool defensivo)', function () {
    $src = readController024();
    expect($src)->toMatch("/'is_grouped_invoice'\\s*=>\\s*\\(bool\\)\\s*\\\$r->is_grouped_invoice/");
});

it('inertiaList select usa COALESCE pra defesa em Pest SQLite sem coluna', function () {
    $src = readController024();
    expect($src)->toMatch('/COALESCE\\(transactions\\.is_grouped_invoice,\\s*0\\)\\s+as\\s+is_grouped_invoice/');
});

// ─── Frontend: SellsGradeAvancada — GroupedInvoiceBadge ─────────────────────

it('SellsGradeAvancada componente GroupedInvoiceBadge existe', function () {
    $src = readGrade024();
    expect($src)->toContain('function GroupedInvoiceBadge');
});

it('SellsGradeAvancada GroupedInvoiceBadge é silent quando is_grouped_invoice=false (não polui Lista)', function () {
    $src = readGrade024();
    // Pattern: if (!isGrouped) return null
    expect($src)->toMatch('/if\\s*\\(!isGrouped\\)\\s*return\\s+null/');
});

it('SellsGradeAvancada renderiza GroupedInvoiceBadge ao lado do invoice_no quando true', function () {
    $src = readGrade024();
    // O badge é chamado com prop isGrouped=row.is_grouped_invoice
    expect($src)->toMatch('/<GroupedInvoiceBadge[^>]*isGrouped=\\{row\\.is_grouped_invoice\\}/');
});

it('SellsGradeAvancada GroupedInvoiceBadge usa label PT-BR "Agrupada" + cor violet', function () {
    $src = readGrade024();
    // Label visível
    expect($src)->toContain('Agrupada');
    // Cor semantic violet (distinta dos badges de payment/produção)
    expect($src)->toMatch('/GroupedInvoiceBadge[\\s\\S]*?text-violet-700/');
});

it('SellsGradeAvancada SaleRow type tem is_grouped_invoice: boolean', function () {
    $src = readGrade024();
    expect($src)->toMatch('/is_grouped_invoice:\\s*boolean/');
});

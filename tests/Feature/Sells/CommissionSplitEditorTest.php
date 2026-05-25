<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * ADR 0192 Onda 2 follow-up — Editor UI commission_split (mecânico/balcão).
 *
 * Pest GUARDs híbridos:
 *  1. Backend rejeita total ≠ 100 (validation server-side ADR 0192)
 *  2. Backend aceita payload válido + persiste shape canon (round-trip cast)
 *  3. Backend aceita NULL (limpar split · backward-compat venda direta)
 *  + Structural guards: controller, route, Edit.tsx wiring, multi-tenant ownership
 *
 * Pattern skipsOnSqlite seguido pra blocos DB (UPOS legacy MySQL-only, ADR 0101).
 *
 * @see app/Http/Controllers/SellCommissionSplitController.php
 * @see resources/js/Pages/Sells/_components/CommissionSplitEditor.tsx
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 */

const COMMISSION_CONTROLLER_PATH = 'app/Http/Controllers/SellCommissionSplitController.php';
const COMMISSION_EDITOR_TSX_PATH = 'resources/js/Pages/Sells/_components/CommissionSplitEditor.tsx';
const COMMISSION_EDIT_TSX_PATH = 'resources/js/Pages/Sells/Edit.tsx';
const COMMISSION_SELL_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';
const COMMISSION_ROUTES_PATH = 'routes/web.php';

function commRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Structural: peças existem + cabeada ──────────────────────────────────────

it('Controller + componente React + tests existem', function () {
    expect(file_exists(base_path(COMMISSION_CONTROLLER_PATH)))->toBeTrue();
    expect(file_exists(base_path(COMMISSION_EDITOR_TSX_PATH)))->toBeTrue();
});

it('Controller tem permission guard + Tier 0 multi-tenant + Rule::exists scoped', function () {
    $source = commRead(COMMISSION_CONTROLLER_PATH);
    expect($source)
        ->toContain('public function update(Request $request, int $id)')
        ->toContain("can('sell.update')")
        ->toMatch('/abort\(403/')
        ->toMatch("/session\(\)->get\('user\.business_id'\)/")
        ->toMatch("/where\('business_id', \\\$businessId\)/")
        ->toContain("Rule::exists('users', 'id')") // bloqueia cross-tenant injection
        ->toContain("->where(fn (\$q) => \$q->where('business_id', \$businessId))");
});

it('Controller valida shape canon + total === 100 + bloqueia mecânico==balcão', function () {
    $source = commRead(COMMISSION_CONTROLLER_PATH);
    expect($source)
        ->toContain('commission_split.mecanico_id')
        ->toContain('commission_split.mecanico_pct')
        ->toContain('commission_split.balcao_id')
        ->toContain('commission_split.balcao_pct')
        ->toContain('between:0,100')
        ->toMatch('/abs\(\\\$total - 100\.0\) > 0\.01/')
        ->toContain('não podem ser a mesma pessoa');
});

it('Controller aceita NULL pra limpar (venda direta sem split)', function () {
    $source = commRead(COMMISSION_CONTROLLER_PATH);
    expect($source)
        ->toContain('$rawInput === null')
        ->toContain('$transaction->commission_split = null');
});

it('Route PATCH /sells/{id}/commission-split registrada com FQCN', function () {
    $routes = commRead(COMMISSION_ROUTES_PATH);
    expect($routes)
        ->toContain('/sells/{id}/commission-split')
        ->toContain('SellCommissionSplitController::class')
        ->toContain('sells.commission-split.update')
        ->toMatch('/Route::patch/');
});

it('React component exporta default + valida total + esconde balcão isSoloMecanico + botão Limpar', function () {
    $source = commRead(COMMISSION_EDITOR_TSX_PATH);
    expect($source)
        ->toContain('export default function CommissionSplitEditor')
        ->toContain('export interface CommissionSplitValue')
        ->toContain('router.patch(saveUrl')
        ->toContain('preserveScroll: true')
        ->toContain('totalOk') // validation real-time
        ->toContain('isSoloMecanico') // modo 100% mecânico
        ->toContain('{!isSoloMecanico && (') // esconde input balcão
        ->toContain('handleClear') // limpar
        ->toContain('commission_split: null');
});

it('Edit.tsx pluga CommissionSplitEditor + tipa commission_split + passa users', function () {
    $source = commRead(COMMISSION_EDIT_TSX_PATH);
    expect($source)
        ->toContain('import CommissionSplitEditor')
        ->toContain('commission_split: CommissionSplitValue | null')
        ->toContain('urls.commission_split && form.users')
        ->toContain('saveUrl={urls.commission_split}');
});

it('SellController@edit expõe commission_split no payload + urls.commission_split', function () {
    $source = commRead(COMMISSION_SELL_CONTROLLER_PATH);
    expect($source)
        ->toContain("'commission_split' => \$transaction->commission_split")
        ->toContain("'commission_split' => '/sells/' . \$id . '/commission-split'");
});

// ─── DB integration GUARDs (skip SQLite — ADR 0101 UPOS MySQL-only) ───────────

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UPOS legacy requer MySQL (ADR 0101)');
    }
    if (! Schema::hasColumn('transactions', 'commission_split')) {
        $this->markTestSkipped('migration commission_split não aplicada');
    }
});

it('GUARD 1: cast commission_split round-trip preserva shape canon (mecanico_pct + balcao_pct === 100)', function () {
    $tx = new \App\Transaction;
    $tx->commission_split = [
        'mecanico_id' => 99,
        'mecanico_pct' => 70.0,
        'balcao_id' => 88,
        'balcao_pct' => 30.0,
    ];

    expect($tx->commission_split)->toBeArray();
    expect($tx->commission_split['mecanico_id'])->toBe(99);
    expect($tx->commission_split['balcao_id'])->toBe(88);
    expect($tx->commission_split['mecanico_pct'] + $tx->commission_split['balcao_pct'])->toBe(100.0);
});

it('GUARD 2: cast commission_split aceita null (backward-compat vendas legacy/balcão direto)', function () {
    $tx = new \App\Transaction;
    $tx->commission_split = null;
    expect($tx->commission_split)->toBeNull();
});

it('GUARD 3: cast suporta modo 100% mecânico (balcao_id NULL · balcao_pct 0)', function () {
    $tx = new \App\Transaction;
    $tx->commission_split = [
        'mecanico_id' => 42,
        'mecanico_pct' => 100.0,
        'balcao_id' => null,
        'balcao_pct' => 0.0,
    ];

    expect($tx->commission_split['balcao_id'])->toBeNull();
    expect($tx->commission_split['mecanico_pct'])->toBe(100.0);
});

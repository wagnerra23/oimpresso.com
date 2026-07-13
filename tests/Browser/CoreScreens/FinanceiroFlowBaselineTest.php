<?php

declare(strict_types=1);

/**
 * Baseline visual de fluxos críticos do Financeiro: captura o estado APÓS a interação,
 * mas antes de persistir dados. Cobre criar recebimento, drawer, baixa e seleção em lote
 * em 1024, 1280 e 1440px. O contrato está em tests/Browser/visreg-flows.json.
 */

use App\Business;
use App\User;

$grayZone = new \ArrayObject();

afterAll(function () use ($grayZone) {
    \Tests\Browser\Support\VisregThreshold::writeGrayZoneSummary($grayZone->getArrayCopy());
});

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    \Illuminate\Support\Facades\DB::purge('mysql');
    \Carbon\Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(fn () => \Carbon\Carbon::setTestNow());

function financeiroFlowCases(): array
{
    $path = dirname(__DIR__) . '/visreg-flows.json';
    $manifest = json_decode((string) @file_get_contents($path), true);
    if (!is_array($manifest['viewports'] ?? null) || !is_array($manifest['screens'] ?? null)) {
        throw new RuntimeException("visreg-flows.json ausente/inválido em {$path}");
    }
    $cases = [];
    foreach ($manifest['screens'] as $slug => $screen) {
        foreach ($screen['viewports'] as $viewportId) {
            $viewport = $manifest['viewports'][$viewportId];
            foreach ($screen['flows'] as $flow) {
                $label = "{$slug} · {$flow['id']} · {$viewportId}";
                $cases[$label] = [$screen, $flow, $viewport, $slug, $viewportId];
            }
        }
    }
    return $cases;
}

function estabilizarVisual($page): void
{
    $page->script(<<<'JS'
(() => {
  const s = document.createElement('style');
  s.textContent = '* { transition:none !important; animation:none !important; font-family:Arial,sans-serif !important; } body { -webkit-font-smoothing:antialiased !important; } select,input[type=date],input[type=datetime-local],input[type=time] { visibility:hidden !important; }';
  document.head.appendChild(s); return true;
})()
JS);
    $page->wait(0.5);
}

function executarFluxoFinanceiro($page, string $action): void
{
    if ($action === 'create_receivable') {
        $page->click('Novo título')->click('Novo recebimento')->assertSee('Nova conta a receber');
        return;
    }
    if ($action === 'open_drawer') {
        expect($page->script("(() => { const row=document.querySelector('tbody tr'); if (!row) return false; row.click(); return true; })()"))->toBeTrue();
        $page->wait(0.3);
        expect($page->script("!!document.querySelector('[role=dialog]')"))->toBeTrue();
        return;
    }
    if ($action === 'open_baixa') {
        expect($page->script(<<<'JS'
(() => {
  const button = [...document.querySelectorAll('button')].find((el) => /\b(Recebi|Paguei)\b/.test(el.textContent || ''));
  if (!button) return false; button.click(); return true;
})()
JS))->toBeTrue();
        $page->wait(0.3);
        expect($page->script("!!document.querySelector('[role=dialog]')"))->toBeTrue();
        return;
    }
    if ($action === 'select_bulk') {
        expect($page->script(<<<'JS'
(() => { const box=document.querySelector('[aria-label^="Selecionar lançamento"]'); if (!box) return false; box.click(); return true; })()
JS))->toBeTrue();
        $page->assertSee('Cancelar lote');
        return;
    }
    throw new RuntimeException("Ação visual não suportada: {$action}");
}

foreach (financeiroFlowCases() as $label => [$screen, $flow, $viewport, $slug, $viewportId]) {
    it("{$label} bate com baseline pós-interação", function () use ($screen, $flow, $viewport, $slug, $viewportId, $grayZone) {
        $business = Business::find(1);
        $admin = $business ? User::where('business_id', 1)->orderBy('id')->first() : null;
        if (!$business || !$admin) test()->markTestSkipped('Tenant visual biz=1 sem admin.');

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($screen['route']))
            ->resize($viewport['width'], $viewport['height'])
            ->assertSee($screen['anchor']);
        executarFluxoFinanceiro($page, $flow['action']);
        estabilizarVisual($page);

        \Tests\Browser\Support\VisregThreshold::assertBandedScreenshot(
            page: $page,
            screenName: "{$slug} · {$flow['id']} · {$viewportId}",
            grayZone: $grayZone,
            baselineSuite: 'FinanceiroFlowBaselineTest',
        );
    });
}

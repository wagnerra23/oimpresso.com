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
        // Só os fluxos de Financeiro — telas `suite: compras` rodam no
        // ComprasFlowBaselineTest (dispatcher de ações próprio). `suite` ausente =
        // financeiro (back-compat: a financeiro-unificado segue inclusa).
        if (($screen['suite'] ?? 'financeiro') !== 'financeiro') {
            continue;
        }
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
  s.textContent = '* { transition:none !important; animation:none !important; font-family:Arial,sans-serif !important; } body { -webkit-font-smoothing:antialiased !important; } input[type=date],input[type=datetime-local],input[type=time] { visibility:hidden !important; }';
  document.head.appendChild(s); return true;
})()
JS);
    $page->wait(0.5);
}

/** Aguarda o React montar o alvo real do fluxo, sem assumir que o cabeçalho da
 * página significa que a tabela, os portais e seus handlers já estão prontos.
 */
function aguardarAlvoVisual($page, string $script, string $alvo): void
{
    for ($tentativa = 0; $tentativa < 20; $tentativa++) {
        if ($page->script($script) === true) {
            return;
        }

        $page->wait(0.25);
    }

    throw new RuntimeException("Alvo visual não ficou disponível: {$alvo}");
}

/** Linha determinística para os fluxos que partem de um lançamento existente.
 *
 * O seed visual padrão só garante empresa+admin. Sem este título, drawer, baixa
 * e lote viram testes acidentais do conteúdo de demo em vez de contratos de UI.
 */
function semearTituloVisualFinanceiro(int $userId): void
{
    // A rota do manifesto fixa junho/2026. Usar o mesmo relógio congelado evita
    // o falso vazio quando o runner muda de mês entre a geração e a comparação.
    $agora = \Carbon\Carbon::getTestNow() ?? \Carbon\Carbon::now();
    $hoje = $agora->toDateString();
    $competencia = $agora->format('Y-m');

    \Illuminate\Support\Facades\DB::table('fin_titulos')->updateOrInsert(
        ['business_id' => 1, 'origem' => 'manual', 'origem_id' => 987654, 'parcela_numero' => 1],
        [
            'numero' => 'VISREG-FIN-001',
            'tipo' => 'receber',
            'status' => 'aberto',
            'cliente_descricao' => 'Cliente de prova visual',
            'valor_total' => 1500.00,
            'valor_aberto' => 1500.00,
            'moeda' => 'BRL',
            'emissao' => $hoje,
            'vencimento' => $hoje,
            'competencia_mes' => $competencia,
            'parcela_total' => 1,
            'created_by' => $userId,
            'updated_at' => now(),
            'created_at' => now(),
        ],
    );
}

function executarFluxoFinanceiro($page, string $action): void
{
    if ($action === 'create_receivable') {
        $page->click('Novo título')->click('Novo recebimento')->assertSee('Nova conta a receber');
        return;
    }
    if ($action === 'open_drawer') {
        aguardarAlvoVisual($page, "!!document.querySelector('tbody tr')", 'linha de lançamento');
        expect($page->script("(() => { document.querySelector('tbody tr').click(); return true; })()"))->toBeTrue();
        aguardarAlvoVisual($page, "!!document.querySelector('[role=dialog]')", 'drawer do lançamento');
        return;
    }
    if ($action === 'open_baixa') {
        aguardarAlvoVisual($page, <<<'JS'
(() => {
  return [...document.querySelectorAll('button')].some((el) => /\b(Recebi|Paguei)\b/.test(el.textContent || ''));
})()
JS, 'botão de baixa');
        expect($page->script(<<<'JS'
(() => {
  const button = [...document.querySelectorAll('button')].find((el) => /\b(Recebi|Paguei)\b/.test(el.textContent || ''));
  button.click(); return true;
})()
JS))->toBeTrue();
        aguardarAlvoVisual($page, "!!document.querySelector('[role=dialog]')", 'sheet de baixa');
        return;
    }
    if ($action === 'select_bulk') {
        aguardarAlvoVisual($page, "!!document.querySelector('[aria-label^=\"Selecionar lançamento\"]')", 'checkbox de lote');
        expect($page->script(<<<'JS'
(() => { document.querySelector('[aria-label^="Selecionar lançamento"]').click(); return true; })()
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
        semearTituloVisualFinanceiro($admin->id);

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

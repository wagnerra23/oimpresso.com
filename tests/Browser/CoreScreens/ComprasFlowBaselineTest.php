<?php

declare(strict_types=1);

/**
 * Baseline visual de fluxos críticos do cockpit de Compras: captura o estado APÓS
 * a interação, mas antes de persistir dados. Cobre abrir o menu de visibilidade de
 * colunas, o menu de Ações da linha e o drawer da compra em 1024, 1280 e 1440px.
 *
 * Espelha o FinanceiroFlowBaselineTest (mesmo motor VisregThreshold + mesmo contrato
 * tests/Browser/visreg-flows.json), mas com dispatcher de ações PRÓPRIO — as
 * interações de Compras divergem das de Financeiro (ver executarFluxoCompras). O
 * filtro por `suite` no comprasFlowCases() garante que este teste só roda os fluxos
 * marcados `suite: compras` no manifesto (o Financeiro filtra `suite: financeiro`),
 * então o mesmo JSON serve os dois sem colisão.
 *
 * Helpers têm sufixo `Compras` de propósito: Pest carrega todos os arquivos de teste
 * no MESMO processo quando se roda o diretório inteiro; nomes globais iguais aos do
 * FinanceiroFlowBaselineTest dariam "Cannot redeclare function".
 *
 * O CI roda cada arquivo isolado (`./vendor/bin/pest .../ComprasFlowBaselineTest.php`).
 *
 * @see tests/Browser/CoreScreens/FinanceiroFlowBaselineTest.php (padrão espelhado)
 * @see database/seeders/VisregComprasFlowSeeder.php (dado determinístico)
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
    // Congela o relógio do PROCESSO do Pest. O browser roda em outro processo e NÃO
    // é afetado — por isso o seed usa data literal fixa (ver VisregComprasFlowSeeder).
    \Carbon\Carbon::setTestNow('2026-06-11 12:00:00');
});

afterEach(fn () => \Carbon\Carbon::setTestNow());

function comprasFlowCases(): array
{
    $path = dirname(__DIR__) . '/visreg-flows.json';
    $manifest = json_decode((string) @file_get_contents($path), true);
    if (!is_array($manifest['viewports'] ?? null) || !is_array($manifest['screens'] ?? null)) {
        throw new RuntimeException("visreg-flows.json ausente/inválido em {$path}");
    }
    $cases = [];
    foreach ($manifest['screens'] as $slug => $screen) {
        // Só os fluxos de Compras — o Financeiro roda os dele no seu próprio arquivo.
        if (($screen['suite'] ?? 'financeiro') !== 'compras') {
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

function estabilizarVisualCompras($page): void
{
    $page->script(<<<'JS'
(() => {
  const s = document.createElement('style');
  s.textContent = '* { transition:none !important; animation:none !important; } body { -webkit-font-smoothing:antialiased !important; } input[type=date],input[type=datetime-local],input[type=time] { visibility:hidden !important; }';
  document.head.appendChild(s); return true;
})()
JS);
    // Sem force de Arial (ITEM 7 · 3c): a fonte REAL self-hosted precisa estar carregada
    // antes da captura, senão a baseline assa o fallback.
    \Tests\Browser\Support\VisregThreshold::aguardarFontesReais($page);
    $page->wait(0.5);
}

/** Aguarda o React montar o alvo real do fluxo, sem assumir que o cabeçalho da
 * página significa que a tabela, os portais e seus handlers já estão prontos.
 */
function aguardarAlvoVisualCompras($page, string $script, string $alvo): void
{
    for ($tentativa = 0; $tentativa < 20; $tentativa++) {
        if ($page->script($script) === true) {
            return;
        }

        $page->wait(0.25);
    }

    throw new RuntimeException("Alvo visual não ficou disponível: {$alvo}");
}

/** Compra determinística para os fluxos que partem de uma linha existente.
 *
 * O seed visual padrão (VisregTenantSeeder) só garante business+admin+location. Sem
 * esta compra, o menu de Ações e o drawer viram testes acidentais de tabela vazia.
 * Auto-commit (browser tests não usam RefreshDatabase) → já visível ao browser no
 * GET /compras seguinte. Data literal fixa: a lista de Compras não filtra por data.
 */
function semearCompraVisualCompras(): void
{
    (new \Database\Seeders\VisregComprasFlowSeeder())->run();
}

function executarFluxoCompras($page, string $action): void
{
    if ($action === 'open_column_visibility') {
        // A Toolbar renderiza fora do <Deferred data="rows"> → o botão existe mesmo
        // antes das linhas chegarem. Evidência do menu: "Compra (ref)" só aparece na
        // lista de colunas (o cabeçalho da tabela mostra "Compra", sem o "(ref)").
        aguardarAlvoVisualCompras($page, <<<'JS'
(() => [...document.querySelectorAll('button')].some((el) => /Visibilidade da coluna/.test(el.textContent || '')))()
JS, 'botão de visibilidade de coluna');
        expect($page->script(<<<'JS'
(() => { const b = [...document.querySelectorAll('button')].find((el) => /Visibilidade da coluna/.test(el.textContent || '')); if (!b) return false; b.click(); return true; })()
JS))->toBeTrue();
        aguardarAlvoVisualCompras($page, <<<'JS'
(() => [...document.querySelectorAll('[role=menu]')].some((m) => /Compra \(ref\)/.test(m.textContent || '')))()
JS, 'menu de visibilidade de colunas');
        return;
    }
    if ($action === 'open_actions_menu') {
        aguardarAlvoVisualCompras($page, "!!document.querySelector('tbody tr')", 'linha de compra');
        expect($page->script(<<<'JS'
(() => {
  const b = [...document.querySelectorAll('tbody tr button')].find((el) => /Ações/.test(el.textContent || ''));
  if (!b) return false;
  b.click(); return true;
})()
JS))->toBeTrue();
        aguardarAlvoVisualCompras($page, <<<'JS'
(() => [...document.querySelectorAll('[role=menu]')].some((m) => /Ver pagamentos/.test(m.textContent || '')))()
JS, 'menu de ações da compra');
        return;
    }
    if ($action === 'open_drawer') {
        aguardarAlvoVisualCompras($page, "!!document.querySelector('tbody tr')", 'linha de compra');
        // Click no corpo da linha (célula sem botão) abre o drawer — a célula da Ação
        // tem stopPropagation, então miramos a primeira célula que NÃO tem botão.
        expect($page->script(<<<'JS'
(() => {
  const row = document.querySelector('tbody tr');
  if (!row) return false;
  const cell = [...row.children].find((td) => !td.querySelector('button')) || row;
  cell.click(); return true;
})()
JS))->toBeTrue();
        // Espera o drawer REAL (não o skeleton): "Total da compra" só existe depois
        // que o compra_detalhe (Inertia::defer) resolve.
        aguardarAlvoVisualCompras($page, <<<'JS'
(() => { const d = document.querySelector('aside.drawer'); return !!d && /Total da compra/.test(d.textContent || ''); })()
JS, 'drawer da compra carregado');
        return;
    }
    throw new RuntimeException("Ação visual não suportada: {$action}");
}

foreach (comprasFlowCases() as $label => [$screen, $flow, $viewport, $slug, $viewportId]) {
    it("{$label} bate com baseline pós-interação", function () use ($screen, $flow, $viewport, $slug, $viewportId, $grayZone) {
        $business = Business::find(1);
        $admin = $business ? User::where('business_id', 1)->orderBy('id')->first() : null;
        if (!$business || !$admin) test()->markTestSkipped('Tenant visual biz=1 sem admin.');
        semearCompraVisualCompras();

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($screen['route']))
            ->resize($viewport['width'], $viewport['height'])
            ->assertSee($screen['anchor']);
        executarFluxoCompras($page, $flow['action']);
        estabilizarVisualCompras($page);

        \Tests\Browser\Support\VisregThreshold::assertBandedScreenshot(
            page: $page,
            screenName: "{$slug} · {$flow['id']} · {$viewportId}",
            grayZone: $grayZone,
            baselineSuite: 'ComprasFlowBaselineTest',
        );
    });
}

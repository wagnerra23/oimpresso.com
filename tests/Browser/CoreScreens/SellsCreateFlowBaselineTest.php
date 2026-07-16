<?php

declare(strict_types=1);

/**
 * Baseline visual de fluxos críticos de Sells/Create (venda balcão — tela de 99% do volume,
 * ROTA LIVRE). Captura o estado APÓS a interação, mas ANTES de persistir. Cobre adicionar
 * produto, abrir a busca de produto, aplicar desconto e adicionar um pagamento, em 1024,
 * 1280 e 1440px. O contrato está em tests/Browser/visreg-flows-sells.json.
 *
 * DECISÃO DE DESIGN — opção (b): teste + manifesto + lint + dispatcher PRÓPRIOS, espelhando
 * o FinanceiroFlowBaselineTest. Motivo: as ações de Sells/Create (busca de produto, desconto,
 * pagamento) divergem por completo das do Financeiro (drawer, baixa, lote), e o gate do
 * Financeiro já é ENFORCING — generalizar o dispatcher exigiria editar aquele teste, risco
 * Tier 0 desnecessário. Todas as funções globais deste arquivo levam o sufixo `Sells` porque
 * o Pest carrega os arquivos de teste no MESMO processo — nome colidindo = fatal redeclare.
 *
 * CUIDADO (relógio de outro processo): o browser Playwright roda em subprocesso com relógio
 * REAL — `Carbon::setTestNow` só congela o processo do Pest. Sells/Create NÃO depende de
 * janela de data (é um formulário novo), mas dois campos exibem o relógio vivo: "Data da
 * venda" (datetime-local, já escondido pela regra base) e "Pago em" (type=text ligado ao
 * defaultDatetime — escondido por estabilizarVisualSells via placeholder). Foi a classe de
 * bug que custou 11 patches no Financeiro; aqui é neutralizada por construção.
 */

use App\Business;
use App\User;
use Database\Seeders\VisregTenantSeeder;

$grayZone = new \ArrayObject();

afterAll(function () use ($grayZone) {
    \Tests\Browser\Support\VisregThreshold::writeGrayZoneSummary($grayZone->getArrayCopy());
});

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    \Illuminate\Support\Facades\DB::purge('mysql');
});

function sellsCreateFlowCases(): array
{
    $path = dirname(__DIR__) . '/visreg-flows-sells.json';
    $manifest = json_decode((string) @file_get_contents($path), true);
    if (!is_array($manifest['viewports'] ?? null) || !is_array($manifest['screens'] ?? null)) {
        throw new RuntimeException("visreg-flows-sells.json ausente/inválido em {$path}");
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

function estabilizarVisualSells($page): void
{
    // Igual ao Financeiro + neutraliza o campo "Pago em" (type=text ligado ao relógio vivo
    // do processo do browser — o datetime-local "Data da venda" já cai na regra base).
    $page->script(<<<'JS'
(() => {
  const s = document.createElement('style');
  s.textContent = '* { transition:none !important; animation:none !important; caret-color:transparent !important; font-family:Arial,sans-serif !important; } body { -webkit-font-smoothing:antialiased !important; } select,input[type=date],input[type=datetime-local],input[type=time],.animate-spin { visibility:hidden !important; } input[placeholder="DD/MM/AAAA HH:mm"] { visibility:hidden !important; }';
  document.head.appendChild(s); return true;
})()
JS);
    $page->wait(0.5);
}

/** Aguarda o React montar/renderizar o alvo real do fluxo (mesmo contrato do Financeiro). */
function aguardarAlvoVisualSells($page, string $script, string $alvo): void
{
    for ($tentativa = 0; $tentativa < 20; $tentativa++) {
        if ($page->script($script) === true) {
            return;
        }

        $page->wait(0.25);
    }

    throw new RuntimeException("Alvo visual não ficou disponível: {$alvo}");
}

/** Adiciona o produto do seed (E2E-0001) ao carrinho — usado por 3 dos 4 fluxos.
 *
 * Digita no input controlado do React via native-setter + evento 'input' (o mesmo motivo
 * pelo qual o Financeiro dirige tudo por ->script): setar .value direto não dispara o
 * onChange do React. Depois clica a opção role=option e espera o indicador de saldo devedor.
 */
function sellsAdicionarProdutoVisual($page): void
{
    aguardarAlvoVisualSells($page, "!!document.querySelector('input[aria-label=\"Buscar produto\"]')", 'campo de busca de produto');

    expect($page->script(<<<'JS'
(() => {
  const input = document.querySelector('input[aria-label="Buscar produto"]');
  if (!input) return false;
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, 'E2E-0001');
  input.dispatchEvent(new Event('input', { bubbles: true }));
  return true;
})()
JS))->toBeTrue();

    aguardarAlvoVisualSells($page, <<<'JS'
(() => [...document.querySelectorAll('[role=option]')].some((o) => /Produto E2E Balcão/i.test(o.textContent || '')))()
JS, 'opção do produto no dropdown');

    expect($page->script(<<<'JS'
(() => {
  const opt = [...document.querySelectorAll('[role=option]')].find((o) => /Produto E2E Balcão/i.test(o.textContent || ''));
  if (!opt) return false;
  opt.click();
  return true;
})()
JS))->toBeTrue();

    // Produto no carrinho + sem pagamento ⇒ indicador "Venda a prazo — saldo devedor".
    aguardarAlvoVisualSells($page, <<<'JS'
(() => [...document.querySelectorAll('*')].some((n) => /Venda a prazo — saldo devedor/i.test(n.textContent || '')))()
JS, 'indicador de venda a prazo');
}

function executarFluxoSells($page, string $action): void
{
    if ($action === 'add_product') {
        sellsAdicionarProdutoVisual($page);
        return;
    }
    if ($action === 'open_product_search') {
        aguardarAlvoVisualSells($page, "!!document.querySelector('input[aria-label=\"Buscar produto\"]')", 'campo de busca de produto');
        expect($page->script(<<<'JS'
(() => {
  const input = document.querySelector('input[aria-label="Buscar produto"]');
  if (!input) return false;
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, 'E2E-0001');
  input.dispatchEvent(new Event('input', { bubbles: true }));
  return true;
})()
JS))->toBeTrue();
        // Captura o DROPDOWN aberto (não clica): estado da busca com a opção listada.
        // Espera o fetch do /products/list ASSENTAR (sem spinner) — senão a requisição em
        // voo deixa a página instável e o screenshot do Playwright estoura o timeout de 5s
        // (flake do run 29285231295, `abrir-busca-produto · wide`).
        aguardarAlvoVisualSells($page, <<<'JS'
(() => {
  const box = document.querySelector('[role=listbox]');
  const hasOpt = [...document.querySelectorAll('[role=option]')].some((o) => /Produto E2E Balcão/i.test(o.textContent || ''));
  const settled = !document.querySelector('.animate-spin'); // fetch terminou (Loader2 sumiu)
  return !!box && hasOpt && settled;
})()
JS, 'dropdown de busca aberto e estável');
        $page->wait(0.5); // settle extra do portal antes do screenshot
        return;
    }
    if ($action === 'apply_discount') {
        sellsAdicionarProdutoVisual($page);
        // Desconto do pedido (Resumo) — % default; 10% sobre R$ 100,00 = R$ 10,00.
        expect($page->script(<<<'JS'
(() => {
  const input = document.querySelector('#discount_amount');
  if (!input) return false;
  const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
  setter.call(input, '10');
  input.dispatchEvent(new Event('input', { bubbles: true }));
  return true;
})()
JS))->toBeTrue();
        aguardarAlvoVisualSells($page, <<<'JS'
(() => [...document.querySelectorAll('*')].some((n) => /Desconto do pedido/i.test(n.textContent || '')))()
JS, 'linha de desconto no resumo');
        return;
    }
    if ($action === 'add_payment') {
        sellsAdicionarProdutoVisual($page);
        expect($page->script(<<<'JS'
(() => {
  const btn = [...document.querySelectorAll('button')].find((b) => /Adicionar pagamento/i.test(b.textContent || ''));
  if (!btn) return false;
  btn.click();
  return true;
})()
JS))->toBeTrue();
        // 2 linhas de pagamento ⇒ 2 labels "Pago em".
        aguardarAlvoVisualSells($page, <<<'JS'
(() => [...document.querySelectorAll('label')].filter((l) => /Pago em/i.test(l.textContent || '')).length >= 2)()
JS, 'segunda linha de pagamento');
        return;
    }
    throw new RuntimeException("Ação visual não suportada: {$action}");
}

foreach (sellsCreateFlowCases() as $label => [$screen, $flow, $viewport, $slug, $viewportId]) {
    it("{$label} bate com baseline pós-interação", function () use ($screen, $flow, $viewport, $slug, $viewportId, $grayZone) {
        // Garante business+admin+produto E2E-0001 (idempotente). No CI o workflow já roda o
        // VisregTenantSeeder antes do server; aqui é defesa pra rodar direto no CT 100/local
        // sem setup extra. O seed é visível ao browser (mesmo MySQL oimpresso_test).
        (new VisregTenantSeeder())->run();

        $business = Business::find(1);
        $admin = $business ? User::where('business_id', 1)->orderBy('id')->first() : null;
        if (!$business || !$admin) test()->markTestSkipped('Tenant visual biz=1 sem admin.');

        $page = visit('/_visreg-login/' . $admin->id . '?to=' . urlencode($screen['route']))
            ->resize($viewport['width'], $viewport['height'])
            ->assertSee($screen['anchor']);
        executarFluxoSells($page, $flow['action']);
        estabilizarVisualSells($page);

        \Tests\Browser\Support\VisregThreshold::assertBandedScreenshot(
            page: $page,
            screenName: "{$slug} · {$flow['id']} · {$viewportId}",
            grayZone: $grayZone,
            baselineSuite: 'SellsCreateFlowBaselineTest',
        );
    });
}

<?php

declare(strict_types=1);
// Cobre UC-PUNI-07, UC-PUNI-08, UC-PUNI-09, UC-PUNI-10
// (resources/js/Pages/Produto/Unificado/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato do ÍNDICE do catálogo (`GET /products/unificado` → `Produto/Unificado/Index`) depois
 * de a tela virar a **Consulta de Produtos** do handoff de 2026-08-18: abas por TIPO, seis
 * KPI-filtros contados sobre a aba, disponibilidade com três estados.
 *
 * Irmão de `ProdutoUnificadoContratoTest`, que contrata a VISIBILIDADE (custo/preço/BOM/tenant).
 * Este contrata os invariantes que a mudança de layout introduziu — e que, sem teste, regridem
 * silenciosamente na próxima mexida na tela.
 *
 * ÂNCORA (contrato, NÃO implementação):
 *   1. HANDOFF §4.2 — a aba de tipo conta **só ativos**; `todos` é o cadastro inteiro
 *   2. HANDOFF §4.3 — os KPIs são contados **sobre a aba ativa**
 *   3. HANDOFF §4.6/§6 exceção 6 — disponibilidade tem QUATRO rótulos, e "não estocável" é
 *      estado próprio: `stockQty = null` ≠ `stockQty = 0`
 *   4. HANDOFF §9 + AR-PROD-015 — o contador de "Margem baixa" É leitura da estrutura de custo:
 *      gatear a coluna e deixar o KPI entrega o mesmo dado agregado
 *   5. ADR 0093 `[T0]` — `App\Product` não tem global scope; o escopo é declarado em cada query
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5): todo assert de ausência tem pré-condição provando que a prop
 *    CHEGOU e que a linha semeada ESTÁ nela. Sem isso, "não tem margem" passaria só porque a
 *    prop não veio — mediria não-execução.
 *
 * ⛔ Multi-tenant Tier 0: tenant canônico de teste (`seededTenant()`); cross-tenant contra o 2º
 *    business seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

function indiceContratoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * GET /products/unificado no branch Inertia, pedindo props por partial reload.
 *
 * @param  list<string>  $props
 * @param  array<string,mixed>  $query
 * @return array<string,mixed>  o objeto `props` da página
 */
function indiceContratoProps(object $test, array $props, array $query = []): array
{
    $url = '/products/unificado' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => indiceContratoInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Unificado/Index',
        'X-Inertia-Partial-Data' => implode(',', $props),
    ])->get($url);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()->toHaveKey('props');

    return $page['props'];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.view', 'web');
    $this->user->givePermissionTo(['product.view']);
});

// =============================================================================
// UC-PUNI-07 — o CONTADOR também é dado de custo: sem `view_purchase_price` o card
//   "Margem baixa" não é montado, e a chave não viaja.
// =============================================================================

it('UC-PUNI-07 · o KPI de margem baixa não é servido a quem não pode ver custo', function () {
    if ($this->user->can('view_purchase_price')) {
        $this->markTestSkipped('User seedado JÁ tem view_purchase_price — sem cenário pra provar o gate.');
    }

    EstoqueFixture::singleProduct((int) $this->business->id);

    $props = indiceContratoProps($this, ['kpis']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop chegou e traz os contadores que NÃO dependem de custo.
    expect($props)->toHaveKey('kpis');
    expect($props['kpis'])->toBeArray()->toHaveKeys(['ativos', 'min', 'zero', 'parado', 'total']);

    expect(array_key_exists('margem', (array) $props['kpis']))->toBeFalse(
        'O contador "Margem baixa" chegou ao navegador para usuário SEM view_purchase_price. '
        . 'Quantos itens estão sob o piso É uma leitura da estrutura de custo — gatear a coluna e '
        . 'deixar o KPI entrega o mesmo dado agregado (handoff §9 + AR-PROD-015).'
    );
});

// =============================================================================
// UC-PUNI-08 — a aba recorta por TIPO derivado, e conta SÓ ativos. `todos` é o cadastro
//   inteiro. Contador que discorda da lista destrói a confiança na tela (handoff §9).
// =============================================================================

it('UC-PUNI-08 · a aba de tipo recorta por tipo derivado e conta só ativos', function () {
    $bizId = (int) $this->business->id;

    // `enable_stock = 0` é o que o UltimatePOS tem pra "não guarda saldo" — o tipo do item é
    // DERIVADO disso (mais `type = combo` e `not_for_selling`), não uma coluna própria.
    $estocavel = EstoqueFixture::singleProduct($bizId, true);
    $servico = EstoqueFixture::singleProduct($bizId, false);

    $abaProdutos = indiceContratoProps($this, ['produtos', 'abas'], ['aba' => 'produtos']);
    $idsProdutos = collect($abaProdutos['produtos'])->pluck('id')->all();

    expect(in_array($estocavel->productId, $idsProdutos, true))->toBeTrue(
        'O item estocável não apareceu na aba "Produtos" — a derivação de tipo não está classificando '
        . 'enable_stock=1 como produto.'
    );
    expect(in_array($servico->productId, $idsProdutos, true))->toBeFalse(
        'O item SEM controle de estoque apareceu na aba "Produtos". Ele é serviço (handoff §6 exceção 6) '
        . 'e a aba de tipo não é filtro decorativo.'
    );

    $abaServicos = indiceContratoProps($this, ['produtos'], ['aba' => 'servicos']);
    $idsServicos = collect($abaServicos['produtos'])->pluck('id')->all();
    expect(in_array($servico->productId, $idsServicos, true))->toBeTrue(
        'O item sem controle de estoque não apareceu na aba "Serviços".'
    );

    // A contagem da aba vem da MESMA subconsulta da listagem — se divergir, o número no topo
    // discorda da lista embaixo.
    expect($abaProdutos)->toHaveKey('abas');
    expect((int) $abaProdutos['abas']['todos'])->toBeGreaterThanOrEqual(
        (int) $abaProdutos['abas']['produtos'],
        'A aba "Todos" contou MENOS que a aba "Produtos" — `todos` é o cadastro inteiro, logo é o teto.'
    );
});

// =============================================================================
// UC-PUNI-09 — disponibilidade tem TRÊS estados que a tela precisa separar:
//   null = não estocável · 0 = sem estoque (bloqueia venda, `[V0]`) · >0 = saldo.
// =============================================================================

it('UC-PUNI-09 · não estocável e sem estoque são estados diferentes no payload', function () {
    $bizId = (int) $this->business->id;

    $estocavel = EstoqueFixture::singleProduct($bizId, true);
    $servico = EstoqueFixture::singleProduct($bizId, false);

    $props = indiceContratoProps($this, ['produtos'], ['aba' => 'todos']);
    $linhas = collect($props['produtos']);

    $linhaEstocavel = (array) $linhas->firstWhere('id', $estocavel->productId);
    $linhaServico = (array) $linhas->firstWhere('id', $servico->productId);

    expect($linhaEstocavel)->not->toBeEmpty();
    expect($linhaServico)->not->toBeEmpty();

    expect($linhaServico['stockQty'])->toBeNull(
        'O item que não controla estoque veio com saldo numérico. Imprimir 0 pra um serviço afirma '
        . '"sem estoque" — e "sem estoque" bloqueia venda (dado [V0]).'
    );
    expect($linhaEstocavel['stockQty'])->not->toBeNull(
        'O item que CONTROLA estoque veio com saldo nulo. "—" esconde o bloqueio de venda: sem saldo '
        . 'conhecido o operador não sabe se pode vender.'
    );
});

// =============================================================================
// UC-PUNI-10 `[T0]` — as props NOVAS (abas/kpis/totalDaAba) são agregações: sem escopo
//   declarado elas somam o catálogo do vizinho sem mostrar uma linha dele.
// =============================================================================

it('UC-PUNI-10 · as agregações do índice não contam produto de outro business', function () {
    $outroBiz = (int) DB::table('business')
        ->where('id', '!=', $this->business->id)
        ->value('id');

    if (! $outroBiz) {
        $this->markTestSkipped('Só há 1 business no seed — sem cenário cross-tenant pra provar o isolamento.');
    }

    $antes = indiceContratoProps($this, ['abas', 'totalDaAba'], ['aba' => 'todos']);
    $todosAntes = (int) $antes['abas']['todos'];
    $totalAntes = (int) $antes['totalDaAba'];

    // Três produtos no VIZINHO. Se alguma agregação não escopar, o número sobe sem que nenhuma
    // linha do intruso apareça — vazamento silencioso, o pior formato.
    for ($i = 0; $i < 3; $i++) {
        EstoqueFixture::singleProduct($outroBiz);
    }

    $depois = indiceContratoProps($this, ['abas', 'totalDaAba'], ['aba' => 'todos']);

    expect((int) $depois['abas']['todos'])->toBe(
        $todosAntes,
        'A contagem das abas subiu depois de cadastrar produto em OUTRO business — a agregação não '
        . 'está escopada por business_id (Tier 0 IRREVOGÁVEL, ADR 0093).'
    );
    expect((int) $depois['totalDaAba'])->toBe(
        $totalAntes,
        'O total do recorte subiu com produto de outro business — o contador da toolbar vaza o '
        . 'tamanho do catálogo do vizinho.'
    );
});

<?php

declare(strict_types=1);
// Cobre UC-PIDX-01, UC-PIDX-02, UC-PIDX-03, UC-PIDX-04, UC-PIDX-05, UC-PIDX-06
// (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Product;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento da LISTA do catálogo (`GET /products` → `Produto/Index`).
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON     — `CU-PROD-15` (consultar/listar) + `CU-PROD-10` `[T0]` (multi-tenant) do SDD §6.1
 *                  (memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md)
 *   2. BLADE     — o MESMO método `ProductController@index`, nos outros dois branches:
 *                  `request()->ajax()` → DataTables server-side (:73-312) e
 *                  `view('product.index')` (:361) → resources/views/product/index.blade.php
 *   3. DELPHI    — `AR-PROD-003` (inativo não some do cadastro), `AR-PROD-015` (custo/margem SOMEM
 *                  sem permissão), `AR-PROD-022` (inativo volta por FILTRO), `AR-PROD-023`
 *                  (Consultar = busca/listagem) da ANTI-REGRESSAO-cadastro-produto-legacy.md
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Documentar só o React CARIMBARIA a perda. A lista Blade — o outro branch DO MESMO MÉTODO —
 * gateia preço de compra e de venda atrás de `@can('view_purchase_price')` /
 * `@can('access_default_selling_price')` (index.blade.php:287,294) e pagina server-side sobre o
 * catálogo inteiro. O branch Inertia (:342-359) não consulta permissão de preço NENHUMA
 * (varredura contada de `view_purchase_price|access_default_selling_price` em
 * ProductController.php: 0 ocorrências) e corta a lista em `limit(200)` (:447) sem paginação.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR/Blade), não do
 *    código. Se nascer vermelho, o vermelho É o achado — não se ajusta o teste ao código.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): o servidor IGNORA `busca`/`mostrarInativos` (devolve
 *    tudo). Logo, um caso que só perguntasse "o produto buscado veio?" passaria POR ACIDENTE —
 *    ele vem porque vem tudo. Todo filtro é provado pelo par (o que casa VEM + o que não casa
 *    FICA DE FORA), e toda prop deferida tem pré-condição de que CHEGOU.
 *
 * ⛔ Escopo honesto: as telas React do Produto ainda NÃO são alcançáveis em prod (a sidebar usa
 *    `<a href>` puro, sem header `X-Inertia` → cai no Blade). Vermelho aqui é BLOQUEADOR DE
 *    MIGRAÇÃO (gate MWART F5, ADR 0104), não incidente de produção.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business seedado.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Version que o servidor usa pro check Inertia (mesmo helper do ProdutoShowContratoTest):
 * mandar a MESMA version evita o 409 (version mismatch) antes de o controller rodar.
 */
function produtoIndexInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * GET /products no branch Inertia, pedindo as props DEFERIDAS por partial reload.
 *
 * @param  list<string>  $props
 * @return array<string,mixed>  o objeto `props` da página
 */
function produtoIndexProps(object $test, array $props, array $query = []): array
{
    $url = '/products' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoIndexInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Index',
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
        $this->business = $this->seededTenant(); // biz=1 (ADR 0101 — nunca biz=4).
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
// UC-PIDX-01 — Blade DataTables (sem limit, paginação server-side) + AR-PROD-022/023:
//   todo produto do catálogo é alcançável pela lista; o que sai da vista sai por FILTRO
//   declarado, nunca por corte invisível.
// =============================================================================

it('UC-PIDX-01 · lista alcança todo o catálogo (sem corte silencioso em 200)', function () {
    $bizId = (int) $this->business->id;

    // Cenário: catálogo maior que o corte. INSERT direto (bulk) — produto sem variação
    // aparece igual no builder (leftJoin + whereNull('v.deleted_at') passa com NULL).
    $unitId = EstoqueFixture::unitId($bizId);
    $createdBy = EstoqueFixture::userId($bizId);
    $marcador = 'ZZZZ-ULTIMO-' . strtoupper(bin2hex(random_bytes(3)));

    $lote = [];
    for ($i = 1; $i <= 205; $i++) {
        $lote[] = [
            // 'AAAA…' garante que o marcador 'ZZZZ…' fique no FIM da ordem por nome (:446),
            // que é exatamente onde o `limit(200)` corta.
            'name' => 'AAAA Carga Lista ' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'business_id' => $bizId,
            'type' => 'single',
            'unit_id' => $unitId,
            'tax_type' => 'exclusive',
            'enable_stock' => 0,
            'sku' => 'CARGA-' . strtoupper(bin2hex(random_bytes(4))),
            'barcode_type' => 'C128',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    $lote[] = [
        'name' => $marcador,
        'business_id' => $bizId,
        'type' => 'single',
        'unit_id' => $unitId,
        'tax_type' => 'exclusive',
        'enable_stock' => 0,
        'sku' => 'CARGA-MARCADOR-' . strtoupper(bin2hex(random_bytes(3))),
        'barcode_type' => 'C128',
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    DB::table('products')->insert($lote);

    $props = produtoIndexProps($this, ['rows', 'kpis']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: as props deferidas chegaram e o cenário É maior que o corte.
    // Sem isto, "faltou produto" poderia ser só "não veio nada".
    expect($props)->toHaveKey('rows')->toHaveKey('kpis');
    expect($props['rows'])->toBeArray();
    expect(count($props['rows']))->toBeGreaterThan(0);
    expect((int) $props['kpis']['total'])->toBeGreaterThan(200);

    // O CONTRATO (a) — reescrito 2026-07-26. A versão anterior era
    // `count(rows) === kpis.total`, que PROIBIA o fix legítimo: o próprio aceite do UC permite
    // "paginação, scroll infinito ou busca server-side", e qualquer um deles faz count ≠ total.
    // Pior: colidia com o UC-PIDX-06 (filtrar inativos por default), que muda o count de propósito.
    //
    // O contrato honesto é: OU a tela entrega tudo que anuncia, OU oferece caminho pro resto.
    // O que ela não pode é truncar em silêncio.
    $entregou = count($props['rows']);
    $anunciou = (int) $props['kpis']['total'];

    if ($entregou < $anunciou) {
        $temCaminhoPraOResto = collect(array_keys($props))
            ->contains(fn ($k) => (bool) preg_match('/(pagin|page|links|cursor|next|scroll|meta)/i', (string) $k));

        expect($temCaminhoPraOResto)->toBeTrue(
            "A tela anuncia {$anunciou} produtos e entrega {$entregou} sem oferecer caminho pro resto "
            . '(nenhuma prop de paginação/cursor). O produto além do corte SOME em silêncio.'
        );
    }

    // O CONTRATO (b): o produto do FIM do alfabeto é alcançável. É o caso que o operador vive —
    // cadastrou, não achou, cadastrou de novo (duplicata).
    $nomes = array_column($props['rows'], 'name');
    expect($nomes)->toContain($marcador);
});

// =============================================================================
// UC-PIDX-02 — Blade filterColumn (:296-301: whereHas variations sub_sku) + CU-PROD-02:
//   a busca roda no SERVIDOR e enxerga o SKU da variação, não só o do produto-pai.
// =============================================================================

it('UC-PIDX-02 · busca é resolvida no servidor e acha pelo SKU da variação', function () {
    $bizId = (int) $this->business->id;

    $alvo = EstoqueFixture::variableProduct($bizId, 2);          // sub_sku = "<sku>-1", "<sku>-2"
    $ruido = EstoqueFixture::singleProduct($bizId, false);        // não casa com o termo

    $subSku = (string) DB::table('variations')
        ->where('id', $alvo->variations[0]['variation_id'])
        ->value('sub_sku');

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (a): o cenário existe — há sub_sku e ele é distinto do ruído.
    expect($subSku)->not->toBeEmpty();
    $skuRuido = (string) Product::where('id', $ruido->productId)->value('sku');
    expect(str_contains($skuRuido, $subSku))->toBeFalse();

    $props = produtoIndexProps($this, ['rows'], ['busca' => $subSku]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (b): veio payload.
    expect($props)->toHaveKey('rows');
    expect($props['rows'])->toBeArray();

    $ids = array_map('intval', array_column($props['rows'], 'id'));

    // O CONTRATO (a): quem casa VEM — buscar pela etiqueta da variação acha o produto.
    expect($ids)->toContain($alvo->productId);

    // O CONTRATO (b): quem NÃO casa FICA DE FORA. Sem este assert o caso passaria por acidente,
    // porque hoje o servidor devolve o catálogo inteiro ignorando `busca` (:345 lido, nunca usado).
    expect($ids)->not->toContain($ruido->productId);
});

// =============================================================================
// UC-PIDX-03 — Blade index.blade.php:287,294 (@can) + AR-PROD-015 (o campo SOME):
//   preço de venda e custo não viajam pra quem não tem direito de vê-los.
// =============================================================================

it('UC-PIDX-03 · lista não entrega preço nem custo a quem não pode vê-los', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: se o user seedado JÁ tem os direitos, este teste mediria o caso
    // errado e "passaria" sem provar nada.
    if ($this->user->can('view_purchase_price') && $this->user->can('access_default_selling_price')) {
        $this->markTestSkipped('User seedado JÁ tem as duas permissões de preço — sem cenário pra provar o gate.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    // VALORES SENTINELA (2026-07-26): asserir por CHAVE (`not->toHaveKey('cost')`) deixa o
    // vazamento PASSAR se alguém só renomear o campo. O contrato é "o valor não viaja", não
    // "a chave se chama X" — então gravamos valores improváveis e varremos o payload por VALOR.
    $custoSentinela = 137.77;
    $vendaSentinela = 293.31;
    DB::table('variations')
        ->whereIn('id', array_column($produto->variations, 'variation_id'))
        ->update([
            // `dpp_inc_tax` e `sell_price_inc_tax` são as colunas que o builder REALMENTE lê
            // (`buildProdutoIndexRows`: `MIN(v.dpp_inc_tax) as min_cost`, `MIN(v.sell_price_inc_tax)
            // as min_price`). Semear só `default_purchase_price` deixaria a sentinela invisível ao
            // caminho sob teste — o assert passaria VERDE com o custo vazando como o valor do fixture.
            'default_purchase_price' => $custoSentinela,
            'dpp_inc_tax' => $custoSentinela,
            'default_sell_price' => $vendaSentinela,
            'sell_price_inc_tax' => $vendaSentinela,
        ]);

    $props = produtoIndexProps($this, ['rows']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a linha do produto recém-criado CHEGOU — "não tem preço" não pode
    // ser verdade só porque não veio linha nenhuma.
    expect($props)->toHaveKey('rows');
    $linha = collect($props['rows'])->firstWhere('id', $produto->productId);
    expect($linha)->not->toBeNull();

    // O CONTRATO: no Blade a coluna nem é renderizada; no Delphi o campo SOME da tela
    // (AR-PROD-015) — não é read-only, é ausência. `margin` cai junto porque é derivado do
    // custo (:453): entregá-lo é entregar o custo por outro caminho.
    $valoresDaLinha = array_values(array_filter(
        array_map(
            static fn ($v) => is_numeric($v) ? round((float) $v, 2) : null,
            array_values((array) $linha)
        ),
        static fn ($v) => $v !== null
    ));

    if (! $this->user->can('view_purchase_price')) {
        expect(in_array(round($custoSentinela, 2), $valoresDaLinha, true))->toBeFalse(
            "O custo vazou na linha da lista para usuário SEM view_purchase_price (sentinela {$custoSentinela}). "
            . 'Renomear a chave não faz este assert passar — ele varre por valor.'
        );

        // `margin` cai junto porque é derivada do custo: entregá-la é entregar o custo por outro
        // caminho (quem tem a venda deduz o custo). O `round()` do builder é INTEIRO (sem casas),
        // então a comparação tem que ser no mesmo grão — senão o assert nunca casa e fica inerte.
        $margemDerivada = (float) round(
            (($vendaSentinela - $custoSentinela) / $vendaSentinela) * 100
        );
        expect(in_array($margemDerivada, $valoresDaLinha, true))->toBeFalse(
            "A margem ({$margemDerivada}%) entrega o custo por dedução, mesmo sem o campo de custo explícito."
        );
    }

    if (! $this->user->can('access_default_selling_price')) {
        expect(in_array(round($vendaSentinela, 2), $valoresDaLinha, true))->toBeFalse(
            "O preço de venda vazou na linha para usuário SEM access_default_selling_price (sentinela {$vendaSentinela})."
        );
    }
});

// =============================================================================
// UC-PIDX-04 — CU-PROD-10 `[T0]` + ADR 0093 + charter §Anti-hooks
//   ("❌ Não acessa produto de outro business_id"): a lista não enxerga o vizinho.
//   GUARD (verde esperado) — o valor é TRAVAR, porque `App\Product` não tem global scope.
// =============================================================================

it('UC-PIDX-04 · lista e KPIs só enxergam o business atual (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meu = EstoqueFixture::singleProduct($bizId);
    $alheio = EstoqueFixture::singleProduct($outroBizId);

    $props = produtoIndexProps($this, ['rows', 'kpis']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o payload chegou COM o meu produto — senão "o alheio não veio"
    // seria verdade por lista vazia.
    expect($props['rows'])->toBeArray();
    $ids = array_map('intval', array_column($props['rows'], 'id'));
    expect($ids)->toContain($meu->productId);

    // O CONTRATO (a): nenhuma linha do vizinho.
    expect($ids)->not->toContain($alheio->productId);

    // O CONTRATO (b): o KPI conta o MEU catálogo, não a soma dos dois.
    $totalDoBusiness = (int) Product::where('business_id', $bizId)
        ->where('type', '!=', 'modifier')
        ->count();
    expect((int) $props['kpis']['total'])->toBe($totalDoBusiness);
});

// =============================================================================
// UC-PIDX-05 — Index.charter §Automation Anti-hooks ("❌ Não escreve no banco (read-only)")
//   + AR-PROD-064 (append-only): abrir a lista é leitura pura.
//   GUARD — o irmão `productStockHistory` FAZ UPDATE dentro de um GET (CU-PROD-11).
// =============================================================================

it('UC-PIDX-05 · abrir a lista não escreve no banco (GET é leitura pura)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $antes = (string) Product::where('id', $produto->productId)->value('updated_at');
    expect($antes)->not->toBeEmpty();

    $props = produtoIndexProps($this, ['rows', 'kpis', 'categorias']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: as 3 props deferidas foram REALMENTE resolvidas (a closure rodou).
    // Sem isto, "não escreveu" seria verdade porque nada executou.
    expect($props)->toHaveKey('rows')->toHaveKey('kpis')->toHaveKey('categorias');
    $ids = array_map('intval', array_column($props['rows'], 'id'));
    expect($ids)->toContain($produto->productId);

    $depois = (string) Product::where('id', $produto->productId)->value('updated_at');
    expect($depois)->toBe($antes);
});

// =============================================================================
// UC-PIDX-06 — Blade `active_state` server-side (:173-179) + AR-PROD-003/022
//   ("some da lista, mas fica acessível por FILTRO"): o toggle é do SERVIDOR.
// =============================================================================

it('UC-PIDX-06 · filtro "Mostrar inativos" é resolvido no servidor', function () {
    $bizId = (int) $this->business->id;

    $ativo = EstoqueFixture::singleProduct($bizId, false);
    $inativo = EstoqueFixture::singleProduct($bizId, false);
    Product::where('id', $inativo->productId)->update(['is_inactive' => 1]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o cenário está montado como o caso descreve.
    expect((int) Product::where('id', $inativo->productId)->value('is_inactive'))->toBe(1);

    // Toggle DESLIGADO (o default do charter: "Mostrar inativos (default: oculto)").
    $props = produtoIndexProps($this, ['rows'], ['mostrarInativos' => 0]);
    $ids = array_map('intval', array_column($props['rows'], 'id'));

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o ativo VEIO — senão a ausência do inativo não prova filtro.
    expect($ids)->toContain($ativo->productId);

    // O CONTRATO (a): com o toggle desligado, o inativo não vem no payload.
    expect($ids)->not->toContain($inativo->productId);

    // O CONTRATO (b): ligado, ele volta — AR-PROD-022 ("acessível por filtro"), marcado.
    $propsComInativos = produtoIndexProps($this, ['rows'], ['mostrarInativos' => 1]);
    $linha = collect($propsComInativos['rows'])->firstWhere('id', $inativo->productId);
    expect($linha)->not->toBeNull();
    expect($linha['active'])->toBeFalse();
});

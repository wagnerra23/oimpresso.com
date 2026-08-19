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

    $abaProdutos = indiceContratoProps($this, ['produtos', 'abas'], ['aba' => 'produtos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 100]);
    $idsProdutos = collect($abaProdutos['produtos'])->pluck('id')->all();

    expect(in_array($estocavel->productId, $idsProdutos, true))->toBeTrue(
        'O item estocável não apareceu na aba "Produtos" — a derivação de tipo não está classificando '
        . 'enable_stock=1 como produto.'
    );
    expect(in_array($servico->productId, $idsProdutos, true))->toBeFalse(
        'O item SEM controle de estoque apareceu na aba "Produtos". Ele é serviço (handoff §6 exceção 6) '
        . 'e a aba de tipo não é filtro decorativo.'
    );

    $abaServicos = indiceContratoProps($this, ['produtos'], ['aba' => 'servicos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 100]);
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

    $props = indiceContratoProps($this, ['produtos'], ['aba' => 'todos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 100]);
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

// =============================================================================
// UC-PUNI-11 — paginação server-side (handoff V2 §4.8 e §9). Três invariantes:
//   a) `produtos` é a FATIA (≤ porPagina) e `totalDaAba` é o total do RECORTE
//   b) páginas não se sobrepõem — sem ordem estável, item aparece duas vezes ou some
//   c) `porPagina` fora da lista branca cai no padrão; `ordem` fora da lista é ignorada
// =============================================================================

it('UC-PUNI-11 · a fatia respeita porPagina e o total continua sendo o do recorte', function () {
    // Seis itens do mesmo tenant garantem mais de uma página com porPagina=10 quando somados
    // ao que o seed já tem; com o tenant vazio, garantem pelo menos a fatia curta.
    for ($i = 0; $i < 6; $i++) {
        EstoqueFixture::singleProduct((int) $this->business->id);
    }

    $consulta = ['aba' => 'todos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 10, 'pagina' => 1];
    $props = indiceContratoProps($this, ['produtos', 'totalDaAba'], $consulta);

    expect($props)->toHaveKey('produtos')->toHaveKey('totalDaAba');

    $fatia = collect($props['produtos']);
    $total = (int) $props['totalDaAba'];

    expect($fatia->count())->toBeLessThanOrEqual(
        10,
        'A resposta trouxe mais linhas que `porPagina`. O LIMIT não está sendo aplicado — com o '
        . 'catálogo real isso é a tela inteira vindo num payload só.'
    );
    expect($total)->toBeGreaterThanOrEqual(
        $fatia->count(),
        'O total do recorte veio MENOR que a fatia. `totalDaAba` é o total autoritativo (V2 §9): '
        . 'se ele contar menos que a página, o rodapé escreve "1–10 de 6".'
    );
    expect($total)->toBeGreaterThanOrEqual(6, 'Os 6 itens semeados não entraram na contagem do recorte.');
});

it('UC-PUNI-11B · página 2 não repete nenhuma linha da página 1', function () {
    // 12 itens com `porPagina = 10` garante duas páginas. O valor TEM de estar na lista branca
    // (10/25/50/100): a 1ª versão deste teste usava `porPagina = 3` e o controller, certíssimo,
    // caiu no padrão 25 e devolveu as 6 numa página só — o teste media a validação, não a
    // paginação. Quem prova a lista branca é o UC-PUNI-11D; aqui o valor é válido de propósito.
    for ($i = 0; $i < 12; $i++) {
        EstoqueFixture::singleProduct((int) $this->business->id);
    }

    $base = ['aba' => 'todos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 10];

    $p1 = collect(indiceContratoProps($this, ['produtos'], $base + ['pagina' => 1])['produtos'])->pluck('id');
    $p2 = collect(indiceContratoProps($this, ['produtos'], $base + ['pagina' => 2])['produtos'])->pluck('id');

    expect($p1)->toHaveCount(10, 'A página 1 não veio cheia — sem ela o teste de sobreposição não mede nada.');
    expect($p2->isNotEmpty())->toBeTrue('A página 2 veio vazia com 12 itens semeados e porPagina=10.');

    expect($p1->intersect($p2)->all())->toBe(
        [],
        'Item apareceu nas duas páginas. Sem desempate estável no ORDER BY, o MySQL é livre pra '
        . 'devolver ordens diferentes entre as duas consultas — e aí item repete numa página e '
        . 'some da outra.'
    );
});

it('UC-PUNI-11C · ordenar por custo é ignorado pra quem não pode ver custo', function () {
    if ($this->user->can('view_purchase_price')) {
        $this->markTestSkipped('User seedado JÁ tem view_purchase_price — sem cenário pra provar o gate.');
    }

    // Dois itens com custos MUITO diferentes e preço igual: se a ordenação por custo passasse,
    // a posição na lista denunciaria qual é o mais caro — o número invisível vazaria por
    // ordem, que é o mesmo vazamento de AR-PROD-015 com um passo a mais.
    $barato = EstoqueFixture::singleProduct((int) $this->business->id);
    $caro = EstoqueFixture::singleProduct((int) $this->business->id);
    DB::table('variations')->whereIn('id', array_column($barato->variations, 'variation_id'))
        ->update(['dpp_inc_tax' => 1.0, 'default_purchase_price' => 1.0]);
    DB::table('variations')->whereIn('id', array_column($caro->variations, 'variation_id'))
        ->update(['dpp_inc_tax' => 9999.0, 'default_purchase_price' => 9999.0]);

    $base = ['aba' => 'todos', 'busca' => 'Produto Estoque Fix', 'porPagina' => 100];

    $asc = collect(indiceContratoProps($this, ['produtos'], $base + ['ordem' => 'custo', 'dir' => 'asc'])['produtos'])->pluck('id')->all();
    $desc = collect(indiceContratoProps($this, ['produtos'], $base + ['ordem' => 'custo', 'dir' => 'desc'])['produtos'])->pluck('id')->all();

    expect($asc)->not->toBeEmpty('O recorte veio vazio — sem linhas, "a ordem não mudou" mediria não-execução.');
    expect($asc)->toBe(
        $desc,
        'Pedir custo asc e custo desc devolveu ordens DIFERENTES para quem não pode ver custo. '
        . 'A ordem denuncia o valor: o primeiro da lista é o mais barato. O controller precisa '
        . 'cair no padrão (nome) quando o perfil não tem `view_purchase_price`.'
    );
});

it('UC-PUNI-11D · porPagina e ordem fora da lista branca caem no padrão', function () {
    EstoqueFixture::singleProduct((int) $this->business->id);

    // `porPagina=99999` seria varredura do catálogo inteiro; `ordem=c.id; DROP` seria injeção
    // no ORDER BY. Os dois têm de morrer na validação, não no banco.
    $props = indiceContratoProps($this, ['filters', 'produtos'], [
        'aba' => 'todos',
        'busca' => 'Produto Estoque Fix',
        'porPagina' => 99999,
        'ordem' => 'c.id; DROP TABLE products',
    ]);

    expect((int) $props['filters']['porPagina'])->toBe(
        25,
        '`porPagina` fora da lista branca não caiu no padrão — a URL vira alavanca pra varrer o catálogo.'
    );
    expect($props['filters']['ordem'])->toBe(
        '',
        '`ordem` fora da lista branca sobreviveu à validação — isso entra cru no ORDER BY.'
    );
});

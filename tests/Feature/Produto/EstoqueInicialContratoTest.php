<?php

declare(strict_types=1);
// Cobre UC-PINIC-01, UC-PINIC-02, UC-PINIC-03, UC-PINIC-04
// (memory/requisitos/Produto/_telas/estoque-inicial.casos.md) — rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;
use Tests\Support\EstoqueProduto;

/**
 * Contrato de comportamento do ESTOQUE INICIAL (`GET /opening-stock/add/{product}` →
 * `POST /opening-stock/save` → `OpeningStockController@save`).
 *
 * ⚠️ FLUXO SEM TELA REACT. Varredura contada nos `.tsx` de `resources/js/Pages/Produto/`
 *    (2026-07-27, sha 16606e35c4): `opening_stock` aparece **2×** e as duas são o MESMO
 *    booleano de permissão nas props (`Edit.tsx:75`, `Index.tsx:29`) — nenhuma tela React informa,
 *    edita ou exibe estoque inicial. O fluxo é 100% Blade (`opening_stock/add.blade.php`
 *    + `form-part.blade.php`), e é ele que a Larissa usa. Por isso o contrato mora em
 *    `memory/requisitos/Produto/_telas/`, não ao lado de um `.tsx`.
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON  — `CU-PROD-04` (estoque inicial + localização + alerta + validade/lote) e
 *               `CU-PROD-10` `[T0]` do SDD §6.1
 *               (memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) +
 *               DOC-RAIZ-ESTOQUE §3 (`opening_stock` → ENTRA) e §7 (INV-6 saldo por
 *               variação × local).
 *   2. BLADE  — `resources/views/opening_stock/form-part.blade.php`, que define o payload
 *               REAL do writer: `stocks[location_id][variation_id][k][quantity|purchase_price|
 *               exp_date|lot_number|transaction_date|purchase_line_note|purchase_line_id]`,
 *               todos como TEXTO formatado (`@format_quantity` / `@num_format`).
 *   3. DELPHI — `AR-PROD-055` (Local de Estoque Padrão) · `AR-PROD-053` (Estoque Mín./Máx.
 *               como base de alerta) · `AR-PROD-051` `[V0]` (Disponível é saldo por local)
 *               da ANTI-REGRESSAO-cadastro-produto-legacy.md (Office Comercial 2026.1.1.38).
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O `CU-PROD-04` estava marcado ✅ no SDD e não tinha **nenhum** UC (lacuna do painel
 * `_STATUS-GENERATED.md`). A única cobertura existente é o `EstoqueOpeningStockTest`
 * (`UC-EST-07`), que exercita o SERVIÇO `ProductUtil::addSingleProductOpeningStock` —
 * o caminho do cadastro rápido/`store()`. O caminho que o operador percorre de fato
 * (`OpeningStockController@save`, chamado pela Blade) **não tinha teste nenhum**, e é
 * ele que carrega o guard de local, o parser pt-BR da quantidade e o lote/validade.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR/Blade/
 *    DOC-RAIZ), NÃO do `save()`. Se nascer vermelho, o vermelho É o achado — não se ajusta
 *    o teste ao código. Eixo ESTOQUE → o fix é decisão [W] sob a REGRA MESTRE (2 caminhos +
 *    tabela antes→depois), nunca conserto silencioso aqui.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não aconteceu" carrega
 *    pré-condição provando que a OPERAÇÃO aconteceu (o saldo legítimo entrou). Sem isso o
 *    teste mede não-execução e chama de isolamento.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business
 *    seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/** Saldo corrente do par (variação × local), ou null se a linha nem existe. */
function inicialSaldo(int $variationId, int $locationId): ?float
{
    $row = DB::table('variation_location_details')
        ->where('variation_id', $variationId)
        ->where('location_id', $locationId)
        ->first();

    return $row ? (float) $row->qty_available : null;
}

/**
 * Um bloco de linha no formato que a **Blade legada** envia. É o INPUT do contrato,
 * não o assert — por isso os números vão como TEXTO pt-BR, como `@format_quantity`
 * e `@num_format` os renderizam.
 */
function inicialLinhaBlade(array $over = []): array
{
    return array_merge([
        'quantity' => '10',
        'purchase_price' => '5,00',
    ], $over);
}

/** Payload completo de `POST /opening-stock/save` pra UMA variação num local. */
function inicialPayload(EstoqueProduto $p, int $locationId, array $linha = []): array
{
    return [
        'product_id' => $p->productId,
        'stocks' => [
            $locationId => [
                $p->variationId(0) => [
                    0 => inicialLinhaBlade($linha),
                ],
            ],
        ],
    ];
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

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    // ARRANGE, não assert: `OpeningStockController@save` lê `financial_year.start` da sessão
    // (`:167`) e o passa pro `Carbon::createFromFormat('Y-m-d', …)`. Em produção quem popula é o
    // middleware `SetSessionData` — que só reconstrói a sessão quando ela NÃO tem o bloco `user`
    // (`SetSessionData.php:29`). Como o teste pré-seta `user.*` acima, o middleware pula e o
    // campo nunca chega: `createFromFormat` estoura, o `catch (\Exception)` genérico do `save()`
    // engole e faz `DB::rollBack()` → NADA é gravado e as 4 pré-condições anti-vácuo reprovam
    // por ARRANJO, não por defeito do writer.
    // Mesma fonte que a produção usa (`BusinessUtil::getCurrentFinancialYear`) — valor não
    // inventado aqui. Os asserts dos 4 UCs seguem intocados: se algum continuar vermelho depois
    // disto, o vermelho É o achado (proibicoes §5 — não se ajusta teste ao código).
    session(['financial_year' => (new \App\Utils\BusinessUtil)->getCurrentFinancialYear($this->business->id)]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.opening_stock', 'web');
    // `OpeningStockController@save` → `BusinessLocation::forDropdown($biz)` roda com
    // `check_permission = true` (default), então sem `access_all_locations` o local de
    // teste NÃO entra no dropdown válido e o writer descarta TODA linha — vácuo verde.
    Permission::findOrCreate('access_all_locations', 'web');
    $this->user->givePermissionTo(['product.opening_stock', 'access_all_locations']);
});

// =============================================================================
// UC-PINIC-01 — CU-PROD-04.5 `[T0]` ("estoque no local do business correto") +
//   AR-PROD-055 (Local de Estoque Padrão é do cadastro do MEU negócio) + ADR 0093.
//   O `save()` valida o local contra `BusinessLocation::forDropdown($business_id)`;
//   este UC TRAVA esse guard, que hoje não tem teste nenhum.
// =============================================================================

it('UC-PINIC-01 · estoque inicial não entra em local de outro business (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $meuLocal = EstoqueFixture::locationId($bizId);
    $localAlheio = EstoqueFixture::locationId($outroBizId);

    // FASE 1 (pré-condição anti-vácuo): o MESMO payload no MEU local PERSISTE. Sem isto,
    // "não entrou no local alheio" seria verdade só porque o writer abortou (ele engole
    // toda exceção num `catch` genérico + rollback e ainda assim redireciona).
    $this->post('/opening-stock/save', inicialPayload($produto, $meuLocal, ['quantity' => '7']));

    expect(inicialSaldo($produto->variationId(0), $meuLocal))->toEqualWithDelta(
        7.0,
        0.0001,
        'PRÉ-CONDIÇÃO do UC-PINIC-01: o estoque inicial no MEU local não entrou — o writer '
        . 'abortou por outro motivo e a fase 2 mediria vácuo.'
    );

    // FASE 2: o MESMO produto, num local que pertence a OUTRO business.
    $this->post('/opening-stock/save', inicialPayload($produto, $localAlheio, ['quantity' => '99']));

    // O CONTRATO: saldo não nasce em local alheio. Tier 0 — ADR 0093 + DOC-RAIZ INV-6
    // (o par variação × local é o endereço do saldo; se o local vaza, o saldo vaza).
    expect(inicialSaldo($produto->variationId(0), $localAlheio))->toBeNull(
        'Estoque inicial gravou saldo num local de OUTRO business (location_id ' . $localAlheio
        . '). O guard `array_key_exists($location_id, $locations)` do save() é a única barreira '
        . 'e não tinha teste.'
    );
});

// =============================================================================
// UC-PINIC-02 — CU-PROD-04.5 + `CU-PROD-10` `[T0]`: o `save()` resolve o produto por
//   `Product::where('business_id', …)->where('id', …)->first()`. Se o id for de outro
//   tenant o `$product` volta null e o método CAI FORA do `if` — sem gravar nada.
//   Este UC trava o resultado (nada escrito), que é o invariante Tier 0.
//   ⚠️ O que este UC NÃO afirma: qual deveria ser a RESPOSTA (hoje é `success: 1`,
//   "estoque inicial adicionado", para um produto que não é seu). Isso é divergência
//   aberta no §Backlog do casos.md — decidir 404 vs 422 seria escolher remédio.
// =============================================================================

it('UC-PINIC-02 · produto de outro business não recebe estoque inicial (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meu = EstoqueFixture::singleProduct($bizId);
    $meuLocal = EstoqueFixture::locationId($bizId);
    $alheio = EstoqueFixture::singleProduct($outroBizId);

    // FASE 1 (pré-condição anti-vácuo): o caminho de escrita funciona pro MEU produto.
    $this->post('/opening-stock/save', inicialPayload($meu, $meuLocal, ['quantity' => '4']));
    expect(inicialSaldo($meu->variationId(0), $meuLocal))->toEqualWithDelta(
        4.0,
        0.0001,
        'PRÉ-CONDIÇÃO do UC-PINIC-02: o estoque inicial do MEU produto não entrou — fase 2 mediria vácuo.'
    );

    // FASE 2: produto de OUTRO business, no MEU local (o local é válido pra mim; o produto não).
    $this->post('/opening-stock/save', [
        'product_id' => $alheio->productId,
        'stocks' => [
            $meuLocal => [
                $alheio->variationId(0) => [0 => inicialLinhaBlade(['quantity' => '99'])],
            ],
        ],
    ]);

    // O CONTRATO: nenhuma linha de saldo nasce para a variação alheia — nem no meu local.
    expect(inicialSaldo($alheio->variationId(0), $meuLocal))->toBeNull(
        'Um business gravou estoque inicial na variação de OUTRO business (variation_id '
        . $alheio->variationId(0) . ').'
    );
});

// =============================================================================
// UC-PINIC-03 — CU-PROD-04.4 `[V0]` ("quantidade fracionada respeita a unidade; `num_uf`
//   não strippa decimal") + REGRA MESTRE (proibicoes.md Tier 0 — origem: incidente
//   2026-06-05, `num_uf` strippando o ponto decimal e inflando valor ~×100k em biz=4).
//   A Blade manda a quantidade como TEXTO (`@format_quantity`), então o writer TEM que
//   parsear: "1.500" é mil e quinhentos, "1,5" é um e meio. Errar aqui grava saldo errado
//   no catálogo inteiro no dia da implantação.
// =============================================================================

it('UC-PINIC-03 · quantidade em pt-BR ("1.500" e "1,5") entra pelo valor certo (V0)', function () {
    $bizId = (int) $this->business->id;
    $meuLocal = EstoqueFixture::locationId($bizId);

    // (a) MILHAR pt-BR — 1 ponto + EXATAMENTE 3 dígitos = separador de milhar (heurística
    //     session-independent do `Util::num_uf`). Tem que virar 1500, nunca 1,5.
    $milhar = EstoqueFixture::singleProduct($bizId);
    $this->post('/opening-stock/save', inicialPayload($milhar, $meuLocal, ['quantity' => '1.500']));

    expect(inicialSaldo($milhar->variationId(0), $meuLocal))->toEqualWithDelta(
        1500.0,
        0.0001,
        'Quantidade "1.500" não fez round-trip pelo `num_uf` no estoque inicial: o separador de '
        . 'milhar pt-BR foi lido como decimal e o saldo entrou ~1000× menor (REGRA MESTRE).'
    );

    // (b) FRACIONADO pt-BR — vírgula é decimal. Contrato do CU-PROD-04.4 ("quantidade
    //     fracionada respeita a unidade"): 1,5 metro de tecido é 1.5, não 15.
    $fracionado = EstoqueFixture::singleProduct($bizId);
    $this->post('/opening-stock/save', inicialPayload($fracionado, $meuLocal, ['quantity' => '1,5']));

    expect(inicialSaldo($fracionado->variationId(0), $meuLocal))->toEqualWithDelta(
        1.5,
        0.0001,
        'Quantidade fracionada "1,5" não entrou como 1.5 — o decimal pt-BR foi engolido ou '
        . 'multiplicado (REGRA MESTRE + CU-PROD-04.4).'
    );
});

// =============================================================================
// UC-PINIC-04 — CU-PROD-04.3 ("`enable_lot_number` habilita lote") + Blade
//   `opening_stock/form-part.blade.php:98` (o campo `lot_number` existe no payload) +
//   DOC-RAIZ §3 (a entrada de estoque inicial cria `purchase_lines` rastreáveis).
//   Rastreabilidade de lote é o que permite recall/bloqueio — se o lote informado no
//   estoque inicial não persiste, o histórico nasce cego.
//   ⚠️ Escopo deliberado: só LOTE. A VALIDADE (`exp_date`) compartilha o mesmo bloco do
//   payload mas o parsing depende de `session('business.date_format')`, e os DOIS writers
//   de estoque inicial a parseiam de formas diferentes (`uf_date` no controller ×
//   `Carbon::createFromFormat('d-m-Y')` no `addSingleProductOpeningStock`). Essa
//   divergência está registrada no §Backlog do casos.md — não a encodo num assert, porque
//   escolher qual dos dois está certo é decisão [W].
// =============================================================================

it('UC-PINIC-04 · lote informado no estoque inicial persiste na linha de compra', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $meuLocal = EstoqueFixture::locationId($bizId);
    $lote = 'LOTE-UC-PINIC-04-' . strtoupper(bin2hex(random_bytes(3)));

    $this->post('/opening-stock/save', inicialPayload($produto, $meuLocal, [
        'quantity' => '12',
        'lot_number' => $lote,
    ]));

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a entrada aconteceu. Sem isto, "o lote não está lá" e "nada
    // foi gravado" seriam indistinguíveis.
    expect(inicialSaldo($produto->variationId(0), $meuLocal))->toEqualWithDelta(
        12.0,
        0.0001,
        'PRÉ-CONDIÇÃO do UC-PINIC-04: o estoque inicial não entrou — o assert de lote mediria vácuo.'
    );

    // O CONTRATO: alguma linha de compra da variação carrega o lote informado.
    // Desacoplado da CHAVE (lição 2026-07-26): a pergunta é "o lote sobreviveu", não
    // "a coluna se chama lot_number" — por isso o filtro é pela variação e a busca é
    // pelo VALOR sentinela.
    $linhas = DB::table('purchase_lines')
        ->where('variation_id', $produto->variationId(0))
        ->get();

    $temLote = false;
    foreach ($linhas as $linha) {
        foreach ((array) $linha as $campo) {
            if (is_string($campo) && $campo === $lote) {
                $temLote = true;
            }
        }
    }

    expect($temLote)->toBeTrue(
        'O lote informado no estoque inicial (' . $lote . ') não sobreviveu à gravação: nenhuma '
        . 'linha de compra da variação o carrega. Sem lote no saldo de abertura, o rastreio de '
        . 'recall nasce cego (CU-PROD-04.3 + Blade form-part:98).'
    );
});

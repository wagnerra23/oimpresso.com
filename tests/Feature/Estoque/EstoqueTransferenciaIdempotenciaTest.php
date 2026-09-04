<?php

declare(strict_types=1);

// @covers-us UC-EST-09

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * R-XFER-003 — IDEMPOTÊNCIA do POST /stock-transfers/update-status/{id}.
 *
 * CONTRATO (resources/js/Pages/StockTransfer/Index.charter.md:28):
 *   "R-XFER-003: status final só após `completed` (estoque movido)".
 * Corolário: a TRANSIÇÃO é o gatilho. Uma transferência que JÁ está no terminal
 * (`sell_transfer.status='final'`) já teve o saldo movido — repetir a chamada NÃO
 * pode movimentar de novo. Estende UC-EST-06 (EstoqueTransferenciaTest), que prova o
 * par origem/destino de UMA transferência mas não a repetição.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * O DEFEITO QUE ESTE ARQUIVO TRAVA (confirmado 2026-09-04, corrigido no mesmo PR)
 * ─────────────────────────────────────────────────────────────────────────────
 * O guard de `updateStatus` era
 *   `$status == 'completed' && $sell_transfer->status != 'completed'`
 * mas NENHUM caminho de escrita grava 'completed' em `sell_transfer.status` — os três
 * gravam 'final' (`store()` · `update()` · o próprio `updateStatus()`). Varredura contada
 * de `sell_transfer`+`status` no repo: 12 linhas, 0 escrevem 'completed'. Não é typo: são
 * DOIS vocabulários (ENTRADA `pending|in_transit|completed` × PERSISTÊNCIA
 * `pending|in_transit|final`), e o guard comparava um com o outro. A migration
 * `2020_09_07_171059_change_completed_stock_transfer_status_to_final` converteu os
 * remanescentes em 2020; desde então o valor comparado não existia mais na coluna.
 *
 * MEDIDO no CT 100 (MySQL real, tenant 98) ANTES do fix:
 *   · 1 repetição  → origem 7 → 4   · destino 3 → 6
 *   · 3 repetições → origem 7 → -2  · destino 3 → 12  (sem teto: `decrement()` puro)
 * Dano acumulado em PRODUÇÃO no dia do achado: ZERO — `sell_transfer`/`purchase_transfer`
 * de qualquer status = 0 registros em 75.417 transactions / 88 businesses. O fix é
 * preventivo; não houve backfill.
 *
 * ⚠️ Eixo ESTOQUE → REGRA MESTRE (memory/proibicoes.md). Se algum destes casos ficar
 * VERMELHO, o achado é o vermelho: a correção volta a ser decisão [W] (dois caminhos +
 * tabela antes→depois), nunca conserto silencioso do teste.
 *
 * ⚠️ O CONTROLE POSITIVO não é enfeite. Nas duas primeiras rodadas da investigação ele
 * falhou (sessão incompleta; depois falta de lastro de compra) e os casos de contrato
 * passaram VERDES POR VÁCUO — nada se movia porque o fluxo abortava no `catch`. Sem ele,
 * o defeito teria sido "confirmado" pelo motivo errado. Não remova.
 *
 * @see app/Http/Controllers/StockTransferController.php::SELL_TRANSFER_TERMINAL
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §7 INV-2
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    session(['user.business_id' => $this->biz]);

    // Usuário com a permissão REAL que a rota exige (StockTransferController:1013).
    $this->usuario = \App\User::find($this->userId);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']);
    $this->usuario->givePermissionTo('purchase.update');
    // `update()` (PUT) exige OUTRA permissão — é o 2o caminho do mesmo defeito.
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $this->usuario->givePermissionTo('purchase.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Sessão IDÊNTICA à que o middleware SetSessionData monta em produção (:58-61).
    // `business` tem de ser o MODEL COMPLETO, não um par solto: `mapPurchaseSell` lê
    // `session('business')['enable_product_expiry']` e `['on_product_expiry']` direto da
    // SESSÃO (TransactionUtil:3223), não do array `$business` que o controller passa.
    // Com um `business` meia-boca o fluxo estoura, o catch faz rollBack, o saldo fica
    // intacto — e um teste sem controle positivo leria isso como "o guard funciona".
    $this->sessao = [
        'user.id' => $this->userId,
        'user.business_id' => $this->biz,
        'business' => \App\Business::find($this->biz),
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];

    /*
     * LASTRO de compra na location de ORIGEM.
     * `mapPurchaseSell` (TransactionUtil:3391) casa cada sell_line com purchase_lines que
     * ainda tenham saldo naquele local; sem lastro ele lança PurchaseSellMismatch e o catch
     * do controller faz rollBack. Em produção o saldo da origem SEMPRE veio de uma entrada
     * (compra ou estoque inicial) — a `EstoqueFixture` semeia o VLD direto (anti-tautologia),
     * então o lastro precisa ser montado aqui para o cenário espelhar o real.
     */
    $this->darLastro = function ($produto, int $location, float $qtd): void {
        $v = $produto->variations[0];
        $compraId = (int) DB::table('transactions')->insertGetId([
            'business_id' => $this->biz,
            'type' => 'purchase',
            'status' => 'received',
            'location_id' => $location,
            'payment_status' => 'paid',
            'transaction_date' => now()->subDay(),
            'total_before_tax' => 0,
            'final_total' => 0,
            'created_by' => $this->userId,
            'essentials_duration' => 0,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $compraId,
            'product_id' => $produto->productId,
            'variation_id' => $v['variation_id'],
            'quantity' => $qtd,
            'quantity_sold' => 0,
            'quantity_adjusted' => 0,
            'quantity_returned' => 0,
            'purchase_price' => 0,
            'purchase_price_inc_tax' => 0,
            'item_tax' => 0,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    };

    /*
     * Monta o par sell_transfer + purchase_transfer como o `store()` deixa no banco.
     * INSERT direto (não pelo mutador sob teste) — anti-tautologia: o estado inicial é
     * fato independente, para o delta medir o efeito do FLUXO e não de si mesmo.
     */
    $this->criarTransferencia = function ($produto, int $origem, int $destino, float $qtd, string $statusSell, string $statusPurchase): array {
        $v = $produto->variations[0];
        $base = [
            'business_id' => $this->biz,
            'transaction_date' => now(),
            'payment_status' => 'paid',
            'total_before_tax' => 0,
            'final_total' => 0,
            'created_by' => $this->userId,
            'essentials_duration' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $sellId = (int) DB::table('transactions')->insertGetId($base + [
            'type' => 'sell_transfer',
            'status' => $statusSell,
            'location_id' => $origem,
        ]);
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $sellId,
            'product_id' => $produto->productId,
            'variation_id' => $v['variation_id'],
            'quantity' => $qtd,
            'quantity_returned' => 0,
            'unit_price' => 0,
            'unit_price_inc_tax' => 0,
            'item_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $purchaseId = (int) DB::table('transactions')->insertGetId($base + [
            'type' => 'purchase_transfer',
            'status' => $statusPurchase,
            'location_id' => $destino,
            'transfer_parent_id' => $sellId,
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $purchaseId,
            'product_id' => $produto->productId,
            'variation_id' => $v['variation_id'],
            'quantity' => $qtd,
            'purchase_price' => 0,
            'purchase_price_inc_tax' => 0,
            'item_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['sell_id' => $sellId, 'purchase_id' => $purchaseId];
    };

    $this->postarCompleted = fn (int $sellId) => $this->actingAs($this->usuario)
        ->withSession($this->sessao)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post("/stock-transfers/update-status/{$sellId}", ['status' => 'completed']);

    // 2o caminho: PUT /stock-transfers/{id} (Route::resource → update()). Payload em
    // pt-BR de propósito — o controller passa `shipping_charges`/`quantity`/`unit_price`
    // por `num_uf` e a data por `uf_date`.
    // `update()` passa a data por `Util::uf_date($x, true)`, que faz
    // `Carbon::createFromFormat(business.date_format . ' H:i'|' h:i A', $x)`. Formato
    // errado estoura "Not enough data available to satisfy format" e o catch devolve
    // success:0 SEM movimentar — o que faria o contrato abaixo passar por vácuo.
    // Derivado do business, nunca chutado.
    $negocio = \App\Business::find($this->biz);
    $formatoData = $negocio->date_format.((int) $negocio->time_format === 12 ? ' h:i A' : ' H:i');

    $this->putEditarComoCompleted = fn (int $sellId, $produto, float $qtd) => $this->actingAs($this->usuario)
        ->withSession($this->sessao)
        ->put("/stock-transfers/{$sellId}", [
            'transaction_date' => now()->format($formatoData),
            'additional_notes' => '',
            'shipping_charges' => '0',
            'final_total' => '0',
            'status' => 'completed',
            'products' => [[
                'product_id' => $produto->productId,
                'variation_id' => $produto->variations[0]['variation_id'],
                'quantity' => (string) $qtd,
                'unit_price' => '0',
                'enable_stock' => 1,
            ]],
        ]);
});

it('UC-EST-09 · CONTROLE POSITIVO · transferência PENDENTE vira completed e movimenta UMA vez', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-XFER-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-XFER-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $origem, 10.0);
    EstoqueFixture::setStock($produto, 0, $destino, 0.0);
    ($this->darLastro)($produto, $origem, 10.0);

    $t = ($this->criarTransferencia)($produto, $origem, $destino, 3.0, 'pending', 'pending');

    // ANTES — medido, não assumido (senão o teste passaria por linha não encontrada).
    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(10.0)
        ->and(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(0.0);

    $resposta = ($this->postarCompleted)($t['sell_id']);

    // Se o fluxo estourou, o catch faz rollBack e o saldo fica intacto por MOTIVO ERRADO.
    // Sem este assert, o contrato abaixo passaria verde sem nunca exercitar o caminho.
    $resposta->assertSuccessful();
    expect($resposta->json('success'))->toBe(
        1,
        'o caminho de movimentacao precisa COMPLETAR, senao o teste mede um rollback. msg do controller: '
            .json_encode($resposta->json('msg'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(7.0, 'origem baixou 3')
        ->and(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(3.0, 'destino subiu 3');

    // E o terminal gravado é 'final' — a premissa da hipótese, medida no banco.
    $statusGravado = DB::table('transactions')->where('id', $t['sell_id'])->value('status');
    expect($statusGravado)->toBe('final', 'o writer grava final; o guard compara com completed');
});

it('UC-EST-09 · R-XFER-003 · transferência JÁ concluída NÃO movimenta de novo ao repetir completed', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-XFER-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-XFER-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);

    // Estado pós-transferência de 3 unidades, JÁ concluída (o que o store() deixa no banco).
    EstoqueFixture::setStock($produto, 0, $origem, 7.0);
    EstoqueFixture::setStock($produto, 0, $destino, 3.0);
    // Lastro FOLGADO de propósito: elimina a explicação concorrente. Se o saldo não mudar,
    // terá sido o guard — nunca um PurchaseSellMismatch abortando o fluxo por falta de lastro.
    ($this->darLastro)($produto, $origem, 100.0);
    $t = ($this->criarTransferencia)($produto, $origem, $destino, 3.0, 'final', 'received');

    expect(EstoqueFixture::currentStock($produto, 0, $origem))->toBe(7.0)
        ->and(EstoqueFixture::currentStock($produto, 0, $destino))->toBe(3.0);

    ($this->postarCompleted)($t['sell_id'])->assertSuccessful();

    expect(EstoqueFixture::currentStock($produto, 0, $origem))
        ->toBe(7.0, 'R-XFER-003: transferencia ja concluida nao pode baixar a origem OUTRA VEZ')
        ->and(EstoqueFixture::currentStock($produto, 0, $destino))
        ->toBe(3.0, 'R-XFER-003: transferencia ja concluida nao pode creditar o destino OUTRA VEZ');
});

it('UC-EST-09 · R-XFER-003 · repetir N vezes nao tem TETO (origem atravessa o zero)', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-XFER-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-XFER-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);

    EstoqueFixture::setStock($produto, 0, $origem, 7.0);
    EstoqueFixture::setStock($produto, 0, $destino, 3.0);
    ($this->darLastro)($produto, $origem, 100.0);
    $t = ($this->criarTransferencia)($produto, $origem, $destino, 3.0, 'final', 'received');

    for ($i = 0; $i < 3; $i++) {
        ($this->postarCompleted)($t['sell_id'])->assertSuccessful();
    }

    // ProductUtil::decreaseProductQuantity usa decrement() puro (ProductUtil.php:424),
    // sem clamp em 0 — nada impede a origem de atravessar o zero.
    expect(EstoqueFixture::currentStock($produto, 0, $origem))
        ->toBe(7.0, 'sem teto: 3 repeticoes levariam a origem de 7 para -2');
    expect(EstoqueFixture::currentStock($produto, 0, $destino))
        ->toBe(3.0, 'sem teto: 3 repeticoes levariam o destino de 3 para 12');
});

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * 2o CAMINHO — PUT /stock-transfers/{id} (`update()`)
 * ─────────────────────────────────────────────────────────────────────────────
 * O `edit()` (GET) sempre filtrou `status != final`, então a TELA nunca abriu uma
 * transferência concluída; mas o `update()` (PUT) não tinha esse filtro e o bloco
 * `if ($status == 'completed')` movimentava o saldo de novo. Alcançável por request
 * com a tela fechada. Corrigir só o `updateStatus()` deixaria este armado
 * (proibicoes.md §5 2026-08-02 — "corrigir UMA de N implementações").
 */
it('UC-EST-09 · CONTROLE POSITIVO · PUT em transferência PENDENTE movimenta (o caminho existe)', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-XFER-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-XFER-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $origem, 10.0);
    EstoqueFixture::setStock($produto, 0, $destino, 0.0);
    ($this->darLastro)($produto, $origem, 10.0);

    $t = ($this->criarTransferencia)($produto, $origem, $destino, 3.0, 'pending', 'pending');

    $resp = ($this->putEditarComoCompleted)($t['sell_id'], $produto, 3.0);
    $devolveu = json_encode($resp->baseResponse->getSession()?->get('status'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Sem este caso, o contrato abaixo passaria mesmo que o PUT estivesse quebrado por
    // qualquer outro motivo (payload, permissão, subscription) — vácuo, não guard.
    expect(EstoqueFixture::currentStock($produto, 0, $origem))
        ->toBe(7.0, 'o PUT precisa MOVIMENTAR quando a transferencia ainda nao foi concluida. HTTP='.$resp->status().' controller devolveu: '.$devolveu);
    expect(DB::table('transactions')->where('id', $t['sell_id'])->value('status'))->toBe('final');
});

it('UC-EST-09 · R-XFER-003 · PUT em transferência JÁ concluída NÃO movimenta (2o caminho)', function () {
    $origem = EstoqueFixture::locationId($this->biz, '-XFER-ORIG');
    $destino = EstoqueFixture::locationId($this->biz, '-XFER-DEST');
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $origem, 7.0);
    EstoqueFixture::setStock($produto, 0, $destino, 3.0);
    ($this->darLastro)($produto, $origem, 100.0);

    $t = ($this->criarTransferencia)($produto, $origem, $destino, 3.0, 'final', 'received');

    ($this->putEditarComoCompleted)($t['sell_id'], $produto, 3.0);

    // A recusa não pode ser por exceção: o catch genérico do `update()` chama
    // `DB::rollBack()` antes de haver transação própria e derruba a transação de quem
    // chamou. Medido: com o guard feito por filtro na query, a transferência SUMIA junto
    // com o setup, e o saldo lido virava 0.0 (estado pré-setup) em vez de 7.0. Este assert
    // trava o design correto — recusa explícita, sem exceção.
    expect(DB::table('transactions')->where('id', $t['sell_id'])->exists())
        ->toBeTrue('a recusa nao pode derrubar a transacao envolvente (rollBack espurio no catch)');

    expect(EstoqueFixture::currentStock($produto, 0, $origem))
        ->toBe(7.0, 'R-XFER-003: o PUT nao pode baixar a origem de uma transferencia ja concluida');
    expect(EstoqueFixture::currentStock($produto, 0, $destino))
        ->toBe(3.0, 'R-XFER-003: o PUT nao pode creditar o destino de uma transferencia ja concluida');
});

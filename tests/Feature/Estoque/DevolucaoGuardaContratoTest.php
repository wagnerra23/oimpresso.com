<?php

declare(strict_types=1);

// @covers-us CU-DEV-07 CU-DEV-08

use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * FAILING-FIRST — as duas guardas AUSENTES da devolução de venda.
 *
 *   CU-DEV-07 `[T0]` — `access_own_sell_return` deve limitar à devolução própria
 *   CU-DEV-08 `[E0]` — devolver MAIS do que foi vendido deve ser recusado
 *
 * ⚠️ ESTE ARQUIVO NASCE VERMELHO, DE PROPÓSITO. O vermelho é O ACHADO, não bug de teste.
 * Por isso ele entra na QUARENTENA da lane (`.github/estoque-pest-quarantine.list`) com o
 * motivo escrito — mesmo tratamento de `ProdutoEditPayloadContratoTest` e
 * `ProdutoBulkEditContratoTest`. Sai da quarentena quando o fix entrar.
 *
 * ⚠️ EIXO ESTOQUE/PERMISSÃO → REGRA MESTRE (proibicoes.md): o teste PROVA, mas o conserto é
 * decisão [W] — dois caminhos independentes + tabela antes→depois. NÃO consertar de carona.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CU-DEV-07 — a hipótese, medida antes de escrever
 * ─────────────────────────────────────────────────────────────────────────────
 * `$sells` é definido UMA vez em SellReturnController, na linha 64, dentro de `index()`.
 * Mas `show()` (l.345) e `destroy()` (l.412) também o usam, para aplicar o filtro
 * "só as minhas". Varredura contada: 11 ocorrências de `$sells` no arquivo — 9 dentro de
 * `index()` (legítimas), 2 fora de escopo.
 *
 * Se a leitura estiver certa, esses dois caminhos lançam ANTES de filtrar e o erro cai no
 * `catch` genérico ("algo deu errado"), em vez de negar. O `index()` — que usa a variável
 * certa — é o CONTROLE POSITIVO: se ele também falhar, o defeito é do setup, não do código.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CU-DEV-08 — a hipótese, medida antes de escrever
 * ─────────────────────────────────────────────────────────────────────────────
 * Front: o input de quantidade de `sell_return/partials/product_row.blade.php` (l.26) NÃO
 * tem `data-rule-max-value` — o irmão do PDV (`sale_pos/product_row.blade.php`) TEM.
 * Back: `SellReturnController@store` faz `$request->except('_token')` e entrega direto ao
 * `addSellReturn`, sem `validate()`. Nenhum dos dois lados trava.
 *
 * @see app/Http/Controllers/SellReturnController.php (64, 345, 412)
 * @see memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md (CU-DEV-07 · CU-DEV-08)
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    session(['user.business_id' => $this->biz]);
});

it('CU-DEV-07 · quem só tem access_own_sell_return recebe NEGATIVA (não erro genérico) ao ver devolução alheia', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    // Devolução criada por OUTRO usuário (o dono da fixture).
    $devolucao = app(TransactionUtil::class)->addSellReturn([
        'transaction_id' => $venda['transaction_id'],
        'discount_type' => 'fixed',
        'discount_amount' => 0,
        'products' => [[
            'sell_line_id' => $venda['sell_line_id'],
            'quantity' => 2.0,
            'unit_price_inc_tax' => 20.0,
        ]],
    ], $this->biz, $this->userId, false);

    // Um SEGUNDO usuário, com APENAS a permissão "own".
    $alheio = \App\User::create([
        'surname' => 'Sr', 'first_name' => 'Alheio', 'email' => 'alheio.dev'.substr((string) microtime(true), -6).'@teste.local',
        'password' => bcrypt('secret-de-teste'), 'username' => 'alheio'.substr((string) microtime(true), -6),
        'business_id' => $this->biz, 'status' => 'active',
    ]);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_own_sell_return', 'guard_name' => 'web']);
    $alheio->givePermissionTo('access_own_sell_return');
    // Spatie cacheia o mapa de permissões; sem invalidar, a permissão recém-dada não vale
    // no request seguinte e o controle positivo cai em 403 por motivo errado.
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    expect($alheio->fresh()->can('access_own_sell_return'))->toBeTrue();

    // Mesma sessão que o middleware SetSessionData monta em produção (ver nota no CU-DEV-08).
    $sessao = [
        'user.id' => $alheio->id,
        'user.business_id' => $this->biz,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];

    // CONTROLE POSITIVO — index() usa a variável certa; tem de responder sem estourar.
    $this->actingAs($alheio)
        ->withSession($sessao)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sell-return')
        ->assertSuccessful();

    // CONTRATO — show() da devolução alheia: negativa explícita, nunca 500.
    $this->actingAs($alheio)
        ->withSession($sessao)
        ->get("/sell-return/{$devolucao->id}")
        ->assertForbidden();
});

it('CU-DEV-08 · devolver 100 de uma venda de 10 é RECUSADO e não credita estoque', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);
    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 10.0);

    $saldoAntes = EstoqueFixture::currentStock($produto, 0, $loc);
    expect($saldoAntes)->toBe(10.0);

    $usuario = \App\User::find($this->userId);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'access_sell_return', 'guard_name' => 'web']);
    $usuario->givePermissionTo('access_sell_return');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // `store()` lê `session('user.id')` pra gravar `created_by` (SellReturnController:283) e
    // `Util::num_f` lê `session('currency')[...]` sem fallback. Em produção quem popula é o
    // middleware SetSessionData; num request de teste a sessão nasce vazia, o insert viola
    // NOT NULL e o `catch` do controller devolve "algo deu errado" — que pareceria defeito do
    // produto e é sessão incompleta.
    $sessao = [
        'user.id' => $usuario->id,
        'user.business_id' => $this->biz,
        'currency' => ['thousand_separator' => '.', 'decimal_separator' => ',', 'symbol' => 'R$', 'code' => 'BRL'],
    ];
    $postarDevolucao = fn (float $qtd) => $this->actingAs($usuario)
        ->withSession($sessao)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->post('/sell-return', [
            'transaction_id' => $venda['transaction_id'],
            'discount_type' => 'fixed',
            'discount_amount' => 0,
            'products' => [[
                'sell_line_id' => $venda['sell_line_id'],
                'quantity' => $qtd,
                'unit_price_inc_tax' => 20.0,
            ]],
        ]);

    // CONTROLE POSITIVO — sem ele, este teste passaria por NÃO-EXECUÇÃO: se o POST fosse
    // rejeitado por qualquer outro motivo (403, rota, sessão), o estoque também não mudaria
    // e o verde não significaria nada. (proibicoes.md §5 2026-07-24 · LC-13)
    $postarDevolucao(2.0);
    expect(EstoqueFixture::currentStock($produto, 0, $loc))
        ->toBe(12.0, 'CONTROLE: devolucao VALIDA de 2 credita o estoque — o POST executa mesmo');

    // Volta ao estado inicial para medir só o efeito do excesso.
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);
    DB::table('transaction_sell_lines')->where('id', $venda['sell_line_id'])->update(['quantity_returned' => 0]);
    DB::table('transactions')->where('type', 'sell_return')->where('return_parent_id', $venda['transaction_id'])->delete();

    // CONTRATO — 100 numa venda de 10.
    $postarDevolucao(100.0);

    // O contrato é o ESTADO, não o status HTTP: o estoque não pode ganhar 100 unidades
    // que nunca saíram. Hoje (hipótese) ele ganha — e é isso que o vermelho denuncia.
    expect(EstoqueFixture::currentStock($produto, 0, $loc))
        ->toBe($saldoAntes, 'devolucao acima do vendido NAO pode creditar estoque sem lastro');

    expect((float) DB::table('transaction_sell_lines')->find($venda['sell_line_id'])->quantity_returned)
        ->toBeLessThanOrEqual(10.0, 'quantity_returned nao pode exceder a quantidade vendida');
});

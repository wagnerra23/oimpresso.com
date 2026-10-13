<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Baseline F2 (MWART, ADR 0104) de /purchase-return — o que a Blade lista ANTES da tela React.
 *
 * Thread compras/playbook/08 (Devoluções de compra). Trava o ramo DataTable de
 * `PurchaseReturnController::index` como ele é hoje, para a F3 (tela React atrás de flag)
 * provar que não mudou o que a lista devolve. Sem mudança de código de produção.
 *
 * É TELA DE VALOR (regra mestre, proibicoes.md): a lista traz o total da devolução
 * (`final_total`), o pago (`SUM(transaction_payments.amount)`) e o devido, que a DataTable
 * calcula em PHP (`final_total − amount_paid`). O rodapé da Blade soma total e devido em
 * JavaScript sobre o `data-orig-value` das linhas. Cada número aqui é provado por DOIS
 * caminhos: a conta à mão sobre o fixture e o `data-orig-value` que a Blade entrega.
 *
 * Diferenças das listas de pedidos/requisições (threads 06/07): permissão `purchase.view`
 * ou `purchase.create`, sem "ver as próprias", e filtro por fornecedor.
 *
 * Por que cada isolamento roda em PAR (controle positivo + contrato): §5 2026-07-24.
 * Tenant do usuário = 98 (ADR 0358); outro negócio = 99 com location própria. NUNCA biz=4.
 * Prefixo `devBase` nas funções (o escopo global do Pest é compartilhado).
 * Mapa de paridade: memory/requisitos/Compras/_telas/purchase-return-parity.md.
 *
 * @see app/Http/Controllers/PurchaseReturnController.php::index
 * @see memory/requisitos/Compras/_telas/RUNBOOK-purchase-return-index.md
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('transaction_payments') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-DEV');
    $this->user = $this->usuarioComPermissoes(['purchase.view', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    devBaseSessao($this, $this->user);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function devBaseSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Transação crua (devolução por padrão). */
function devBaseTransacao(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_return', 'status' => 'final', 'payment_status' => 'due',
        'ref_no' => 'DC-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => 0,
        'final_total' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function devBasePagamento(int $transacaoId, int $businessId, float $valor): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $transacaoId, 'business_id' => $businessId, 'amount' => $valor,
        'method' => 'cash', 'is_return' => 0, 'paid_on' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Linhas da DataTable, por `ref_no`. */
function devBaseLista($teste, string $query = ''): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders($teste->ajax)->get('/purchase-return'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();

    return collect($r->json('data'))->keyBy(fn ($l) => strip_tags((string) $l['ref_no']));
}

/** O número cru que a Blade entrega na célula (`data-orig-value`) — é ele que o rodapé soma. */
function devBaseValorDaCelula(string $celula): float
{
    expect(preg_match('/data-orig-value="([^"]*)"/', $celula, $m))->toBe(1);

    return round((float) $m[1], 2);
}

test('baseline: a lista traz as devoluções do negócio e não as de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = 'DC-MINHA-'.uniqid();
    $alheia = 'DC-ALHEIA-'.uniqid();
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $minha]);
    devBaseTransacao($outro->id, EstoqueFixture::locationId($outro->id, '-DEV'), $this->user->id, ['ref_no' => $alheia]);

    $refs = devBaseLista($this)->keys()->all();

    expect($refs)->toContain($minha);      // controle positivo
    expect($refs)->not->toContain($alheia);
});

test('baseline: a lista traz só devolução de compra — a compra do mesmo negócio fica fora', function () {
    $devolucao = 'DC-TIPO-'.uniqid();
    $compra = 'COMPRA-TIPO-'.uniqid();
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $devolucao]);
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $compra, 'type' => 'purchase', 'status' => 'received']);

    $refs = devBaseLista($this)->keys()->all();

    expect($refs)->toContain($devolucao);
    expect($refs)->not->toContain($compra);
});

test('baseline valor: total, pago e devido da devolução — por dois caminhos', function () {
    $paga = 'DC-PAGA-'.uniqid();
    $semPagamento = 'DC-SEMPG-'.uniqid();
    $id = devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $paga, 'final_total' => 1770.50, 'payment_status' => 'partial']);
    devBasePagamento($id, $this->business->id, 500.25);
    devBasePagamento($id, $this->business->id, 270.25);
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $semPagamento, 'final_total' => 640.00]);

    // Caminho 1: à mão. Pago = 500,25 + 270,25 = 770,50; devido = 1770,50 − 770,50 = 1000,00.
    // Sem pagamento, o devido é o próprio total (SUM de nada é nulo, e a Blade trata como zero).
    expect(round(1770.50 - (500.25 + 270.25), 2))->toBe(1000.0);

    // Caminho 2: o número que a Blade entrega em cada célula (data-orig-value).
    $linhas = devBaseLista($this);
    expect(devBaseValorDaCelula((string) $linhas[$paga]['final_total']))->toBe(1770.5);
    expect(devBaseValorDaCelula((string) $linhas[$paga]['payment_due']))->toBe(1000.0);
    expect(devBaseValorDaCelula((string) $linhas[$semPagamento]['final_total']))->toBe(640.0);
    expect(devBaseValorDaCelula((string) $linhas[$semPagamento]['payment_due']))->toBe(640.0);

    // O rodapé soma o data-orig-value das linhas: das duas, total 2410,50 e devido 1640,00.
    $total = devBaseValorDaCelula((string) $linhas[$paga]['final_total']) + devBaseValorDaCelula((string) $linhas[$semPagamento]['final_total']);
    $devido = devBaseValorDaCelula((string) $linhas[$paga]['payment_due']) + devBaseValorDaCelula((string) $linhas[$semPagamento]['payment_due']);
    expect(round($total, 2))->toBe(round(1770.50 + 640.00, 2));
    expect(round($devido, 2))->toBe(round(1000.00 + 640.00, 2));
});

test('baseline: a devolução mostra a compra de origem', function () {
    $refCompra = 'COMPRA-ORIG-'.uniqid();
    $compra = devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $refCompra, 'type' => 'purchase', 'status' => 'received']);
    $ref = 'DC-ORIG-'.uniqid();
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $ref, 'return_parent_id' => $compra]);

    $celula = strip_tags((string) devBaseLista($this)[$ref]['parent_purchase']);

    expect(trim($celula))->toBe($refCompra);
});

test('baseline: sem acesso a todos os locais, só aparecem devoluções dos locais permitidos', function () {
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-DEV2');
    $restrito = $this->usuarioComPermissoes(['purchase.view'], $this->business);
    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);

    $permitida = 'DC-LOC-OK-'.uniqid();
    $vetada = 'DC-LOC-NAO-'.uniqid();
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitida]);
    devBaseTransacao($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetada]);

    $this->actingAs($restrito);
    devBaseSessao($this, $restrito);
    $refs = devBaseLista($this)->keys()->all();

    expect($refs)->toContain($permitida);
    expect($refs)->not->toContain($vetada);
});

test('baseline: o filtro de local traz só as devoluções daquele local', function () {
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-DEV3');
    $deste = 'DC-ESTE-'.uniqid();
    $doOutro = 'DC-OUTRO-'.uniqid();
    devBaseTransacao($this->business->id, $this->local, $this->user->id, ['ref_no' => $deste]);
    devBaseTransacao($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $doOutro]);

    $refs = devBaseLista($this, 'location_id='.$this->local)->keys()->all();

    expect($refs)->toContain($deste);
    expect($refs)->not->toContain($doOutro);
});

test('baseline: sem purchase.view nem purchase.create, a lista responde 403', function () {
    $semAcesso = $this->usuarioComPermissoes(['access_all_locations'], $this->business);
    $this->actingAs($semAcesso);
    devBaseSessao($this, $semAcesso);

    $this->withHeaders($this->ajax)->get('/purchase-return')->assertForbidden();
});

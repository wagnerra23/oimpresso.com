<?php

declare(strict_types=1);

// @covers-us US-NFSE-006
// Tier 0 (ADR 0093): a NFS-e só se liga a venda da PRÓPRIA empresa, e o detalhe da nota
// só mostra venda da mesma empresa da nota. Auditoria NFSe 2026-11-05, lote 0, PR 0.2:
// StoreNfseRequest aceitava `transaction_id` só como integer e App\Transaction não tem
// escopo de empresa — o `show` devolvia número, data, total e cliente da venda alheia.
//
// Sessão = 98 (fictício canônico, ADR 0358). Adversário = 99. Nunca biz=4.
// Todo caso negativo tem controle positivo (a venda da própria empresa passa).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\NFSe\Http\Requests\StoreNfseRequest;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

const NFSVENDA_TENANT = 98;
const NFSVENDA_ADVERSARIO = 99;

/** Venda crua (FK desligada só no insert: o adversário 99 pode não estar semeado). */
function nfsvendaCriarVenda(int $businessId, int $createdBy): int
{
    Schema::disableForeignKeyConstraints();
    try {
        return (int) DB::table('transactions')->insertGetId([
            'business_id'      => $businessId,
            'type'             => 'sell',
            'status'           => 'final',
            'invoice_no'       => 'CT-NFSVENDA-' . uniqid(),
            'transaction_date' => now(),
            'created_by'       => $createdBy,
            'final_total'      => 100,
        ]);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

function nfsvendaCriarNota(int $businessId, ?int $transactionId): int
{
    return (int) DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => $businessId,
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'Tomador Ficticio Ltda',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'status'          => 'emitida',
        'idempotency_key' => 'ct-nfsvenda-' . uniqid('', true),
        'transaction_id'  => $transactionId,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasColumn('nfse_emissoes', 'transaction_id')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes.transaction_id (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSVENDA_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    $this->user = $this->usuarioComPermissoes(['nfse.view'], \App\Business::find(NFSVENDA_TENANT));
    $this->sessao = ['user.business_id' => NFSVENDA_TENANT, 'business.id' => NFSVENDA_TENANT];
    session($this->sessao);

    $this->vendaPropria = nfsvendaCriarVenda(NFSVENDA_TENANT, $this->user->id);
    $this->vendaAlheia = nfsvendaCriarVenda(NFSVENDA_ADVERSARIO, $this->user->id);
});

it('[T0] emitir NFS-e recusa venda de outra empresa e aceita a da propria (controle positivo)', function () {
    $regras = (new StoreNfseRequest())->rules();

    $alheia = Validator::make(['transaction_id' => $this->vendaAlheia], ['transaction_id' => $regras['transaction_id']]);
    expect($alheia->fails())->toBeTrue('Venda da empresa 99 foi aceita numa NFS-e da empresa 98.');
    expect($alheia->errors()->has('transaction_id'))->toBeTrue();

    $propria = Validator::make(['transaction_id' => $this->vendaPropria], ['transaction_id' => $regras['transaction_id']]);
    expect($propria->errors()->has('transaction_id'))->toBeFalse('Controle positivo caiu: a venda da propria empresa foi recusada.');
});

it('[T0] detalhe da nota nao mostra venda de outra empresa e mostra a da propria (controle positivo)', function () {
    $notaComAlheia = nfsvendaCriarNota(NFSVENDA_TENANT, $this->vendaAlheia);
    $notaComPropria = nfsvendaCriarNota(NFSVENDA_TENANT, $this->vendaPropria);

    $this->actingAs($this->user)->withSession($this->sessao)
        ->get("/nfse/{$notaComAlheia}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Nfse/Show')->where('venda', null));

    $this->actingAs($this->user)->withSession($this->sessao)
        ->get("/nfse/{$notaComPropria}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Nfse/Show')->where('venda.id', $this->vendaPropria));
});

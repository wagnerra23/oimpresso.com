<?php
// @covers-us US-SELL-055

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;

/**
 * UC-VCOB-01..04 · US-SELL-055 — a cobrança Pix/boleto emitida pela venda leva o SALDO EM
 * ABERTO e não duplica uma cobrança que ainda vale.
 *
 * Âncora: resources/js/Pages/Sells/Show.casos.md (UC-VCOB-*), derivado do SPEC US-SELL-055.
 *
 * Números feitos à mão (venda de 500,00):
 *   pago 200,00 → saldo 300,00 → cobrança de 30000 centavos (antes do conserto: 50000)
 *   pago 500,00 → saldo 0,00   → 422, não emite
 *   cobrança paga de 200,00 já lançada como PG-COB-<id> → saldo 300,00 → emite 30000
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! Schema::hasTable('cobrancas') || ! Schema::hasColumn('fin_contas_bancarias', 'payment_gateway_credential_id')) {
        $this->markTestSkipped('Schema PaymentGateway ausente — roda na lane MySQL / CT 100.');
    }

    $biz = $this->seededTenant();
    $this->bizId = (int) $biz->id;
    $this->user = $this->usuarioComPermissoes(['direct_sell.view'], $biz);

    $this->contactId = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'customer', 'name' => 'Cliente Cobrança Teste',
        'contact_id' => 'CO-' . uniqid(), 'created_by' => $this->user->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->credId = (int) DB::table('payment_gateway_credentials')->insertGetId([
        'business_id' => $this->bizId, 'gateway_key' => 'inter', 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'Inter teste', 'config_json' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $accountId = (int) DB::table('accounts')->insertGetId([
        'business_id' => $this->bizId, 'name' => 'Conta cobrança teste', 'account_number' => '777',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('fin_contas_bancarias')->insert([
        'business_id' => $this->bizId, 'account_id' => $accountId, 'banco_codigo' => '077',
        'agencia' => '0001', 'carteira' => '112', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Teste', 'payment_gateway_credential_id' => $this->credId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->venda = Transaction::create([
        'business_id' => $this->bizId, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'partial',
        'contact_id' => $this->contactId, 'invoice_no' => 'COB-' . uniqid(), 'transaction_date' => now(),
        'final_total' => 500.00, 'total_before_tax' => 500.00, 'tax_amount' => 0, 'discount_amount' => 0,
        'created_by' => $this->user->id,
    ]);

    $this->pagar = fn (string $valor, ?string $no = null) => DB::table('transaction_payments')->insert([
        'business_id' => $this->bizId, 'transaction_id' => $this->venda->id, 'amount' => $valor,
        'is_return' => 0, 'method' => 'cash', 'transaction_no' => $no, 'paid_on' => now(),
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->cobranca = fn (string $status, int $centavos, string $vencimento) => (int) DB::table('cobrancas')->insertGetId([
        'business_id' => $this->bizId, 'payment_gateway_credential_id' => $this->credId,
        'gateway_external_id' => 'ext-' . uniqid(), 'tipo' => 'pix_cob', 'status' => $status,
        'valor_centavos' => $centavos, 'valor_pago_centavos' => $status === 'paga' ? $centavos : null,
        'vencimento' => $vencimento, 'payer_name' => 'Cliente', 'origem_type' => 'sale',
        'origem_id' => $this->venda->id, 'idempotency_key' => 'idem-' . uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // O gateway real fala com o banco; aqui ele só devolve o que recebeu.
    $this->recebido = null;
    $this->mock(PaymentGatewayContract::class, function ($m) {
        $m->shouldReceive('for')->andReturnSelf();
        $m->shouldReceive('emitirPix')->andReturnUsing(function (EmitirCobrancaInput $input) {
            $this->recebido = $input;

            return new CobrancaEmitidaResult(cobrancaId: 1, gatewayExternalId: 'ext', tipo: 'pix_cob',
                emitidaEm: new DateTimeImmutable(), pixEmv: '000201-teste');
        });
    });

    $this->emitir = fn () => $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->bizId, 'business.id' => $this->bizId])
        ->postJson("/sells/{$this->venda->id}/emitir-cobranca", [
            'tipo' => 'pix_cob', 'vencimento' => now()->toDateString(),
        ]);
});

it('UC-VCOB-01 cobra o saldo em aberto, não o total da venda', function () {
    ($this->pagar)('200.0000');

    ($this->emitir)()->assertOk()->assertJsonPath('pix_emv', '000201-teste');

    expect($this->recebido)->not->toBeNull();
    expect($this->recebido->valorCentavos)->toBe(30000);
    expect($this->recebido->origemId)->toBe($this->venda->id);
});

it('UC-VCOB-02 recusa venda já quitada', function () {
    ($this->pagar)('500.0000');

    ($this->emitir)()->assertStatus(422);
    expect($this->recebido)->toBeNull();
});

it('UC-VCOB-03 recusa outra cobrança enquanto a anterior está em aberto no prazo', function () {
    $id = ($this->cobranca)('emitida', 50000, now()->addDays(3)->toDateString());

    ($this->emitir)()->assertStatus(409)->assertJsonPath('cobranca_id', $id);
    expect($this->recebido)->toBeNull();
});

it('UC-VCOB-04 cobrança paga só libera nova cobrança depois de lançada na venda', function () {
    $id = ($this->cobranca)('paga', 20000, now()->toDateString());

    // Paga e ainda não lançada: emitir de novo seria cobrar em dobro.
    ($this->emitir)()->assertStatus(409)->assertJsonPath('cobranca_id', $id);
    expect($this->recebido)->toBeNull();

    // Lançada como PG-COB-<id>: o saldo cai para 300,00 e a nova cobrança leva só isso.
    ($this->pagar)('200.0000', 'PG-COB-' . $id);
    ($this->emitir)()->assertOk();
    expect($this->recebido->valorCentavos)->toBe(30000);
});

it('UC-VCOB-05 o detalhe da venda traz o Pix copia-e-cola só enquanto a cobrança vale', function () {
    $id = ($this->cobranca)('emitida', 50000, now()->addDays(3)->toDateString());
    DB::table('cobrancas')->where('id', $id)->update(['pix_emv' => '00020126PIXTESTE']);

    $abrir = fn () => $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->bizId, 'business.id' => $this->bizId])
        ->getJson("/sells/{$this->venda->id}/sheet-data");

    $abrir()->assertOk()
        ->assertJsonPath('cobranca.kind', 'pending')
        ->assertJsonPath('cobranca.cob.pix_emv', '00020126PIXTESTE');

    // Paga: o código não é mais mostrado.
    DB::table('cobrancas')->where('id', $id)->update(['status' => 'paga', 'paga_em' => now()]);
    $abrir()->assertOk()
        ->assertJsonPath('cobranca.kind', 'paid')
        ->assertJsonPath('cobranca.cob.pix_emv', null);
});

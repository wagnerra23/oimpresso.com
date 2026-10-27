<?php
// @covers-us US-SELL-075

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Contracts\TefDriver;
use Modules\PaymentGateway\Dto\TefRespostaTerminal;
use Modules\PaymentGateway\Http\Controllers\TefController;
use Modules\PaymentGateway\Models\TefTransacao;
use Tests\Support\EstoqueFixture;

/**
 * UC-TEF-01..06 · US-SELL-075 — maquininha integrada ao caixa, camada independente da adquirente.
 *
 * Âncora: resources/js/Pages/Sells/Create.casos.md (UC-TEF-*), derivado da US-SELL-075
 * (o caixa manda o valor, a máquina devolve bandeira, NSU e autorização; o pagamento
 * nasce preenchido). Driver: `simulador` (valor terminado em ,99 é negado).
 *
 * Tenant fictício 98 via EstoqueFixture (ADR 0358). Nada aqui grava venda nem pagamento.
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('tef_transacoes')) {
        $this->markTestSkipped('Schema ausente — roda na lane MySQL (sells-pest).');
    }
    $this->biz = EstoqueFixture::businessId();
    config([
        'sells.tef.enabled'      => true,
        'sells.tef.business_ids' => [$this->biz],
        'sells.tef.driver'       => 'simulador',
    ]);
    $this->user = $this->usuarioComPermissoes(['sell.create'], \App\Business::findOrFail($this->biz));
    $this->caixa = fn () => $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz]);
});

it('UC-TEF-01 a flag desligada esconde a maquininha da empresa', function () {
    config(['sells.tef.enabled' => false]);
    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '10.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-flag',
    ])->assertNotFound();

    config(['sells.tef.enabled' => true, 'sells.tef.business_ids' => [$this->biz + 1]]);
    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '10.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-flag2',
    ])->assertNotFound();

    expect(DB::table('tef_transacoes')->where('business_id', $this->biz)->count())->toBe(0);
});

it('UC-TEF-02 o valor vai para a máquina e a aprovação devolve bandeira, NSU e autorização', function () {
    $r = ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '204.90', 'modalidade' => 'credito', 'parcelas' => 3, 'idempotency_key' => 'k-aprova',
    ])->assertCreated()->assertJsonPath('status', 'pendente')->json();

    $final = ($this->caixa)()->getJson('/paymentgateway/tef/cobrancas/'.$r['id'])->assertOk()->json();

    expect($final['status'])->toBe('aprovada')
        ->and($final['valor'])->toEqual(204.9)
        ->and($final['parcelas'])->toBe(3)
        ->and($final['bandeira'])->toBe('visa')
        ->and($final['nsu'])->not->toBeEmpty()
        ->and($final['autorizacao'])->not->toBeEmpty();
    expect(DB::table('tef_transacoes')->where('id', $r['id'])->value('valor_centavos'))->toBe(20490);
});

it('UC-TEF-03 o mesmo clique reenviado não cobra duas vezes', function () {
    $dados = ['valor' => '50.00', 'modalidade' => 'debito', 'idempotency_key' => 'k-idem'];
    $a = ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', $dados)->assertCreated()->json('id');
    $b = ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', $dados)->assertOk()->json('id');

    expect($b)->toBe($a);
    expect(DB::table('tef_transacoes')->where('business_id', $this->biz)->where('idempotency_key', 'k-idem')->count())->toBe(1);
});

it('UC-TEF-04 cartão recusado volta como negada, sem preencher autorização', function () {
    $id = ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '12.99', 'modalidade' => 'debito', 'idempotency_key' => 'k-nega',
    ])->json('id');

    ($this->caixa)()->getJson('/paymentgateway/tef/cobrancas/'.$id)
        ->assertOk()->assertJsonPath('status', 'negada')->assertJsonPath('autorizacao', null);
});

it('UC-TEF-05 a cobrança de outra empresa não aparece (Tier 0)', function () {
    $alheia = DB::table('tef_transacoes')->insertGetId([
        'business_id' => 99, 'driver' => 'simulador', 'valor_centavos' => 1000, 'modalidade' => 'debito',
        'status' => 'aprovada', 'idempotency_key' => 'k-99', 'created_at' => now(), 'updated_at' => now(),
    ]);

    ($this->caixa)()->getJson('/paymentgateway/tef/cobrancas/'.$alheia)->assertNotFound();
});

it('UC-TEF-06 valor com mais de 2 casas é recusado e os centavos batem por dois caminhos', function () {
    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '204.99605', 'modalidade' => 'debito', 'idempotency_key' => 'k-casas',
    ])->assertUnprocessable()->assertJsonValidationErrors('valor');

    // Caminho 1: conversão por string do controller. Caminho 2: conta à mão em inteiros.
    foreach (['204.99' => 20499, '12.5' => 1250, '0.1' => 10, '1000' => 100000, '1234.05' => 123405] as $valor => $esperado) {
        expect(TefController::centavos((string) $valor))->toBe($esperado);
    }
});

it('UC-TEF-07 sem resposta da adquirente a cobrança vira erro visível, não fica pendurada', function () {
    $this->app->instance(\Modules\PaymentGateway\Services\Tef\SimuladorTefDriver::class, new class implements TefDriver {
        public function key(): string { return 'simulador'; }
        public function iniciar(TefTransacao $tef): TefRespostaTerminal { throw new RuntimeException('timeout'); }
        public function consultar(TefTransacao $tef): TefRespostaTerminal { throw new RuntimeException('timeout'); }
    });

    ($this->caixa)()->postJson('/paymentgateway/tef/cobrancas', [
        'valor' => '30.00', 'modalidade' => 'pix', 'idempotency_key' => 'k-erro',
    ])->assertCreated()->assertJsonPath('status', 'erro');
});

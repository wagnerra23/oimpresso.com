<?php
// @covers-us US-FIN-081

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Tests\Support\WithSeededTenant;

/**
 * US-FIN-081 — link para o cliente pagar o título (UC-FINPAG-01..05 do PagamentoPublico.casos.md).
 *
 * Âncora: a US no SPEC e o charter da tela. Tenant 98 (ADR 0358); o adversário cross-tenant é o 99.
 * Nunca biz=4, nunca biz=1.
 *
 * Números feitos à mão (título de 150,00):
 *   aberto 150,00                → cobrança de 15000 centavos
 *   banco avisa 15000 pagos      → baixa de 150,00, aberto 0,00, título quitado
 *   banco avisa  5000 pagos      → baixa de  50,00, aberto 100,00
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['fin_titulo_links_pagamento', 'fin_titulos', 'cobrancas', 'payment_gateway_credentials'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}) — roda na lane MySQL.");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outro = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    config(['financeiro.link_pagamento.enabled' => true]);
    // O UnificadoController exige `financeiro.dashboard.view` no construtor: sem ela a rota
    // devolve 403 antes de buscar o título, e o 404 do UC-FINPAG-03 nunca seria exercido.
    $this->user = $this->usuarioComPermissoes(['financeiro.dashboard.view'], $this->seededTenant());

    $this->cred = $cred = (int) DB::table('payment_gateway_credentials')->insertGetId([
        'business_id' => $this->biz, 'gateway_key' => 'inter', 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'Inter teste', 'config_json' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $account = (int) DB::table('accounts')->insertGetId([
        'business_id' => $this->biz, 'name' => 'Conta link teste', 'account_number' => '778',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('fin_contas_bancarias')->insert([
        'business_id' => $this->biz, 'account_id' => $account, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Teste', 'payment_gateway_credential_id' => $cred,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->novoTitulo = fn (int $biz) => Titulo::create([
        'business_id' => $biz, 'numero' => 'LNK-' . bin2hex(random_bytes(3)), 'tipo' => 'receber',
        'status' => 'aberto', 'cliente_descricao' => 'Cliente link', 'valor_total' => 150.0, 'valor_aberto' => 150.0,
        'moeda' => 'BRL', 'emissao' => now()->toDateString(), 'vencimento' => now()->addDays(5)->toDateString(),
        'competencia_mes' => now()->format('Y-m'), 'origem' => 'manual', 'created_by' => $this->user->id,
    ]);
    $this->titulo = ($this->novoTitulo)($this->biz);
    $this->token = app(LinkDePagamentoDoTitulo::class)->gerar($this->biz, (int) $this->titulo->id, $this->user->id)['token'];

    $this->pagoPeloBanco = function (int $centavos) {
        $id = (int) DB::table('cobrancas')->insertGetId([
            'business_id' => $this->biz, 'payment_gateway_credential_id' => $this->cred, 'gateway_external_id' => 'ext-' . uniqid(), 'tipo' => 'pix_cobv',
            'status' => 'paga', 'valor_centavos' => 15000, 'valor_pago_centavos' => $centavos,
            'vencimento' => now()->toDateString(), 'paga_em' => now(), 'payer_name' => 'Cliente link',
            'origem_type' => 'fin_titulo', 'origem_id' => $this->titulo->id, 'forma_pagamento' => 'pix',
            'idempotency_key' => 'idem-' . uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        (new OnCobrancaPagaCreateFinanceiroTitulo())->handle(new CobrancaPaga(
            cobrancaId: $id, businessId: $this->biz, valorPagoCentavos: $centavos, pagaEm: new DateTimeImmutable(),
            formaPagamento: 'pix', occurredAt: new DateTimeImmutable(), payerCpfCnpj: null,
            origemType: 'fin_titulo', origemId: (int) $this->titulo->id,
        ));

        return DB::table('fin_titulos')->where('id', $this->titulo->id)->first(['status', 'valor_aberto']);
    };
});

it('UC-FINPAG-01 · link válido abre a página com o valor em aberto, sem documento, sem indexar', function () {
    $this->get("/pagar/{$this->token}")
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Financeiro/PagamentoPublico')
            ->where('titulo.situacao', 'aberto')
            ->where('titulo.valor_aberto', 150)
            ->where('titulo.numero', $this->titulo->numero)
            ->missing('titulo.cliente_id')
            ->missing('titulo.payer_cpf_cnpj'));
});

it('UC-FINPAG-02 · token desconhecido e flag desligada dão 404', function () {
    $this->get('/pagar/' . str_repeat('A', 48))->assertNotFound();
    $this->post('/pagar/' . str_repeat('A', 48) . '/pix')->assertNotFound();

    config(['financeiro.link_pagamento.enabled' => false]);
    $this->get("/pagar/{$this->token}")->assertNotFound();
    $this->post("/pagar/{$this->token}/pix")->assertNotFound();
});

it('UC-FINPAG-03 · uma empresa não gera link nem baixa título de outra', function () {
    expect(fn () => app(LinkDePagamentoDoTitulo::class)->gerar($this->outro, (int) $this->titulo->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $sessao = ['user.business_id' => $this->biz, 'business.id' => $this->biz];

    // Âncora positiva: com a mesma permissão, o título da própria empresa gera o link. Assim o
    // 404 abaixo vem da busca do título, não da trava de permissão nem da flag.
    $proprio = ($this->novoTitulo)($this->biz);
    $this->actingAs($this->user)->withSession($sessao)
        ->postJson("/financeiro/unificado/{$proprio->id}/link-pagamento")->assertOk()->assertJsonStructure(['url', 'expira_em']);

    $deOutra = ($this->novoTitulo)($this->outro);
    $this->actingAs($this->user)->withSession($sessao)
        ->postJson("/financeiro/unificado/{$deOutra->id}/link-pagamento")->assertNotFound();
    expect(DB::table('fin_titulo_links_pagamento')->where('titulo_id', $deOutra->id)->exists())->toBeFalse();
});

it('UC-FINPAG-04 · pagar com Pix cobra o valor em aberto; com cobrança pagável, não emite outra', function () {
    $this->recebidos = [];
    $this->mock(PaymentGatewayContract::class, function ($m) {
        $m->shouldReceive('for')->andReturnSelf();
        $m->shouldReceive('emitirPix')->andReturnUsing(function (EmitirCobrancaInput $input) {
            $this->recebidos[] = $input;
            DB::table('cobrancas')->insert([
                'business_id' => $input->businessId, 'gateway_external_id' => 'ext-' . uniqid(), 'tipo' => 'pix_cobv',
                'status' => 'emitida', 'valor_centavos' => $input->valorCentavos, 'vencimento' => now()->toDateString(),
                'pix_emv' => '000201-teste', 'origem_type' => $input->origemType, 'origem_id' => $input->origemId,
                'idempotency_key' => $input->idempotencyKey, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return new CobrancaEmitidaResult(cobrancaId: 1, gatewayExternalId: 'ext', tipo: 'pix_cobv',
                emitidaEm: new DateTimeImmutable(), pixEmv: '000201-teste');
        });
    });

    $this->post("/pagar/{$this->token}/pix")->assertRedirect("/pagar/{$this->token}")->assertSessionHas('pagamento_gerado');
    $this->post("/pagar/{$this->token}/pix")->assertRedirect("/pagar/{$this->token}");

    expect($this->recebidos)->toHaveCount(1);
    expect($this->recebidos[0]->valorCentavos)->toBe(15000)
        ->and($this->recebidos[0]->origemType)->toBe('fin_titulo')
        ->and($this->recebidos[0]->origemId)->toBe((int) $this->titulo->id)
        ->and($this->recebidos[0]->businessId)->toBe($this->biz);
    $this->get("/pagar/{$this->token}")
        ->assertInertia(fn (AssertableInertia $p) => $p->where('titulo.meio.pix_copia_cola', '000201-teste'));
});

it('UC-FINPAG-05 · o banco avisa o pagamento e o título recebe baixa pelo valor pago', function () {
    $depois = ($this->pagoPeloBanco)(15000);

    expect($depois->status)->toBe('quitado')
        ->and(number_format((float) $depois->valor_aberto, 2, '.', ''))->toBe('0.00');
    $baixa = DB::table('fin_titulo_baixas')->where('business_id', $this->biz)->where('titulo_id', $this->titulo->id)->get();
    expect($baixa)->toHaveCount(1)
        ->and(number_format((float) $baixa[0]->valor_baixa, 2, '.', ''))->toBe('150.00')
        ->and((int) $baixa[0]->created_by)->toBe($this->user->id);

    $this->get("/pagar/{$this->token}")
        ->assertInertia(fn (AssertableInertia $p) => $p->where('titulo.situacao', 'pago'));
});

it('UC-FINPAG-05 · pagamento parcial abate só o que entrou', function () {
    $depois = ($this->pagoPeloBanco)(5000);

    expect(number_format((float) $depois->valor_aberto, 2, '.', ''))->toBe('100.00');
    expect((float) DB::table('fin_titulo_baixas')->where('titulo_id', $this->titulo->id)->sum('valor_baixa'))->toBe(50.0);
});

it('UC-FINPAG-05 · flag desligada: o webhook não baixa título fora da empresa dona, como antes', function () {
    config(['financeiro.link_pagamento.enabled' => false]);
    $depois = ($this->pagoPeloBanco)(15000);

    expect($depois->status)->toBe('aberto')
        ->and(number_format((float) $depois->valor_aberto, 2, '.', ''))->toBe('150.00');
    expect(DB::table('fin_titulo_baixas')->where('titulo_id', $this->titulo->id)->exists())->toBeFalse();
});

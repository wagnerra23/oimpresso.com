<?php
// @covers-us US-FIN-082

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\LinkDePagamentoDoTitulo;
use Modules\Financeiro\Services\PortalDoCliente;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Tests\Support\WithSeededTenant;

/**
 * US-FIN-082 — portal do cliente (UC-FINPOR-01..05 do PortalCliente.casos.md).
 *
 * Âncora: a US no SPEC e o charter da tela. Tenant 98 (ADR 0358); o adversário cross-tenant é o 99.
 * Nunca biz=4, nunca biz=1.
 *
 * Números feitos à mão (cliente A, empresa 98):
 *   aberto 100,00 + parcial com 40,00 em aberto → total em aberto 140,00
 *   pagar o de 100,00 por Pix                  → cobrança de 10000 centavos
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['fin_portal_cliente_links', 'fin_titulo_links_pagamento', 'fin_titulos', 'cobrancas'] as $t) {
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
    config(['financeiro.portal_cliente.enabled' => true, 'financeiro.link_pagamento.enabled' => true]);
    $this->user = $this->usuarioComPermissoes([], $this->seededTenant());

    $this->contato = fn (int $biz, string $nome) => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome, 'contact_id' => 'PO-' . uniqid(),
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->titulo = fn (int $biz, int $contato, float $total, float $aberto, string $status = 'aberto') => Titulo::create([
        'business_id' => $biz, 'numero' => 'POR-' . bin2hex(random_bytes(3)), 'tipo' => 'receber', 'status' => $status,
        'cliente_id' => $contato, 'cliente_descricao' => 'Cliente portal', 'valor_total' => $total, 'valor_aberto' => $aberto,
        'moeda' => 'BRL', 'emissao' => now()->toDateString(), 'vencimento' => now()->addDays(5)->toDateString(),
        'competencia_mes' => now()->format('Y-m'), 'origem' => 'manual', 'created_by' => $this->user->id,
    ]);

    $this->a = ($this->contato)($this->biz, 'Cliente A');
    $this->b = ($this->contato)($this->biz, 'Cliente B');
    $this->t100 = ($this->titulo)($this->biz, $this->a, 100.0, 100.0);
    $this->tParcial = ($this->titulo)($this->biz, $this->a, 90.0, 40.0, 'parcial');
    $this->tPago = ($this->titulo)($this->biz, $this->a, 70.0, 0.0, 'quitado');
    $this->tDeB = ($this->titulo)($this->biz, $this->b, 999.0, 999.0);
    $this->token = app(PortalDoCliente::class)->gerar($this->biz, $this->a, $this->user->id)['token'];
});

it('UC-FINPOR-01 · o portal lista só os títulos do cliente, com total em aberto e histórico, sem indexar', function () {
    $this->get("/portal-cliente/{$this->token}")
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Financeiro/PortalCliente')
            ->where('portal.situacao', 'aberto')
            ->where('portal.cliente', 'Cliente A')
            ->where('portal.total_aberto', 140)
            ->has('portal.abertos', 2)
            ->has('portal.pagos', 1)
            ->where('portal.pagos.0.numero', $this->tPago->numero)
            ->missing('portal.contact_id'));
});

it('UC-FINPOR-02 · token desconhecido e flag desligada dão 404', function () {
    $this->get('/portal-cliente/' . str_repeat('A', 48))->assertNotFound();

    config(['financeiro.portal_cliente.enabled' => false]);
    $this->get("/portal-cliente/{$this->token}")->assertNotFound();
    $this->post("/portal-cliente/{$this->token}/{$this->t100->id}/pix")->assertNotFound();
});

it('UC-FINPOR-03 · o token de um cliente não paga título de outro cliente', function () {
    $this->mock(PaymentGatewayContract::class, fn ($m) => $m->shouldNotReceive('for'));

    $this->post("/portal-cliente/{$this->token}/{$this->tDeB->id}/pix")
        ->assertRedirect("/portal-cliente/{$this->token}")
        ->assertSessionHasErrors('pagamento');
    expect(DB::table('fin_titulo_links_pagamento')->where('titulo_id', $this->tDeB->id)->exists())->toBeFalse();
});

it('UC-FINPOR-04 · uma empresa não gera portal de cliente de outra, nem o portal de uma mostra títulos de outra', function () {
    $deOutra = ($this->contato)($this->outro, 'Cliente da 99');
    expect(fn () => app(PortalDoCliente::class)->gerar($this->biz, $deOutra))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $this->actingAs($this->user)->withSession(['user.business_id' => $this->biz, 'business.id' => $this->biz])
        ->postJson("/financeiro/clientes/{$deOutra}/portal-link")->assertNotFound();

    // Título da empresa 99 com o MESMO contact_id do cliente A não entra no portal da 98.
    DB::table('fin_titulos')->insert([
        'business_id' => $this->outro, 'numero' => 'X99-' . uniqid(), 'tipo' => 'receber', 'status' => 'aberto',
        'cliente_id' => $this->a, 'valor_total' => 500, 'valor_aberto' => 500, 'moeda' => 'BRL',
        'emissao' => now()->toDateString(), 'vencimento' => now()->toDateString(), 'competencia_mes' => now()->format('Y-m'),
        'origem' => 'manual', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->get("/portal-cliente/{$this->token}")
        ->assertInertia(fn (AssertableInertia $p) => $p->has('portal.abertos', 2)->where('portal.total_aberto', 140));
});

it('UC-FINPOR-05 · pagar pelo portal cobra o valor em aberto do título, pelo link de pagamento', function () {
    $this->recebidos = [];
    $this->mock(PaymentGatewayContract::class, function ($m) {
        $m->shouldReceive('for')->andReturnSelf();
        $m->shouldReceive('emitirPix')->andReturnUsing(function (EmitirCobrancaInput $input) {
            $this->recebidos[] = $input;

            return new CobrancaEmitidaResult(cobrancaId: 1, gatewayExternalId: 'ext', tipo: 'pix_cobv',
                emitidaEm: new DateTimeImmutable(), pixEmv: '000201-portal');
        });
    });
    // O pagamento precisa de conta com gateway na empresa.
    $cred = (int) DB::table('payment_gateway_credentials')->insertGetId([
        'business_id' => $this->biz, 'gateway_key' => 'inter', 'ambiente' => 'sandbox', 'ativo' => true,
        'nome_display' => 'Inter teste', 'config_json' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $account = (int) DB::table('accounts')->insertGetId([
        'business_id' => $this->biz, 'name' => 'Conta portal teste', 'account_number' => '779',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('fin_contas_bancarias')->insert([
        'business_id' => $this->biz, 'account_id' => $account, 'banco_codigo' => '077', 'agencia' => '0001',
        'carteira' => '112', 'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture
        'beneficiario_razao_social' => 'Teste', 'payment_gateway_credential_id' => $cred,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $linkDoOperador = app(LinkDePagamentoDoTitulo::class)->gerar($this->biz, (int) $this->t100->id)['token'];

    $this->post("/portal-cliente/{$this->token}/{$this->t100->id}/pix")
        ->assertRedirect("/portal-cliente/{$this->token}")
        ->assertSessionHas('portal_gerado.meio.pix_copia_cola', '000201-portal');

    expect($this->recebidos)->toHaveCount(1);
    expect($this->recebidos[0]->valorCentavos)->toBe(10000)
        ->and($this->recebidos[0]->origemType)->toBe('fin_titulo')
        ->and($this->recebidos[0]->origemId)->toBe((int) $this->t100->id);
    // O link que o operador já mandou continua valendo.
    $this->get("/pagar/{$linkDoOperador}")->assertOk();
});

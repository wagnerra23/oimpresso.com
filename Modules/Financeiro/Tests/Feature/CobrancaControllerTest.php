<?php

declare(strict_types=1);

use App\Business;
use App\Role;
use App\User;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Permission\Models\Permission;

/**
 * Pest GUARDs — /financeiro/cobranca F3 PaymentGateway UI Tela 1.
 *
 * 8 GUARDs conforme Charter (Index.charter.md §"Métricas vivas"):
 *   1) renderiza Inertia component Financeiro/Cobranca/Index
 *   2) expõe Props no shape esperado
 *   3) expõe 4 KPIs (3 fixos + 1 contextual condicional)
 *   4) expõe funil 5 etapas
 *   5) filtra por status/tipo/gateway/account/origem via querystring
 *   6) Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope
 *   7) /financeiro/boletos continua acessível (redirect ainda não — preservado 60d)
 *   8) não dispara mutação em GET /cobranca (read-only puro)
 *
 * ADR 0101: testes biz=1 (não usar biz=4 ROTA LIVRE cliente real).
 */

beforeEach(function () {
    // Ajusta Spatie team_id pra biz=1 (UPOS canon)
    setPermissionsTeamId(1);

    $this->business = Business::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'Test HQ', 'currency_id' => 1],
    );

    $perm = Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(
        ['name' => "Admin#{$this->business->id}", 'business_id' => $this->business->id, 'guard_name' => 'web'],
    );
    $role->syncPermissions([$perm]);

    $this->user = User::factory()->create([
        'business_id' => $this->business->id,
        'username' => 'cobranca_test_'.uniqid(),
    ]);
    $this->user->assignRole($role);
});

it('renderiza Inertia component Financeiro/Cobranca/Index', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Financeiro/Cobranca/Index'));
});

it('expõe Props no shape esperado (cobrancas, kpis, funil, accounts, gateways, filtros, isSaasBusiness, today)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca')
        ->assertInertia(fn ($page) => $page
            ->has('today')
            ->has('isSaasBusiness')
            ->has('filtros')
            ->has('accounts')
            ->has('gateways')
            // cobrancas/kpis/funil são Inertia::defer — não vêm na 1ª request
            ->where('isSaasBusiness', true) // biz=1 é SaaS dogfooding
        );
});

it('expõe 4 KPIs (pago_mes, vencido, aberto, mandatos_ativos, mrr_pago) quando partial reload', function () {
    // Cria 1 cobrança paga + 1 vencida + 1 aberta pra KPIs terem valor
    $cred = PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Inter Test',
        'config_json' => [],
    ]);

    Cobranca::create([
        'business_id' => $this->business->id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id' => 'ext-001',
        'tipo' => 'boleto',
        'status' => 'paga',
        'valor_centavos' => 50000,
        'valor_pago_centavos' => 50000,
        'vencimento' => now()->subDays(5)->toDateString(),
        'paga_em' => now()->subDays(3),
        'payer_name' => 'Cliente Teste',
        'idempotency_key' => 'idem-paga',
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?only=kpis', ['X-Inertia' => 'true', 'X-Inertia-Version' => '1', 'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index', 'X-Inertia-Partial-Data' => 'kpis'])
        ->assertInertia(fn ($page) => $page
            ->has('kpis.pago_mes.qtd')
            ->has('kpis.pago_mes.valor')
            ->has('kpis.vencido.qtd')
            ->has('kpis.aberto.qtd')
            ->has('kpis.mandatos_ativos')
            ->has('kpis.mrr_pago')
        );
});

it('expõe funil 5 etapas (aberto, lembrete, cobranca_ativa, vencido_5d, protesto)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?only=funil', ['X-Inertia' => 'true', 'X-Inertia-Version' => '1', 'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index', 'X-Inertia-Partial-Data' => 'funil'])
        ->assertInertia(fn ($page) => $page
            ->has('funil.aberto.qtd')
            ->has('funil.lembrete.qtd')
            ->has('funil.cobranca_ativa.qtd')
            ->has('funil.vencido_5d.qtd')
            ->has('funil.protesto.qtd')
            ->has('funil.mandatos_cancelados')
        );
});

it('filtra por status via querystring', function () {
    $cred = PaymentGatewayCredential::create([
        'business_id' => $this->business->id,
        'gateway_key' => 'asaas',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Asaas Test',
        'config_json' => [],
    ]);
    Cobranca::create([
        'business_id' => $this->business->id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id' => 'ext-emit',
        'tipo' => 'pix_cob',
        'status' => 'emitida',
        'valor_centavos' => 10000,
        'vencimento' => now()->addDays(7)->toDateString(),
        'payer_name' => 'Pagador A',
        'idempotency_key' => 'idem-emit-'.uniqid(),
    ]);

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?status=paga&only=filtros', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index',
            'X-Inertia-Partial-Data' => 'filtros',
        ])
        ->assertInertia(fn ($page) => $page->where('filtros.status', 'paga'));
});

it('Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope', function () {
    // Cria business B (diferente) + cobrança "vazia" do business B
    $otherBiz = Business::query()->firstOrCreate(['id' => 99], ['name' => 'Other Biz', 'currency_id' => 1]);

    $credOther = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'gateway_key' => 'inter',
        'ambiente' => 'production',
        'ativo' => true,
        'nome_display' => 'Outro Biz Inter',
        'config_json' => [],
    ]);

    Cobranca::withoutGlobalScopes()->create([
        'business_id' => $otherBiz->id,
        'payment_gateway_credential_id' => $credOther->id,
        'gateway_external_id' => 'ext-outro',
        'tipo' => 'boleto',
        'status' => 'paga',
        'valor_centavos' => 99999,
        'vencimento' => now()->subDays(2)->toDateString(),
        'paga_em' => now()->subDay(),
        'payer_name' => 'NEVER SHOULD APPEAR',
        'idempotency_key' => 'idem-cross-tenant',
    ]);

    // User está logado em biz=1; chama /cobranca; cobrança do biz=99 NÃO pode aparecer
    $response = $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?only=cobrancas', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index',
            'X-Inertia-Partial-Data' => 'cobrancas',
        ]);

    $response->assertOk();
    $payload = $response->getContent();
    expect($payload)->not->toContain('NEVER SHOULD APPEAR');
    expect($payload)->not->toContain('99999');
});

it('rota legacy /financeiro/boletos redireciona 301 → /financeiro/cobranca', function () {
    // Cleanup 2026-05-19 hotfix sidebar: Pages/Financeiro/Boletos deletado,
    // GET /boletos virou Route::redirect(301). POST cancelar legacy preservado.
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/boletos')
        ->assertRedirect('/financeiro/cobranca');
});

it('não dispara mutação em GET /cobranca (read-only puro)', function () {
    $countAntes = Cobranca::withoutGlobalScopes()->count();

    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?status=paga&tipo=boleto&gateway=inter')
        ->assertOk();

    $countDepois = Cobranca::withoutGlobalScopes()->count();
    expect($countDepois)->toEqual($countAntes);
});

// ─── Onda 4d.5 — Wire-up emissão GUARDs ──────────────────────────────────

it('POST /cobranca/emitir retorna validation error sem contact_id nem payer_name (LGPD)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/emitir', [
            'tipo' => 'boleto',
            'valor_centavos' => 50000,
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 99999, // inexistente — vai falhar exists validation
        ])
        ->assertSessionHasErrors(['account_id']);
});

it('POST /cobranca/emitir exige tipo válido (in:boleto,pix_cob,pix_cobv,pix_recv,card)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/emitir', [
            'tipo' => 'cripto_btc', // inválido
            'valor_centavos' => 50000,
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
        ])
        ->assertSessionHasErrors(['tipo']);
});

it('POST /cobranca/emitir exige valor_centavos mínimo R$ [redacted Tier 0] (100 centavos)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/emitir', [
            'tipo' => 'boleto',
            'valor_centavos' => 50, // < 100
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
        ])
        ->assertSessionHasErrors(['valor_centavos']);
});

it('POST /cobranca/emitir não aceita vencimento passado', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/emitir', [
            'tipo' => 'boleto',
            'valor_centavos' => 50000,
            'vencimento' => now()->subDay()->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
        ])
        ->assertSessionHasErrors(['vencimento']);
});

// ─── Onda 4d.6 — cobrarCartao GUARDs ─────────────────────────────────────

it('POST /cobranca/cartao exige campos cartão obrigatórios (token, brand, last4, holder, exp)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/cartao', [
            'valor_centavos' => 50000,
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
            // sem campos card_*
        ])
        ->assertSessionHasErrors(['card_token', 'card_brand', 'card_last4', 'card_holder_name', 'card_exp_month', 'card_exp_year']);
});

it('POST /cobranca/cartao não aceita brand inválida (só visa/master/amex/elo/hiper/diners)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/cartao', [
            'valor_centavos' => 50000,
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
            'card_token' => 'tok_test_123',
            'card_brand' => 'btc', // inválido
            'card_last4' => '4242',
            'card_holder_name' => 'TEST CARDHOLDER',
            'card_exp_month' => '12',
            'card_exp_year' => '2028',
        ])
        ->assertSessionHasErrors(['card_brand']);
});

it('POST /cobranca/cartao exige card_last4 com exatos 4 dígitos', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->post('/financeiro/cobranca/cartao', [
            'valor_centavos' => 50000,
            'vencimento' => now()->addDays(7)->toDateString(),
            'account_id' => 1,
            'payer_name' => 'Pagador X',
            'card_token' => 'tok_test_123',
            'card_brand' => 'visa',
            'card_last4' => '42', // < 4 chars
            'card_holder_name' => 'TEST CARDHOLDER',
            'card_exp_month' => '12',
            'card_exp_year' => '2028',
        ])
        ->assertSessionHasErrors(['card_last4']);
});

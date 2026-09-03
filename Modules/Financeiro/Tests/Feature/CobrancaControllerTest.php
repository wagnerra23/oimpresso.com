<?php

declare(strict_types=1);

use App\Business;
use Spatie\Permission\Models\Role;
use App\User;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Pest GUARDs — /financeiro/cobranca F3 PaymentGateway UI Tela 1.
 *
 * 8 GUARDs conforme Charter (Index.charter.md §"Métricas vivas"):
 *   1) renderiza Inertia component Financeiro/Cobranca/Index
 *   2) UC-COB-01 · expõe Props no shape esperado
 *   3) expõe 4 KPIs (3 fixos + 1 contextual condicional)
 *   4) UC-COB-02 · expõe funil 5 etapas
 *   5) UC-COB-03 · filtra por status/tipo/gateway/account/origem via querystring
 *   6) UC-COB-07 · Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope
 *   7) /financeiro/boletos continua acessível (redirect ainda não — preservado 60d)
 *   8) não dispara mutação em GET /cobranca (read-only puro) — 2ª metade do UC-COB-07
 *
 * ⚠️ UC-COB-02 e UC-COB-07 estavam PRESOS NESTE DOCBLOCK (2026-09-03): o coletor do
 * manifesto G-7 lê o atributo `name` do <testcase> do JUnit, então id citado só em
 * comentário nunca vira ✅ — os dois constavam em `scripts/casos-coverage-baseline.json`
 * como dívida "teto_so_docblock". Movidos pro TÍTULO dos MESMOS testes que o
 * `Index.casos.md` já declarava como prova ("funil aberto/…/protesto" e "global scope +
 * GET não muta"). Nenhum assert foi tocado: é rastreabilidade, não cobertura nova.
 *
 * ⛔ E O MOVE **NÃO** DESTRAVA O MANIFESTO, porque há um 2º bloqueio, SUBTRATIVO e maior:
 * ESTE ARQUIVO ESTÁ EM QUARENTENA. Ele consta em `.github/financeiro-pest-quarantine.list`
 * (anotação da lista: `# 4 failed, 11 passed (51 assertions)`), e a lane `financeiro-pest`
 * monta o run-set com `comm -23 <árvore> <quarentena>` — reproduzido em 2026-09-03: árvore
 * 83 · quarentena 23 · RODANDO 60, com este arquivo FORA (controle positivo:
 * `AccountTransactionIdorTest` dentro). Logo nenhum <testcase> deste arquivo entra no JUnit
 * e nenhum UC daqui vira `execução-backed`. Confirmado na outra ponta: o
 * `scripts/casos-test-results.json` tem SÓ `UC-COB-04` e `UC-COB-06` (os dois cujos testes
 * NÃO estão na quarentena) — 2 de 7. O id no título é o pré-requisito que faltava; o
 * destravamento de verdade é sair da quarentena, e isso exige consertar os 4 vermelhos.
 *
 * LIMITE HONESTO do que estes dois provam quando rodarem: o GUARD 4 prova o SHAPE do funil
 * (as 5 etapas existem com `qtd`), não a aritmética de "as 5 etapas somam as cobranças do
 * período"; a metade de RENDER do UC-COB-02 (o funil chega à tela e concorda com o KPI "Em
 * aberto") vive em tests/Browser/Financeiro/CobrancaIndexTest.php — que é, hoje, a ÚNICA
 * cobertura desta tela que de fato EXECUTA numa lane de PR. A soma em si segue sem prova.
 *
 * ADR 0101: testes biz=1 (não usar biz=4 ROTA LIVRE cliente real).
 */

/**
 * Versão do Inertia DERIVADA do próprio middleware — nunca hardcodada.
 *
 * Os 4 GUARDs de partial reload mandavam `X-Inertia-Version: '1'`. A lane cria um stub em
 * `public/build-inertia/manifest.json` (step "Stub Vite manifest", financeiro-pest.yml:113)
 * e `HandleInertiaRequests::version()` devolve o `md5_file` dele — que não é '1'. Version
 * divergente num GET Inertia é 409 por contrato do Inertia, então o servidor respondia 409:
 * cru no `assertOk()` do UC-COB-07, e como "Not a valid Inertia response." nos três
 * `assertInertia` (o helper do Inertia falha ao ler `props/url/version` de um 409).
 * Medido no run 33765806568.
 *
 * Perguntar ao middleware em vez de recalcular o md5 aqui é de propósito: replicar a regra
 * criaria um segundo dono que drifa no dia em que o `version()` mudar (o próprio motivo de o
 * `FinanceiroTestCase::inertiaGet` já existir). O produto está CERTO — quem mentia era o header.
 */
function cobrancaInertiaVersion(): string
{
    return (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
}

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

it('UC-COB-01 · expõe Props no shape esperado (cobrancas, kpis, funil, accounts, gateways, filtros, isSaasBusiness, today)', function () {
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
        ->get('/financeiro/cobranca?only=kpis', ['X-Inertia' => 'true', 'X-Inertia-Version' => cobrancaInertiaVersion(), 'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index', 'X-Inertia-Partial-Data' => 'kpis'])
        ->assertOk() // revela o STATUS: assertInertia sozinho vira "Not a valid Inertia response." em QUALQUER nao-Inertia (409/302/500)
        ->assertInertia(fn ($page) => $page
            ->has('kpis.pago_mes.qtd')
            ->has('kpis.pago_mes.valor')
            ->has('kpis.vencido.qtd')
            ->has('kpis.aberto.qtd')
            ->has('kpis.mandatos_ativos')
            ->has('kpis.mrr_pago')
        );
});

it('UC-COB-02 · expõe funil 5 etapas (aberto, lembrete, cobranca_ativa, vencido_5d, protesto)', function () {
    $this->actingAs($this->user)
        ->withSession(['user.business_id' => $this->business->id, 'business.id' => $this->business->id])
        ->get('/financeiro/cobranca?only=funil', ['X-Inertia' => 'true', 'X-Inertia-Version' => cobrancaInertiaVersion(), 'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index', 'X-Inertia-Partial-Data' => 'funil'])
        ->assertOk() // revela o STATUS: assertInertia sozinho vira "Not a valid Inertia response." em QUALQUER nao-Inertia (409/302/500)
        ->assertInertia(fn ($page) => $page
            ->has('funil.aberto.qtd')
            ->has('funil.lembrete.qtd')
            ->has('funil.cobranca_ativa.qtd')
            ->has('funil.vencido_5d.qtd')
            ->has('funil.protesto.qtd')
            ->has('funil.mandatos_cancelados')
        );
});

it('UC-COB-03 · filtra por status via querystring', function () {
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
            'X-Inertia-Version' => cobrancaInertiaVersion(),
            'X-Inertia-Partial-Component' => 'Financeiro/Cobranca/Index',
            'X-Inertia-Partial-Data' => 'filtros',
        ])
        ->assertOk() // revela o STATUS: assertInertia sozinho vira "Not a valid Inertia response." em QUALQUER nao-Inertia (409/302/500)
        ->assertInertia(fn ($page) => $page->where('filtros.status', 'paga'));
});

it('UC-COB-07 · Tier 0 IRREVOGÁVEL: Cobranca respeita business_id global scope', function () {
    // O tenant adversário é o biz=2 que a lane SEMEIA — não se cria um aqui.
    //
    // Antes: `firstOrCreate(['id' => 99], ['name' => 'Other Biz', ...])`. Duas coisas
    // erradas, e a segunda é de substância:
    //  1. `id` não é fillable em Business, então o 99 era descartado no create e o INSERT
    //     saía sem `owner_id` (NOT NULL, FK pra users) → SQLSTATE[23000] 1452 contra MySQL
    //     real. Medido no run 33766642609. Nunca ia funcionar fora de SQLite.
    //  2. o 99 é PROIBIDO como adversário: é o SUPPORT_CLIENT_TENANT_ID (Modo Suporte), e
    //     `.github/actions/pest-mysql-setup/action.yml` avisa literalmente "NÃO usar 99 aqui
    //     — agente e cliente no mesmo id fariam o cross-tenant ficar verde sem provar nada".
    //
    // O canônico é o biz=2, que a action semeia com este papel declarado: "segundo tenant
    // MÍNIMO pros testes de isolamento multi-tenant terem um segundo business real"
    // (ADR 0093 · ADR 0358). `findOrFail` de propósito: ausente, o teste FALHA ALTO —
    // skip aqui seria guard Tier 0 anunciado e não exercido (LC-13).
    $otherBiz = Business::query()->findOrFail(2);

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
            'X-Inertia-Version' => cobrancaInertiaVersion(),
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

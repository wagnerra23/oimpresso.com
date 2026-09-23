<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Contrato da tela `/settings/payment-gateways` (Gateways de Pagamento · Tier-0 credenciais).
 *
 * UC-PGSET-01  abrir a tela e ver as credenciais do PRÓPRIO business (caminho feliz)
 * UC-PGSET-02  `[T0]` credencial de outro business nunca aparece na lista
 * UC-PGSET-03  toggle (Trust L3 confirmado no front) inverte `ativo` e PERSISTE
 * UC-PGSET-04  `[T0]` toggle em credencial de outro business devolve 404 e não a altera
 * UC-PGSET-05  o payload da lista nunca carrega `config_json` (segredos do gateway)
 * UC-PGSET-06  KPIs contam só o business da sessão, com `fail` = ativa E não-ok
 * UC-PGSET-07  GET da tela é read-only (não cria credencial nem cobrança)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FONTE DOS CASOS (derivados do contrato, não da implementação)
 * ─────────────────────────────────────────────────────────────────────────────
 * Charter `Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.charter.md`
 * (§Goals "Tabela de credenciais", "3 KPIs", "ConfirmToggleModal"; §Automation Anti-hooks
 * "não exibe config_json", "não acessa credencial de outro business_id", "não dispara cobrança
 * ao abrir", "não cria credencial no GET") cruzado com o controller real
 * `PaymentGatewaysController@index` (Inertia::render + defer gateways/kpis) e `@toggle`.
 *
 * Os mesmos comportamentos já tinham teste em
 * `Modules/PaymentGateway/Tests/Feature/Settings/PaymentGatewaysControllerTest.php`, mas
 * (a) em biz=1 (empresa REAL — ADR 0358) e 99, (b) sem citar UC-id (G-2) e (c) em arquivo que
 * NENHUMA lane de PR executa (test-lane-coverage: órfão). Este arquivo não o substitui: é o
 * contrato rastreável, no tenant fictício 98, com controle positivo em cada caso negativo.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TENANTS (ADR 0358)
 * ─────────────────────────────────────────────────────────────────────────────
 * Sessão = 98 (fictício canônico). Adversário cross-tenant = 99 (a outra fictícia).
 * NUNCA biz=4. Nenhum teste aqui chama API de gateway: `healthCheck` (que fala com o banco
 * externo) fica FORA deste contrato de propósito — segue no backlog do casos.md.
 *
 * Segredos: o `config_json` de fixture carrega um MARCADOR inerte (não é credencial real).
 *
 * @see Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysController.php::index
 * @see Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysController.php::toggle
 * @see Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
// `Tests\TestCase` NÃO se declara aqui: `tests/Pest.php` já faz `uses(TestCase::class)->in('Feature')`.
uses(DatabaseTransactions::class);

const PGSET_TENANT = 98;
const PGSET_ADVERSARIO = 99;

/** Versão Inertia coerente com o build (senão o partial reload vira 409). */
function pgsetInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * Par (gateway_key, ambiente) ainda livre para o business — respeita a UNIQUE
 * (business_id, gateway_key, ambiente) mesmo numa base persistente (CT 100).
 *
 * @param  array<int, array{0:string,1:string}>  $jaUsados
 * @return array{0:string,1:string}
 */
function pgsetComboLivre(int $businessId, array $jaUsados = []): array
{
    foreach (['inter', 'c6', 'asaas', 'bcb_pix', 'pesapal'] as $key) {
        foreach (['sandbox', 'production'] as $amb) {
            if (in_array([$key, $amb], $jaUsados, true)) {
                continue;
            }
            $existe = DB::table('payment_gateway_credentials')
                ->where('business_id', $businessId)
                ->where('gateway_key', $key)
                ->where('ambiente', $amb)
                ->exists();
            if (! $existe) {
                return [$key, $amb];
            }
        }
    }

    throw new RuntimeException("Sem par (gateway_key, ambiente) livre para o business {$businessId}.");
}

/** Cria credencial crua no business dado (sem global scope: ainda não há auth). */
function pgsetCriar(int $businessId, array $attrs = [], array $jaUsados = []): PaymentGatewayCredential
{
    [$key, $amb] = pgsetComboLivre($businessId, $jaUsados);

    return PaymentGatewayCredential::withoutGlobalScopes()->create(array_merge([
        'business_id' => $businessId,
        'gateway_key' => $key,
        'ambiente' => $amb,
        'ativo' => true,
        'nome_display' => 'CT PGSET ' . uniqid(),
        'config_json' => [],
        'health_status' => 'ok',
    ], $attrs));
}

beforeEach(function () {
    $this->tenant = \App\Business::find(PGSET_TENANT);
    if (! $this->tenant) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    $this->user = \App\User::factory()->create([
        'business_id' => PGSET_TENANT,
        'username' => 'ct_pgset_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    $this->sessao = ['user.business_id' => PGSET_TENANT, 'business.id' => PGSET_TENANT];

    /** Partial reload de uma prop deferida; devolve o array `props` da resposta. */
    $this->recarregar = function (string $prop): array {
        $resp = $this->actingAs($this->user)
            ->withSession($this->sessao)
            ->get('/settings/payment-gateways', [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => pgsetInertiaVersion(),
                'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
                'X-Inertia-Partial-Data' => $prop,
            ]);
        $resp->assertOk();

        return $resp->json('props') ?? [];
    };

    $this->nomesDaLista = function (): array {
        $gateways = ($this->recarregar)('gateways')['gateways'] ?? [];

        return array_map(fn ($g) => $g['nome'] ?? null, is_array($gateways) ? $gateways : []);
    };
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-01 — caminho feliz
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-01 abre a tela e a credencial do PROPRIO business aparece na lista com o estado dela', function () {
    $propria = pgsetCriar(PGSET_TENANT, ['ativo' => false, 'health_status' => 'ok']);

    $this->actingAs($this->user)
        ->withSession($this->sessao)
        ->get('/settings/payment-gateways')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/PaymentGateways/Index'));

    $gateways = ($this->recarregar)('gateways')['gateways'] ?? [];
    $linha = collect($gateways)->firstWhere('id', $propria->id);

    $this->assertNotNull($linha, 'A credencial do proprio business NAO apareceu na lista. Recebido: ' . json_encode($gateways));
    expect($linha['nome'])->toBe($propria->nome_display);
    expect($linha['driver'])->toBe($propria->gateway_key);
    expect($linha['ativo'])->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-02 — [T0] lista não sai do business da sessão (par A/B)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-02 [T0] credencial de OUTRO business nao aparece, e a propria aparece (controle positivo)', function () {
    if (! \App\Business::find(PGSET_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem tenant 99 (adversario fictício, ADR 0358) — cross-tenant não exercitável.');
    }

    $propria = pgsetCriar(PGSET_TENANT);
    $alheia = pgsetCriar(PGSET_ADVERSARIO, ['nome_display' => 'CT PGSET ALHEIA ' . uniqid()]);

    $nomes = ($this->nomesDaLista)();

    $this->assertContains($propria->nome_display, $nomes, 'Controle positivo caiu: lista vazia/quebrada, o (B) ficaria verde por vacuo.');
    $this->assertNotContains($alheia->nome_display, $nomes, 'Vazamento cross-tenant: credencial do business 99 na lista do 98.');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-03 — toggle inverte e persiste
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-03 toggle inverte ativo nos dois sentidos e persiste no banco', function () {
    $cred = pgsetCriar(PGSET_TENANT, ['ativo' => true]);

    $this->actingAs($this->user)->withSession($this->sessao)
        ->postJson("/settings/payment-gateways/{$cred->id}/toggle")
        ->assertOk()
        ->assertJson(['credential_id' => $cred->id, 'ativo' => false]);
    expect((bool) DB::table('payment_gateway_credentials')->where('id', $cred->id)->value('ativo'))->toBeFalse();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->postJson("/settings/payment-gateways/{$cred->id}/toggle")
        ->assertOk()
        ->assertJson(['credential_id' => $cred->id, 'ativo' => true]);
    expect((bool) DB::table('payment_gateway_credentials')->where('id', $cred->id)->value('ativo'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-04 — [T0] toggle cross-tenant
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-04 [T0] toggle em credencial de outro business devolve 404 e nao altera o registro', function () {
    if (! \App\Business::find(PGSET_ADVERSARIO)) {
        $this->markTestSkipped('Lane sem tenant 99 (adversario fictício, ADR 0358) — cross-tenant não exercitável.');
    }

    $alheia = pgsetCriar(PGSET_ADVERSARIO, ['ativo' => true]);

    $this->actingAs($this->user)->withSession($this->sessao)
        ->postJson("/settings/payment-gateways/{$alheia->id}/toggle")
        ->assertNotFound();

    expect((bool) DB::table('payment_gateway_credentials')->where('id', $alheia->id)->value('ativo'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-05 — segredo nunca no payload
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-05 a lista nunca carrega o conteudo de config_json no payload', function () {
    $marcador = 'CTPGSETMARCADOR' . strtoupper(bin2hex(random_bytes(6)));
    $cred = pgsetCriar(PGSET_TENANT, ['config_json' => ['client_secret' => $marcador]]);

    $resp = $this->actingAs($this->user)->withSession($this->sessao)
        ->get('/settings/payment-gateways', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => pgsetInertiaVersion(),
            'X-Inertia-Partial-Component' => 'Settings/PaymentGateways/Index',
            'X-Inertia-Partial-Data' => 'gateways',
        ]);
    $resp->assertOk();
    $corpo = (string) $resp->getContent();

    // Controle positivo: a credencial ESTÁ no payload — senão o (B) seria verde por lista vazia.
    $this->assertStringContainsString($cred->nome_display, $corpo, 'Controle positivo caiu: a credencial nem veio no payload.');
    $this->assertStringNotContainsString($marcador, $corpo, 'Segredo do gateway (config_json) vazou no payload Inertia.');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-06 — KPIs
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-06 KPIs sobem exatamente pelo que o business ganhou (fail = ativa e nao-ok)', function () {
    $antes = ($this->recarregar)('kpis')['kpis'] ?? null;
    $this->assertIsArray($antes, 'kpis ausente no partial reload.');

    $usados = [];
    $a = pgsetCriar(PGSET_TENANT, ['ativo' => true, 'health_status' => 'ok']);
    $usados[] = [$a->gateway_key, $a->ambiente];
    $b = pgsetCriar(PGSET_TENANT, ['ativo' => true, 'health_status' => 'down'], $usados);
    $usados[] = [$b->gateway_key, $b->ambiente];
    pgsetCriar(PGSET_TENANT, ['ativo' => false, 'health_status' => 'down'], $usados);

    // Ruído de outro business: não pode mexer em nenhum contador do 98.
    if (\App\Business::find(PGSET_ADVERSARIO)) {
        pgsetCriar(PGSET_ADVERSARIO, ['ativo' => true, 'health_status' => 'down']);
    }

    $depois = ($this->recarregar)('kpis')['kpis'] ?? [];

    expect($depois['ativos'] - $antes['ativos'])->toBe(2);
    expect($depois['total'] - $antes['total'])->toBe(3);
    expect($depois['fail'] - $antes['fail'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PGSET-07 — GET read-only
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PGSET-07 abrir a tela (render + reload das props deferidas) nao cria credencial nem cobranca', function () {
    pgsetCriar(PGSET_TENANT);

    $credAntes = DB::table('payment_gateway_credentials')->count();
    $cobAntes = DB::table('cobrancas')->count();

    $this->actingAs($this->user)->withSession($this->sessao)
        ->get('/settings/payment-gateways')
        ->assertOk();
    ($this->recarregar)('gateways');
    ($this->recarregar)('kpis');

    expect(DB::table('payment_gateway_credentials')->count())->toBe($credAntes);
    expect(DB::table('cobrancas')->count())->toBe($cobAntes);
});

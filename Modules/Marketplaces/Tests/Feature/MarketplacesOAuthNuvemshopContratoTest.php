<?php

declare(strict_types=1);
// @covers-us US-MKT-026
// @covers-uc UC-PEDIDO-05

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Jobs\RenovarTokenContaJob;
use Modules\Marketplaces\Services\ContasVendedor;
use Modules\Marketplaces\Services\MarketplacesAcesso;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da conexão da loja Nuvemshop (US-MKT-026 · ADR 0443, fatia a).
 *
 * Prova: com a flag desligada nada sai para a Nuvemshop; ligada, conectar manda para a
 * autorização do app com o `state` da sessão; o callback só aceita esse `state` e grava o token
 * cifrado, sem vencimento, na conta da empresa da SESSÃO (98), nunca de outra (99); o token sem
 * vencimento não passa pela renovação do Mercado Livre.
 *
 * A resposta de token tem a forma do exemplo da documentação pública (tiendanube.github.io/api-documentation/
 * authentication), com o token trocado por um valor sem formato de chave (o gitleaks barrava o do exemplo). HTTP é falso: nenhum teste fala com a Nuvemshop.
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (schema UltimatePOS).
 */
const NS_RESPOSTA_DOC = [
    'access_token' => 'token-de-teste-nuvemshop',
    'token_type' => 'bearer',
    'scope' => 'read_orders,write_products',
    'user_id' => '789',
];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('mkt_account_credentials')) {
        $this->markTestSkipped('Tabelas mkt_* ausentes — migrations do Modules/Marketplaces não rodaram.');
    }
    config([
        'services.nuvemshop.ativo' => true,
        'services.nuvemshop.app_id' => '4321',
        'services.nuvemshop.client_secret' => 'segredo-app-teste',
        'services.nuvemshop.token_url' => 'https://nuvem-falsa.example/apps/authorize/token',
    ]);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([MarketplacesAcesso::PERMISSAO, MarketplacesAcesso::PERMISSAO_CONECTAR], $this->seededTenant());
    $this->nuvem = (int) Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->value('id');

    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === MarketplacesAcesso::PACOTE && in_array((int) $biz, [$this->b98, $this->b99], true));
    app()->instance(ModuleUtil::class, $fake);
});

function nsLogar($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

function nsConectarECallback($t, string $code = 'CODIGO'): void
{
    $destino = $t->get('/marketplaces/contas/nuvemshop/conectar')->headers->get('Location');
    parse_str((string) parse_url((string) $destino, PHP_URL_QUERY), $q);
    $t->get('/marketplaces/oauth/callback/nuvemshop?code='.$code.'&state='.($q['state'] ?? ''))->assertRedirect('/home');
}

it('UC-PEDIDO-05 · US-MKT-026 · catálogo traz a Nuvemshop cadastrada e inativa', function () {
    expect($this->nuvem)->toBeGreaterThan(0)
        ->and((bool) Marketplace::query()->whereKey($this->nuvem)->value('ativo'))->toBeFalse();
});

it('UC-PEDIDO-05 · US-MKT-026 · flag desligada: conectar volta com aviso e não manda para a Nuvemshop', function () {
    config(['services.nuvemshop.ativo' => false]);
    nsLogar($this, $this->u98);

    $this->get('/marketplaces/contas/nuvemshop/conectar')->assertRedirect('/home');

    expect(session('marketplaces.oauth_state_nuvemshop'))->toBeNull();
});

it('UC-PEDIDO-05 · US-MKT-026 · conectar manda para a autorização do app com o state da sessão', function () {
    nsLogar($this, $this->u98);

    $destino = (string) $this->get('/marketplaces/contas/nuvemshop/conectar')->headers->get('Location');
    parse_str((string) parse_url($destino, PHP_URL_QUERY), $q);

    expect(str_starts_with($destino, 'https://www.nuvemshop.com.br/apps/4321/authorize?'))->toBeTrue()
        ->and(strlen($q['state']))->toBe(40)
        ->and(session('marketplaces.oauth_state_nuvemshop.business_id'))->toBe($this->b98);
});

it('UC-PEDIDO-05 · US-MKT-026 · callback com state de outra sessão não chama a Nuvemshop nem grava conta', function () {
    Http::fake();
    nsLogar($this, $this->u98);
    session(['marketplaces.oauth_state_nuvemshop' => [
        'state' => str_repeat('a', 40), 'business_id' => $this->b98, 'user_id' => $this->u98->id, 'expira_em' => now()->addMinutes(5)->getTimestamp(),
    ]]);

    $this->get('/marketplaces/oauth/callback/nuvemshop?code=CODIGO&state='.str_repeat('b', 40))->assertRedirect('/home');

    Http::assertNothingSent();
    // SUPERADMIN: confere que nada foi gravado em empresa alguma.
    expect(ContaVendedor::withoutGlobalScopes()->where('marketplace_id', $this->nuvem)->where('seller_id_external', '789')->exists())->toBeFalse();
});

it('UC-PEDIDO-05 · US-MKT-026 · callback válido grava o token cifrado e sem vencimento na empresa da sessão, sem duplicar', function () {
    Http::fake(['nuvem-falsa.example/*' => Http::response(NS_RESPOSTA_DOC)]);
    nsLogar($this, $this->u98);

    nsConectarECallback($this);
    nsConectarECallback($this);

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://nuvem-falsa.example/apps/authorize/token'
        && $r->isJson() && $r['grant_type'] === 'authorization_code' && $r['code'] === 'CODIGO'
        && $r['client_id'] === '4321' && $r['client_secret'] === 'segredo-app-teste');

    // SUPERADMIN: leitura das duas empresas para provar onde a conta foi parar.
    $contas = ContaVendedor::withoutGlobalScopes()->where('marketplace_id', $this->nuvem)->where('seller_id_external', '789')->get();
    expect($contas)->toHaveCount(1)
        ->and((int) $contas[0]->business_id)->toBe($this->b98)
        ->and($contas[0]->access_token)->toBe('token-de-teste-nuvemshop')
        ->and($contas[0]->access_token_expires_at)->toBeNull()
        ->and($contas[0]->refresh_token)->toBeNull()
        ->and((string) DB::table('mkt_account_credentials')->where('id', $contas[0]->id)->value('access_token'))
        ->not->toContain('token-de-teste-nuvemshop');
});

it('UC-PEDIDO-05 · US-MKT-026 · token sem vencimento é devolvido sem renovar e fica fora da rotina horária do ML', function () {
    Http::fake();
    Queue::fake();
    $conta = app(ContasVendedor::class)->gravarTokens($this->b98, $this->nuvem, [
        'access_token' => 'token-loja', 'refresh_token' => null, 'expires_in' => null, 'user_id' => '9001', 'scope' => null,
    ]);

    expect(app(ContasVendedor::class)->tokenValido($conta))->toBe('token-loja');
    $this->artisan('marketplaces:renovar-tokens')->assertSuccessful();

    Http::assertNothingSent();
    Queue::assertNotPushed(RenovarTokenContaJob::class, fn ($job) => $job->contaId === (int) $conta->id);
    expect($conta->fresh()->status)->toBe(ContaVendedor::STATUS_ATIVA);
});

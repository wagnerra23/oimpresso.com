<?php

declare(strict_types=1);
// @covers-us US-MKT-002
// @covers-us US-MKT-003

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Services\MarketplacesAcesso;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da conexão com o Mercado Livre (US-MKT-002 catálogo · US-MKT-003 OAuth · ADR 0439).
 *
 * Prova: o catálogo traz o ML ativo; só empresa com o pacote e usuário com permissão conecta;
 * o callback só aceita o `state` da própria sessão; o token vai cifrado para a conta da empresa
 * da SESSÃO (98), nunca de outra (99); reconectar a mesma conta não duplica.
 * HTTP do Mercado Livre é falso — nenhum teste fala com a API real.
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (schema UltimatePOS).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('mkt_account_credentials')) {
        $this->markTestSkipped('Tabelas mkt_* ausentes — migrations do Modules/Marketplaces não rodaram.');
    }
    config([
        'marketplaces.mercado_livre.client_id' => 'app-teste',
        'marketplaces.mercado_livre.client_secret' => 'segredo-app-teste',
        'marketplaces.mercado_livre.redirect_uri' => 'https://erp.example/marketplaces/oauth/callback/mercado-livre',
        'marketplaces.mercado_livre.token_url' => 'https://ml-falso.example/oauth/token',
    ]);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([MarketplacesAcesso::PERMISSAO, MarketplacesAcesso::PERMISSAO_CONECTAR], $this->seededTenant());
    $this->u98SemConectar = $this->usuarioComPermissoes([MarketplacesAcesso::PERMISSAO], $this->seededTenant());

    $GLOBALS['mkt_pacote'] = [$this->b98, $this->b99];
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === MarketplacesAcesso::PACOTE && in_array((int) $biz, $GLOBALS['mkt_pacote'], true));
    app()->instance(ModuleUtil::class, $fake);
});

function mktLogar($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

it('US-MKT-002 · catálogo traz o Mercado Livre ativo e Shopee e Amazon cadastrados inativos', function () {
    $porSlug = Marketplace::query()->whereIn('slug', ['mercado_livre', 'shopee', 'amazon_br'])->pluck('ativo', 'slug')->map(fn ($v) => (bool) $v)->all();

    expect($porSlug)->toBe(['amazon_br' => false, 'mercado_livre' => true, 'shopee' => false]);
});

it('US-MKT-003 · conectar manda para a autorização do ML com state e o app da plataforma', function () {
    mktLogar($this, $this->u98);

    $r = $this->get('/marketplaces/contas/mercado-livre/conectar');

    $r->assertRedirect();
    $destino = $r->headers->get('Location');
    parse_str((string) parse_url($destino, PHP_URL_QUERY), $q);
    expect(str_starts_with($destino, 'https://auth.mercadolivre.com.br/authorization?'))->toBeTrue()
        ->and($q['client_id'])->toBe('app-teste')
        ->and($q['response_type'])->toBe('code')
        ->and(strlen($q['state']))->toBe(40)
        ->and(session('marketplaces.oauth_state.business_id'))->toBe($this->b98);
});

it('US-MKT-003 · sem pacote ou sem permissão de conectar, 403', function () {
    mktLogar($this, $this->u98SemConectar);
    $this->get('/marketplaces/contas/mercado-livre/conectar')->assertForbidden();

    $GLOBALS['mkt_pacote'] = [$this->b99];
    mktLogar($this, $this->u98);
    $this->get('/marketplaces/contas/mercado-livre/conectar')->assertForbidden();
});

it('US-MKT-003 · callback com state de outra sessão não chama o ML nem grava conta', function () {
    Http::fake();
    mktLogar($this, $this->u98);
    session(['marketplaces.oauth_state' => [
        'state' => str_repeat('a', 40), 'business_id' => $this->b98, 'user_id' => $this->u98->id, 'expira_em' => now()->addMinutes(5)->getTimestamp(),
    ]]);

    $this->get('/marketplaces/oauth/callback/mercado-livre?code=CODIGO&state='.str_repeat('b', 40))->assertRedirect('/home');

    Http::assertNothingSent();
    // SUPERADMIN: confere que nada foi gravado em empresa alguma.
    expect(ContaVendedor::withoutGlobalScopes()->where('seller_id_external', '123456789')->exists())->toBeFalse();
});

it('US-MKT-003 · callback válido grava o token cifrado na conta da empresa da sessão, sem duplicar', function () {
    Http::fake(['ml-falso.example/*' => Http::response([
        'access_token' => 'APP_USR-acesso-falso', 'refresh_token' => 'TG-refresh-falso',
        'expires_in' => 21600, 'user_id' => 123456789, 'scope' => 'offline_access read write',
    ])]);
    mktLogar($this, $this->u98);

    foreach ([1, 2] as $_) {
        $state = $this->get('/marketplaces/contas/mercado-livre/conectar')->headers->get('Location');
        parse_str((string) parse_url($state, PHP_URL_QUERY), $q);
        $this->get('/marketplaces/oauth/callback/mercado-livre?code=CODIGO&state='.$q['state'])->assertRedirect('/home');
    }

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://ml-falso.example/oauth/token'
        && $r['grant_type'] === 'authorization_code' && $r['code'] === 'CODIGO' && $r['client_secret'] === 'segredo-app-teste');

    // SUPERADMIN: leitura das duas empresas para provar onde a conta foi parar.
    $contas = ContaVendedor::withoutGlobalScopes()->where('seller_id_external', '123456789')->get();
    expect($contas)->toHaveCount(1)
        ->and((int) $contas[0]->business_id)->toBe($this->b98)
        ->and($contas[0]->access_token)->toBe('APP_USR-acesso-falso')
        ->and((string) DB::table('mkt_account_credentials')->where('id', $contas[0]->id)->value('access_token'))->not->toContain('APP_USR');
});

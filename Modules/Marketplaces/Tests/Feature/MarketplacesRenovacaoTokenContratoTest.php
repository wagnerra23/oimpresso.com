<?php

declare(strict_types=1);
// @covers-us US-MKT-003

use Illuminate\Console\Scheduling\Schedule;
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

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da renovação do token do Mercado Livre (US-MKT-003): token com folga não chama o ML;
 * token vencendo é renovado e o PAR novo é gravado (o refresh do ML é de uso único); refresh
 * recusado marca a conta para reconectar; a rotina horária só enfileira quem vence, com a empresa
 * da própria conta. HTTP do ML é falso. Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mkt_account_credentials')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas mkt_* (ADR 0358).');
    }
    config([
        'marketplaces.mercado_livre.client_id' => 'app-teste',
        'marketplaces.mercado_livre.client_secret' => 'segredo-app-teste',
        'marketplaces.mercado_livre.redirect_uri' => 'https://erp.example/cb',
        'marketplaces.mercado_livre.token_url' => 'https://ml-falso.example/oauth/token',
    ]);
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->ml = (int) Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->value('id');
});

function mktConta(int $biz, int $mkt, string $seller, $vence): ContaVendedor
{
    // SUPERADMIN: fixture grava em duas empresas.
    return ContaVendedor::withoutGlobalScopes()->create([
        'business_id' => $biz, 'marketplace_id' => $mkt, 'seller_id_external' => $seller,
        'access_token' => 'acesso-velho', 'refresh_token' => 'refresh-velho', 'access_token_expires_at' => $vence,
    ]);
}

it('US-MKT-003 · token com folga é devolvido sem chamar o Mercado Livre', function () {
    Http::fake();
    $conta = mktConta($this->b98, $this->ml, 's-folga', now()->addHours(3));

    expect(app(ContasVendedor::class)->tokenValido($conta))->toBe('acesso-velho');
    Http::assertNothingSent();
});

it('US-MKT-003 · token vencendo é renovado e o par novo substitui o velho', function () {
    Http::fake(['ml-falso.example/*' => Http::response([
        'access_token' => 'acesso-novo', 'refresh_token' => 'refresh-novo', 'expires_in' => 21600, 'user_id' => 111,
    ])]);
    $conta = mktConta($this->b98, $this->ml, 's-vence', now()->addMinutes(2));

    expect(app(ContasVendedor::class)->tokenValido($conta))->toBe('acesso-novo');

    Http::assertSent(fn (HttpRequest $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'refresh-velho');
    // SUPERADMIN: releitura direta.
    $relida = ContaVendedor::withoutGlobalScopes()->find($conta->id);
    expect($relida->refresh_token)->toBe('refresh-novo')
        ->and($relida->seller_id_external)->toBe('s-vence')
        ->and($relida->access_token_expires_at->greaterThan(now()->addHours(5)))->toBeTrue()
        ->and(ContaVendedor::withoutGlobalScopes()->where('seller_id_external', '111')->exists())->toBeFalse();
});

it('US-MKT-003 · refresh recusado marca a conta para reconectar', function () {
    Http::fake(['ml-falso.example/*' => Http::response(['error' => 'invalid_grant'], 400)]);
    $conta = mktConta($this->b98, $this->ml, 's-recusa', now()->subMinute());

    expect(fn () => app(ContasVendedor::class)->tokenValido($conta))->toThrow(RuntimeException::class);
    // SUPERADMIN: releitura direta.
    expect(ContaVendedor::withoutGlobalScopes()->find($conta->id)->status)->toBe(ContaVendedor::STATUS_TOKEN_EXPIRADO);
});

it('US-MKT-003 · a rotina horária enfileira só quem vence, cada job com a empresa da conta', function () {
    Queue::fake();
    $vence98 = mktConta($this->b98, $this->ml, 's-r98', now()->addMinutes(30));
    $vence99 = mktConta($this->b99, $this->ml, 's-r99', now()->addMinutes(30));
    $folga = mktConta($this->b98, $this->ml, 's-rfolga', now()->addHours(4));

    $this->artisan('marketplaces:renovar-tokens')->assertSuccessful();

    Queue::assertPushed(RenovarTokenContaJob::class, fn ($j) => $j->contaId === $vence98->id && $j->businessId === $this->b98);
    Queue::assertPushed(RenovarTokenContaJob::class, fn ($j) => $j->contaId === $vence99->id && $j->businessId === $this->b99);
    Queue::assertNotPushed(RenovarTokenContaJob::class, fn ($j) => $j->contaId === $folga->id);

    $agendado = collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'marketplaces:renovar-tokens'));
    expect($agendado)->toBeTrue();
});

it('US-MKT-003 · job com a empresa errada não renova a conta de outra empresa', function () {
    Http::fake();
    $conta = mktConta($this->b98, $this->ml, 's-cruzado', now()->addMinutes(2));

    app()->call([new RenovarTokenContaJob($this->b99, (int) $conta->id), 'handle']);

    Http::assertNothingSent();
});

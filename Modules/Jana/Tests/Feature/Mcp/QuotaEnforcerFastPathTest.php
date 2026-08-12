<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Services\Mcp\QuotaEnforcer;

uses(\Tests\TestCase::class);

/**
 * Incidente 2026-08-12 — o QuotaEnforcer consultava o banco em toda requisição.
 *
 * O MCP roda no CT 100 contra o MySQL do Hostinger (~360ms por roundtrip) e o
 * banco tem ZERO quotas cadastradas: eram ~360ms por request para descobrir que
 * não há nada a enforçar. O docblock da classe já prometia "Cache 60s pra
 * reduzir queries repetidas" — a promessa não estava implementada (LC-15).
 *
 * O que estes testes travam é a LINHA entre o que pode e o que não pode ser
 * memoizado: a EXISTÊNCIA de quota é configuração e pode; o CONSUMO não pode,
 * senão o enforcement afrouxa e alguém estoura o limite dentro da janela.
 *
 * @see Modules/Jana/Services/Mcp/QuotaEnforcer.php@temQuotaConfigurada
 */

/**
 * User REAL semeado pela lane.
 *
 * `mcp_quotas.user_id` tem FK para `users` — um id fictício quebra com
 * SQLSTATE[23000] no caso que insere quota (foi o que o CI pegou na 1ª versão
 * deste teste, e os outros 3 casos passaram justamente porque não inseriam).
 * O banco tem zero quotas cadastradas, e o before/after limpa as deste user,
 * então usar o user semeado não contamina os testes vizinhos.
 */
function userQuotaTeste(): int
{
    return (int) DB::table('users')->orderBy('id')->value('id');
}

beforeEach(function () {
    config(['copiloto.mcp.quota_cache_ttl' => 60]);
    $u = userQuotaTeste();
    QuotaEnforcer::esquecerQuotas($u);
    DB::table('mcp_quotas')->where('user_id', $u)->delete();
});

afterEach(function () {
    $u = userQuotaTeste();
    DB::table('mcp_quotas')->where('user_id', $u)->delete();
    QuotaEnforcer::esquecerQuotas($u);
});

it('devolve sem_quota quando o user não tem quota configurada', function () {
    $r = app(QuotaEnforcer::class)->checar(userQuotaTeste());

    expect($r['ok'])->toBeTrue()
        ->and($r['sem_quota'] ?? false)->toBeTrue();
});

it('memoiza a ausência de quota em vez de reconsultar o banco', function () {
    app(QuotaEnforcer::class)->checar(userQuotaTeste());

    // A chave existir prova que a 2ª chamada não vai ao banco — que era o
    // ~360ms pago por requisição só para ouvir "não há quota".
    expect(Cache::has(QuotaEnforcer::chaveTemQuota(userQuotaTeste())))->toBeTrue();

    $r = app(QuotaEnforcer::class)->checar(userQuotaTeste());
    expect($r['ok'])->toBeTrue();
});

it('enxerga a quota nova depois de invalidar', function () {
    app(QuotaEnforcer::class)->checar(userQuotaTeste());

    DB::table('mcp_quotas')->insert([
        'user_id'         => userQuotaTeste(),
        'kind'            => 'calls',
        'period'          => 'daily',
        'limit'           => 1000,
        'current_usage'   => 0,
        'ativo'           => true,
        'block_on_exceed' => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    QuotaEnforcer::esquecerQuotas(userQuotaTeste());

    // Sem a invalidação, criar a 1ª quota de um user só valeria após o TTL.
    $r = app(QuotaEnforcer::class)->checar(userQuotaTeste());
    expect($r['sem_quota'] ?? false)->toBeFalse();
});

it('não memoiza quando o TTL está zerado', function () {
    config(['copiloto.mcp.quota_cache_ttl' => 0]);

    app(QuotaEnforcer::class)->checar(userQuotaTeste());

    // Escape sem deploy: nada é gravado no cache.
    expect(Cache::has(QuotaEnforcer::chaveTemQuota(userQuotaTeste())))->toBeFalse();
});

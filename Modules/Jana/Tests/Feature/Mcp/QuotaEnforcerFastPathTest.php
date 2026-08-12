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

/** User fictício de teste, fora do range real. */
const USER_QUOTA_TESTE = 9871;

beforeEach(function () {
    config(['copiloto.mcp.quota_cache_ttl' => 60]);
    QuotaEnforcer::esquecerQuotas(USER_QUOTA_TESTE);
    DB::table('mcp_quotas')->where('user_id', USER_QUOTA_TESTE)->delete();
});

afterEach(function () {
    DB::table('mcp_quotas')->where('user_id', USER_QUOTA_TESTE)->delete();
    QuotaEnforcer::esquecerQuotas(USER_QUOTA_TESTE);
});

it('devolve sem_quota quando o user não tem quota configurada', function () {
    $r = app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);

    expect($r['ok'])->toBeTrue()
        ->and($r['sem_quota'] ?? false)->toBeTrue();
});

it('memoiza a ausência de quota em vez de reconsultar o banco', function () {
    app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);

    // A chave existir prova que a 2ª chamada não vai ao banco — que era o
    // ~360ms pago por requisição só para ouvir "não há quota".
    expect(Cache::has(QuotaEnforcer::chaveTemQuota(USER_QUOTA_TESTE)))->toBeTrue();

    $r = app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);
    expect($r['ok'])->toBeTrue();
});

it('enxerga a quota nova depois de invalidar', function () {
    app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);

    DB::table('mcp_quotas')->insert([
        'user_id'         => USER_QUOTA_TESTE,
        'kind'            => 'calls',
        'period'          => 'daily',
        'limit'           => 1000,
        'current_usage'   => 0,
        'ativo'           => true,
        'block_on_exceed' => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    QuotaEnforcer::esquecerQuotas(USER_QUOTA_TESTE);

    // Sem a invalidação, criar a 1ª quota de um user só valeria após o TTL.
    $r = app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);
    expect($r['sem_quota'] ?? false)->toBeFalse();
});

it('não memoiza quando o TTL está zerado', function () {
    config(['copiloto.mcp.quota_cache_ttl' => 0]);

    app(QuotaEnforcer::class)->checar(USER_QUOTA_TESTE);

    // Escape sem deploy: nada é gravado no cache.
    expect(Cache::has(QuotaEnforcer::chaveTemQuota(USER_QUOTA_TESTE)))->toBeFalse();
});

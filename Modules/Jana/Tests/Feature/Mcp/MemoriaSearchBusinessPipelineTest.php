<?php

declare(strict_types=1);

use Laravel\Mcp\Response as McpResponse;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Contracts\MemoriaPersistida;
use Modules\Jana\Mcp\Tools\MemoriaSearchTool;
use Modules\Jana\Services\Memoria\MeilisearchDriver;

uses(Tests\TestCase::class);

/**
 * Gap #2 US-RET-002 (SPEC-retrieval-tools-mcp-unificado) — memoria-search via pipeline
 * bom BUSINESS-scoped (MeilisearchDriver::buscarBusiness), atrás de flag, com fallback.
 *
 * Memória é da EMPRESA (business_id, NÃO user_id — ADR 0093/0056). Cobre buscarViaPipeline:
 * formata hits · null (fallback) em vazio/erro/driver-incompatível. Driver mockado.
 */

function memoriaSearchBizTool(): MemoriaSearchTool
{
    return new class extends MemoriaSearchTool
    {
        public function chamarPipeline(int $b, string $q, int $l): ?McpResponse
        {
            return $this->buscarViaPipeline($b, $q, $l);
        }
    };
}

function fatoBiz(int $id, string $fato, array $metadata = [], ?float $score = null): MemoriaPersistida
{
    return new MemoriaPersistida(
        id: $id,
        businessId: 1,
        userId: 1,
        fato: $fato,
        metadata: $metadata,
        validFrom: '2026-05-01 00:00:00',
        validUntil: null,
        score: $score,
    );
}

it('roteia por buscarBusiness (business-scoped, SEM user_id) e formata os hits', function () {
    $driver = Mockery::mock(MeilisearchDriver::class);
    // assinatura business-scoped: (businessId, query, topK) — sem userId
    $driver->shouldReceive('buscarBusiness')->once()->with(1, 'meta de venda', 5)->andReturn([
        fatoBiz(9, 'Cliente prefere boleto (ADR seedado).', ['doc_type' => 'adr'], 0.88),
    ]);
    app()->instance(MemoriaContrato::class, $driver);

    $resp = memoriaSearchBizTool()->chamarPipeline(1, 'meta de venda', 5);

    expect($resp)->toBeInstanceOf(McpResponse::class);
    $txt = (string) $resp->content();
    expect($txt)->toContain('(pipeline)')
        ->toContain('Fato #9')
        ->toContain('Cliente prefere boleto')
        ->toContain('score 0.880');
});

it('pipeline vazio → null (fallback FULLTEXT)', function () {
    $driver = Mockery::mock(MeilisearchDriver::class);
    $driver->shouldReceive('buscarBusiness')->once()->andReturn([]);
    app()->instance(MemoriaContrato::class, $driver);

    expect(memoriaSearchBizTool()->chamarPipeline(1, 'nada', 5))->toBeNull();
});

it('pipeline que lança → null (fallback gracioso, nunca propaga)', function () {
    $driver = Mockery::mock(MeilisearchDriver::class);
    $driver->shouldReceive('buscarBusiness')->once()->andThrow(new \RuntimeException('Meili down'));
    app()->instance(MemoriaContrato::class, $driver);

    expect(memoriaSearchBizTool()->chamarPipeline(1, 'q', 5))->toBeNull();
});

it('driver não-MeilisearchDriver (Null/Mcp dev/CI) → null (fallback, guard instanceof)', function () {
    // Driver genérico do contrato (sem buscarBusiness) → guard devolve null.
    $driver = Mockery::mock(MemoriaContrato::class);
    app()->instance(MemoriaContrato::class, $driver);

    expect(memoriaSearchBizTool()->chamarPipeline(1, 'q', 5))->toBeNull();
});

it('GUARDA cross-tenant: user biz=1 pedindo business_id=99 → erro, NUNCA chega no DB (Tier 0)', function () {
    // Revisão adversarial 2026-05-29 (finding #7): a guarda cross-tenant existia no
    // handle() mas SEM teste. Tier 0 IRREVOGÁVEL (ADR 0093) — vazamento entre tenants
    // é o pior bug. Este teste é a rede de segurança da guarda (early-return, antes do DB).
    $user = new class extends \App\User // App\User É Authenticatable; subclasse real (setAttribute ok)
    {
        public function hasRole($roles, $guard = null): bool
        {
            return false; // não-superadmin
        }
    };
    $user->business_id = 1;

    $request = Mockery::mock(\Laravel\Mcp\Request::class);
    $request->shouldReceive('get')->with('query', '')->andReturn('segredo do concorrente');
    $request->shouldReceive('get')->with('business_id')->andReturn(99);
    $request->shouldReceive('get')->with('limit', 5)->andReturn(5);
    $request->shouldReceive('user')->andReturn($user);

    $resp = (new MemoriaSearchTool())->handle($request);

    expect((string) $resp->content())
        ->toContain('Cross-tenant violation')
        ->toContain('biz=1')
        ->toContain('biz=99');
});

it('GUARDA cross-tenant: superadmin NÃO é bloqueado pela guarda de business', function () {
    // Superadmin pode cruzar tenant (suporte). A guarda só pode disparar pra não-superadmin.
    // Confirma que a guarda NÃO retorna o erro de violação pra superadmin (passa adiante).
    $user = new class extends \App\User
    {
        public function hasRole($roles, $guard = null): bool
        {
            return true; // superadmin
        }
    };
    $user->business_id = 1;

    $request = Mockery::mock(\Laravel\Mcp\Request::class);
    $request->shouldReceive('get')->with('query', '')->andReturn('q');
    $request->shouldReceive('get')->with('business_id')->andReturn(99);
    $request->shouldReceive('get')->with('limit', 5)->andReturn(5);
    $request->shouldReceive('user')->andReturn($user);

    // Pipeline ON + driver vazio → null → cai no FULLTEXT. Como o foco é só a guarda,
    // basta garantir que a resposta NÃO é a violação cross-tenant.
    config()->set('copiloto.mcp_search.memoria_pipeline', true);
    $driver = Mockery::mock(MeilisearchDriver::class);
    $driver->shouldReceive('buscarBusiness')->andReturn([
        fatoBiz(7, 'fato cross-tenant via superadmin', [], 0.5),
    ]);
    app()->instance(MemoriaContrato::class, $driver);

    $resp = (new MemoriaSearchTool())->handle($request);

    expect((string) $resp->content())->not->toContain('Cross-tenant violation');
});

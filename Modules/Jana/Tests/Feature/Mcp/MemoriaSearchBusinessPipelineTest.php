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

<?php

declare(strict_types=1);

use Laravel\Mcp\Response as McpResponse;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Contracts\MemoriaPersistida;
use Modules\Jana\Mcp\Tools\MemoriaSearchTool;

uses(Tests\TestCase::class);

/**
 * Gap #2 Área A (handoff 2026-05-29 / proposta jana-mcp-search-tools-pipeline-bom)
 * — memoria-search passa a usar o pipeline bom (MemoriaContrato::buscar:
 * hybrid+HyDE+RRF+time-decay+Peso Real+reranker) atrás da flag
 * JANA_MCP_SEARCH_PIPELINE_MEMORIA, com fallback gracioso pro FULLTEXT.
 *
 * Cobre buscarViaPipeline (a parte nova): formata os hits e devolve null (=
 * sinal de fallback) em vazio/erro — nunca propaga exceção pro time. A
 * MemoriaContrato é mockada no container; subclasse anônima expõe o protected.
 */

function memoriaSearchToolExpondoPipeline(): MemoriaSearchTool
{
    return new class extends MemoriaSearchTool
    {
        public function chamarPipeline(int $b, int $u, string $q, int $l): ?McpResponse
        {
            return $this->buscarViaPipeline($b, $u, $q, $l);
        }
    };
}

function fatoPersistido(int $id, string $fato, array $metadata = [], ?float $score = null): MemoriaPersistida
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

it('pipeline com hits formata output com marcador (pipeline) + fato + categoria + score', function () {
    $mock = Mockery::mock(MemoriaContrato::class);
    $mock->shouldReceive('buscar')->once()->with(1, 1, 'meta de venda', 5)->andReturn([
        fatoPersistido(7, 'Meta R$5M/ano é o norte (ADR 0022).', ['doc_type' => 'adr'], 0.91),
    ]);
    app()->instance(MemoriaContrato::class, $mock);

    $resp = memoriaSearchToolExpondoPipeline()->chamarPipeline(1, 1, 'meta de venda', 5);

    expect($resp)->toBeInstanceOf(McpResponse::class);
    $txt = (string) $resp->content();
    expect($txt)->toContain('(pipeline)')
        ->toContain('Fato #7')
        ->toContain('Meta R$5M/ano')
        ->toContain('[adr]')
        ->toContain('score 0.910');
});

it('pipeline vazio devolve null (fallback pro FULLTEXT business-wide)', function () {
    $mock = Mockery::mock(MemoriaContrato::class);
    $mock->shouldReceive('buscar')->once()->andReturn([]);
    app()->instance(MemoriaContrato::class, $mock);

    expect(memoriaSearchToolExpondoPipeline()->chamarPipeline(1, 1, 'nada', 5))->toBeNull();
});

it('pipeline que lança devolve null (fallback gracioso — nunca propaga erro pro time)', function () {
    $mock = Mockery::mock(MemoriaContrato::class);
    $mock->shouldReceive('buscar')->once()->andThrow(new \RuntimeException('Meilisearch down'));
    app()->instance(MemoriaContrato::class, $mock);

    expect(memoriaSearchToolExpondoPipeline()->chamarPipeline(1, 1, 'q', 5))->toBeNull();
});

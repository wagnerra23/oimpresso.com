<?php

use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Contracts\MemoriaPersistida;
use Modules\Copiloto\Services\Memoria\MeilisearchDriver;
use Modules\Copiloto\Services\Memoria\NullMemoriaDriver;

/**
 * Testes da MemoriaContrato — verdade canônica ADRs 0031/0033/0036.
 *
 * Driver default: MeilisearchDriver (canônico, sprint 4).
 * Driver dev/CI: NullMemoriaDriver (fixtures em memória).
 * Driver futuro: Mem0RestDriver (sprint 8+ condicional).
 */

it('resolve MeilisearchDriver quando memoria.driver=auto e dry_run=false', function () {
    config(['copiloto.memoria.driver' => 'auto']);
    config(['copiloto.dry_run' => false]);

    $driver = app(MemoriaContrato::class);

    expect($driver)->toBeInstanceOf(MeilisearchDriver::class);
});

it('resolve NullMemoriaDriver quando memoria.driver=null', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);

    expect($driver)->toBeInstanceOf(NullMemoriaDriver::class);
});

it('resolve NullMemoriaDriver quando dry_run=true (override)', function () {
    config(['copiloto.memoria.driver' => 'auto']);
    config(['copiloto.dry_run' => true]);

    $driver = app(MemoriaContrato::class);

    expect($driver)->toBeInstanceOf(NullMemoriaDriver::class);
});

it('rejeita driver mem0_rest com erro claro (não implementado ainda)', function () {
    config(['copiloto.memoria.driver' => 'mem0_rest']);

    expect(fn () => app(MemoriaContrato::class))
        ->toThrow(\RuntimeException::class, "Driver de memória 'mem0_rest' não implementado");
});

it('NullMemoriaDriver lembra e busca fato no scope do user', function () {
    $driver = new NullMemoriaDriver();

    $persistida = $driver->lembrar(
        businessId: 4,
        userId: 12,
        fato: 'Larissa quer meta de R$80k/mês',
        metadata: ['categoria' => 'meta_faturamento']
    );

    expect($persistida)->toBeInstanceOf(MemoriaPersistida::class);
    expect($persistida->businessId)->toBe(4);
    expect($persistida->userId)->toBe(12);
    expect($persistida->fato)->toBe('Larissa quer meta de R$80k/mês');
    expect($persistida->metadata['categoria'])->toBe('meta_faturamento');
    expect($persistida->validUntil)->toBeNull();

    $resultados = $driver->buscar(4, 12, 'meta');

    expect($resultados)->toHaveCount(1);
    expect($resultados[0]->fato)->toBe('Larissa quer meta de R$80k/mês');
});

it('NullMemoriaDriver isola por business_id (multi-tenant US-COPI-MEM-005)', function () {
    $driver = new NullMemoriaDriver();

    $driver->lembrar(businessId: 4, userId: 12, fato: 'fato do biz 4');
    $driver->lembrar(businessId: 8, userId: 12, fato: 'fato do biz 8');

    $resultadosBiz4 = $driver->buscar(4, 12, 'fato');
    $resultadosBiz8 = $driver->buscar(8, 12, 'fato');

    expect($resultadosBiz4)->toHaveCount(1);
    expect($resultadosBiz4[0]->fato)->toBe('fato do biz 4');
    expect($resultadosBiz8)->toHaveCount(1);
    expect($resultadosBiz8[0]->fato)->toBe('fato do biz 8');
});

it('NullMemoriaDriver atualizar supersedes antigo (append-only temporal)', function () {
    $driver = new NullMemoriaDriver();

    $original = $driver->lembrar(4, 12, 'meta R$80k');
    $driver->atualizar($original->id, 'meta R$120k');

    $ativos = $driver->listar(4, 12);

    expect($ativos)->toHaveCount(1);
    expect($ativos[0]->fato)->toBe('meta R$120k');
});

it('NullMemoriaDriver esquecer remove (LGPD opt-out)', function () {
    $driver = new NullMemoriaDriver();

    $persistida = $driver->lembrar(4, 12, 'fato sensível');
    expect($driver->listar(4, 12))->toHaveCount(1);

    $driver->esquecer($persistida->id);
    expect($driver->listar(4, 12))->toHaveCount(0);
});

it('Interface MemoriaContrato exige 5 métodos canônicos', function () {
    $reflection = new \ReflectionClass(MemoriaContrato::class);
    $methods = collect($reflection->getMethods())->pluck('name')->all();

    expect($methods)->toContain('lembrar', 'buscar', 'atualizar', 'esquecer', 'listar');
});

it('CopilotoMemoriaFato model usa Searchable e SoftDeletes', function () {
    $model = new \Modules\Copiloto\Entities\CopilotoMemoriaFato();
    $traits = class_uses_recursive($model);

    expect($traits)->toContain(\Laravel\Scout\Searchable::class);
    expect($traits)->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('CopilotoMemoriaFato shouldBeSearchable só pra ativos não deletados', function () {
    $ativo = new \Modules\Copiloto\Entities\CopilotoMemoriaFato([
        'business_id' => 4, 'user_id' => 12, 'fato' => 'x',
    ]);
    expect($ativo->shouldBeSearchable())->toBeTrue();

    $superseded = new \Modules\Copiloto\Entities\CopilotoMemoriaFato([
        'business_id' => 4, 'user_id' => 12, 'fato' => 'x',
    ]);
    $superseded->valid_until = now();
    expect($superseded->shouldBeSearchable())->toBeFalse();
});

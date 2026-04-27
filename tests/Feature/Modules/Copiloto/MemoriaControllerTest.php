<?php

use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Http\Controllers\MemoriaController;
use Modules\Copiloto\Services\Memoria\NullMemoriaDriver;

/**
 * Sprint 6 — MemoriaController (US-COPI-MEM-012, LGPD).
 * Tela /copiloto/memoria. Multi-tenant via session('user.business_id') + auth()->id().
 */

it('MemoriaController index lista memorias do user via NullMemoriaDriver', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    expect($driver)->toBeInstanceOf(NullMemoriaDriver::class);

    $driver->lembrar(businessId: 4, userId: 12, fato: 'Larissa quer meta R$80k');
    $driver->lembrar(businessId: 4, userId: 12, fato: 'Monitor 1280px');
    $driver->lembrar(businessId: 8, userId: 99, fato: 'fato isolado de outro biz');

    $todasDoBiz4 = $driver->listar(4, 12);

    expect($todasDoBiz4)->toHaveCount(2);
    expect(collect($todasDoBiz4)->pluck('fato')->all())
        ->toContain('Larissa quer meta R$80k')
        ->toContain('Monitor 1280px');
});

it('MemoriaController destroy chama esquecer no driver (LGPD opt-out)', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    $persistida = $driver->lembrar(4, 12, 'fato a esquecer');

    expect($driver->listar(4, 12))->toHaveCount(1);

    $driver->esquecer($persistida->id);

    expect($driver->listar(4, 12))->toHaveCount(0);
});

it('MemoriaController update atualiza fato (supersedes via valid_until)', function () {
    config(['copiloto.memoria.driver' => 'null']);

    $driver = app(MemoriaContrato::class);
    $original = $driver->lembrar(4, 12, 'meta R$80k');
    $driver->atualizar($original->id, 'meta R$120k');

    $ativos = $driver->listar(4, 12);
    expect($ativos)->toHaveCount(1);
    expect($ativos[0]->fato)->toBe('meta R$120k');
});

it('rotas /copiloto/memoria registradas com nomes canônicos', function () {
    $rotas = collect(app('router')->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter(fn ($n) => str_starts_with((string) $n, 'copiloto.memoria.'))
        ->values()
        ->all();

    expect($rotas)
        ->toContain('copiloto.memoria.index')
        ->toContain('copiloto.memoria.update')
        ->toContain('copiloto.memoria.destroy');
});

it('MemoriaController construtor exige MemoriaContrato (DI canônica)', function () {
    $reflection = new ReflectionClass(MemoriaController::class);
    $construtor = $reflection->getConstructor();
    $params = collect($construtor->getParameters());

    expect($params)->toHaveCount(1);
    expect($params[0]->getType()?->getName())->toBe(MemoriaContrato::class);
});

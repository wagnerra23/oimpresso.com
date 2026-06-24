<?php

declare(strict_types=1);

use App\Console\Commands\Governance\ProdFlagsCommand;

// buildLive() replica ContactController::shouldRenderInertiaCliente — fonte-de-verdade do
// "que está live por tenant". Config-puro (sem DB). Proposta SDD 2026-06-24 (metade produtor).

it('inclui componente enabled com allowlist como os business_ids (string)', function () {
    config(['mwart.cliente_create.enabled' => true, 'mwart.cliente_create.business_ids' => [4]]);

    $live = (new ProdFlagsCommand)->buildLive();

    expect($live)->toHaveKey('Cliente/Create')
        ->and($live['Cliente/Create'])->toBe(['4']);
});

it('marca enabled SEM allowlist como ["*"] (todos os tenants)', function () {
    config(['mwart.cliente_edit.enabled' => true, 'mwart.cliente_edit.business_ids' => []]);

    $live = (new ProdFlagsCommand)->buildLive();

    expect($live['Cliente/Edit'] ?? null)->toBe(['*']);
});

it('exclui componente com flag desabilitada (nao-live, existir != estar live)', function () {
    config(['mwart.cliente_map.enabled' => false]);

    $live = (new ProdFlagsCommand)->buildLive();

    expect($live)->not->toHaveKey('Cliente/Map');
});

it('o comando roda e sai 0 (dry-run)', function () {
    $this->artisan('governance:prod-flags')->assertExitCode(0);
});

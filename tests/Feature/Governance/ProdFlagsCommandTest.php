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

// mergeLive() — merge-safe: preserva não-gerenciados, autoritativo sobre gerenciados (Cliente/*).
// Sem isso, --write apagava Sells/Financeiro/RB/OficinaAuto (seed manual) — regressão.

it('preserva componentes nao-gerenciados no merge (seed manual de outros modulos)', function () {
    $existing = [
        'Sells/Index' => ['4'],
        'Financeiro/Dre/Index' => ['4'],
        'Cliente/Index' => ['4'],   // gerenciado — sera sobrescrito
    ];
    $derived = ['Cliente/Index' => ['*']];

    $merged = (new ProdFlagsCommand)->mergeLive($existing, $derived);

    expect($merged)->toHaveKey('Sells/Index')
        ->and($merged['Sells/Index'])->toBe(['4'])
        ->and($merged['Financeiro/Dre/Index'])->toBe(['4'])
        ->and($merged['Cliente/Index'])->toBe(['*']);   // derivado venceu o seed stale
});

it('remove componente gerenciado que caiu (flag off nao deixa entrada zumbi)', function () {
    $existing = ['Cliente/Map' => ['4'], 'Sells/Index' => ['4']];
    $derived = [];   // nenhuma flag cliente live

    $merged = (new ProdFlagsCommand)->mergeLive($existing, $derived);

    expect($merged)->not->toHaveKey('Cliente/Map')   // gerenciado + off → removido
        ->and($merged['Sells/Index'])->toBe(['4']);  // nao-gerenciado → preservado
});

it('managedComponents lista exatamente os componentes de FLAG_COMPONENT', function () {
    expect((new ProdFlagsCommand)->managedComponents())
        ->toBe(array_values(ProdFlagsCommand::FLAG_COMPONENT));
});

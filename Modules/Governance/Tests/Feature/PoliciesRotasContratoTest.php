<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * /governance/policies — o contrato das ROTAS, lido do registry vivo (`gatherMiddleware()`).
 *
 * Casos: resources/js/Pages/governance/Policies.casos.md (UC-GPOL-05, 06, 07).
 * Os três são propriedade da rota, não da tela; o lugar barato de prová-los é o registry,
 * que é o que o casos.md manda (UC-GPOL-07 "Pronto quando"). Sem banco: roda na lane sqlite.
 *
 * Até aqui os três ficavam na fila ⛓ do `casos:report`: o id só aparecia em comentário do
 * e2e `governance-policies.spec.ts`, e comentário nunca chega ao manifesto de vereditos.
 *
 * Não cobre: o papel `Admin#{empresa}`, que o `Gate::before` do AuthServiceProvider ainda
 * libera em `governance.*` (ADR 0415; passo da ADR 0392 em aberto, decisão [W]).
 */

function govPolRota(string $nome): \Illuminate\Routing\Route
{
    $rota = \Route::getRoutes()->getByName($nome);
    expect($rota)->not->toBeNull("rota {$nome} não registrada");

    return $rota;
}

/** Rotas governance.policies.* que escrevem (método diferente de GET/HEAD). */
function govPolRotasDeEscrita(): array
{
    return collect(\Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'governance.policies.'))
        ->filter(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']) !== [])
        ->map(fn ($r) => $r->getName())
        ->values()
        ->all();
}

it('UC-GPOL-05 · não existe alternar em lote: a única escrita é o toggle de UM id por requisição', function () {
    // positivo: o toggle existe e é a única porta de escrita das policies
    expect(govPolRotasDeEscrita())->toBe(['governance.policies.toggle']);

    $toggle = govPolRota('governance.policies.toggle');
    // um parâmetro só, o id, na URL — coleção não cabe em `{id}`
    expect($toggle->parameterNames())->toBe(['id'])
        ->and($toggle->uri())->toContain('{id}');
});

it('UC-GPOL-06 · ler exige governance.dashboard.view; alternar exige governance.policies.edit', function () {
    $leitura = govPolRota('governance.policies.index')->gatherMiddleware();
    $escrita = govPolRota('governance.policies.toggle')->gatherMiddleware();

    expect($leitura)->toContain('can:governance.dashboard.view')
        ->and($leitura)->not->toContain('can:governance.policies.edit');
    expect($escrita)->toContain('can:governance.policies.edit');
});

it('UC-GPOL-07 · o toggle é limitado a 10 por minuto, mais apertado que a leitura', function () {
    $escrita = govPolRota('governance.policies.toggle')->gatherMiddleware();
    $leitura = govPolRota('governance.policies.index')->gatherMiddleware();

    expect($escrita)->toContain('throttle:10,1')
        ->and($leitura)->toContain('throttle:60,1');
});

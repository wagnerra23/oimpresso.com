<?php

declare(strict_types=1);

use App\Services\FeatureFlagService;
use Modules\Fiscal\Http\Controllers\CockpitController;

uses(Tests\TestCase::class);

/**
 * UC-FCKP-17 — atalho "Naturezas de operação" no topo do /fiscal (RUNBOOK-tributacao §Naturezas).
 *
 * A seção mora na Tributação (`/nfe-brasil/tributacao#naturezas`); o cockpit só leva até ela. O
 * botão aparece com as duas condições juntas: a flag `useV2NfeNaturezas` ligada para a empresa e a
 * permissão que a própria rota da Tributação exige (`nfe.tributacao.manage`). Faltando uma, a prop
 * `naturezasUrl` vem null e a tela não desenha o botão — sem flag, quem clicasse cairia num 404;
 * sem permissão, num 403.
 *
 * SEM BANCO, de propósito: a decisão é só flag × permissão × rota. Usuário e flag são dublês, então
 * o teste roda em qualquer lane (inclusive SQLite) e não pula com `0 failed` sem medir (LC-13).
 * Empresa 98, tenant fictício (ADR 0358).
 *
 * @covers-us US-FISCAL-002
 */

function cockpitAtalhoUsuario(array $permissoes): \App\User
{
    return new class($permissoes) extends \App\User {
        public function __construct(private array $permissoesDoDuble = [])
        {
            parent::__construct();
        }

        public function can($abilities, $arguments = []): bool
        {
            return in_array($abilities, $this->permissoesDoDuble, true);
        }
    };
}

function cockpitAtalhoFlag(bool $ligada): void
{
    $flags = Mockery::mock(FeatureFlagService::class);
    $flags->shouldReceive('isOn')
        ->with('useV2NfeNaturezas', ['business_id' => 98])
        ->andReturn($ligada);
    app()->instance(FeatureFlagService::class, $flags);
}

function cockpitAtalhoUrl(): ?string
{
    $metodo = new ReflectionMethod(CockpitController::class, 'naturezasUrl');

    return $metodo->invoke(app(CockpitController::class), 98);
}

it('UC-FCKP-17 · flag ligada e permissão da Tributação: o atalho leva à seção Naturezas', function () {
    cockpitAtalhoFlag(true);
    $this->actingAs(cockpitAtalhoUsuario(['nfe.tributacao.manage']));

    expect(cockpitAtalhoUrl())->toBe(route('nfe-brasil.tributacao.index').'#naturezas');
});

it('UC-FCKP-17 · flag desligada: sem atalho, mesmo com permissão', function () {
    cockpitAtalhoFlag(false);
    $this->actingAs(cockpitAtalhoUsuario(['nfe.tributacao.manage', 'superadmin']));

    expect(cockpitAtalhoUrl())->toBeNull();
});

it('UC-FCKP-17 · só fiscal.access, sem a permissão da Tributação: sem atalho', function () {
    cockpitAtalhoFlag(true);
    $this->actingAs(cockpitAtalhoUsuario(['fiscal.access']));

    expect(cockpitAtalhoUrl())->toBeNull();
});

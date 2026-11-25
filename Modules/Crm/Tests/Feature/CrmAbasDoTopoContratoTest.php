<?php

declare(strict_types=1);

use App\Services\FeatureFlagService;
use App\User;
use Modules\Crm\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Abas do topo das telas do CRM (`DataController::abasDoCrm`, lidas pelo ModuleTabs).
 *
 * Âncora: pendências CRM-01 e CRM-02, medidas em produção em 2026-11-25 (empresa sem as telas
 * novas ligadas, usuário "Operacional" sem Admin):
 *  - CRM-02: Relatórios e Configurações apareciam para quem leva 403 nelas. A aba tem de seguir o
 *    MESMO portão do controller da rota (ReportController/CrmSettingsController exigem Admin;
 *    Leads, Acompanhamentos, Campanhas e Login de contatos pedem a permissão own/all deles).
 *  - CRM-01: com a chave da tela nova desligada a rota devolve a Blade; a aba precisa sair com
 *    `recarrega` para não ser aberta como visita Inertia (janela de erro).
 *
 * Não toca banco: usuário e chaves são dublês. Não há tenant de dado aqui; o business_id 98 é só
 * o atributo passado ao serviço de chaves (ADR 0358).
 */
function abasUsuario(array $perms): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->andReturnUsing(fn ($p) => in_array($p, $perms, true));
    auth()->setUser($user);

    return $user;
}

function abasChaves(array $ligadas): void
{
    app()->instance(FeatureFlagService::class, new class($ligadas)
    {
        public function __construct(private array $ligadas) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return in_array($flag, $this->ligadas, true) && ($attrs['business_id'] ?? null) === 98;
        }
    });
}

function abasKeys(array $abas): array
{
    return array_column($abas, 'key');
}

it('CRM-02 · Operacional sem Admin não vê Relatórios nem Configurações', function () {
    abasUsuario(['crm.access_all_leads', 'crm.access_all_schedule', 'crm.access_all_campaigns', 'crm.access_contact_login']);
    abasChaves([]);

    $keys = abasKeys(DataController::abasDoCrm(98, false));

    expect($keys)->toBe(['dashboard', 'leads', 'follow-ups', 'campaigns', 'contacts-login', 'proposals']);
});

it('CRM-02 · Admin vê Relatórios e Configurações', function () {
    abasUsuario([]);
    abasChaves([]);

    $keys = abasKeys(DataController::abasDoCrm(98, true));

    expect($keys)->toContain('reports');
    expect($keys)->toContain('settings');
});

it('CRM-02 · sem a permissão own/all a aba some (a mesma regra do controller)', function () {
    abasUsuario(['crm.access_own_leads']);
    abasChaves([]);

    $keys = abasKeys(DataController::abasDoCrm(98, false));

    expect($keys)->toContain('leads');
    expect($keys)->not->toContain('follow-ups');
    expect($keys)->not->toContain('campaigns');
    expect($keys)->not->toContain('contacts-login');
});

it('CRM-01 · chave desligada marca a aba para recarregar; chave ligada não', function () {
    abasUsuario(['crm.access_all_campaigns', 'crm.access_contact_login']);
    abasChaves(['useV2CrmCampanhas']);

    $porKey = collect(DataController::abasDoCrm(98, true))->keyBy('key');

    expect($porKey['campaigns'])->not->toHaveKey('recarrega');
    expect($porKey['contacts-login']['recarrega'] ?? null)->toBeTrue();
    expect($porKey['proposals']['recarrega'] ?? null)->toBeTrue();
    expect($porKey['reports']['recarrega'] ?? null)->toBeTrue();
    expect($porKey['settings']['recarrega'] ?? null)->toBeTrue();
    // Painel, Leads e Acompanhamentos são Inertia sem chave.
    expect($porKey['dashboard'])->not->toHaveKey('recarrega');
});

it('CRM-01 · a chave é lida para a empresa da sessão, não para outra', function () {
    abasUsuario(['crm.access_all_campaigns']);
    abasChaves(['useV2CrmCampanhas']);

    $porKey = collect(DataController::abasDoCrm(99, false))->keyBy('key');

    expect($porKey['campaigns']['recarrega'] ?? null)->toBeTrue();
});

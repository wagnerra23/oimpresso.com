<?php

declare(strict_types=1);

use App\Services\FeatureFlagService;
use Modules\Repair\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Abas do topo das telas da Assistência técnica (`DataController::abasDoRepair`, lidas pelo
 * ModuleTabs e pelos sub-itens do menu lateral).
 *
 * Âncora: medido em produção em 2026-11-27 (empresa WR2). A aba "Configurações" abria a janela de
 * erro do Inertia com a página Blade dentro, porque a tela React de Configurações está desligada
 * para a empresa e a aba navegava como visita Inertia. Mesmo conserto do CRM-01
 * (`CrmAbasDoTopoContratoTest`): tela desligada ⇒ a aba sai com `recarrega`.
 *
 * Não toca banco: as chaves são dublês. O business_id 98 é só o atributo passado ao serviço de
 * chaves (ADR 0358).
 */
function repairAbasChaves(array $ligadas): void
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

beforeEach(function () {
    // A 1ª porta do `mwartEnabled` é o config/mwart.php; desligada aqui para o teste medir só a chave.
    foreach (['repair_dashboard_index', 'repair_job_sheet_index', 'repair_index', 'repair_status_index', 'repair_device_models_index', 'repair_settings_index'] as $tela) {
        config(["mwart.{$tela}.enabled" => false, "mwart.{$tela}.business_ids" => []]);
    }
});

it('mantém a ordem e os rótulos do protótipo', function () {
    repairAbasChaves([]);

    $abas = app(DataController::class)->abasDoRepair(98);

    expect(array_column($abas, 'key'))->toBe(['painel', 'producao', 'folhas', 'reparos', 'status', 'modelos', 'config']);
    expect(array_column($abas, 'label'))->toBe(['Painel', 'Produção', 'Folhas de OS', 'Reparos', 'Status', 'Modelos', 'Configurações']);
});

it('chave desligada marca a aba para recarregar; chave ligada não', function () {
    repairAbasChaves(['useV2RepairIndex']);

    $porKey = collect(app(DataController::class)->abasDoRepair(98))->keyBy('key');

    expect($porKey['reparos'])->not->toHaveKey('recarrega');
    expect($porKey['config']['recarrega'] ?? null)->toBeTrue();
    expect($porKey['status']['recarrega'] ?? null)->toBeTrue();
    // Produção da oficina não tem Blade: nunca recarrega.
    expect($porKey['producao'])->not->toHaveKey('recarrega');
});

it('a porta do config/mwart.php também conta como tela ligada', function () {
    repairAbasChaves([]);
    config(['mwart.repair_settings_index.enabled' => true, 'mwart.repair_settings_index.business_ids' => [98]]);

    $porKey = collect(app(DataController::class)->abasDoRepair(98))->keyBy('key');

    expect($porKey['config'])->not->toHaveKey('recarrega');
});

it('a chave é lida para a empresa pedida, não para outra', function () {
    repairAbasChaves(['useV2RepairSettings']);

    $porKey = collect(app(DataController::class)->abasDoRepair(99))->keyBy('key');

    expect($porKey['config']['recarrega'] ?? null)->toBeTrue();
});

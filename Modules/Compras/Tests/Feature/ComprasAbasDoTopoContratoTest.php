<?php

use App\Services\FeatureFlagService;
use App\User;
use Modules\Compras\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Abas do topo das telas de Compras (`DataController::abasDeCompras`, lidas pelo ModuleTabs).
 *
 * Pedidos, Requisições e Devoluções de compra são telas do protótipo (`data.jsx`, cmp-*) que já
 * tinham Page React atrás de flag e não tinham aba (triagem do Grupo 2, [F] 2026-11-27). Cada
 * aba segue a condição do menu clássico da tela: configuração da empresa + permissão. Com a flag
 * desligada a aba leva `recarrega`, porque a rota responde a Blade.
 *
 * Não toca banco: usuário e chaves são dublês. O business_id 98 é só o atributo passado ao serviço
 * de chaves (ADR 0358).
 */
function comprasAbasUsuario(array $perms): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->andReturnUsing(fn ($p) => in_array($p, $perms, true));
    auth()->setUser($user);

    return $user;
}

function comprasAbasChaves(array $ligadas): void
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

function comprasAbasSettings(array $common): void
{
    session(['business.common_settings' => $common]);
}

it('UC-CMP-ABAS-01 · com configuração e permissão aparecem as três abas, na ordem do protótipo', function () {
    comprasAbasUsuario(['purchase_order.view_all', 'purchase_requisition.view_own', 'purchase.view']);
    comprasAbasChaves(['useV2ComprasPedidos', 'useV2ComprasRequisicoes', 'useV2ComprasDevolucoes']);
    comprasAbasSettings(['enable_purchase_order' => 1, 'enable_purchase_requisition' => 1]);

    $abas = DataController::abasDeCompras(98);

    expect(array_column($abas, 'key'))->toBe(['lista', 'pedidos', 'requisicoes', 'devolucoes', 'fornecedores']);
    expect(array_column($abas, 'href'))->toContain('/purchase-order', '/purchase-requisition', '/purchase-return');
    foreach ($abas as $aba) {
        expect($aba)->not->toHaveKey('recarrega');
    }
});

it('UC-CMP-ABAS-02 · sem a configuração da empresa, Pedidos e Requisições não aparecem', function () {
    comprasAbasUsuario(['purchase_order.view_all', 'purchase_requisition.view_all', 'purchase.view']);
    comprasAbasChaves([]);
    comprasAbasSettings([]);

    expect(array_column(DataController::abasDeCompras(98), 'key'))->toBe(['lista', 'devolucoes', 'fornecedores']);
});

it('UC-CMP-ABAS-03 · sem permissão a aba some (a mesma regra do controller)', function () {
    comprasAbasUsuario([]);
    comprasAbasChaves([]);
    comprasAbasSettings(['enable_purchase_order' => 1, 'enable_purchase_requisition' => 1]);

    expect(array_column(DataController::abasDeCompras(98), 'key'))->toBe(['lista', 'fornecedores']);
});

it('UC-CMP-ABAS-04 · flag desligada para a empresa marca a aba para recarregar', function () {
    comprasAbasUsuario(['purchase_order.view_own', 'purchase.create']);
    comprasAbasChaves(['useV2ComprasPedidos']);
    comprasAbasSettings(['enable_purchase_order' => 1]);

    $porKey = collect(DataController::abasDeCompras(99))->keyBy('key');

    expect($porKey['pedidos']['recarrega'] ?? null)->toBeTrue();
    expect($porKey['devolucoes']['recarrega'] ?? null)->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Repair\Http\Controllers\RepairController;

/**
 * Thread 03 do playbook Repair — "repair.create / repair.edit — sair do Blade".
 *
 * Decisão [W] 2026-10-01 (D1, `_DECISOES-W-2026-10-01b.md`): *"é uma venda, tipo de venda
 * igual ao OS auto"*. O cadastro de reparo é o PDV com `sub_type=repair` — `/pos/create?
 * sub_type=repair` para criar, `/repair/repair/{id}/edit` → `editarVenda` → `/pos/{id}/edit?
 * sub_type=repair` para editar (UC-RSHW-05/06, `RepairShowContratoTest`).
 *
 * `RepairController@create` e `@edit` montavam o formulário Blade `repair::repair.create|edit`,
 * mas NUNCA tiveram rota: o resource é `->except(['create','edit'])` desde o import de
 * 2025-05-16. Eram código morto e saíram. Este teste trava que nenhuma rota volte a apontar
 * para eles — perguntando ao registry de rotas, não ao disco (`method_exists` provaria só que
 * o arquivo tem o método, não o que o roteador serve).
 *
 * Sem banco: lê só o roteador da aplicação. Roda em qualquer lane que suba o app.
 */
uses(Tests\TestCase::class);

/** @return array<int, string> ações registradas que apontam para o RepairController */
function rfvAcoesDoRepair(): array
{
    $acoes = [];
    foreach (Route::getRoutes()->getRoutes() as $rota) {
        $acao = $rota->getActionName();
        if (str_starts_with($acao, RepairController::class.'@')) {
            $acoes[$rota->methods()[0].' '.$rota->uri()] = $acao;
        }
    }

    return $acoes;
}

it('nenhuma rota serve o formulário Blade legado de reparo (RepairController@create/@edit)', function () {
    $acoes = rfvAcoesDoRepair();

    // Controle positivo: as rotas do módulo estão carregadas. Sem isto, um roteador vazio
    // passaria o assert de ausência abaixo sem ter olhado nada.
    expect($acoes)->toHaveKey('GET repair/repair/{id}/edit');
    expect($acoes['GET repair/repair/{id}/edit'])->toBe(RepairController::class.'@editarVenda');

    $legadas = array_filter(
        $acoes,
        fn (string $acao) => in_array($acao, [RepairController::class.'@create', RepairController::class.'@edit'], true)
    );
    expect($legadas)->toBe([]);
});

it('o controller não carrega mais os métodos do formulário Blade', function () {
    // Complemento do teste acima: se alguém religar o resource sem o `except`, o Laravel
    // registraria `create`/`edit` e apontaria para métodos inexistentes — este assert
    // documenta que a remoção foi deliberada e não um método perdido num refactor.
    expect(method_exists(RepairController::class, 'create'))->toBeFalse();
    expect(method_exists(RepairController::class, 'edit'))->toBeFalse();
    expect(method_exists(RepairController::class, 'editarVenda'))->toBeTrue();
});

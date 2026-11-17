<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Modules\Whatsapp\Http\Controllers\Api\EmployeeScorecardController;

uses(Tests\TestCase::class);

/**
 * R-WA-MENU-JSON · o menu Atendimento não leva a JSON cru.
 *
 * Auditoria Whatsapp 2026-11-05 (P1): o item "Time" do submenu e o ghost `time` do
 * header apontavam pra `atendimento.employee.scorecards` → `EmployeeScorecardController::index`,
 * que devolve `JsonResponse` — o usuário clicava e via JSON na tela.
 *
 * O contrato é condicional de propósito: ENQUANTO `index()` devolver JsonResponse,
 * o DataController não pode citar a rota (nome nem href) em código executável.
 * Quando existir tela (Inertia), o 1º assert muda e o teste obriga a rever este arquivo.
 * Comentários do DataController são ignorados (token_get_all), senão a nota que explica
 * a remoção faria o teste reprovar sozinho.
 */
function dataControllerCodigoSemComentarios(): string
{
    $fonte = file_get_contents(base_path('Modules/Whatsapp/Http/Controllers/DataController.php'));
    $codigo = '';
    foreach (token_get_all($fonte) as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $codigo .= is_array($t) ? $t[1] : $t;
    }

    return $codigo;
}

it('R-WA-MENU-JSON-01 — scorecards index segue devolvendo JsonResponse (pré-condição do contrato)', function () {
    $tipo = (new ReflectionMethod(EmployeeScorecardController::class, 'index'))->getReturnType();

    expect($tipo?->getName())->toBe(JsonResponse::class);
});

it('R-WA-MENU-JSON-02 — DataController não aponta submenu nem ghost pra rota de JSON cru', function () {
    $codigo = dataControllerCodigoSemComentarios();

    // controle positivo: o leitor enxerga o código (uma rota que DEVE estar lá)
    expect($codigo)->toContain("route('atendimento.csat.index')");

    expect(str_contains($codigo, 'atendimento.employee.scorecards'))->toBeFalse();
    expect(str_contains($codigo, '/atendimento/employee/scorecards'))->toBeFalse();
});

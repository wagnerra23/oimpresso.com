<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Producao\Http\Controllers\DataController;
use Modules\Producao\Http\Controllers\InstallController;

uses(Tests\TestCase::class);

/**
 * Esqueleto do Modules/Producao — contrato da ADR 0431 (2026-10-15), não do código:
 *
 *  - D1: módulo `Producao`, prefixo `/producao`, chave de sistema `producao`;
 *  - D-PERM: `producao.etapa.mover` e `producao.desempenho.ver`, aprovadas por [W];
 *  - pacote do superadmin `default => false` (add-on contratado; o piloto entra
 *    marcando o pacote, nunca por `if ($business_id === N)` — proibicoes.md);
 *  - `Modules/IProduction` não existe (removido no PR #842; ADR 0431 §Decisão 3).
 *
 * Sem tabela e sem tenant: o esqueleto não grava nada. O isolamento multi-tenant
 * passa a ser provado na thread 02, que cria as tabelas.
 */
it('expõe as permissões aprovadas por [W] em D-PERM', function () {
    $valores = array_column((new DataController())->user_permissions(), 'value');

    expect($valores)->toContain('producao.etapa.mover')
        ->and($valores)->toContain('producao.desempenho.ver');

    foreach ((new DataController())->user_permissions() as $permissao) {
        $this->assertStringStartsWith('producao.', $permissao['value'], 'toda permissão do módulo usa o prefixo producao.* (D1)');
        $this->assertFalse($permissao['default'], "permissão {$permissao['value']} não pode nascer concedida por padrão");
    }
});

it('nasce com o pacote do superadmin desligado por padrão', function () {
    $pacote = (new DataController())->superadmin_package();

    expect($pacote)->toHaveCount(1)
        ->and($pacote[0]['name'])->toBe('producao_module')
        ->and($pacote[0]['default'])->toBeFalse();
});

it('registra a instalação 1-clique em /producao com a chave de sistema producao', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($rota) => $rota->uri())
        ->all();

    expect($uris)->toContain('producao/install')
        ->and($uris)->toContain('producao/install/uninstall')
        ->and($uris)->toContain('producao/install/update');

    $chave = (new ReflectionMethod(InstallController::class, 'moduleSystemKey'))
        ->invoke(app(InstallController::class));

    expect($chave)->toBe('producao');
});

it('não traz de volta o Modules/IProduction', function () {
    expect(base_path('Modules/IProduction/module.json'))->not->toBeFile()
        ->and(base_path('Modules/Producao/module.json'))->toBeFile();
});

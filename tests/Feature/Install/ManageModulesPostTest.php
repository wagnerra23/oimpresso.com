<?php

/**
 * /manage-modules — instalar / desinstalar / atualizar por POST + CSRF.
 *
 * A tela montava <a href> (GET) para as ações de cada módulo nWidart; GET que muda estado
 * é alvo de CSRF e de prefetch. Agora a tela usa <form method="POST"> + @csrf quando o
 * módulo REGISTRA POST na mesma URL — decidido pelo router (ModulesController::aceitaPost),
 * não por lista à mão — e mantém o link GET nos que ainda só têm GET (senão 405).
 *
 * DB-less: só router, reflexão e render de partial. Roda na lane sqlite.
 */

use App\Http\Controllers\Install\ModulesController;
use Illuminate\Support\Facades\Route;

function manageModulesUrl(string $modulo, string $metodo): string
{
    try {
        return action('\Modules\\'.$modulo.'\Http\Controllers\InstallController@'.$metodo);
    } catch (\Throwable $e) {
        return '#';
    }
}

function manageModulesCorpo(string $classe, string $metodo): string
{
    $ref = new ReflectionMethod($classe, $metodo);
    $linhas = file($ref->getFileName());

    return implode('', array_slice($linhas, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
}

/** Censo derivado do router: [modulo => [acao => aceitaPost]] para módulos com InstallController. */
function manageModulesCenso(): array
{
    $censo = [];
    foreach (array_keys(json_decode(file_get_contents(base_path('modules_statuses.json')), true)) as $modulo) {
        if (! class_exists('Modules\\'.$modulo.'\\Http\\Controllers\\InstallController')) {
            continue;
        }
        foreach (['index' => 'install', 'uninstall' => 'uninstall', 'update' => 'update'] as $metodo => $acao) {
            $censo[$modulo][$acao] = ModulesController::aceitaPost(manageModulesUrl($modulo, $metodo));
        }
    }

    return $censo;
}

it('aceitaPost pergunta ao router: módulo migrado aceita, GET-only não, # não', function () {
    expect(ModulesController::aceitaPost(url('connector/install/uninstall')))->toBeTrue();
    expect(ModulesController::aceitaPost(url('arquivos/install/update')))->toBeTrue();
    expect(ModulesController::aceitaPost(url('arquivos/install')))->toBeTrue();

    // Financeiro só registra GET em install/uninstall → POST daria 405 → link GET fica.
    expect(ModulesController::aceitaPost(url('financeiro/install/uninstall')))->toBeFalse();
    expect(ModulesController::aceitaPost('#'))->toBeFalse();
    // Rota POST que não é de InstallController de módulo não conta.
    expect(ModulesController::aceitaPost(url('manage-modules')))->toBeFalse();
});

it('o censo derivado tem os dois lados: há módulo com POST e módulo só-GET', function () {
    $censo = manageModulesCenso();

    expect(count($censo))->toBeGreaterThan(10);
    expect(collect($censo)->filter(fn ($a) => $a['uninstall'])->keys()->all())->toContain('Connector', 'Arquivos');
    expect(collect($censo)->reject(fn ($a) => $a['uninstall'])->count())->toBeGreaterThan(0);
});

it('POST na rota de um módulo migrado cai no método que executa a ação', function () {
    $rota = Route::getRoutes()->match(\Illuminate\Http\Request::create(url('connector/install/uninstall'), 'POST'));

    expect($rota->getActionName())->toBe('Modules\Connector\Http\Controllers\InstallController@uninstall');
});

it('nos módulos cujo uninstall/update aceita POST, o GET da mesma URL não executa', function () {
    foreach (manageModulesCenso() as $modulo => $acoes) {
        $classe = 'Modules\\'.$modulo.'\\Http\\Controllers\\InstallController';
        foreach (['uninstall', 'update'] as $metodo) {
            if (! $acoes[$metodo]) {
                continue;
            }
            $get = Route::getRoutes()->match(\Illuminate\Http\Request::create(manageModulesUrl($modulo, $metodo), 'GET'));
            if (! str_ends_with($get->getActionName(), '@'.$metodo)) {
                continue; // GET vai para outro método — não é o mesmo endpoint.
            }

            $corpo = manageModulesCorpo($classe, $metodo);
            // Guarda inline (Connector/Arquivos) ou via trait InstalacaoSoPorPost.
            $guarda = strpos($corpo, "isMethod('post')");
            if ($guarda === false) {
                $guarda = strpos($corpo, 'confirmacaoSeNaoForPost(');
            }
            $acao = strpos($corpo, 'parent::'.$metodo.'(');

            expect($guarda)->not->toBeFalse("{$modulo}@{$metodo} aceita POST mas executa no GET");
            expect($acao)->not->toBeFalse();
            expect($guarda)->toBeLessThan($acao);
        }
    }
});

it('a partial renderiza form POST com CSRF quando o módulo aceita POST', function () {
    $html = view('install.modules.partials.acao', [
        'url' => url('connector/install/uninstall'),
        'post' => true,
        'classe' => 'btn btn-warning btn-xs',
        'rotulo' => 'Desinstalar',
        'confirmar' => 'Confirma?',
        'is_demo' => false,
    ])->render();

    expect($html)->toContain('method="POST"');
    expect($html)->toContain('action="'.url('connector/install/uninstall').'"');
    expect($html)->toContain('name="_token"');
    expect($html)->toContain("return confirm('Confirma?')");
    expect($html)->not->toContain('href="'.url('connector/install/uninstall').'"');
});

it('a partial mantém o link GET quando o módulo só registra GET', function () {
    $html = view('install.modules.partials.acao', [
        'url' => url('financeiro/install/uninstall'),
        'post' => false,
        'classe' => 'btn btn-warning btn-xs',
        'rotulo' => 'Desinstalar',
        'is_demo' => false,
    ])->render();

    expect($html)->toContain('href="'.url('financeiro/install/uninstall').'"');
    expect($html)->not->toContain('method="POST"');
});

it('a view de /manage-modules usa a partial nas 3 ações', function () {
    $view = file_get_contents(resource_path('views/install/modules/index.blade.php'));

    expect(substr_count($view, "@include('install.modules.partials.acao'"))->toBe(3);
    expect($view)->toContain("\$module['install_post']");
    expect($view)->toContain("\$module['uninstall_post']");
    expect($view)->toContain("\$module['update_post']");
});

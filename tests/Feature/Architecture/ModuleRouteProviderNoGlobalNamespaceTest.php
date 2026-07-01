<?php

declare(strict_types=1);

// Tests\TestCase é aplicado globalmente em tests/Pest.php. NÃO redeclarar aqui.

/**
 * Anti-regressão: RouteServiceProvider de módulo NÃO pode declarar
 * `protected $namespace`.
 *
 * Por quê: o boot() do Illuminate\Foundation\Support\Providers\RouteServiceProvider
 * chama setRootControllerNamespace($this->namespace), que seta o root controller
 * namespace GLOBAL do UrlGenerator (singleton compartilhado). O último módulo a
 * bootar "vence" e passa a prefixar TODA `action('App\Http\Controllers\...@metodo')`
 * legada (string sem `\` inicial) com o namespace do módulo → InvalidArgumentException
 * "Action ... not defined" → HTTP 500.
 *
 * Incidente 2026-07-01: /sell-return (Devolução) quebrou no ROTA LIVRE (biz=4)
 * porque OficinaAuto (um dos 5 módulos com o padrão) venceu o boot e poluiu o
 * root namespace. A DataTable de devolução usa action() string no blade da coluna.
 *
 * Rotas de módulo usam FQCN [Controller::class, 'metodo'] — não precisam de
 * namespace de grupo. Este teste garante que ninguém reintroduza a propriedade.
 *
 * Refs:
 *   - app/Http/Controllers/SellReturnController.php (index → Datatables::make)
 *   - Illuminate\Routing\UrlGenerator::formatAction()
 */

const MODULE_ROUTE_PROVIDERS_GLOB = __DIR__ . '/../../../Modules/*/Providers/RouteServiceProvider.php';

describe('RouteServiceProvider de módulo não polui root controller namespace global', function () {
    it('nenhum RouteServiceProvider de módulo declara protected $namespace', function () {
        $files = glob(MODULE_ROUTE_PROVIDERS_GLOB) ?: [];

        expect($files)->not->toBeEmpty(); // sanity: o glob acha os providers

        $ofensores = [];
        foreach ($files as $file) {
            $src = file_get_contents($file);
            // Padrão banido: `protected $namespace = '...';` (com ou sem tipo)
            if (preg_match('/protected\s+(?:\??string\s+)?\$namespace\s*=/', $src)) {
                $ofensores[] = str_replace(__DIR__ . '/../../../', '', $file);
            }
        }

        expect($ofensores)->toBe(
            [],
            'Estes RouteServiceProvider reintroduziram `protected $namespace` (polui o '
            . 'root controller namespace GLOBAL e quebra action() legada → HTTP 500): '
            . implode(', ', $ofensores)
        );
    });
});

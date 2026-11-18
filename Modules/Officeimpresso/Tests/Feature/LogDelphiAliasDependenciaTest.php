<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Modules\Officeimpresso\Http\Middleware\LogDelphiAccess;

uses(Tests\TestCase::class);

/**
 * Dependência Connector → Officeimpresso pelo alias `log.delphi` (auditoria 2026-11-05, Lote 3).
 *
 * O alias só nasce em OfficeimpressoServiceProvider::boot(). Os três grupos de rota de
 * Modules/Connector/Routes/api.php (connector/api, connector/api/crm, fieldforce) o usam.
 * O nWidart não lê o campo `requires` do module.json, então nada impede desligar o
 * Officeimpresso e manter o Connector ligado. A declaração está no `provides` do
 * module.json deste módulo; quem segura o contrato são estes casos, que olham o roteador
 * já montado, não o texto do arquivo de rotas.
 *
 * Sem banco: os casos não tocam dado nenhum, logo não há tenant envolvido.
 */
function rotasConnectorApi(): array
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $r) => str_starts_with($r->uri(), 'connector/api'))
        ->values()
        ->all();
}

it('o alias log.delphi aponta para LogDelphiAccess quando o Officeimpresso está ligado', function () {
    expect(app('router')->getMiddleware())->toHaveKey('log.delphi');
    expect(app('router')->getMiddleware()['log.delphi'])->toBe(LogDelphiAccess::class);
});

it('toda rota connector/api resolve todos os middlewares para classe existente', function () {
    $rotas = rotasConnectorApi();
    // Sem rota o caso passaria por vácuo.
    expect(count($rotas))->toBeGreaterThan(0);

    $comLogDelphi = 0;
    foreach ($rotas as $rota) {
        if (in_array('log.delphi', $rota->gatherMiddleware(), true)) {
            $comLogDelphi++;
        }
        foreach (app('router')->gatherRouteMiddleware($rota) as $mw) {
            if (! is_string($mw)) {
                continue;
            }
            $classe = explode(':', $mw, 2)[0];
            expect(class_exists($classe))->toBeTrue("rota {$rota->uri()}: middleware [{$mw}] não resolve para classe");
        }
    }
    // Os 3 grupos usam o alias; se ele sair do Connector, este caso deixa de valer e precisa ser revisto.
    expect($comLogDelphi)->toBeGreaterThan(0);
});

it('sem o alias (Officeimpresso desligado) a rota connector/api quebra ao montar o pipeline', function () {
    $rota = collect(rotasConnectorApi())->first(fn (Route $r) => in_array('GET', $r->methods(), true)
        && ! str_contains($r->uri(), '{')
        && in_array('log.delphi', $r->gatherMiddleware(), true));
    expect($rota)->not->toBeNull();

    // Reproduz o boot sem o provider do Officeimpresso: o roteador sem o alias.
    $router = app(Router::class);
    $prop = new ReflectionProperty(Router::class, 'middleware');
    $aliases = $prop->getValue($router);
    unset($aliases['log.delphi']);
    $prop->setValue($router, $aliases);

    $this->withoutExceptionHandling();
    expect(fn () => $this->getJson('/'.$rota->uri()))
        ->toThrow(BindingResolutionException::class, 'log.delphi');
});

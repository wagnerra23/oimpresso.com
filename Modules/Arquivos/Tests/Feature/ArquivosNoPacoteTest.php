<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Arquivos\Http\Middleware\ArquivosNoPacote;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Auditoria Arquivos 2026-11-05, lote L2 (D2) — a tela /arquivos confere o pacote da empresa,
 * como o menu já conferia. Sem banco: o pacote vem de ModuleUtil (mock) e o vínculo com as
 * rotas é lido do roteador.
 */
uses(Tests\TestCase::class);

function arquivosPacoteRequest(int $businessId): Request
{
    $request = Request::create('/arquivos', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('user.business_id', $businessId);

    return $request;
}

function arquivosPacoteMock(bool $noPacote, array &$chamadas): void
{
    $mock = Mockery::mock(ModuleUtil::class);
    $mock->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(function (...$args) use ($noPacote, &$chamadas) {
            $chamadas[] = $args;

            return $noPacote;
        });
    app()->instance(ModuleUtil::class, $mock);
}

it('empresa sem arquivos_module no pacote toma 403', function () {
    $chamadas = [];
    arquivosPacoteMock(false, $chamadas);
    $passou = false;

    try {
        (new ArquivosNoPacote())->handle(arquivosPacoteRequest(99), function () use (&$passou) {
            $passou = true;

            return response('ok');
        });
        $status = 200;
    } catch (HttpException $e) {
        $status = $e->getStatusCode();
    }

    expect($status)->toBe(403)
        ->and($passou)->toBeFalse()
        ->and($chamadas)->toBe([[99, 'arquivos_module', 'superadmin_package']]);
});

it('CONTROLE: empresa com arquivos_module no pacote segue para a tela', function () {
    $chamadas = [];
    arquivosPacoteMock(true, $chamadas);

    $resposta = (new ArquivosNoPacote())->handle(arquivosPacoteRequest(98), fn () => response('tela'));

    expect($resposta->getStatusCode())->toBe(200)
        ->and($resposta->getContent())->toBe('tela')
        ->and($chamadas)->toBe([[98, 'arquivos_module', 'superadmin_package']]);
});

it('as rotas da tela passam pela checagem de pacote', function (string $nome) {
    $rota = Route::getRoutes()->getByName($nome);

    expect($rota)->not->toBeNull()
        ->and($rota->gatherMiddleware())->toContain(ArquivosNoPacote::class);
})->with([
    'arquivos.index',
    'arquivos.classificar',
    'arquivos.excluir',
    'arquivos.restaurar',
    'arquivos.retencao.simular',
]);

it('o download (anexos de outros módulos) não depende do pacote do Arquivos', function () {
    $rota = Route::getRoutes()->getByName('arquivos.download');

    expect($rota)->not->toBeNull()
        ->and($rota->gatherMiddleware())->not->toContain(ArquivosNoPacote::class);
});

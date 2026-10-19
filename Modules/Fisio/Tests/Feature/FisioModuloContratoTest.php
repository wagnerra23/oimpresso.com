<?php

declare(strict_types=1);
// Cobre UC-FPN-01 (Fisio/Painel.casos.md) na parte de contrato estático e UC-FPS-07 (Fisio/Pacientes/Show.casos.md).
// @covers-us US-FISIO-002

use Illuminate\Support\Facades\Route;
use Modules\Fisio\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * Esqueleto do Modules/Fisio — contrato da ADR 0437 + handoff F1 (T-02), não do código.
 *
 * Sem tabela e sem tenant: estes casos não gravam nada, então rodam em qualquer lane
 * (inclusive sqlite). O comportamento com tenant real está em FisioModuloVisibilidadeTest.
 *
 * Os dois testes de varredura de fonte NÃO são "presença ≠ correção" (LC-11): eles provam
 * AUSÊNCIA de uma forma proibida (hardcode de business, forceDelete em dado clínico), que
 * é exatamente o que uma varredura consegue provar.
 */
it('nasce com o pacote fisio_module desligado por padrão (add-on contratado, nunca por if de business)', function () {
    $pacote = (new DataController())->superadmin_package();

    expect($pacote)->toHaveCount(1)
        ->and($pacote[0]['name'])->toBe('fisio_module')
        ->and($pacote[0]['default'])->toBeFalse();
});

it('expõe fisio.access e fisio.editar, todas com prefixo fisio. e nenhuma concedida por padrão', function () {
    $permissoes = (new DataController())->user_permissions();
    $valores = array_column($permissoes, 'value');

    expect($valores)->toContain('fisio.access')->toContain('fisio.editar');
    foreach ($permissoes as $p) {
        $this->assertStringStartsWith('fisio.', $p['value']);
        $this->assertFalse($p['default'], "permissão {$p['value']} não pode nascer concedida");
    }
});

it('registra as rotas da F1 com os nomes que as telas consomem', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->methods()[0].' '.$r->uri())->all();

    foreach ([
        'GET fisio', 'GET fisio/pacientes', 'POST fisio/pacientes', 'GET fisio/pacientes/{id}', 'PUT fisio/pacientes/{id}',
        'DELETE fisio/pacientes/{id}', 'POST fisio/pacientes/{id}/registros', 'POST fisio/pacientes/{id}/programas',
        'POST fisio/programas/{id}/itens', 'PUT fisio/programas/{id}/itens/ordem', 'PUT fisio/programas/{id}/itens/{item}',
        'DELETE fisio/programas/{id}/itens/{item}', 'POST fisio/programas/{id}/enviar',
        'GET fisio/biblioteca', 'POST fisio/biblioteca/exercicios',
        'GET fisio/p/{token}', 'POST fisio/p/{token}/pin', 'POST fisio/p/{token}/consentimentos',
        'DELETE fisio/p/{token}/consentimentos/{purpose}', 'POST fisio/p/{token}/checkin',
    ] as $esperada) {
        expect($uris)->toContain($esperada);
    }
});

it('UC-FPS-07 · nenhum código do módulo faz forceDelete, truncate ou hardcode de business', function () {
    $raiz = base_path('Modules/Fisio');
    expect($raiz.'/module.json')->toBeFile();

    $arquivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
    $lidos = 0;
    foreach ($arquivos as $f) {
        $rel = str_replace('\\', '/', substr((string) $f, strlen($raiz)));
        if (! str_ends_with($rel, '.php') || str_starts_with($rel, '/Tests/')) {
            continue;
        }
        $src = (string) file_get_contents((string) $f);
        $lidos++;
        expect($src)->not->toContain('forceDelete(', "forceDelete em {$rel} — guarda de 20 anos (Lei 13.787/2018)")
            ->and($src)->not->toMatch('/->truncate\(\)/', "truncate em {$rel}")
            ->and($src)->not->toMatch('/business_id\s*[!=]==?\s*\d+/', "hardcode de business em {$rel}");
    }

    // Controle negativo: a varredura leu código de verdade (não passou verde com 0 arquivos).
    expect($lidos)->toBeGreaterThan(5);
});

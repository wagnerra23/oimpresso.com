<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(PontoTestCase::class);

/**
 * Contrato de roteamento do Ponto: id que não é número dá 404, não 500.
 *
 * Origem (medido em produção 2026-09-29, biz=1): GET `/ponto/importacoes/create` → 500.
 * A rota de criação é `/importacoes/novo`; o `/create` casava `/importacoes/{id}` com
 * id="create", e o `ImportacaoController::show(int $id)` estourava TypeError.
 *
 * Medição da população (`Modules/Ponto/Http/routes.php`): 12 rotas GET com parâmetro,
 * 0 de 12 com restrição. 11 são de id numérico — 4 delas com `int` tipado no controller
 * (TypeError → 500 com id não numérico) e 1 sem o método (`escalas/{escala}` show). A 12ª,
 * `relatorios/{chave}`, é string por desenho e fica fora.
 *
 * Os casos provam em duas camadas: o ROUTER não casa a URL (oráculo do registry, não do
 * disco) e o HTTP devolve 404. Os `int`-tipados são os discriminantes pelo status: sem o
 * `whereNumber` eles dão 500. Os demais só a asserção do router discrimina, porque o
 * `findOrFail` sem tipo já cairia em 404 (e o MySQL converteria `"1abc"` em 1).
 *
 * Tier 0: tenant fictício 98 (ADR 0358), nunca biz=4.
 *
 * @see Modules/Ponto/Http/routes.php
 */

const ROTAID_MARCADOR = 'ROTA-ID-NAO-NUMERICO';

function rotaIdLogar98($t): int
{
    // `seededTenant()` (WithSeededTenant) resolve o biz=98; `business`/`admin` e
    // `ensurePontoPermissions` são protegidos no PontoTestCase, daí o escopo da instância.
    return (function (): int {
        $this->business = $this->seededTenant();
        $bizId = (int) $this->business->id;
        $user = \App\User::where('business_id', $bizId)->first();
        if (! $user) {
            $this->markTestSkipped("Tenant {$bizId} sem usuário.");
        }
        $this->admin = $user;
        $this->ensurePontoPermissions($bizId);

        session([
            'user.business_id' => $bizId,
            'user.id'          => $user->id,
            'business.id'      => $bizId,
            'business.name'    => $this->business->name,
            'is_admin'         => true,
        ]);
        $this->actingAs($user);

        return $bizId;
    })->call($t);
}

afterEach(function () {
    try {
        DB::table('ponto_importacoes')->where('nome_arquivo', 'like', ROTAID_MARCADOR . '%')->delete();
    } catch (\Throwable $e) {
        // schema ausente — limpeza best-effort
    }
});

it('id não numérico em rota de id do Ponto → o router não casa e o HTTP dá 404', function (string $url) {
    rotaIdLogar98($this);

    // A URI da rota que casou (null = nenhuma). Comparar a URI deixa a falha dizer QUAL
    // rota engoliu a URL, em vez de um "true is false".
    $casou = null;
    try {
        $casou = Route::getRoutes()->match(Request::create($url, 'GET'))->uri();
    } catch (NotFoundHttpException $e) {
        // nenhuma rota casou — é o esperado
    }
    expect($casou)->toBeNull();

    $this->get($url)->assertStatus(404);
})->with([
    'importacoes/create (o 500 medido em prod)' => '/ponto/importacoes/create',
    'importacoes/{id}/original'                 => '/ponto/importacoes/abc/original',
    'banco-horas/{colaborador}'                 => '/ponto/banco-horas/abc',
    'escalas/{escala}/edit'                     => '/ponto/escalas/abc/edit',
    'escalas/{escala}'                          => '/ponto/escalas/abc',
    'espelho/{colaborador}'                     => '/ponto/espelho/abc',
    'espelho/{colaborador}/imprimir'            => '/ponto/espelho/abc/imprimir',
    'intercorrencias/{intercorrencia}'          => '/ponto/intercorrencias/abc',
    'intercorrencias/{intercorrencia}/edit'     => '/ponto/intercorrencias/abc/edit',
    'intercorrencias/{id}/anexo'                => '/ponto/intercorrencias/abc/anexo',
    'colaboradores/{id}/editar'                 => '/ponto/colaboradores/abc/editar',
]);

it('as rotas válidas seguem respondendo: /importacoes/novo e o show de uma importação real → 200', function () {
    $bizId = rotaIdLogar98($this);
    if (! Schema::hasTable('ponto_importacoes')) {
        $this->markTestSkipped('ponto_importacoes ausente nesta lane.');
    }

    $this->inertiaGet('/ponto/importacoes/novo')->assertStatus(200);

    $imp = new Importacao();
    $imp->forceFill([
        'business_id'        => $bizId,
        'usuario_id'         => $this->admin->id,
        'tipo'               => 'AFD',
        'nome_arquivo'       => ROTAID_MARCADOR . '-' . uniqid() . '.txt',
        'arquivo_path'       => "ponto/importacoes/{$bizId}/fixture.txt",
        'hash_arquivo'       => hash('sha256', uniqid('', true)),
        'tamanho_bytes'      => 1024,
        'estado'             => Importacao::ESTADO_CONCLUIDA,
        'linhas_total'       => 0,
        'linhas_processadas' => 0,
        'linhas_sucesso'     => 0,
        'linhas_erro'        => 0,
    ])->save();

    $this->assertInertiaComponent($this->inertiaGet('/ponto/importacoes/' . $imp->id), 'Ponto/Importacoes/Show');
});

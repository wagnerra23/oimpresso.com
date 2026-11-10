<?php
// @covers-us US-COMVIS-004

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Entities\Apontamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController;
use Modules\ComunicacaoVisual\Services\ApontamentoTracker;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * API JSON de apontamento de produção (spool plotter) — US-COMVIS-004.
 *
 * Contrato: Index.casos.md UC-CV-05, UC-CV-06 e UC-CVAPT-01..05. Tenant 98 contra 99 (ADR 0358).
 * Chama o controller direto, sem o stack de rotas: o que se prova aqui é a regra do controller,
 * e o gate de pacote das rotas tem teste próprio.
 * ⚠️ SKIP em SQLite (lane sem as tabelas comvis_*): leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules\ComunicacaoVisual\Http\Controllers\ApontamentoController
 * @see memory/requisitos/ComunicacaoVisual/SDD-tela-orcamento-m2-v1.0.md §6 CU-CV-04/CU-CV-06
 * @see resources/js/Pages/ComunicacaoVisual/Index.casos.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

const APT_CTRL_PERMISSAO = 'comvis.apontamento.create';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_apontamentos')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $this->operador = $this->usuarioComPermissoes([APT_CTRL_PERMISSAO], $this->biz);
    $this->os = aptCtrlOs($this->biz->id);
});

function aptCtrlOs(int $businessId): Os
{
    // SUPERADMIN: fixture criada para uma empresa escolhida pelo teste, sem depender da sessão.
    return Os::withoutGlobalScopes()->create([
        'business_id' => $businessId,
        'numero' => 'OS-APT-'.uniqid(),
        'status_etapa' => 'producao',
        'valor_total' => 0,
    ]);
}

function aptCtrlAberto(int $businessId, Os $os, int $operadorId, array $extra = []): Apontamento
{
    // SUPERADMIN: fixture criada para uma empresa escolhida pelo teste, sem depender da sessão.
    return Apontamento::withoutGlobalScopes()->create([
        'business_id' => $businessId,
        'os_id' => $os->id,
        'operador_id' => $operadorId,
        'iniciado_em' => now()->subMinutes(5),
    ] + $extra);
}

function aptCtrlLogar($test, $user, int $businessId): void
{
    $test->actingAs($user);
    session(['user.business_id' => $businessId, 'business.id' => $businessId, 'user.id' => $user->id]);
}

function aptCtrlChamar($test, $user, string $acao, array $parametros = [], ?int $businessId = null)
{
    aptCtrlLogar($test, $user, $businessId ?? $test->biz->id);

    return app()->call([app(ApontamentoController::class), $acao], $parametros);
}

function aptCtrlRequest(array $dados = [], string $metodo = 'POST'): Request
{
    return Request::create('/comunicacao-visual/api/apontamentos', $metodo, $dados);
}

/** O id de uma peça de orçamento da empresa, com 4 m². */
function aptCtrlItem(int $businessId): int
{
    $payload = ['data_emissao' => '2026-10-21',
        'itens' => [['descricao' => 'Fachada ACM', 'largura_m' => 4, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 180]]];
    $orcamento = app(OrcamentoGravador::class)->gravar($businessId, app(OrcamentoCalculator::class)->calcular($payload), 'enviado');

    return (int) DB::table('comvis_orcamento_itens')->where('orcamento_id', $orcamento->id)->value('id');
}

/** Roda a chamada e devolve os erros de validação; falha se ela passar sem ser recusada. */
function aptCtrlErrosDe($test, callable $chamada): array
{
    try {
        $chamada();
    } catch (ValidationException $e) {
        return $e->errors();
    }
    $test->fail('A validação deveria ter recusado o id de outra empresa.');
}

// ------------------------------------------------------------------
// Fluxo do operador
// ------------------------------------------------------------------

it('UC-CV-06 · iniciar grava o apontamento do operador logado e devolve 201', function () {
    $resp = aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest([
        'os_id' => $this->os->id,
        'maquina' => 'plotter-roland-1',
    ])]);

    $json = $resp->getData(true);
    expect($resp->getStatusCode())->toBe(201)
        ->and($json['os_id'])->toBe($this->os->id)
        ->and($json['operador_id'])->toBe($this->operador->id)
        ->and($json['maquina'])->toBe('plotter-roland-1')
        ->and($json['finalizado_em'])->toBeNull();

    $gravado = Apontamento::query()->findOrFail($json['id']);
    expect($gravado->business_id)->toBe($this->biz->id)
        ->and($gravado->finalizado_em)->toBeNull();
});

it('UC-CV-06 · o operador não abre um segundo spool enquanto o primeiro está aberto', function () {
    aptCtrlAberto($this->biz->id, $this->os, $this->operador->id);

    $resp = aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest(['os_id' => $this->os->id])]);

    expect($resp->getStatusCode())->toBe(422)
        ->and(Apontamento::query()->where('operador_id', $this->operador->id)->count())->toBe(1);
});

it('UC-CV-06 · finalizar calcula o drift no servidor', function () {
    $aberto = aptCtrlAberto($this->biz->id, $this->os, $this->operador->id, ['m2_orcado' => 10.000]);

    $resp = aptCtrlChamar($this, $this->operador, 'finalizar', [
        'request' => aptCtrlRequest(['m2_produzido' => 8.0, 'observacoes' => 'Produção ok com pequena sobra']),
        'apontamento' => $aberto->id,
    ]);

    $json = $resp->getData(true);
    expect($resp->getStatusCode())->toBe(200)
        ->and($json['finalizado_em'])->not->toBeNull()
        ->and((float) $json['m2_produzido'])->toBe(8.0)
        ->and((float) $json['drift_percent'])->toBe(-20.0)
        ->and((float) $aberto->fresh()->drift_percent)->toBe(-20.0);
});

it('UC-CV-06 · em-andamento devolve o spool aberto do operador e nada quando não há', function () {
    expect(aptCtrlChamar($this, $this->operador, 'emAndamento')->getData(true))->toBeNull();

    $aberto = aptCtrlAberto($this->biz->id, $this->os, $this->operador->id);

    $json = aptCtrlChamar($this, $this->operador, 'emAndamento')->getData(true);
    expect($json['id'])->toBe($aberto->id)
        ->and($json['finalizado_em'])->toBeNull();
});

// ------------------------------------------------------------------
// Tier 0: isolamento entre empresas (ADR 0093)
// ------------------------------------------------------------------

it('UC-CV-05 · em-andamento não enxerga o spool da empresa 98 numa sessão da 99', function () {
    aptCtrlAberto($this->biz->id, $this->os, $this->operador->id);

    $resp = aptCtrlChamar($this, $this->operador, 'emAndamento', [], $this->outra->id);

    expect($resp->getStatusCode())->toBe(200)
        ->and($resp->getData(true))->toBeNull();
});

it('UC-CVAPT-03 · iniciar em OS de outra empresa é recusado igual a OS que não existe', function () {
    $alheia = aptCtrlOs($this->outra->id);

    $erroAlheia = aptCtrlErrosDe($this, fn () => aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest(['os_id' => $alheia->id])]));
    $erroInexistente = aptCtrlErrosDe($this, fn () => aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest(['os_id' => 2147483000])]));

    expect(array_keys($erroAlheia))->toBe(['os_id'])
        ->and($erroAlheia)->toBe($erroInexistente)
        ->and(Apontamento::query()->where('operador_id', $this->operador->id)->count())->toBe(0);
});

it('UC-CVAPT-04 · peça de orçamento de outra empresa é recusada, e a da própria empresa passa', function () {
    $alheio = aptCtrlItem($this->outra->id);
    $proprio = aptCtrlItem($this->biz->id);

    $erros = aptCtrlErrosDe($this, fn () => aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest([
        'os_id' => $this->os->id,
        'orcamento_item_id' => $alheio,
    ])]));
    expect(array_keys($erros))->toBe(['orcamento_item_id'])
        ->and(Apontamento::query()->where('operador_id', $this->operador->id)->count())->toBe(0);

    $resp = aptCtrlChamar($this, $this->operador, 'iniciar', ['request' => aptCtrlRequest([
        'os_id' => $this->os->id,
        'orcamento_item_id' => $proprio,
    ])]);
    expect($resp->getStatusCode())->toBe(201)
        ->and((float) $resp->getData(true)['m2_orcado'])->toBe(4.0);
});

it('UC-CVAPT-05 · o Tracker também recusa OS de outra empresa, sem depender da validação', function () {
    $alheia = aptCtrlOs($this->outra->id);
    aptCtrlLogar($this, $this->operador, $this->biz->id);

    expect(fn () => app(ApontamentoTracker::class)->iniciar(osId: $alheia->id, operadorId: $this->operador->id))
        ->toThrow(RuntimeException::class);
});

// ------------------------------------------------------------------
// PII e permissão
// ------------------------------------------------------------------

it('UC-CVAPT-01 · a lista devolve do operador só id e nome', function () {
    $this->operador->forceFill(['email' => 'operador.apontamento@teste.invalid', 'username' => 'operador_apt_98'])->save();
    aptCtrlAberto($this->biz->id, $this->os, $this->operador->id);

    $resp = aptCtrlChamar($this, $this->operador, 'index', ['request' => aptCtrlRequest([], 'GET')]);

    $linha = collect($resp->getData(true)['data'])->firstWhere('operador_id', $this->operador->id);
    expect($linha['operador'])->toBe(['id' => $this->operador->id, 'nome' => $this->operador->nome_exibicao])
        ->and($resp->getContent())->not->toContain('operador.apontamento')
        ->and($resp->getContent())->not->toContain('operador_apt_98');
});

it('UC-CVAPT-02 · sem comvis.apontamento.create não lista, não inicia, não finaliza nem cancela', function () {
    $semPermissao = $this->usuarioComPermissoes(['comvis.orcamento.view'], $this->biz);
    $aberto = aptCtrlAberto($this->biz->id, $this->os, $this->operador->id);

    $chamadas = [
        'index' => ['request' => aptCtrlRequest([], 'GET')],
        'iniciar' => ['request' => aptCtrlRequest(['os_id' => $this->os->id])],
        'finalizar' => ['request' => aptCtrlRequest(['m2_produzido' => 1]), 'apontamento' => $aberto->id],
        'cancelar' => ['request' => aptCtrlRequest(['motivo' => 'motivo qualquer']), 'apontamento' => $aberto->id],
    ];
    foreach ($chamadas as $acao => $parametros) {
        try {
            aptCtrlChamar($this, $semPermissao, $acao, $parametros);
            $this->fail("Sem a permissão, {$acao} deveria devolver 403.");
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    }

    $depois = $aberto->fresh();
    expect($depois->finalizado_em)->toBeNull()
        ->and($depois->m2_produzido)->toBeNull()
        ->and(Apontamento::query()->where('operador_id', $semPermissao->id)->count())->toBe(0);
});

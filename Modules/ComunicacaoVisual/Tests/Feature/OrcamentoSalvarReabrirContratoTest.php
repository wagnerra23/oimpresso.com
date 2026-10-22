<?php
// @covers-us US-COMVIS-021

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-021 — salvar e reabrir o orçamento na calculadora de m² (`/comunicacao-visual`).
 *
 * Âncora: SPEC US-COMVIS-021 (aceite) e o UC-FLX-01 do catálogo de fluxos, que precisa de um
 * orçamento salvo para existir o que aprovar. Contrato: Index.casos.md UC-CVSAL-01..06.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358), conta à mão:
 *   Banner  3,000 × 1,500 × 1 = 4,500 m² × 60,00 = 270,00
 *   Adesivo 2,000 × 1,000 × 3 = 6,000 m² × 40,00 = 240,00
 *   subtotal 510,00 + instalação 200,00 = total 710,00
 * Regravado: Placa 2,000 × 1,000 × 1 = 2,000 m² × 50,00 = 100,00 + instalação 200,00 = 300,00
 * ⚠️ SKIP em SQLite (lane sem as tabelas comvis_*): leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_os_itens')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }

    $this->payload = [
        'data_emissao' => '2026-10-21', 'custo_instalacao' => 200,
        'itens' => [
            ['descricao' => 'Banner lona 440g', 'largura_m' => 3, 'altura_m' => 1.5, 'quantidade' => 1, 'preco_unitario_m2' => 60],
            ['descricao' => 'Adesivo vitrine', 'largura_m' => 2, 'altura_m' => 1, 'quantidade' => 3, 'preco_unitario_m2' => 40],
        ],
    ];
});

/** Chama o controller como a tela chama: sessão da empresa + usuário com as permissões dadas. */
function cvsalChamar($test, string $acao, array $parametros, array $permissoes = ['comvis.orcamento.create'], $biz = null)
{
    $biz = $biz ?? $test->biz;
    $user = $test->usuarioComPermissoes($permissoes, $biz);
    $test->actingAs($user);
    session(['user.business_id' => $biz->id, 'business.id' => $biz->id, 'user.id' => $user->id]);

    return app()->call([app(OrcamentoController::class), $acao], $parametros);
}

function cvsalRequest(string $metodo, array $payload): Request
{
    return Request::create('/comunicacao-visual/api/orcamentos', $metodo, $payload);
}

it('UC-CVSAL-01 · salvar grava os valores do servidor, não os da tela', function () {
    // O cliente manda um total qualquer: o servidor descarta e recalcula.
    $resp = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload + ['total' => 1, 'subtotal' => 1])]);
    $json = $resp->getData(true);

    // 1º caminho: conta à mão. 2º caminho: o calculator refaz a mesma conta.
    $aMao = round(3 * 1.5 * 1 * 60 + 2 * 1 * 3 * 40 + 200, 2);
    expect($resp->getStatusCode())->toBe(201)
        ->and($aMao)->toBe(710.0)
        ->and(app(OrcamentoCalculator::class)->calcular($this->payload)['total'])->toBe($aMao)
        ->and($json['status'])->toBe('rascunho')
        ->and($json['numero'])->toMatch('/^ORC-\d{4}-\d{5}$/');

    $gravado = DB::table('comvis_orcamentos')->where('id', $json['id'])->first();
    expect((int) $gravado->business_id)->toBe($this->biz->id)
        ->and(number_format((float) $gravado->total, 2, '.', ''))->toBe('710.00')
        ->and(number_format((float) $gravado->subtotal, 2, '.', ''))->toBe('510.00')
        ->and(DB::table('comvis_orcamento_itens')->where('orcamento_id', $json['id'])->count())->toBe(2);
});

it('UC-CVSAL-02 · reabrir devolve as peças e os ajustes, e aparece na lista da empresa', function () {
    $id = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true)['id'];

    $tela = OrcamentoController::paraTela($this->biz->id, $id);
    expect($tela['itens'])->toHaveCount(2)
        ->and($tela['itens'][0]['descricao'])->toBe('Banner lona 440g')
        ->and($tela['itens'][0]['largura_m'])->toBe(3.0)
        ->and($tela['itens'][0]['altura_m'])->toBe(1.5)
        ->and($tela['itens'][1]['quantidade'])->toBe(3)
        ->and($tela['itens'][1]['preco_unitario_m2'])->toBe(40.0)
        // Instalação não tem campo na tela: reabrir tem de trazê-la para não zerar ao regravar.
        ->and($tela['custo_instalacao'])->toBe(200.0)
        ->and($tela['total'])->toBe(710.0)
        ->and(collect(OrcamentoController::recentes($this->biz->id))->pluck('id')->all())->toContain($id);
});

it('UC-CVSAL-03 · regravar o reaberto mantém o número e troca as peças pelos valores do servidor', function () {
    $criado = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true);
    $novo = ['data_emissao' => '2026-10-21', 'custo_instalacao' => 200,
        'itens' => [['descricao' => 'Placa PS', 'largura_m' => 2, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 50]]];

    $resp = cvsalChamar($this, 'update', ['request' => cvsalRequest('PUT', $novo), 'id' => $criado['id']]);

    $aMao = round(2 * 1 * 1 * 50 + 200, 2);
    expect($resp->getStatusCode())->toBe(200)
        ->and($aMao)->toBe(300.0)
        ->and(app(OrcamentoCalculator::class)->calcular($novo)['total'])->toBe($aMao)
        ->and($resp->getData(true)['numero'])->toBe($criado['numero'])
        ->and(DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->where('numero', $criado['numero'])->count())->toBe(1)
        ->and(number_format((float) DB::table('comvis_orcamentos')->where('id', $criado['id'])->value('total'), 2, '.', ''))->toBe('300.00')
        ->and(DB::table('comvis_orcamento_itens')->where('orcamento_id', $criado['id'])->pluck('descricao')->all())->toBe(['Placa PS']);
});

it('UC-CVSAL-04 · orçamento que já virou OS ou foi recusado não regrava', function () {
    $novo = ['data_emissao' => '2026-10-21',
        'itens' => [['descricao' => 'Placa PS', 'largura_m' => 2, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 50]]];

    $aprovado = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true)['id'];
    app(OrcamentoViraOs::class)->aprovar($this->biz->id, $aprovado);
    $recusado = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true)['id'];
    DB::table('comvis_orcamentos')->where('id', $recusado)->update(['status' => 'recusado']);

    foreach ([$aprovado, $recusado] as $id) {
        $resp = cvsalChamar($this, 'update', ['request' => cvsalRequest('PUT', $novo), 'id' => $id]);
        expect($resp->getStatusCode())->toBe(422)
            ->and(number_format((float) DB::table('comvis_orcamentos')->where('id', $id)->value('total'), 2, '.', ''))->toBe('710.00')
            ->and(DB::table('comvis_orcamento_itens')->where('orcamento_id', $id)->count())->toBe(2);
    }
});

it('UC-CVSAL-05 · a empresa 99 não reabre, não lista nem regrava o orçamento da 98', function () {
    $id = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true)['id'];
    $novo = ['data_emissao' => '2026-10-21',
        'itens' => [['descricao' => 'Alheio', 'largura_m' => 1, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 1]]];

    expect(OrcamentoController::paraTela($this->outra->id, $id))->toBeNull()
        ->and(collect(OrcamentoController::recentes($this->outra->id))->pluck('id')->all())->not->toContain($id);

    expect(fn () => cvsalChamar($this, 'update', ['request' => cvsalRequest('PUT', $novo), 'id' => $id], ['comvis.orcamento.create'], $this->outra))
        ->toThrow(ModelNotFoundException::class);
    expect(number_format((float) DB::table('comvis_orcamentos')->where('id', $id)->value('total'), 2, '.', ''))->toBe('710.00');
});

it('UC-CVSAL-06 · sem permissão de criar não salva nem regrava', function () {
    $id = cvsalChamar($this, 'store', ['request' => cvsalRequest('POST', $this->payload)])->getData(true)['id'];
    $antes = DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count();

    foreach ([['store', []], ['update', ['id' => $id]]] as [$acao, $extra]) {
        try {
            cvsalChamar($this, $acao, ['request' => cvsalRequest('POST', $this->payload)] + $extra, ['comvis.orcamento.view']);
            $this->fail("Sem comvis.orcamento.create, {$acao} deveria ser recusado.");
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    }

    expect(DB::table('comvis_orcamentos')->where('business_id', $this->biz->id)->count())->toBe($antes)
        ->and(cvsalChamar($this, 'index', [], ['comvis.orcamento.view'])->getStatusCode())->toBe(200);
});

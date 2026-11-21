<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Http\Controllers\TipoInstalacaoController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-007 · UC-PCV-25 — cadastro dos tipos de instalação e dos itens que o instalador marca no celular.
 *
 * Âncora: SPEC US-COMVIS-007 ("Checklist ferramentas + EPI (NR-35 trabalho em altura)") e o checklist por
 * instalação do protótipo da Produção (producao-cv-data.jsx). Contrato:
 * resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVTIPO-01..05.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): um tipo da 99 que a 98 não pode ver nem editar.
 * ⚠️ SKIP em SQLite ou sem a coluna itens_verificacao_json: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('cv_instalacoes_catalogo', 'itens_verificacao_json')) {
        $this->markTestSkipped('Requer MySQL com cv_instalacoes_catalogo.itens_verificacao_json.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    $this->sufixo = substr(uniqid(), -8);
    $this->gestor = $this->usuarioComPermissoes(['comvis.material.manage'], $this->biz);
    $this->tipoOutra = DB::table('cv_instalacoes_catalogo')->insertGetId([
        'business_id' => $this->outra->id, 'nome' => 'Totem '.$this->sufixo, 'exige_nr35' => false,
        'itens_verificacao_json' => json_encode(['Base nivelada']), 'ativo' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** Chama o controller como a rota chamaria, com a sessão da empresa do usuário. */
function cvTipo($t, \App\User $user, string $acao, array $dados = [], array $params = []): \Illuminate\Http\JsonResponse
{
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    $metodo = ['index' => 'GET', 'store' => 'POST', 'update' => 'PUT'][$acao];
    app()->instance('request', Request::create('/comunicacao-visual/api/tipos-instalacao', $metodo, $dados));

    try {
        return app()->call([app(TipoInstalacaoController::class), $acao], $params);
    } catch (HttpException $e) {
        return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
    } catch (ValidationException $e) {
        return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
    }
}

it('UC-CVTIPO-01 · cria o tipo com nome, NR-35 e itens, e a lista só traz os da empresa', function () {
    $r = cvTipo($this, $this->gestor, 'store', [
        'nome' => '  Fachada em altura '.$this->sufixo.'  ', 'exige_nr35' => true,
        'itens' => [' Andaime e EPI ', 'Nível e prumo', 'Teste de vedação'],
    ]);

    expect($r->getStatusCode())->toBe(201);
    $tipo = $r->getData(true)['tipo'];
    expect($tipo['nome'])->toBe('Fachada em altura '.$this->sufixo)
        ->and($tipo['exige_nr35'])->toBeTrue()
        ->and($tipo['itens'])->toBe(['Andaime e EPI', 'Nível e prumo', 'Teste de vedação'])
        ->and($tipo['ativo'])->toBeTrue();
    expect((int) DB::table('cv_instalacoes_catalogo')->where('id', $tipo['id'])->value('business_id'))->toBe($this->biz->id);

    $lista = collect(cvTipo($this, $this->gestor, 'index')->getData(true)['tipos']);
    expect($lista->pluck('id'))->toContain($tipo['id'])
        ->and($lista->pluck('id'))->not->toContain($this->tipoOutra);
});

it('UC-CVTIPO-02 · nome vazio ou repetido, item em branco ou repetido e mais de 20 itens são recusados sem gravar', function () {
    cvTipo($this, $this->gestor, 'store', ['nome' => 'Letreiro '.$this->sufixo]);
    $antes = DB::table('cv_instalacoes_catalogo')->where('business_id', $this->biz->id)->count();

    $casos = [
        'nome' => ['nome' => '   '],
        'repetido' => ['nome' => 'LETREIRO '.$this->sufixo],
        'branco' => ['nome' => 'Adesivo '.$this->sufixo, 'itens' => ['Limpar a superfície', '  ']],
        'duplicado' => ['nome' => 'Adesivo '.$this->sufixo, 'itens' => ['Limpar a superfície', 'limpar a superfície']],
        'demais' => ['nome' => 'Adesivo '.$this->sufixo, 'itens' => array_map(fn ($i) => "Item {$i}", range(1, 21))],
    ];
    foreach ($casos as $caso => $dados) {
        $r = cvTipo($this, $this->gestor, 'store', $dados);
        expect($r->getStatusCode())->toBe(422, "caso {$caso}");
    }
    expect(cvTipo($this, $this->gestor, 'store', $casos['repetido'])->getData(true)['errors']['nome'][0])
        ->toBe('Já existe um tipo de instalação com esse nome.');
    expect(DB::table('cv_instalacoes_catalogo')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

it('UC-CVTIPO-03 · edita itens e desativa; o desativado continua na lista com ativo falso', function () {
    $id = cvTipo($this, $this->gestor, 'store', ['nome' => 'Fachada '.$this->sufixo, 'itens' => ['Andaime e EPI']])
        ->getData(true)['tipo']['id'];

    $r = cvTipo($this, $this->gestor, 'update', ['itens' => ['Andaime e EPI', 'Teste de vedação'], 'ativo' => false], ['id' => $id]);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getData(true)['tipo'])->toMatchArray([
        'nome' => 'Fachada '.$this->sufixo, 'itens' => ['Andaime e EPI', 'Teste de vedação'], 'ativo' => false,
    ]);
    $naLista = collect(cvTipo($this, $this->gestor, 'index')->getData(true)['tipos'])->firstWhere('id', $id);
    expect($naLista['ativo'])->toBeFalse();
    // renomear para o próprio nome não conta como repetido
    expect(cvTipo($this, $this->gestor, 'update', ['nome' => 'Fachada '.$this->sufixo], ['id' => $id])->getStatusCode())->toBe(200);
});

it('UC-CVTIPO-04 · tipo de outra empresa é não encontrado e não muda', function () {
    $r = cvTipo($this, $this->gestor, 'update', ['nome' => 'Invadido '.$this->sufixo, 'ativo' => false], ['id' => $this->tipoOutra]);

    expect($r->getStatusCode())->toBe(404);
    $linha = DB::table('cv_instalacoes_catalogo')->where('id', $this->tipoOutra)->first();
    expect($linha->nome)->toBe('Totem '.$this->sufixo)
        ->and((bool) $linha->ativo)->toBeTrue();
});

it('UC-CVTIPO-05 · grava só quem cuida do catálogo; quem só vê OS lê a lista; sem permissão 403', function () {
    $soVe = $this->usuarioComPermissoes(['comvis.os.view'], $this->biz);
    $ninguem = $this->usuarioComPermissoes([], $this->biz);
    $antes = DB::table('cv_instalacoes_catalogo')->where('business_id', $this->biz->id)->count();

    expect(cvTipo($this, $soVe, 'index')->getStatusCode())->toBe(200)
        ->and(cvTipo($this, $soVe, 'store', ['nome' => 'Sem permissão '.$this->sufixo])->getStatusCode())->toBe(403)
        ->and(cvTipo($this, $ninguem, 'index')->getStatusCode())->toBe(403)
        ->and(cvTipo($this, $ninguem, 'store', ['nome' => 'Sem permissão '.$this->sufixo])->getStatusCode())->toBe(403);
    expect(DB::table('cv_instalacoes_catalogo')->where('business_id', $this->biz->id)->count())->toBe($antes);
});

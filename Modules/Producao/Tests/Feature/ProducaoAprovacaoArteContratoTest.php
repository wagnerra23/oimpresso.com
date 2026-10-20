<?php

declare(strict_types=1);
// Cobre UC-PCART-01..05 (Producao/Apontamento/Index.casos.md) — UC-FLX-02 do catálogo de fluxos.

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\AprovacaoArte;
use Modules\Producao\Entities\Ordem;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\ApontamentoOperadorService;
use Modules\Producao\Services\AprovacaoArteService;
use Modules\Producao\Services\ProcessoDaOrdem;
use Modules\Producao\Services\SituacaoDaOrdem;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * US-COMVIS-NEW-004 · UC-FLX-02 — "Envia link da prova; aprovado avança para Impressão, recusado volta
 * para Arte com o comentário" (fluxos-data.jsx, etapa "Aprovação do cliente"). Roteiro da OS:
 * bloco 1 Arte → bloco 2 Aprovação do cliente → bloco 3 Impressão. Tenant 98 × 99 (ADR 0358).
 * ⚠️ SKIP em SQLite: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
    }
    if (! Schema::hasTable('pcp_aprovacoes_arte')) {
        $this->markTestSkipped('pcp_aprovacoes_arte ausente — migrations do Modules/Producao não rodaram.');
    }
    $this->B98 = $this->seededTenant();
    $this->b98 = (int) $this->B98->id;
    $this->B99 = $this->seededSupportClientTenant();
    $this->b99 = (int) $this->B99->id;
    app(ProcessoDaOrdem::class)->garantir($this->b98);
    $this->pcp = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->pcp->assignRole('producao.liberar#'.$this->b98);
    $this->designer = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    $this->impressor = $this->usuarioComPermissoes(['producao.access'], $this->B98);
    // SUPERADMIN: fixture da empresa 98; o escopo é provado nos asserts.
    $this->posto = Posto::withoutGlobalScopes()->create(['business_id' => $this->b98, 'codigo' => 'ART-'.uniqid(), 'nome' => 'Arte',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00']);
    $this->ordem = Ordem::withoutGlobalScopes()->create(['business_id' => $this->b98, 'transaction_id' => 990002,
        'transaction_sell_line_id' => random_int(1000000, 9999999), 'product_id' => 1, 'quantidade' => '1.0000',
        'current_stage_id' => app(ProcessoDaOrdem::class)->etapaInicial($this->b98)->id]);
    $et = fn ($bloco, $nome, $user) => Programacao::withoutGlobalScopes()->create(['business_id' => $this->b98,
        'os_tipo' => Ordem::OS_TIPO, 'os_id' => $this->ordem->id, 'posto_id' => $this->posto->id, 'bloco' => $bloco, 'trilha' => 0,
        'nome' => $nome, 'user_id' => $user->id, 'inicio_previsto' => now()->addMinutes($bloco), 'fim_previsto' => now()->addHour(),
        'status' => 'pendente', 'created_by' => $this->pcp->id]);
    $this->arte = $et(1, 'Arte', $this->designer);
    $this->aprovacao = $et(2, 'Aprovação do cliente', $this->designer);
    $this->impressao = $et(3, 'Impressão', $this->impressor);

    app(SituacaoDaOrdem::class)->liberar($this->b98, $this->ordem->id, $this->pcp);
    $ap = app(ApontamentoOperadorService::class)->iniciar($this->b98, $this->designer->id, $this->arte->id, 'i-'.uniqid(), now());
    app(ApontamentoOperadorService::class)->concluir($this->b98, $this->designer->id, $ap->id, 'c-'.uniqid(), now());

    $this->link = app(AprovacaoArteService::class)->solicitar($this->b98, $this->ordem->id, $this->aprovacao->id,
        'https://arquivos.exemplo.test/prova-banner.png', $this->designer);
    $this->url = '/arte/'.$this->link['token'];
});

function artIniciaImpressao($t)
{
    return app(ApontamentoOperadorService::class)->iniciar($t->b98, $t->impressor->id, $t->impressao->id, 'i-'.uniqid(), now());
}

it('UC-PCART-01 · aprovar conclui a Aprovação do cliente e libera a Impressão; o link guarda só o hash', function () {
    $pedido = AprovacaoArte::withoutGlobalScopes()->findOrFail($this->link['id']);
    expect($pedido->token_hash)->toBe(hash('sha256', $this->link['token']))->and($pedido->token_hash)->not->toBe($this->link['token'])
        ->and((int) $pedido->arte_programacao_id)->toBe($this->arte->id)
        ->and($pedido->expira_em->toDateString())->toBe(now()->addDays(7)->toDateString());

    $this->get($this->url)->assertOk()->assertSee('prova-banner.png')->assertSee('Aprovação do cliente');
    // Antes de aprovar, a Impressão espera a aprovação (regra do bloco anterior).
    expect(fn () => artIniciaImpressao($this))->toThrow(ApontamentoRecusado::class, 'Falta terminar a etapa anterior: Aprovação do cliente.');

    $this->post($this->url.'/aprovar')->assertOk()->assertSee('Arte aprovada');

    expect($this->aprovacao->fresh()->status)->toBe('concluida')
        ->and($pedido->fresh()->decisao)->toBe(AprovacaoArte::APROVADA)
        ->and($this->ordem->fresh()->currentStage->key)->toBe('em_execucao');
    expect(DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', AprovacaoArteService::EV_APROVADA)
        ->where('os_id', $this->ordem->id)->count())->toBe(1);
    artIniciaImpressao($this);
    expect($this->impressao->fresh()->status)->toBe('iniciada');
});

it('UC-PCART-02 · recusar volta para a Arte com o comentário; a Impressão continua esperando', function () {
    $this->post($this->url.'/recusar', ['comentario' => 'Trocar o telefone do rodapé'])->assertOk()->assertSee('Pedido de alteração enviado');

    $pedido = AprovacaoArte::withoutGlobalScopes()->findOrFail($this->link['id']);
    expect($pedido->decisao)->toBe(AprovacaoArte::RECUSADA)->and($pedido->comentario)->toBe('Trocar o telefone do rodapé');
    $nova = Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)->where('nome', 'Arte (alteração)')->first();
    expect($nova)->not->toBeNull()->and($nova->status)->toBe('pendente')->and((int) $nova->bloco)->toBe(1)
        ->and((int) $nova->user_id)->toBe($this->designer->id)
        ->and($this->aprovacao->fresh()->status)->toBe('pendente');
    $ev = DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', AprovacaoArteService::EV_RECUSADA)->where('os_id', $this->ordem->id)->first();
    expect(json_decode($ev->dados, true)['comentario'])->toBe('Trocar o telefone do rodapé');
    expect(fn () => artIniciaImpressao($this))->toThrow(ApontamentoRecusado::class, 'Falta terminar a etapa anterior');

    // Recusa sem comentário não vale: nada muda.
    $outro = app(AprovacaoArteService::class)->solicitar($this->b98, $this->ordem->id, $this->aprovacao->id, 'https://arquivos.exemplo.test/v2.pdf', $this->designer);
    $this->post('/arte/'.$outro['token'].'/recusar', ['comentario' => ''])->assertSessionHasErrors('comentario');
    expect(AprovacaoArte::withoutGlobalScopes()->find($outro['id'])->decisao)->toBeNull();
});

it('UC-PCART-03 · o link é de uso único: respondido, nova decisão é recusada (410)', function () {
    $this->post($this->url.'/aprovar')->assertOk();

    $this->post($this->url.'/aprovar')->assertStatus(410)->assertSee('já foi respondida');
    $this->post($this->url.'/recusar', ['comentario' => 'mudei de ideia'])->assertStatus(410);
    $this->get($this->url)->assertStatus(410);
    expect(Programacao::withoutGlobalScopes()->where('os_id', $this->ordem->id)->where('nome', 'Arte (alteração)')->exists())->toBeFalse()
        ->and(AprovacaoArte::withoutGlobalScopes()->find($this->link['id'])->decisao)->toBe(AprovacaoArte::APROVADA);
});

it('UC-PCART-04 · link vencido é recusado (410) e a etapa fica como estava', function () {
    $this->travel(AprovacaoArteService::DIAS_VALIDADE + 1)->days();

    $this->get($this->url)->assertStatus(410)->assertSee('venceu');
    $this->post($this->url.'/aprovar')->assertStatus(410);
    expect($this->aprovacao->fresh()->status)->toBe('pendente')
        ->and(AprovacaoArte::withoutGlobalScopes()->find($this->link['id'])->decisao)->toBeNull();
});

it('UC-PCART-05 · OS de outra empresa não gera link (404) e token desconhecido é 404', function () {
    expect(fn () => app(AprovacaoArteService::class)->solicitar($this->b99, $this->ordem->id, $this->aprovacao->id, 'https://x.test/a.png', $this->designer))
        ->toThrow(fn (ApontamentoRecusado $e) => expect($e->codigo)->toBe(ApontamentoRecusado::NAO_ENCONTRADO));

    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $alheio = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    $this->actingAs($alheio)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte",
        ['programacao_id' => $this->aprovacao->id, 'arquivo' => 'https://x.test/a.png'])->assertNotFound();

    $this->get('/arte/'.str_repeat('a', 48))->assertNotFound();
    expect(AprovacaoArte::withoutGlobalScopes()->where('business_id', $this->b99)->count())->toBe(0);
});

// Caso separado de propósito: o SetSessionData só preenche a sessão quando ela está vazia, e no teste
// a sessão sobrevive entre requisições do mesmo caso. Trocar de usuário no mesmo it() herdaria a
// empresa 99 da requisição anterior (em produção o login regenera a sessão).
it('UC-PCART-05 · o designer da própria empresa gera o link (201)', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $r = $this->actingAs($this->designer)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte",
        ['programacao_id' => $this->aprovacao->id, 'arquivo' => 'https://x.test/b.png'])->assertCreated()->assertJsonStructure(['link', 'expira_em']);

    $pedido = AprovacaoArte::withoutGlobalScopes()->findOrFail($r->json('id'));
    expect((int) $pedido->business_id)->toBe($this->b98)->and($pedido->arquivo)->toBe('https://x.test/b.png');
    $this->get(parse_url($r->json('link'), PHP_URL_PATH))->assertOk()->assertSee('b.png');
});

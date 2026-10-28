<?php

declare(strict_types=1);
// Cobre UC-PCART-01..07 e UC-PCV-11 (Producao/Apontamento/Index.casos.md) — UC-FLX-02 do catálogo de fluxos
// e a trava da arte da thread 10 do playbook de Produção.

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

// ── UC-PCV-11 · arte aprovada travada (Producao.casos.md · thread 10 do playbook) ──
// "Dado a arte aprovada · Quando alguém troca o arquivo da arte · Então a OS volta para 'aguardando
// aprovação' e um novo link é enviado · E o operador abre sempre a versão aprovada."

it('UC-PCV-11 · trocar o arquivo da arte aprovada reabre a aprovação com link novo; a vigente segue a aprovada até o cliente aprovar', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $this->post($this->url.'/aprovar')->assertOk();
    $base = "/producao/ordens/{$this->ordem->id}";
    expect($this->actingAs($this->designer)->getJson($base.'/arte')->assertOk()->json('arte.arquivo'))
        ->toBe('https://arquivos.exemplo.test/prova-banner.png');

    $r = $this->postJson($base.'/arte/trocar', ['arquivo' => 'https://arquivos.exemplo.test/prova-banner-v2.png'])->assertCreated();

    // A OS volta a esperar o cliente: Aprovação reaberta como etapa nova no mesmo bloco, a Impressão espera.
    $nova = Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)
        ->where('nome', 'Aprovação do cliente (nova versão)')->first();
    expect($nova)->not->toBeNull()->and($nova->status)->toBe('pendente')->and((int) $nova->bloco)->toBe(2)
        ->and($this->aprovacao->fresh()->status)->toBe('concluida');
    expect(fn () => artIniciaImpressao($this))->toThrow(ApontamentoRecusado::class, 'Falta terminar a etapa anterior: Aprovação do cliente (nova versão).');
    // Link novo, e o operador continua vendo a versão APROVADA (discriminante: v2 ainda não vale).
    $link = parse_url($r->json('link'), PHP_URL_PATH);
    expect($link)->not->toBe($this->url)
        ->and($this->getJson($base.'/arte')->json('arte.arquivo'))->toBe('https://arquivos.exemplo.test/prova-banner.png');
    expect(DB::table('pcp_eventos')->where('business_id', $this->b98)->where('tipo', AprovacaoArteService::EV_ARQUIVO_TROCADO)
        ->where('os_id', $this->ordem->id)->count())->toBe(1);

    // O cliente aprova a nova versão: ela passa a valer e a Impressão anda.
    $this->post($link.'/aprovar')->assertOk();
    expect($this->getJson($base.'/arte')->json('arte.arquivo'))->toBe('https://arquivos.exemplo.test/prova-banner-v2.png');
    artIniciaImpressao($this);
    expect($this->impressao->fresh()->status)->toBe('iniciada');
});

it('UC-PCV-11 · sem arte aprovada ou com o mesmo arquivo, trocar é recusado e nada muda', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    $etapas = fn () => Programacao::withoutGlobalScopes()->where('os_tipo', Ordem::OS_TIPO)->where('os_id', $this->ordem->id)->count();
    $antes = $etapas();
    $base = "/producao/ordens/{$this->ordem->id}";

    // Prova ainda esperando o cliente: não há arte aprovada para trocar.
    $this->actingAs($this->designer)->postJson($base.'/arte/trocar', ['arquivo' => 'https://x.test/v2.png'])
        ->assertStatus(422)->assertJson(['codigo' => ApontamentoRecusado::APROVACAO_ENCERRADA]);
    expect($this->getJson($base.'/arte')->json('arte'))->toBeNull();

    // Aprovada: o mesmo arquivo não é troca.
    $this->post($this->url.'/aprovar')->assertOk();
    $this->postJson($base.'/arte/trocar', ['arquivo' => 'https://arquivos.exemplo.test/prova-banner.png'])->assertStatus(422);
    expect($etapas())->toBe($antes)
        ->and(AprovacaoArte::withoutGlobalScopes()->where('ordem_id', $this->ordem->id)->count())->toBe(1);
});

// Caso separado: a sessão do teste guarda a empresa da primeira requisição (ver o UC-PCART-05 acima).
it('UC-PCV-11 · OS de outra empresa não troca nem mostra a arte (404)', function () {
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));
    app(AprovacaoArteService::class)->aprovar($this->link['token']);
    $alheio = $this->usuarioComPermissoes(['producao.access'], $this->B99);

    $this->actingAs($alheio)->postJson("/producao/ordens/{$this->ordem->id}/arte/trocar", ['arquivo' => 'https://x.test/v9.png'])->assertNotFound();
    $this->getJson("/producao/ordens/{$this->ordem->id}/arte")->assertNotFound();
    expect(AprovacaoArte::withoutGlobalScopes()->where('ordem_id', $this->ordem->id)->count())->toBe(1);
});

// ── UC-PCART-06 · o link da prova vai ao cliente por WhatsApp (US-COMVIS-NEW-004) ──
// O envio é do Modules/Whatsapp pelo contrato AvisaClienteWhatsapp; aqui ele é um dublê que grava as
// chamadas. Flag `producao.aprovacao_arte.whatsapp` nasce desligada.

function artVendaComCliente($t, int $biz): array
{
    // `transactions.location_id` aceita null; o envio da prova não lê a loja. Criar loja exigiria
    // invoice_scheme + invoice_layout da empresa (FK NOT NULL), que a 99 do seed não tem.
    $local = DB::table('business_locations')->where('business_id', $biz)->value('id');
    $contato = (int) DB::table('contacts')->insertGetId(['business_id' => $biz, 'type' => 'customer', 'name' => 'Marta Prova',
        'mobile' => '48988887777', 'contact_status' => 'active', 'created_by' => $t->pcp->id, 'created_at' => now(), 'updated_at' => now()]);
    $venda = (int) DB::table('transactions')->insertGetId(['business_id' => $biz, 'location_id' => $local, 'type' => 'sell', 'status' => 'final',
        'contact_id' => $contato, 'invoice_no' => 'V-'.substr(uniqid(), -6), 'payment_status' => 'due', 'transaction_date' => now(),
        'final_total' => 500, 'essentials_duration' => 0, 'created_by' => $t->pcp->id, 'created_at' => now(), 'updated_at' => now()]);

    return [$contato, $venda];
}

function artDubleDoAviso(): object
{
    $duble = new class implements \App\Contracts\Atendimento\AvisaClienteWhatsapp {
        public array $chamadas = [];

        public function avisar(int $businessId, int $contactId, string $texto): array
        {
            $this->chamadas[] = compact('businessId', 'contactId', 'texto');

            return ['enviado' => true, 'motivo' => null];
        }
    };
    app()->instance(\App\Contracts\Atendimento\AvisaClienteWhatsapp::class, $duble);

    return $duble;
}

it('UC-PCART-06 · com a flag desligada nada é enviado e o link volta ao operador', function () {
    config(['producao.aprovacao_arte.whatsapp' => false]);
    $duble = artDubleDoAviso();
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $r = $this->actingAs($this->designer)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte",
        ['programacao_id' => $this->aprovacao->id, 'arquivo' => 'https://x.test/c.png'])->assertCreated();

    expect($r->json('whatsapp'))->toBeNull()->and($r->json('link'))->toContain('/arte/')->and($duble->chamadas)->toBe([]);
});

it('UC-PCART-06 · com a flag ligada o link vai ao cliente da venda da OS, na empresa da OS', function () {
    config(['producao.aprovacao_arte.whatsapp' => true]);
    $duble = artDubleDoAviso();
    [$contato, $venda] = artVendaComCliente($this, $this->b98);
    DB::table('pcp_ordens')->where('id', $this->ordem->id)->update(['transaction_id' => $venda]);
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $r = $this->actingAs($this->designer)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte",
        ['programacao_id' => $this->aprovacao->id, 'arquivo' => 'https://x.test/d.png'])->assertCreated();

    expect($r->json('whatsapp'))->toBe(['enviado' => true, 'motivo' => null])->and($duble->chamadas)->toHaveCount(1);
    $c = $duble->chamadas[0];
    expect($c['businessId'])->toBe($this->b98)->and($c['contactId'])->toBe($contato)
        ->and($c['texto'])->toContain($r->json('link'))->and($c['texto'])->toContain('Olá, Marta!');
});

it('UC-PCART-06 · venda de outra empresa ou sem cliente não manda nada e pede contato manual', function () {
    config(['producao.aprovacao_arte.whatsapp' => true]);
    $duble = artDubleDoAviso();
    // A OS aponta para uma venda da empresa 99: o cliente dela nunca recebe o link da 98 (Tier 0).
    [, $vendaAlheia] = artVendaComCliente($this, $this->b99);
    DB::table('pcp_ordens')->where('id', $this->ordem->id)->update(['transaction_id' => $vendaAlheia]);
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $r = $this->actingAs($this->designer)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte",
        ['programacao_id' => $this->aprovacao->id, 'arquivo' => 'https://x.test/e.png'])->assertCreated();

    expect($r->json('whatsapp'))->toBe(['enviado' => false, 'motivo' => 'sem_cliente'])->and($duble->chamadas)->toBe([])
        ->and(AprovacaoArte::withoutGlobalScopes()->find($r->json('id')))->not->toBeNull();
});

// ── UC-PCART-07 · fila do Painel: reenviar o link ou enviar a nova prova (Painel/Index.casos.md) ──

function artReenvia($t, array $corpo = [])
{
    // partialMock() é protegido no TestCase; fora do it() o dublê entra pelo container.
    $m = \Mockery::mock(ModuleUtil::class)->makePartial();
    $m->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module');
    app()->instance(ModuleUtil::class, $m);

    return $t->actingAs($t->designer)->postJson("/producao/ordens/{$t->ordem->id}/aprovacao-arte/reenviar", $corpo);
}

it('UC-PCART-07 · aguardando o cliente: reenviar gera link novo para a mesma prova e o anterior deixa de valer', function () {
    $r = artReenvia($this)->assertCreated();

    expect($r->json('link'))->not->toContain($this->link['token']);
    $this->get($this->url)->assertStatus(410);
    $this->get(parse_url($r->json('link'), PHP_URL_PATH))->assertOk()->assertSee('prova-banner.png');
    $s = app(\Modules\Producao\Services\ArteDasOrdens::class)->travadas($this->b98, [$this->ordem->id]);
    expect($s[$this->ordem->id]['estado'])->toBe('aguardando')->and(AprovacaoArte::withoutGlobalScopes()
        ->where('ordem_id', $this->ordem->id)->count())->toBe(2);
});

it('UC-PCART-07 · ajuste pedido: exige o arquivo novo; com ele, sai link novo e a OS volta a aguardar', function () {
    $this->post($this->url.'/recusar', ['comentario' => 'Trocar o telefone'])->assertOk();

    artReenvia($this)->assertStatus(422)->assertJsonPath('codigo', ApontamentoRecusado::APROVACAO_ENCERRADA);
    artReenvia($this, ['arquivo' => 'https://arquivos.exemplo.test/prova-banner.png'])->assertStatus(422);
    expect(AprovacaoArte::withoutGlobalScopes()->where('ordem_id', $this->ordem->id)->count())->toBe(1);

    $r = artReenvia($this, ['arquivo' => 'https://arquivos.exemplo.test/prova-banner-v2.png'])->assertCreated();
    $this->get(parse_url($r->json('link'), PHP_URL_PATH))->assertOk()->assertSee('prova-banner-v2.png');
    $s = app(\Modules\Producao\Services\ArteDasOrdens::class)->travadas($this->b98, [$this->ordem->id]);
    expect($s[$this->ordem->id]['estado'])->toBe('aguardando')->and($s[$this->ordem->id]['arquivo'])->toContain('v2');
});

it('UC-PCART-07 · arte aprovada não reenvia (422) e nada é gravado', function () {
    $this->post($this->url.'/aprovar')->assertOk();

    artReenvia($this)->assertStatus(422);
    expect(AprovacaoArte::withoutGlobalScopes()->where('ordem_id', $this->ordem->id)->count())->toBe(1);
});

it('UC-PCART-07 · OS de outra empresa não reenvia (404) e o link da 98 segue valendo', function () {
    $alheio = $this->usuarioComPermissoes(['producao.access'], $this->B99);
    $this->partialMock(ModuleUtil::class, fn ($m) => $m->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($b, $perm) => $perm === 'producao_module'));

    $this->actingAs($alheio)->postJson("/producao/ordens/{$this->ordem->id}/aprovacao-arte/reenviar", [])->assertNotFound();
    expect(AprovacaoArte::withoutGlobalScopes()->where('ordem_id', $this->ordem->id)->count())->toBe(1);
    $this->get($this->url)->assertOk();
});

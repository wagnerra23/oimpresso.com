<?php

declare(strict_types=1);

use Modules\KB\Entities\KbNode;

/**
 * Cross-tenant isolation specs (Tier 0 IRREVOGÁVEL — ADR 0093).
 *
 * @covers-us US-KB-001
 *   Cobre o critério multi-tenant da US-KB-001 (bridge canon dos ADRs): o caso
 *   "bridge job respects business scope (job(1) nao toca docs biz=99)" prova que
 *   KbBridgeFromMcpJob não cruza tenant. NÃO cobre a US inteira (idempotência e
 *   derivação de arestas vivem em Tests/Unit/KbBridgeFromMcpJobTest.php, fora de lane).
 *
 * Cobre especificamente o RISCO R5 da ADR 0149:
 *   "Multi-tenant leak via bridge cross-business"
 *
 * Também ataca riscos relacionados:
 *   R1 duplicação acidental kb_nodes ↔ mcp_memory_documents (Tier 0 via bridge)
 *
 * Cenário: 2 businesses (1 e 99) — user de biz=1 NÃO PODE ver/modificar
 * NADA de biz=99, e vice-versa. Mesmo se autenticado como admin.
 *
 * Wagner palavras textuais (ADR 0093): "vazar dados entre tenants é o pior
 * bug possível neste projeto". biz=4 (ROTA LIVRE prod) NUNCA em tests.
 *
 * REALIDADE V1 (gate coarse — SCHEMA-DB-V1 §12): o middleware REAL é
 * `can:jana.mcp.memory.manage`. O user de biz=1 recebe a coarse DE PROPÓSITO:
 * assim ele PASSA o middleware e o bloqueio cross-tenant tem que vir do GLOBAL SCOPE
 * `business_id` (firstOrFail → 404), provando isolamento de verdade. Sem a coarse, o
 * 403 viria do middleware e o teste NÃO exercitaria o isolamento (falso-verde Tier 0).
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

/**
 * CONTROLE POSITIVO — obrigatório em todo caso cross-tenant deste arquivo.
 *
 * POR QUE (medido, não suposto): TODOS os asserts deste arquivo eram negativos
 * (`toBeIn([403,404])`, `toBeIn([403,404,422])`). Um cenário em que a sessão
 * devolve 403 pra TUDO satisfaz cada um deles — inclusive o 403 que o docblock
 * do topo já classifica como "falso-verde Tier 0", porque vem do middleware e
 * não do global scope. Sem um caso que EXIJA 200, o gate não consegue ficar
 * vermelho: é verde-que-não-pode-reprovar.
 *
 * Não é hipotético. Nos artefatos JUnit da lane, o run 31502400773 fechou VERDE
 * tendo como PRIMEIRO request autenticado do processo um caso cego deste arquivo
 * — e é exatamente o primeiro request autenticado que o 403 intermitente atinge
 * (6/6 nas falhas 31425398494 · 31482695145 · 31491029075 · 31513833674 ·
 * 31514548359 · 31517070137). Aquele verde é indistinguível de "passou pelo
 * motivo errado".
 *
 * Desenho de risco mínimo: usa `GET /kb/nodes/{slug}` — o MESMO caminho que
 * L1/L3 do KbNodeBodyReaderTest já provam verde nesta lane — e só a permissão
 * coarse `jana.mcp.memory.manage`, que os 6 casos já concedem. Não cria comment,
 * favorite nem edge, então não perturba nenhuma contagem asseverada abaixo.
 *
 * Espelha a nota "CONTROLE POSITIVO obrigatório" do L3 (KbNodeBodyReaderTest).
 */
function kbControlePositivoBiz1(string $sufixo): void
{
    $slug = "controle-positivo-{$sufixo}";

    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'article', 'slug' => $slug,
        'title' => 'CONTROLE POSITIVO biz1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'visivel-ao-proprio-tenant']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Se ISTO falhar, o cross-tenant abaixo não prova isolamento nenhum — a sessão
    // não está funcional e o 403/404 dele viria de graça.
    test()->getJson("/kb/nodes/{$slug}")->assertOk();
}

it('blocks kb_node read across businesses (R5)', function () {
    // Cria node em biz=99
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'secret-biz99',
        'title' => 'Conteúdo confidencial biz 99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'SECRET']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view']);

    // Via Eloquent (global scope) — ANTES do controle positivo, que semeia um nó biz=1.
    expect(KbNode::all())->toHaveCount(0);

    kbControlePositivoBiz1('read');

    // Via HTTP — GET detalhe SLUG conhecido NÃO deve retornar conteúdo
    $response = $this->getJson('/kb/nodes/secret-biz99');
    expect($response->status())->toBeIn([403, 404]);
});

it('blocks kb_edge creation across businesses (R5)', function () {
    // Cria 2 nodes em biz=99
    $a99 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 99, 'type' => 'article', 'slug' => 'a99',
        'title' => 'A99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $b99 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 99, 'type' => 'article', 'slug' => 'b99',
        'title' => 'B99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Cria node em biz=1
    $a1 = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'a1',
        'title' => 'A1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // User de biz=1 admin tenta criar edge from=a1 (biz=1) → to=a99 (biz=99)
    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view', 'kb.write']);

    kbControlePositivoBiz1('edge');

    // Via HTTP — espera-se 403 ou 422 (validação de business_id)
    // TODO[CL]: confirmar endpoint com Agent A. Provavelmente POST /kb/edges.
    // Se endpoint não existe ainda, marcar como skip.
    if (!\Illuminate\Support\Facades\Route::has('kb.edges.store')) {
        test()->markTestSkipped('Endpoint POST /kb/edges não criado ainda — Agent A pendente.');
    }

    $response = $this->postJson('/kb/edges', [
        'from_node_id' => $a1,
        'to_node_id'   => $a99,  // cross-tenant
        'edge_type'    => 'cross-link',
    ]);

    expect($response->status())->toBeIn([403, 404, 422]);
    expect(\DB::table('kb_edges')->where('to_node_id', $a99)->count())->toBe(0);
});

it('blocks kb_comment cross-tenant (user biz=1 nao comenta node biz=99)', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'biz99-comm',
        'title' => 'biz 99 commentable', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view', 'kb.comment']);

    kbControlePositivoBiz1('comment');

    $response = $this->postJson('/kb/nodes/biz99-comm/comments', [
        'block_idx' => 0,
        'text'      => 'try cross-tenant',
    ]);

    expect($response->status())->toBeIn([403, 404]);
    expect(\DB::table('kb_comments')->count())->toBe(0);
});

it('blocks kb_favorite cross-tenant', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'biz99-fav',
        'title' => 'biz 99 to favorite', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view', 'kb.favorite']);

    kbControlePositivoBiz1('favorite');

    $response = $this->postJson('/kb/nodes/biz99-fav/favorite');

    expect($response->status())->toBeIn([403, 404]);
    expect(\DB::table('kb_favorites')->count())->toBe(0);
});

it('bridge job respects business scope (job(1) nao toca docs biz=99)', function () {
    // Cria mcp_docs em ambos businesses — guardamos os IDs pra escopar o assert
    // ao PRÓPRIO doc (ver nota de ordem-dependente abaixo).
    $biz1DocId  = kbCreateMcpDoc(1, 'adr', ['slug' => 'biz1-adr', 'title' => 'biz 1 adr']);
    $biz99DocId = kbCreateMcpDoc(99, 'adr', ['slug' => 'biz99-adr', 'title' => 'biz 99 adr']);

    // Roda job APENAS pra biz=1
    foreach (['Modules\\KB\\Jobs\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\Bridge\\KbBridgeFromMcpJob'] as $candidate) {
        if (class_exists($candidate)) {
            $jobClass = $candidate;
            break;
        }
    }
    if (!isset($jobClass)) {
        test()->markTestSkipped('KbBridgeFromMcpJob ainda não criado pelo Agent A.');
    }

    // handle() recebe serviços via DI (KbBridgeStateService + KbEdgeAutoDeriver) — resolver
    // pelo container em vez de chamar handle() sem args (senão ArgumentCountError).
    app()->call([new $jobClass(1), 'handle']);

    // Assert ESCOPADO ao source_doc_id que ESTE teste criou (não contagem global).
    //
    // Por quê escopado: `mcp_memory_documents` é tabela CORE COMPARTILHADA e NÃO é
    // resetada por kbTeardownSchema (só as kb_* são — ver Helpers.php). Sob
    // executionOrder="random" (phpunit.xml), docs biz=1 de OUTROS testes (+ docs
    // globais business_id=NULL, que o job também bridgeia) acumulam no run → a
    // contagem global `kb_nodes where business_id=1` fica N>1 (3/6 no CI efêmero,
    // ~1268 no CT 100 staging com dados reais) e o assert antigo `->toBe(1)`
    // quebrava order-dependent. Escopar ao doc próprio é robusto em QUALQUER
    // ambiente E prova o contrato real: job(1) bridgeia SEU doc biz=1 e NUNCA
    // toca o doc biz=99. (ADR 0093 multi-tenant Tier 0 · ADR 0101 biz=1/biz=99.)
    expect(\DB::table('kb_nodes')->where('source_doc_id', $biz1DocId)->where('business_id', 1)->count())->toBe(1)
        ->and(\DB::table('kb_nodes')->where('source_doc_id', $biz99DocId)->count())->toBe(0)
        ->and(\DB::table('kb_nodes')->where('business_id', 99)->count())->toBe(0);
});

it('PUT cross-tenant: user biz=1 NAO pode editar node biz=99 mesmo conhecendo slug', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'shared-slug',
        'title' => 'original biz 99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'biz99 content']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view', 'kb.write']);

    kbControlePositivoBiz1('put');

    $response = $this->putJson('/kb/nodes/shared-slug', [
        'title'       => 'HACKED biz 1',
        'body_blocks' => [['kind' => 'para', 'text' => 'I overwrote biz 99 content']],
    ]);

    expect($response->status())->toBeIn([403, 404]);
    $row = \DB::table('kb_nodes')->where('business_id', 99)->where('slug', 'shared-slug')->first();
    expect($row->title)->toBe('original biz 99');  // intocado
});

it('DELETE cross-tenant: user biz=1 NAO pode soft-deletar node biz=99', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'cant-delete',
        'title' => 'biz99 alive', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 1, permissions: ['jana.mcp.memory.manage', 'kb.view', 'kb.softdelete']);

    kbControlePositivoBiz1('delete');

    // destroy() exige confirm=CONFIRMO (safety guard); mandamos pra o 422 de validação NÃO
    // mascarar o teste de isolamento — assim o bloqueio vem do global scope (firstOrFail →
    // 404), provando a isolação de verdade.
    $response = $this->deleteJson('/kb/nodes/cant-delete', ['confirm' => 'CONFIRMO']);

    expect($response->status())->toBeIn([403, 404]);
    $row = \DB::table('kb_nodes')->where('business_id', 99)->where('slug', 'cant-delete')->first();
    expect($row->deleted_at)->toBeNull();
});

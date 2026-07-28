<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da tela VIVA `/kb` (KB V3 — browser do acervo canon `mcp_memory_documents`).
 *
 * Casos: resources/js/Pages/kb/Index.casos.md (UC-KB-01..06)
 * SDD:   memory/requisitos/KB/SDD-tela-kb-unificado-v1.0.md §6.1 (CU-KB-01..08) + §5.3 F1..F5
 *
 * POR QUE ESTE ARQUIVO EXISTE: antes dele a tela-âncora do módulo não tinha contrato
 * executável NENHUM. O único teste que a mencionava — o caso "GET /kb returns Inertia
 * kb/Index component" em Tests/Feature/Http/KbNodeControllerTest.php — está `->skip(...)`
 * E asserta um payload que o Controller nunca serviu (`has('nodes')`, quando o real é
 * docs/filters/kpis/github_repo). Ver SDD §9 D-2.
 *
 * ⚠️ ESTE ARQUIVO AINDA NÃO ESTÁ NA ALLOWLIST de .github/workflows/kb-pest.yml. A lane é
 * catraca-por-prova-verde: só entra o que já passou contra MySQL real. A entrada é
 * PROPOSTA (decisão [W]) — enquanto não entrar, o veredito destes UC é estruturalmente
 * pendente, e o casos.md diz isso.
 *
 * ⚠️ NÃO é multi-tenant por business_id — e isso é DE PROPÓSITO. `mcp_memory_documents`
 * é REPO-WIDE por decisão (ADR 0053; docblock do model: "docs canon do git são da
 * plataforma, não per-business"). O isolamento desta tela é `scope_required` + `admin_only`
 * via McpMemoryDocument::scopeAcessiveisPara. Por isso aqui não há biz=99: seria testar
 * um eixo que a tabela não tem. O eixo business_id do KB é testado na tela irmã
 * (KbIndexV2ContractTest, sobre kb_nodes). ADR 0101 continua valendo: NUNCA biz=4.
 *
 * @see resources/js/Pages/kb/Index.charter.md
 * @see Modules/KB/Http/routes.php — Route::get('/') + '/{slug}/show' + '/{slug}' DELETE
 * @see Modules/KB/Tests/Feature/KbIndexV2ContractTest.php — mesmo padrão, tela irmã
 */

// Guard SQLite: kbBootstrapSchema/kbActAsUser montam schema + auth. A suíte roda contra
// MySQL real (lane kb-pest / CT 100 oimpresso-staging) — ADR 0101 / ADR 0062.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite: rodar no CT 100 (oimpresso-staging MySQL, biz=1). ADR 0101 / ADR 0062.'
        );
    }

    // A lane não builda o front (só stub de manifest), e config/inertia.php tem
    // testing.ensure_pages_exist => true → assertInertia tentaria localizar o .tsx em
    // disco. O contrato aqui é o NOME do componente servido pela rota, não o bundle.
    // Mesmo tratamento do KbIndexV2ContractTest (bloqueador de lane, não de prod).
    config(['inertia.testing.ensure_pages_exist' => false]);

    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// O KbController inteiro está atrás de `can:jana.mcp.memory.manage` (construtor) — a
// permissão coarse canônica do KB até o "PR de rename Spatie" (SCHEMA-DB-V1 §12).
$permKb = ['jana.mcp.memory.manage'];

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-01 — Listar o acervo com filtro, busca e paginação
// ─────────────────────────────────────────────────────────────────────────────

it('I1: GET /kb autenticado renderiza kb/Index e o documento do acervo aparece (UC-KB-01)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb01-doc-visivel', 'title' => 'DOC UCKB01 VISIVEL']);

    $response = $this->get('/kb');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $p) => $p->component('kb/Index'));
    // ANTI-VÁCUO: prova que a listagem REALMENTE trouxe o documento — sem isto, os asserts
    // de filtro abaixo passariam por lista vazia (verde por não-execução, proibicoes §5 2026-07-24).
    expect($response->getContent())->toContain('uckb01-doc-visivel');
});

it('I1b: filtro por type esconde o que não bate e mantém o que bate (UC-KB-01)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    kbCreateMcpDoc(1, 'adr',     ['slug' => 'uckb01b-tipo-adr',     'title' => 'TIPO ADR']);
    kbCreateMcpDoc(1, 'session', ['slug' => 'uckb01b-tipo-session', 'title' => 'TIPO SESSION']);

    $response = $this->get('/kb?type=adr');

    $response->assertOk();
    expect($response->getContent())->toContain('uckb01b-tipo-adr');
    expect($response->getContent())->not->toContain('uckb01b-tipo-session');
});

it('I1c: documento soft-deletado continua alcançável na listagem de governança (UC-KB-01)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $id = kbCreateMcpDoc(1, 'reference', ['slug' => 'uckb01c-deletado', 'title' => 'DOC DELETADO']);
    DB::table('mcp_memory_documents')->where('id', $id)->update(['deleted_at' => now()]);

    $response = $this->get('/kb');

    $response->assertOk();
    // `withTrashed()` é intencional: /kb é tela de governança/LGPD — sumir com o que foi
    // marcado como deletado esconderia exatamente o que precisa ficar auditável.
    expect($response->getContent())->toContain('uckb01c-deletado');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-02 — O conteúdo respeita o MESMO escopo que a lista  [must][T0]
//
// 🔴 PREDIÇÃO DE VERMELHO (predição, não veredito — quem decide é a lane):
// KbController@show não chama acessiveisPara. Varredura contada (git grep -n
// "acessiveisPara" -- '*.php', sem head_limit): 13 linhas em 8 arquivos; dentro do
// KbController há 1 único site, em buildDocsPayload (a lista). SDD §9 D-1(a).
//
// O assert é de COMPORTAMENTO ("o corpo não sai"), não de status: 403, 404 ou payload
// filtrado satisfazem igualmente. Travar o status reprovaria arbitrariamente 2 dos 3
// remédios legítimos, e a escolha do remédio é de [W].
// ─────────────────────────────────────────────────────────────────────────────

it('I2: conteúdo de doc admin_only não é servido a quem a lista já esconde (UC-KB-02)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $segredo = 'CONTEUDO ADMIN ONLY QUE NAO PODE VAZAR UCKB02';
    $id = kbCreateMcpDoc(1, 'reference', [
        'slug'       => 'uckb02-admin-only',
        'title'      => 'DOC ADMIN ONLY',
        'content_md' => "# Restrito\n\n{$segredo}\n",
    ]);
    DB::table('mcp_memory_documents')->where('id', $id)->update(['admin_only' => 1]);

    // PRÉ-CONDIÇÃO (anti-vácuo): a LISTA já esconde — se isto falhar, a premissa do UC
    // caiu e o assert seguinte não significaria nada.
    $lista = $this->get('/kb');
    $lista->assertOk();
    expect($lista->getContent())->not->toContain('uckb02-admin-only');

    // CONTRATO: o conteúdo obedece à mesma regra da lista.
    $detalhe = $this->get('/kb/uckb02-admin-only/show');
    expect($detalhe->getContent())->not->toContain($segredo);
});

it('I2b: conteúdo de doc com scope_required que o user não tem não é servido (UC-KB-02)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $segredo = 'CONTEUDO ESCOPADO QUE NAO PODE VAZAR UCKB02B';
    $id = kbCreateMcpDoc(1, 'session', [
        'slug'       => 'uckb02b-escopado',
        'title'      => 'DOC ESCOPADO',
        'content_md' => "# Escopado\n\n{$segredo}\n",
    ]);
    // Permissão que o user de teste NÃO recebeu (ele só tem a coarse do módulo).
    DB::table('mcp_memory_documents')->where('id', $id)
        ->update(['scope_required' => 'copiloto.mcp.admin']);

    $lista = $this->get('/kb');
    $lista->assertOk();
    expect($lista->getContent())->not->toContain('uckb02b-escopado'); // pré-condição

    $detalhe = $this->get('/kb/uckb02b-escopado/show');
    expect($detalhe->getContent())->not->toContain($segredo);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-03 — Ler o documento no preview
// ─────────────────────────────────────────────────────────────────────────────

it('I3: preview entrega o corpo markdown do documento acessível (UC-KB-03)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $marcador = 'CORPO MARKDOWN UCKB03 CHEGOU NO LEITOR';
    kbCreateMcpDoc(1, 'adr', [
        'slug'       => 'uckb03-legivel',
        'title'      => 'DOC LEGIVEL',
        'content_md' => "# Titulo\n\n{$marcador}\n",
    ]);

    $response = $this->get('/kb/uckb03-legivel/show');

    $response->assertOk();
    // Contrato = o corpo chega ao leitor. NÃO travo o nome da chave do payload: se o
    // backend renomear o campo mantendo o corpo, o comportamento continua correto.
    expect($response->getContent())->toContain($marcador);
});

it('I3b: documento sem git_path não carrega link do GitHub (UC-KB-03)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    kbCreateMcpDoc(1, 'reference', [
        'slug'  => 'uckb03b-sem-gitpath',
        'title' => 'DOC SEM GIT PATH',
    ]); // helper não seta git_path → fica NULL

    $response = $this->get('/kb/uckb03b-sem-gitpath/show');

    $response->assertOk();
    // Link quebrado é pior que link ausente: sem git_path, nenhuma URL do GitHub sai.
    expect($response->getContent())->not->toContain('github.com');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-04 — Soft-delete LGPD exige confirmação no SERVIDOR
// ─────────────────────────────────────────────────────────────────────────────

it('I4: DELETE sem confirmação é rejeitado E o documento continua não-deletado (UC-KB-04)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $id = kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb04-sem-confirmacao', 'title' => 'DOC SEM CONFIRM']);

    $response = $this->deleteJson('/kb/uckb04-sem-confirmacao', []);

    expect($response->status())->toBe(422);
    // ANTI-VÁCUO: o que importa não é o status, é que NADA foi deletado.
    expect(DB::table('mcp_memory_documents')->where('id', $id)->value('deleted_at'))->toBeNull();
});

it('I4b: DELETE com palavra errada também não deleta (UC-KB-04)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $id = kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb04b-confirm-errado', 'title' => 'DOC CONFIRM ERRADO']);

    $response = $this->deleteJson('/kb/uckb04b-confirm-errado', ['confirm' => 'confirmo']);

    expect($response->status())->toBe(422);
    expect(DB::table('mcp_memory_documents')->where('id', $id)->value('deleted_at'))->toBeNull();
});

it('I4c: DELETE confirmado marca como deletado SEM apagar a linha (soft, auditável) (UC-KB-04)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $id = kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb04c-soft-delete', 'title' => 'DOC SOFT DELETE']);

    $response = $this->deleteJson('/kb/uckb04c-soft-delete', ['confirm' => 'CONFIRMO']);

    $response->assertOk();
    $row = DB::table('mcp_memory_documents')->where('id', $id)->first();
    expect($row)->not->toBeNull();            // a linha continua existindo (soft, não hard)
    expect($row->deleted_at)->not->toBeNull(); // e está marcada como deletada
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-05 — Restaurar documento deletado
// ─────────────────────────────────────────────────────────────────────────────

it('I5: restore devolve o documento ao acervo (UC-KB-05)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    $id = kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb05-restaurar', 'title' => 'DOC RESTAURAR']);
    DB::table('mcp_memory_documents')->where('id', $id)->update(['deleted_at' => now()]);

    $response = $this->postJson('/kb/uckb05-restaurar/restore');

    $response->assertOk();
    expect(DB::table('mcp_memory_documents')->where('id', $id)->value('deleted_at'))->toBeNull();
});

it('I5b: restaurar documento que NÃO está deletado não devolve sucesso (UC-KB-05)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb05b-nao-deletado', 'title' => 'DOC NAO DELETADO']);

    $response = $this->postJson('/kb/uckb05b-nao-deletado/restore');

    // Sucesso falso ("ok" pra operação que não ocorreu) é a classe de bug que o projeto
    // já catalogou nos toasts mock da tela irmã. 404 aqui é o comportamento correto.
    expect($response->status())->not->toBe(200);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-KB-06 — Abrir a tela é leitura pura (auth · sem escrita · sem Job)
// ─────────────────────────────────────────────────────────────────────────────

it('I6: GET /kb anônimo nunca devolve 200 nem 500 (UC-KB-06)', function () {
    $response = $this->get('/kb');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    expect(in_array($response->status(), [302, 401, 403], true))->toBeTrue();
});

it('I6b: GET /kb não escreve no acervo (UC-KB-06)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    kbCreateMcpDoc(1, 'adr', ['slug' => 'uckb06b-readonly', 'title' => 'DOC READONLY']);

    $antes = DB::table('mcp_memory_documents')->count();

    $response = $this->get('/kb');
    $response->assertOk();
    // ANTI-VÁCUO: prova que o render REALMENTE percorreu o acervo — "nada mudou" sobre
    // uma tela que não carregou nada não prova read-only, prova tela vazia.
    expect($response->getContent())->toContain('uckb06b-readonly');

    expect(DB::table('mcp_memory_documents')->count())->toBe($antes);
});

it('I6c: GET /kb não enfileira Job (sem IA/e-mail/whatsapp no render) (UC-KB-06)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);
    Queue::fake();

    $this->get('/kb')->assertOk();

    Queue::assertNothingPushed();
});

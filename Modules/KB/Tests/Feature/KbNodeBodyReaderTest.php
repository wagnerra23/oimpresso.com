<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Contrato do LEITOR do corpo da V2 — UC-KBV2-14 (`resources/js/Pages/kb/Index.v2.casos.md`).
 *
 * A tela /kb/v2 lista `kb_nodes`, mas o CORPO do documento canônico não vive lá:
 * `KbBridgeFromMcpJob` mantém `body_blocks = NULL` + `is_editable = false` — invariante
 * Tier 0 (ADR 0061: doc do git não vira editável no app, senão vira 2ª fonte da verdade).
 * O corpo vem por **JOIN** com `mcp_memory_documents.content_md`, servido pelo endpoint
 * que já existe: `GET /kb/nodes/{slug}` → `KbNodeController@show`.
 *
 * Este teste prova as DUAS metades que a Onda 1 precisa:
 *   (a) o corpo APARECE (o JOIN devolve o markdown real, não excerpt truncado);
 *   (b) o nó bridgeado CONTINUA não-editável (invariante intacta; PUT devolve 422).
 * Mais o piso de sempre: auth e Tier 0 (biz=1 não lê corpo de biz=99).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093): biz=1 canônico (ADR 0101), biz=99 fictício.
 * NUNCA biz=4 (ROTA LIVRE prod) — `kbActAsUser`/`kbCreateBusinessRow` lançam se tentar.
 *
 * `uses(Tests\TestCase::class)` já aplicado globalmente em tests/Pest.php.
 *
 * @see resources/js/Pages/kb/_components/NodeReader.charter.md — Goal 2 (o contrato do endpoint)
 * @see resources/js/Pages/kb/_lib/useKbNodeBody.ts — o consumidor client-side
 * @see Modules/KB/Jobs/KbBridgeFromMcpJob.php — onde a invariante nasce
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'SQLite: rodar no CT 100 (oimpresso-staging MySQL, biz=1). ADR 0101 / ADR 0061.'
        );
    }

    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// Mesma permissão coarse do KbController/KbNodeController (dívida de rename registrada
// no docblock dos dois — `jana.mcp.memory.manage` até o PR Spatie).
$permKb = ['jana.mcp.memory.manage'];

/**
 * Semeia um nó bridge REAL: doc canônico em mcp_memory_documents + kb_node apontando
 * pra ele com body_blocks NULL (exatamente o que o KbBridgeFromMcpJob produz).
 *
 * @return array{slug:string, doc_id:int, markdown:string}
 */
function kbSeedBridgedNode(int $bizId, string $slug, string $markdown): array
{
    // `git_path` é NOT NULL na tabela real (migration 2026_04_29_100008) e é o que
    // o Controller usa pra montar `github_url` — sem ele o permalink sai null.
    // `git_sha` NÃO é sobrescrito de propósito: o sha sintético de `kbCreateMcpDoc`
    // é o que o teardown usa pra limpar SÓ o que o teste criou.
    $docId = kbCreateMcpDoc($bizId, 'adr', [
        'slug'       => $slug,
        'title'      => "Doc canonico {$slug}",
        'content_md' => $markdown,
        'git_path'   => "memory/decisions/{$slug}.md",
    ]);

    DB::table('kb_nodes')->insert([
        'business_id'   => $bizId,
        'type'          => 'adr',
        'slug'          => $slug,
        'title'         => "Doc canonico {$slug}",
        'excerpt'       => 'excerpt truncado — NAO e o corpo',
        'body_blocks'   => null,   // ← INVARIANTE Tier 0 (ADR 0061)
        'is_editable'   => false,  // ← INVARIANTE Tier 0
        'source_doc_id' => $docId,
        'status'        => 'ok',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    return ['slug' => $slug, 'doc_id' => $docId, 'markdown' => $markdown];
}

// ── UC-KBV2-14 (a) — o corpo do documento canônico CHEGA, via JOIN ──────────
it('L1: GET /kb/nodes/{slug} devolve o corpo do doc canonico via JOIN (UC-KBV2-14)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    // Markdown com marcador único + tabela GFM (o corpus real é cheio de tabela).
    $markdown = "# Decisao\n\nMARCADOR-CORPO-CANONICO-L1\n\n| campo | valor |\n|---|---|\n| status | aceito |\n";
    $seed = kbSeedBridgedNode(1, 'adr-9001-corpo-do-leitor', $markdown);

    $response = $this->getJson("/kb/nodes/{$seed['slug']}");

    $response->assertOk();

    // O corpo INTEIRO — não o excerpt. Igualdade estrita: prova o JOIN, não "tem a chave".
    expect($response->json('content_md'))->toBe($markdown);
    expect($response->json('content_md'))->toContain('MARCADOR-CORPO-CANONICO-L1');
    // E o excerpt do nó NÃO é o que responde a pergunta "qual é o corpo?".
    expect($response->json('content_md'))->not->toBe('excerpt truncado — NAO e o corpo');

    // Permalink da fonte (o git é a verdade; a tela é leitura).
    expect($response->json('github_url'))->toContain("memory/decisions/{$seed['slug']}.md");
    expect($response->json('node.slug'))->toBe($seed['slug']);
});

// ── UC-KBV2-14 (b) — ler o corpo NÃO torna o nó editável (invariante intacta) ─
it('L2: nó bridgeado continua não-editável depois de servir o corpo (UC-KBV2-14)', function () use ($permKb) {
    kbActAsUser(bizId: 1, permissions: $permKb);

    $seed = kbSeedBridgedNode(1, 'adr-9002-continua-readonly', "# X\n\nCORPO-L2\n");

    // Serve o corpo (é o que o leitor faz ao abrir o nó).
    $this->getJson("/kb/nodes/{$seed['slug']}")->assertOk();

    // A invariante Tier 0 permanece gravada no banco: NADA foi copiado pra kb_nodes.
    $row = DB::table('kb_nodes')->where('slug', $seed['slug'])->first();
    expect($row->body_blocks)->toBeNull();
    expect((int) $row->is_editable)->toBe(0);

    // E a porta de escrita continua fechada pra bridge (ADR 0061).
    $put = $this->putJson("/kb/nodes/{$seed['slug']}", [
        'title'       => 'TENTATIVA DE EDICAO PROIBIDA',
        'body_blocks' => [['kind' => 'para', 't' => 'nao pode']],
    ]);
    $put->assertStatus(422);
    expect($put->json('error'))->toBe('NODE_NOT_EDITABLE');

    // O título original sobreviveu — a rejeição não é cosmética.
    $depois = DB::table('kb_nodes')->where('slug', $seed['slug'])->first();
    expect($depois->title)->toBe("Doc canonico {$seed['slug']}");
    expect($depois->body_blocks)->toBeNull();
});

// ── Tier 0 — o corpo de outro business NUNCA é servido (ADR 0093) ───────────
//
// O que este caso mede é o CONTRATO ("cross-tenant não devolve o corpo"), não o
// código exato da negativa. Aceita 403 OU 404 de propósito — as duas são fail-secure
// e a escolha entre elas depende de QUAL camada barra primeiro (o `can:` do
// constructor ou o `firstOrFail()` sob global scope). Essa ordem é sensível ao cache
// do Spatie no MySQL persistente-no-run com `executionOrder="random"` — a flakiness
// GAP 2 já catalogada no header do kb-pest.yml. Cravar 404 acoplaria o teste ao
// timing do PermissionRegistrar, não ao isolamento (medido: em 2 runs seguidos a
// vítima do 403 mudou de teste). O que NÃO se afrouxa: 200 reprova, e o segredo
// não pode aparecer na resposta.
//
// CONTROLE POSITIVO obrigatório: sem ele, um cenário onde TUDO devolve 403 passaria
// verde sem provar isolamento nenhum — seria verde por não-execução (LC-13). O caso
// só passa se a sessão do próprio business consegue LER de fato.
it('L3: biz=1 nao le o corpo de um no de biz=99 — nunca conteudo, e le o seu (ADR 0093)', function () use ($permKb) {
    // Nó + doc no tenant fictício biz=99 (NUNCA biz=4 — ROTA LIVRE prod).
    kbCreateBusinessRow(99);
    kbSeedBridgedNode(99, 'adr-9003-segredo-biz99', "# Segredo\n\nCONTEUDO-SECRETO-BIZ99\n");

    kbActAsUser(bizId: 1, permissions: $permKb);
    $meu = kbSeedBridgedNode(1, 'adr-9003b-meu-doc-visivel', "# Meu\n\nCORPO-DO-MEU-BUSINESS\n");

    // (a) CONTROLE POSITIVO — a sessão está funcional e serve o corpo do PRÓPRIO business.
    //     Se isto falhar, o (b) abaixo não prova nada (403-em-tudo não é isolamento).
    $meuResp = $this->getJson("/kb/nodes/{$meu['slug']}");
    $meuResp->assertOk();
    expect($meuResp->json('content_md'))->toContain('CORPO-DO-MEU-BUSINESS');

    // (b) Tier 0 — o nó do outro tenant não devolve corpo.
    $response = $this->getJson('/kb/nodes/adr-9003-segredo-biz99');

    expect($response->status())->not->toBe(200);
    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
    expect($response->getContent())->not->toContain('CONTEUDO-SECRETO-BIZ99');
    expect($response->getContent())->not->toContain('adr-9003-segredo-biz99');
});

// ── Piso da rota — anônimo não lê corpo nenhum ─────────────────────────────
it('L4: GET /kb/nodes/{slug} anonimo nao devolve corpo (auth exigida)', function () {
    $response = $this->get('/kb/nodes/adr-9004-qualquer');

    expect($response->status())->not->toBe(200);
    expect($response->status())->not->toBe(500);
    expect(in_array($response->status(), [302, 401, 403, 404], true))->toBeTrue();
});

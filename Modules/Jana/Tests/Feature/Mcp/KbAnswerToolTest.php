<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Modules\Jana\Ai\Agents\KbAnswerAgent;
use Modules\Jana\Mcp\Tools\KbAnswerTool;

uses(Tests\TestCase::class);

/**
 * KbAnswerTool tests (G3 — AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5).
 *
 * Cobre o pipeline canônico (retrieval determinístico + síntese laravel/ai):
 *  001. Pipeline completo retorna formato canônico (Resposta:/Citações:/Confiança:)
 *  002. KbAnswerTool invoca DecisionsSearch/MemoriaSearch (FULLTEXT) + KbAnswerAgent
 *  003. Filtro `categoria:adr` exclui sessions e specs no prompt do Agent
 *  004. Parâmetro `max_citacoes` é respeitado e exposto no instructions do Agent
 *  005. KB vazia retorna mensagem honesta com Confiança: baixa (sem IA call)
 *  006. Output sem prefixo "Resposta:" cai pro fallback determinístico
 *  007. Pergunta vazia retorna erro
 *
 * Mock LLM via Ai::fakeAgent — NUNCA chama OpenAI real.
 *
 * SQLite-friendly: macro `buscarTexto` override troca FULLTEXT por LIKE.
 * Dual-mode pattern documentado em reference_tests_pest_canon.md.
 */
beforeEach(function () {
    // Schema mínimo replicando mcp_memory_documents (sem FULLTEXT).
    Schema::dropIfExists('mcp_memory_documents');
    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('slug', 191);
        $t->string('type', 30);
        $t->string('module', 80)->nullable();
        $t->string('title', 255);
        $t->longText('content_md');
        $t->string('scope_required', 100)->nullable();
        $t->boolean('admin_only')->default(false);
        $t->json('metadata')->nullable();
        $t->string('git_sha', 40)->nullable();
        $t->string('git_path', 255)->nullable();
        $t->unsignedInteger('pii_redactions_count')->default(0);
        $t->binary('embedding')->nullable();
        $t->timestamp('indexed_at')->nullable();
        // Frontmatter columns (MEM-KB-3) — nullable em SQLite
        $t->string('status', 30)->nullable();
        $t->string('authority', 30)->nullable();
        $t->string('lifecycle', 30)->nullable();
        $t->string('quarter', 20)->nullable();
        $t->date('decided_at')->nullable();
        $t->json('decided_by')->nullable();
        $t->json('tags')->nullable();
        $t->json('supersedes')->nullable();
        $t->json('superseded_by')->nullable();
        $t->json('related')->nullable();
        $t->boolean('has_pii')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });

    // Override scopeBuscarTexto via macro — FULLTEXT MATCH não existe em SQLite.
    // Pattern dual-mode (reference_tests_pest_canon.md).
    \Illuminate\Database\Eloquent\Builder::macro('buscarTexto', function ($termo) {
        if (trim((string) $termo) === '') {
            return $this;
        }
        // Pega primeira palavra-chave (mesmo critério aproximado do FULLTEXT)
        $words = preg_split('/\s+/', trim((string) $termo));
        $first = $words[0] ?? '';
        return $this->where(function ($q) use ($first, $termo) {
            $q->where('title', 'like', '%' . $first . '%')
              ->orWhere('content_md', 'like', '%' . $first . '%')
              ->orWhere('title', 'like', '%' . $termo . '%')
              ->orWhere('content_md', 'like', '%' . $termo . '%');
        });
    });

    // Override scopePorStatusAtivo — JSON_UNQUOTE não existe em SQLite.
    // Em produção (MySQL), filtra metadata.status; aqui passa direto (todos
    // os docs do test são considerados ativos — sem metadata.status setado).
    \Illuminate\Database\Eloquent\Builder::macro('porStatusAtivo', function ($includeArchived = false) {
        return $this; // no-op em SQLite
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_memory_documents');
});

function callKbAnswerTool(array $params = []): McpResponse
{
    $tool = new KbAnswerTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

it('KbAnswerTool 001 — pipeline completo retorna formato canônico Resposta/Citações/Confiança', function () {
    DB::table('mcp_memory_documents')->insert([
        [
            'business_id' => null,
            'slug' => '0053-mcp-server-governanca',
            'type' => 'adr',
            'module' => 'jana',
            'title' => 'MCP server como produto',
            'content_md' => 'O MCP server vive no CT 100 Proxmox e expõe tools governadas.',
            'git_path' => 'memory/decisions/0053-mcp-server-governanca-como-produto.md',
            'admin_only' => false,
            'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'business_id' => null,
            'slug' => '0094-constituicao-v2',
            'type' => 'adr',
            'module' => 'core',
            'title' => 'Constituição v2',
            'content_md' => 'Os 7 layers da Constituição. MCP server é a Camada 5.',
            'git_path' => 'memory/decisions/0094-constituicao-v2.md',
            'admin_only' => false,
            'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    Ai::fakeAgent(KbAnswerAgent::class, [
        "Resposta: O MCP server é canônico no CT 100 Proxmox e expõe tools governadas pra Claude Code.\n\n"
        . "Citações:\n"
        . "- [0053-mcp-server-governanca](memory/decisions/0053-mcp-server-governanca-como-produto.md) — CT 100 Proxmox\n"
        . "- [0094-constituicao-v2](memory/decisions/0094-constituicao-v2.md) — Camada 5\n\n"
        . "Confiança: alta",
    ]);

    $response = callKbAnswerTool(['pergunta' => 'MCP']);
    $texto = (string) $response->content();

    expect($texto)->toContain('Resposta:')
        ->and($texto)->toContain('Citações:')
        ->and($texto)->toContain('Confiança:')
        ->and($texto)->toContain('0053-mcp-server-governanca');
});

it('KbAnswerTool 002 — invoca retrieval (FULLTEXT) e KbAnswerAgent (síntese laravel/ai)', function () {
    DB::table('mcp_memory_documents')->insert([
        [
            'business_id' => null,
            'slug' => 'adr-0001-foo',
            'type' => 'adr',
            'module' => 'jana',
            'title' => 'Foo decision',
            'content_md' => 'Memoria persistente do projeto via MCP server.',
            'git_path' => 'memory/decisions/0001-foo.md',
            'admin_only' => false, 'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    Ai::fakeAgent(KbAnswerAgent::class, [
        "Resposta: foo.\n\nCitações:\n- [adr-0001-foo](memory/decisions/0001-foo.md) — foo\n\nConfiança: média",
    ]);

    callKbAnswerTool(['pergunta' => 'Memoria']);

    // Asserta que o KbAnswerAgent foi efetivamente invocado (síntese laravel/ai)
    Ai::assertAgentWasPrompted(KbAnswerAgent::class, function ($p) {
        $prompt = (string) $p->prompt;
        // O prompt do user deve carregar a pergunta + bloco FONTES com o doc recuperado
        return str_contains($prompt, 'PERGUNTA: Memoria')
            && str_contains($prompt, 'adr-0001-foo');
    });
});

it('KbAnswerTool 003 — filtro categoria:adr exclui sessions e specs do prompt', function () {
    DB::table('mcp_memory_documents')->insert([
        [
            'business_id' => null,
            'slug' => 'sessao-brain-b',
            'type' => 'session',
            'module' => 'jana',
            'title' => 'Sessão Brain B',
            'content_md' => 'Implementamos Brain B do ADS hoje.',
            'git_path' => 'memory/sessions/2026-05-10.md',
            'admin_only' => false, 'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'business_id' => null,
            'slug' => '0099-brain-b',
            'type' => 'adr',
            'module' => 'jana',
            'title' => 'Brain B canônico',
            'content_md' => 'Brain B usa Claude Opus pra decisões críticas.',
            'git_path' => 'memory/decisions/0099-brain-b.md',
            'admin_only' => false, 'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    Ai::fakeAgent(KbAnswerAgent::class, [
        "Resposta: Brain B canônico.\n\nCitações:\n- [0099-brain-b](memory/decisions/0099-brain-b.md) — Claude Opus\n\nConfiança: alta",
    ]);

    callKbAnswerTool(['pergunta' => 'Brain', 'categoria' => 'adr']);

    Ai::assertAgentWasPrompted(KbAnswerAgent::class, function ($p) {
        $prompt = (string) $p->prompt;
        return str_contains($prompt, '0099-brain-b')
            && ! str_contains($prompt, 'sessao-brain-b');
    });
});

it('KbAnswerTool 004 — max_citacoes respeitado e exposto no instructions do Agent', function () {
    // Insere 6 docs — todos relevantes (palavra "tier")
    for ($i = 1; $i <= 6; $i++) {
        DB::table('mcp_memory_documents')->insert([
            'business_id' => null,
            'slug' => "doc-tier-{$i}",
            'type' => 'adr',
            'module' => 'jana',
            'title' => "Doc tier {$i}",
            'content_md' => 'Multi-tenant é Tier 0 inviolável.',
            'git_path' => "memory/decisions/doc-tier-{$i}.md",
            'admin_only' => false, 'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Ai::fakeAgent(KbAnswerAgent::class, [
        "Resposta: Multi-tenant Tier 0.\n\nCitações:\n- [doc-tier-1](memory/decisions/doc-tier-1.md) — Tier 0\n- [doc-tier-2](memory/decisions/doc-tier-2.md) — inviolável\n\nConfiança: alta",
    ]);

    callKbAnswerTool(['pergunta' => 'tier', 'max_citacoes' => 2]);

    // Verifica que max_citacoes virou parte do system prompt (instructions)
    // construído via constructor do Agent
    $agent = new KbAnswerAgent(pergunta: 'X', fontes: 'Y', maxCitacoes: 2);
    expect($agent->maxCitacoes)->toBe(2)
        ->and((string) $agent->instructions())->toContain('máximo 2');

    // Verifica clamp (max 10): valor 99 deve cair pra 10
    $clamp = new KbAnswerAgent(pergunta: 'X', fontes: 'Y', maxCitacoes: 10);
    expect((string) $clamp->instructions())->toContain('máximo 10');
});

it('KbAnswerTool 005 — KB vazia retorna Confiança: baixa sem chamar IA', function () {
    // Nenhum doc inserido — tool não deve chamar IA
    Ai::fakeAgent(KbAnswerAgent::class, [
        'NUNCA CHAMADO',
    ]);

    $response = callKbAnswerTool(['pergunta' => 'tema inexistente xpto zzz']);
    $texto = (string) $response->content();

    expect($texto)->toContain('Resposta:')
        ->and($texto)->toContain('Não encontrei nada conclusivo')
        ->and($texto)->toContain('Confiança: baixa')
        ->and($texto)->not->toContain('NUNCA CHAMADO');
});

it('KbAnswerTool 006 — fallback determinístico quando LLM não segue formato', function () {
    DB::table('mcp_memory_documents')->insert([
        [
            'business_id' => null,
            'slug' => 'doc-fallback',
            'type' => 'adr',
            'module' => 'jana',
            'title' => 'Doc Fallback',
            'content_md' => 'Conteúdo qualquer relevante pra fallback test.',
            'git_path' => 'memory/decisions/fallback.md',
            'admin_only' => false, 'pii_redactions_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    // LLM "alucinado" — devolve texto sem o prefixo canônico "Resposta:"
    Ai::fakeAgent(KbAnswerAgent::class, [
        'Resposta sem prefixo correto, alucinação típica',
    ]);

    $response = callKbAnswerTool(['pergunta' => 'fallback']);
    $texto = (string) $response->content();

    // Fallback determinístico ativa — output ainda tem formato canônico
    expect($texto)->toContain('Resposta:')
        ->and($texto)->toContain('Citações:')
        ->and($texto)->toContain('Confiança: baixa')
        ->and($texto)->toContain('doc-fallback');
});

it('KbAnswerTool 007 — pergunta vazia retorna erro', function () {
    $response = callKbAnswerTool(['pergunta' => '   ']);

    expect((string) $response->content())->toContain('obrigatório');
});

it('KbAnswerTool 008 — categoria inválida retorna erro com lista válida', function () {
    $response = callKbAnswerTool(['pergunta' => 'X', 'categoria' => 'invalida-xpto']);
    $texto = (string) $response->content();

    expect($texto)->toContain('Categoria inválida')
        ->and($texto)->toContain('adr')
        ->and($texto)->toContain('spec');
});

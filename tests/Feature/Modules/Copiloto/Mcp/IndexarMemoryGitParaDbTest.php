<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * MEM-MCP-1.a (ADR 0053) — Service que sincroniza memory/ git → DB.
 *
 * Testa: parse frontmatter, PII redaction, UPSERT idempotente, history,
 * soft-delete de docs sumidos, isolamento multi-tenant. Setup cria pasta tmp
 * com .md files.
 *
 * ── HISTÓRICO (leia antes de confiar neste arquivo) ─────────────────────────
 * Até 2026-08-03 este teste não rodava em lugar NENHUM: fora do CI de PR (nenhuma
 * lane o citava — medido por scripts/governance/test-lane-coverage.mjs) E pulado
 * na nightly do CT100, que roda MySQL e cai no markTestSkipped abaixo. Não era
 * feedback tardio; era ausência total com cara de verde (LICOES_CODE.md LC-13).
 * A fixture tinha drifado 3 migrations atrás do schema real e os 7 casos davam
 * QueryException assim que a lane ligou (PR #5213, run 30783000852).
 *
 * Consequência prática: até aqui NENHUM caso exercia multi-tenant — a coluna
 * `business_id` sequer existia na fixture. O caso Tier 0 no fim do arquivo fecha
 * isso. Se você adicionar caso novo, ancore no contrato (ADR/model docblock),
 * nunca no que o service faz hoje (proibicoes.md §5 2026-06-05).
 */

$repoTmp = null;

beforeEach(function () use (&$repoTmp) {
    // Quarentena Onda 2 SDD floor: mcp_memory_documents(_history) são REAIS-migradas;
    // schema sintético manual + roda em MySQL persistente → corruptor. Pula no MySQL.
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // ⚠️ Fixture DERIVADA das migrations reais, não escrita de cabeça. Ela havia
    // drifado 3 migrations atrás do schema (faltavam business_id + os 11 tipados +
    // os 3 contextual), e como este arquivo não rodava em lane NENHUMA — fora do CI
    // de PR E pulado na nightly, que é MySQL e cai no markTestSkipped acima — o drift
    // ficou invisível até a lane ligar (PR #5213). Ao mexer aqui, confira contra:
    //   Modules/Jana/Database/Migrations/2026_04_29_100008_create_mcp_memory_documents_table.php
    //   ...2026_04_30_200001_add_business_id_to_mcp_memory_documents.php
    //   ...2026_05_01_100001_add_typed_cols_to_mcp_memory_documents.php
    //   ...2026_05_15_120000_add_contextual_context_to_mcp_memory_documents.php
    // `type` é enum no MySQL e string aqui de propósito (sqlite não tem ENUM).
    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('business_id')->nullable();
        $t->string('slug', 200)->unique();
        $t->string('type', 20);
        $t->string('module', 50)->nullable();
        $t->string('status', 20)->nullable();
        $t->string('authority', 20)->nullable();
        $t->string('lifecycle', 20)->nullable();
        $t->string('quarter', 10)->nullable();
        $t->date('decided_at')->nullable();
        $t->json('decided_by')->nullable();
        $t->json('tags')->nullable();
        $t->json('supersedes')->nullable();
        $t->json('superseded_by')->nullable();
        $t->json('related')->nullable();
        $t->boolean('has_pii')->default(false);
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->text('contextual_context')->nullable();
        $t->boolean('contextual_indexed')->default(false);
        $t->timestamp('contextualized_at')->nullable();
        $t->string('scope_required', 100)->nullable();
        $t->boolean('admin_only')->default(false);
        $t->json('metadata')->nullable();
        $t->string('git_sha', 40)->nullable();
        $t->string('git_path', 300);
        $t->unsignedSmallInteger('pii_redactions_count')->default(0);
        $t->binary('embedding')->nullable();
        $t->timestamp('indexed_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('mcp_memory_documents_history', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('document_id');
        $t->string('slug', 200);
        $t->string('git_sha', 40)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->json('metadata')->nullable();
        $t->timestamp('changed_at')->useCurrent();
        $t->unsignedInteger('changed_by_user_id')->nullable();
        $t->string('change_reason', 100)->nullable();
        $t->timestamp('created_at')->useCurrent();
    });

    // Cria repo temporário com estrutura mínima de memory/
    $repoTmp = sys_get_temp_dir() . '/mcp_test_' . uniqid();
    mkdir("$repoTmp/memory/decisions", 0777, true);
    mkdir("$repoTmp/memory/sessions", 0777, true);
});

afterEach(function () use (&$repoTmp) {
    // afterEach roda mesmo em teste pulado (PHPUnit 12.5); mcp_memory_documents(_history) reais → só dropar em sqlite.
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('mcp_memory_documents_history');
        Schema::dropIfExists('mcp_memory_documents');
    }

    if ($repoTmp && is_dir($repoTmp)) {
        // Cleanup recursive
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($repoTmp, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($repoTmp);
    }
});

it('indexa ADR com frontmatter + parseia campos corretos', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0046-test-adr.md", <<<MD
---
name: ADR Test 0046
status: aceito
admin_only: false
---

# Test ADR

Conteúdo da ADR.
MD);

    $service = new IndexarMemoryGitParaDb($repoTmp);
    $stats = $service->run();

    expect($stats['indexados'])->toBe(1);
    expect($stats['novos'])->toBe(1);
    expect($stats['atualizados'])->toBe(0);

    $doc = McpMemoryDocument::where('slug', '0046-test-adr')->first();
    expect($doc)->not->toBeNull();
    expect($doc->type)->toBe('adr');
    expect($doc->title)->toBe('ADR Test 0046');
    expect($doc->metadata)->toMatchArray(['name' => 'ADR Test 0046', 'status' => 'aceito', 'admin_only' => false]);
    expect($doc->content_md)->toContain('Conteúdo da ADR');
});

/**
 * ATUALIZADO 2026-08-03 — este caso codificava o contrato PRÉ-#5193 e ninguém viu,
 * porque o arquivo não rodava em lane nenhuma. Ao ligar a lane ele deu
 * `Failed asserting that 3 is identical to 4` (run 30806132071).
 *
 * NÃO é vazamento — é a mudança deliberada do #5193, documentada no docblock de
 * `IndexarMemoryGitParaDb::deveRedigir()`: número CRU só é redigido se o DV de CPF
 * fechar, porque run id do GitHub Actions (`run 30366164436`) estava sendo apagado
 * como se fosse CPF (32 casos medidos em produção 2026-08-02). O `12345678900` do
 * fixture antigo tem DV INVÁLIDO (o correto seria `...09`), então virou o caso que
 * o #5193 passou a preservar de propósito — 4 redações caíram pra 3.
 *
 * O conserto NÃO é trocar 4 por 3: contador mágico rota de novo. Cada perna do
 * contrato novo vira asserção nomeada, incluindo a que prova que o #5193 não abriu
 * buraco (CPF cru com DV VÁLIDO continua redigido).
 */
it('PII redactor: CPF pontuado e CPF cru com DV válido são redigidos; CPF cru com DV inválido NÃO (guard de run id, #5193)', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0001-pii.md", <<<MD
# Doc com PII

CPF pontuado, DV invalido: 123.456.789-00. # pii-allowlist
CPF cru, DV VALIDO: 11144477735. # pii-allowlist
CPF cru, DV invalido (formato de run id): 12345678900. # pii-allowlist
CNPJ empresa: 12.345.678/0001-90. # pii-allowlist
Cartão: 1234 5678 9012 3456.
MD);

    $service = new IndexarMemoryGitParaDb($repoTmp);
    $stats = $service->run();

    $doc = McpMemoryDocument::where('slug', '0001-pii')->first();

    // (a) CPF PONTUADO: sempre redigido, com DV válido ou não — formato explícito
    //     é declaração de intenção, não pede prova.
    expect($doc->content_md)->not->toContain('123.456.789-00'); // pii-allowlist
    expect($doc->content_md)->toContain('XXX.XXX.XXX-NN');

    // (b) ANTI-VAZAMENTO — CPF CRU com DV VÁLIDO segue redigido. Se esta asserção
    //     cair, o guard de run id virou buraco de LGPD.
    expect($doc->content_md)->not->toContain('11144477735'); // pii-allowlist

    // (c) O guard do #5193 — CRU com DV inválido é PRESERVADO (é run id, não PII).
    expect($doc->content_md)->toContain('12345678900'); // pii-allowlist

    // (d) CNPJ e cartão caem em `default => true` (paridade com PiiRedactor).
    expect($doc->content_md)->toContain('XX.XXX.XXX/XXXX-NN');
    expect($doc->content_md)->toContain('****-****-****-****');

    // Contador = as 4 pernas redigidas de (a)(b)(d); (c) não conta por desenho.
    expect($stats['redactions'])->toBe(4);
    expect($doc->pii_redactions_count)->toBe(4);
});

it('idempotente: re-rodar com mesmo conteúdo não cria history', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0010-idem.md", "# T1\n\ncontent");

    $service = new IndexarMemoryGitParaDb($repoTmp);
    $service->run();
    $service->run(); // segunda vez, mesmo conteúdo

    $doc = McpMemoryDocument::where('slug', '0010-idem')->first();
    expect($doc->history()->count())->toBe(0);
});

it('atualização gera snapshot history', function () use (&$repoTmp) {
    $path = "$repoTmp/memory/decisions/0011-versao.md";
    file_put_contents($path, "# Original\n\nv1");
    (new IndexarMemoryGitParaDb($repoTmp))->run();

    file_put_contents($path, "# Atualizado\n\nv2");
    $stats = (new IndexarMemoryGitParaDb($repoTmp, 'webhook'))->run();

    expect($stats['atualizados'])->toBe(1);

    $doc = McpMemoryDocument::where('slug', '0011-versao')->first();
    expect($doc->title)->toBe('Atualizado');
    expect($doc->history()->count())->toBe(1);
    expect($doc->history->first()->title)->toBe('Original');
    expect($doc->history->first()->change_reason)->toBe('webhook');
});

it('soft-delete docs sumidos do filesystem', function () use (&$repoTmp) {
    $path = "$repoTmp/memory/decisions/0012-some.md";
    file_put_contents($path, '# T');
    (new IndexarMemoryGitParaDb($repoTmp))->run();

    expect(McpMemoryDocument::where('slug', '0012-some')->exists())->toBeTrue();

    unlink($path);
    $stats = (new IndexarMemoryGitParaDb($repoTmp))->run();

    expect($stats['removidos'])->toBe(1);
    expect(McpMemoryDocument::where('slug', '0012-some')->exists())->toBeFalse();
    expect(McpMemoryDocument::withTrashed()->where('slug', '0012-some')->exists())->toBeTrue();
});

it('detecta módulo via heurística no slug', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0050-copiloto-metricas.md", '# CP');
    file_put_contents("$repoTmp/memory/decisions/0040-financeiro-take-rate.md", '# FN');

    (new IndexarMemoryGitParaDb($repoTmp))->run();

    expect(McpMemoryDocument::where('slug', '0050-copiloto-metricas')->first()->module)->toBe('copiloto');
    expect(McpMemoryDocument::where('slug', '0040-financeiro-take-rate')->first()->module)->toBe('financeiro');
});

it('infere scope_required pra credenciais como admin-only', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0030-credenciais-secretas.md", '# Creds');

    (new IndexarMemoryGitParaDb($repoTmp))->run();

    $doc = McpMemoryDocument::where('slug', '0030-credenciais-secretas')->first();
    expect($doc->scope_required)->toBe('copiloto.mcp.admin');
});

/**
 * Tier 0 multi-tenant (ADR 0093).
 *
 * ÂNCORA DE CONTRATO — a asserção vem do que `McpMemoryDocument::scopeDoBusiness()`
 * DECLARA, não do que o service faz hoje: "doBusiness = business_id = X OR
 * business_id IS NULL, então docs de plataforma (NULL = ADR 0053 cross-tenant by
 * design) aparecem pra todos, mas docs de um business específico NÃO vazam pra
 * outro tenant."
 *
 * Por que este caso não existia: a fixture não tinha a coluna `business_id`, então
 * era IMPOSSÍVEL escrevê-lo — e como o arquivo não rodava em lane nenhuma, ninguém
 * esbarrou nisso. Os 7 casos acima nunca tocaram multi-tenant.
 */
it('Tier 0 — carimba o business_id do construtor e doBusiness NÃO vaza entre tenants', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0060-doc-do-tenant-1.md", "# T1\n\nconteudo");

    (new IndexarMemoryGitParaDb($repoTmp, 'test', null, 1))->run();

    // (a) o service carimba o businessId que recebeu no construtor
    $doc = McpMemoryDocument::where('slug', '0060-doc-do-tenant-1')->first();
    expect($doc)->not->toBeNull('pré-condição: o doc do tenant 1 foi indexado');
    expect($doc->business_id)->toBe(1);

    // Vizinhos: um doc de OUTRO tenant e um doc de plataforma (NULL = global).
    McpMemoryDocument::create([
        'slug' => 'doc-do-tenant-99', 'business_id' => 99, 'type' => 'session',
        'title' => 'Do outro tenant', 'content_md' => '# 99',
        'git_path' => 'memory/sessions/doc-do-tenant-99.md',
        'admin_only' => false, 'metadata' => [], 'pii_redactions_count' => 0,
    ]);
    McpMemoryDocument::create([
        'slug' => 'doc-de-plataforma', 'business_id' => null, 'type' => 'reference',
        'title' => 'Global', 'content_md' => '# Global',
        'git_path' => 'memory/reference/doc-de-plataforma.md',
        'admin_only' => false, 'metadata' => [], 'pii_redactions_count' => 0,
    ]);

    // (b) tenant 1 vê o seu + o global, e NÃO vê o do 99
    $slugsBiz1 = McpMemoryDocument::doBusiness(1)->pluck('slug')->all();
    expect($slugsBiz1)->toContain('0060-doc-do-tenant-1');
    expect($slugsBiz1)->toContain('doc-de-plataforma');
    expect($slugsBiz1)->not->toContain('doc-do-tenant-99');

    // (c) simétrico — tenant 99 não enxerga o doc do 1
    $slugsBiz99 = McpMemoryDocument::doBusiness(99)->pluck('slug')->all();
    expect($slugsBiz99)->toContain('doc-do-tenant-99');
    expect($slugsBiz99)->toContain('doc-de-plataforma');
    expect($slugsBiz99)->not->toContain('0060-doc-do-tenant-1');
});

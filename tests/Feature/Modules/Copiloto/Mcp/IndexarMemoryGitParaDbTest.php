<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;
use Modules\Copiloto\Services\Mcp\IndexarMemoryGitParaDb;

/**
 * MEM-MCP-1.a (ADR 0053) — Service que sincroniza memory/ git → DB.
 *
 * Testa: parse frontmatter, PII redaction, UPSERT idempotente, history,
 * soft-delete de docs sumidos. Setup cria pasta tmp com .md files.
 */

$repoTmp = null;

beforeEach(function () use (&$repoTmp) {
    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('slug', 200)->unique();
        $t->string('type', 20);
        $t->string('module', 50)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
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
    Schema::dropIfExists('mcp_memory_documents_history');
    Schema::dropIfExists('mcp_memory_documents');

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

it('PII redactor redacta CPF + CNPJ + cartão e conta redactions', function () use (&$repoTmp) {
    file_put_contents("$repoTmp/memory/decisions/0001-pii.md", <<<MD
# Doc com PII

CPF do cliente: 123.456.789-00 e outro 12345678900.
CNPJ empresa: 12.345.678/0001-90.
Cartão: 1234 5678 9012 3456.
MD);

    $service = new IndexarMemoryGitParaDb($repoTmp);
    $stats = $service->run();

    expect($stats['redactions'])->toBe(4); // 2 CPFs + 1 CNPJ + 1 cartão

    $doc = McpMemoryDocument::where('slug', '0001-pii')->first();
    expect($doc->content_md)->not->toContain('123.456.789-00');
    expect($doc->content_md)->not->toContain('12345678900');
    expect($doc->content_md)->toContain('XXX.XXX.XXX-NN');
    expect($doc->content_md)->toContain('XX.XXX.XXX/XXXX-NN');
    expect($doc->content_md)->toContain('****-****-****-****');
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

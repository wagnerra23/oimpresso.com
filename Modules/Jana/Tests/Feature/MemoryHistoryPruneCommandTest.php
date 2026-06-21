<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * jana:memory-history-prune — poda preventiva de mcp_memory_documents_history
 * (incidente 2026-06-21: tabela inflou pra 5 GB e derrubou prod por estourar a cota).
 *
 * Invariantes:
 *  001. Preserva as últimas --keep versões por document_id (deleta as mais antigas).
 *  002. Defesa temporal: tudo dentro de --days fica intocado, mesmo passando do top-N.
 *  003. --dry-run não deleta nada.
 *  004. Poda é POR document_id — não vaza entre docs.
 *
 * Dual-mode SQLite (pattern reference_tests_pest_canon): schema sintético mínimo.
 * A query do comando é driver-agnóstica (subquery correlacionada, sem window fn).
 *
 * @see Modules\Jana\Console\Commands\MemoryHistoryPruneCommand
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente (quarentena Onda 2 SDD floor).');
    }

    Schema::dropIfExists('mcp_memory_documents_history');
    Schema::create('mcp_memory_documents_history', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('document_id');
        $t->string('slug', 200)->nullable();
        $t->string('title', 250)->nullable();
        $t->text('content_md')->nullable();
        $t->timestamp('changed_at')->nullable();
        $t->timestamp('created_at')->nullable();
        $t->index(['document_id', 'changed_at'], 'mcp_mh_doc_changed_idx');
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('mcp_memory_documents_history');
    }
});

/** Insere $n versões de um doc; changed_at = now()-($baseDays+i) dias (i=0 é a mais nova). */
function seedHistory(int $documentId, int $n, int $baseDays): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = [
            'document_id' => $documentId,
            'slug' => "doc-{$documentId}",
            'title' => "Doc {$documentId} v{$i}",
            'content_md' => str_repeat('x', 100),
            'changed_at' => now()->subDays($baseDays + $i),
            'created_at' => now()->subDays($baseDays + $i),
        ];
    }
    DB::table('mcp_memory_documents_history')->insert($rows);
}

it('MemoryHistoryPrune 001 — preserva as últimas --keep versões por doc', function () {
    seedHistory(documentId: 1, n: 30, baseDays: 100); // 30 versões, todas > 90d

    $exit = Artisan::call('jana:memory-history-prune', ['--keep' => 20, '--days' => 90]);
    expect($exit)->toBe(0);

    $restantes = DB::table('mcp_memory_documents_history')->where('document_id', 1)->count();
    expect($restantes)->toBe(20); // 10 mais antigas podadas

    // As preservadas são as 20 mais NOVAS (v0..v19); v20..v29 (as mais antigas) somem.
    $titulos = DB::table('mcp_memory_documents_history')->where('document_id', 1)->pluck('title')->all();
    expect($titulos)->toContain('Doc 1 v0')   // a mais nova — preservada
        ->toContain('Doc 1 v19')               // 20ª mais nova — preservada
        ->not->toContain('Doc 1 v20')          // 21ª — podada
        ->not->toContain('Doc 1 v29');         // a mais antiga — podada
});

it('MemoryHistoryPrune 002 — janela de dias protege history quente (nada podado)', function () {
    seedHistory(documentId: 1, n: 30, baseDays: 0); // 30 versões, todas dentro de 90d

    $exit = Artisan::call('jana:memory-history-prune', ['--keep' => 20, '--days' => 90]);
    expect($exit)->toBe(0);

    // Todas dentro da janela → 0 podadas mesmo passando do top-20.
    expect(DB::table('mcp_memory_documents_history')->where('document_id', 1)->count())->toBe(30);
});

it('MemoryHistoryPrune 003 — --dry-run não deleta nada', function () {
    seedHistory(documentId: 1, n: 30, baseDays: 100);

    $exit = Artisan::call('jana:memory-history-prune', ['--keep' => 20, '--days' => 90, '--dry-run' => true]);
    expect($exit)->toBe(0);

    expect(DB::table('mcp_memory_documents_history')->where('document_id', 1)->count())->toBe(30);
});

it('MemoryHistoryPrune 004 — poda é por document_id (não vaza entre docs)', function () {
    seedHistory(documentId: 10, n: 25, baseDays: 100); // 25 antigas → perde 5
    seedHistory(documentId: 20, n: 5, baseDays: 100);  // 5 antigas → top-N protege todas

    $exit = Artisan::call('jana:memory-history-prune', ['--keep' => 20, '--days' => 90]);
    expect($exit)->toBe(0);

    expect(DB::table('mcp_memory_documents_history')->where('document_id', 10)->count())->toBe(20);
    expect(DB::table('mcp_memory_documents_history')->where('document_id', 20)->count())->toBe(5);
});

<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpMemoryDocumentHistory;

/**
 * Camada 1 (defesa-em-profundidade vs incidente 2026-06-26) — TETO NO WRITE.
 *
 * McpMemoryDocumentHistory::podarExcedentePorDoc() mantém só as KEEP_PER_DOC
 * versões mais novas de um documento, deletando o excedente na hora. Bounda a
 * tabela em docs × KEEP independente do cron de poda — torna o burst que
 * derrubou a escrita do ERP (Hostinger revogou INSERT ao estourar a cota)
 * impossível na origem.
 *
 * Invariantes:
 *  001. Acima do teto → poda pro top-KEEP (preserva as mais NOVAS).
 *  002. No/abaixo do teto → não deleta nada.
 *  003. Poda é por document_id — não vaza entre docs.
 *
 * Dual-mode SQLite (pattern reference_tests_pest_canon): schema sintético mínimo.
 *
 * @see Modules\Jana\Entities\Mcp\McpMemoryDocumentHistory::podarExcedentePorDoc
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente.');
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

/** Insere $n versões; v0 é a mais nova (changed_at = now()-i). */
function semearVersoes(int $documentId, int $n): void
{
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = [
            'document_id' => $documentId,
            'slug' => "doc-{$documentId}",
            'title' => "Doc {$documentId} v{$i}",
            'content_md' => str_repeat('x', 100),
            'changed_at' => now()->subMinutes($i),
            'created_at' => now()->subMinutes($i),
        ];
    }
    DB::table('mcp_memory_documents_history')->insert($rows);
}

it('WriteCap 001 — acima do teto poda pro top-KEEP (preserva as mais novas)', function () {
    $keep = McpMemoryDocumentHistory::KEEP_PER_DOC;
    semearVersoes(documentId: 1, n: $keep + 10);

    $podadas = McpMemoryDocumentHistory::podarExcedentePorDoc(1);

    expect($podadas)->toBe(10);
    expect(DB::table('mcp_memory_documents_history')->where('document_id', 1)->count())->toBe($keep);

    $titulos = DB::table('mcp_memory_documents_history')->where('document_id', 1)->pluck('title')->all();
    expect($titulos)->toContain('Doc 1 v0')                 // mais nova — preservada
        ->toContain('Doc 1 v' . ($keep - 1))                // KEEP-ésima mais nova — preservada
        ->not->toContain('Doc 1 v' . $keep);                // (KEEP+1)-ésima — podada
});

it('WriteCap 002 — no/abaixo do teto não deleta nada', function () {
    $keep = McpMemoryDocumentHistory::KEEP_PER_DOC;
    semearVersoes(documentId: 1, n: $keep); // exatamente no teto

    $podadas = McpMemoryDocumentHistory::podarExcedentePorDoc(1);

    expect($podadas)->toBe(0);
    expect(DB::table('mcp_memory_documents_history')->where('document_id', 1)->count())->toBe($keep);
});

it('WriteCap 003 — poda é por document_id (não vaza entre docs)', function () {
    $keep = McpMemoryDocumentHistory::KEEP_PER_DOC;
    semearVersoes(documentId: 10, n: $keep + 5); // perde 5
    semearVersoes(documentId: 20, n: 3);         // abaixo do teto — intacto

    McpMemoryDocumentHistory::podarExcedentePorDoc(10);

    expect(DB::table('mcp_memory_documents_history')->where('document_id', 10)->count())->toBe($keep);
    expect(DB::table('mcp_memory_documents_history')->where('document_id', 20)->count())->toBe(3);
});

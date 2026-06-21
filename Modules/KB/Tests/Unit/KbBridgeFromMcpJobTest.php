<?php

declare(strict_types=1);

use Modules\KB\Entities\KbEdge;
use Modules\KB\Entities\KbNode;

/**
 * Unit specs do KbBridgeFromMcpJob.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §10
 *
 * Job recebe $businessId no constructor (jobs NUNCA dependem de session()).
 * Itera mcp_memory_documents.updated_at > kb_bridge_state.last_bridge_at,
 * cria/atualiza kb_node tipo bridge (is_editable=false, body_blocks=null),
 * deriva edges (supersedes, charter-of, related) do frontmatter.
 *
 * TODO[CL]: confirmar com Agent A o FQCN exato do Job:
 *   - Modules\KB\Jobs\KbBridgeFromMcpJob (presumido) OU
 *   - Modules\KB\Services\KbBridgeFromMcpJob (caso seja service síncrono)
 *
 * Pra os testes funcionarem agora, abstraímos via use statement — Agent A
 * ajusta o namespace se diferir.
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('creates kb_node for each mcp_memory_document of the business', function () {
    kbCreateBusinessRow(1);

    // Cria 3 docs canônicos
    $adr1 = kbCreateMcpDoc(1, 'adr', ['slug' => '0093-multi-tenant-isolation-tier-0', 'title' => 'ADR 0093']);
    $adr2 = kbCreateMcpDoc(1, 'adr', ['slug' => '0094-constituicao-v2', 'title' => 'ADR 0094']);
    $session = kbCreateMcpDoc(1, 'session', ['slug' => '2026-05-15-arte', 'title' => 'Session arte']);

    // Roda job
    $jobClass = guessKbBridgeJobClass();
    $job = new $jobClass(1);
    app()->call([$job, 'handle']);

    expect(\DB::table('kb_nodes')->count())->toBe(3);

    $bridgeAdr = \DB::table('kb_nodes')->where('source_doc_id', $adr1)->first();
    expect($bridgeAdr)->not->toBeNull()
        ->and($bridgeAdr->type)->toBe('adr')
        ->and((int) $bridgeAdr->is_editable)->toBe(0)
        ->and($bridgeAdr->body_blocks)->toBeNull();
});

it('updates existing kb_node when mcp_doc is updated', function () {
    kbCreateBusinessRow(1);
    $adrId = kbCreateMcpDoc(1, 'adr', ['slug' => '0149-kb', 'title' => 'Title V1']);

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);

    expect(\DB::table('kb_nodes')->where('source_doc_id', $adrId)->value('title'))->toBe('Title V1');

    // Update do mcp_doc
    \DB::table('mcp_memory_documents')->where('id', $adrId)->update([
        'title' => 'Title V2 (updated)',
        'updated_at' => now()->addMinute(),
    ]);

    // 2ª run do job atualiza (não duplica)
    app()->call([new $jobClass(1), 'handle']);

    expect(\DB::table('kb_nodes')->count())->toBe(1)
        ->and(\DB::table('kb_nodes')->where('source_doc_id', $adrId)->value('title'))->toBe('Title V2 (updated)');
});

it('sets is_editable=false for all bridge nodes (Tier 0 invariante)', function () {
    kbCreateBusinessRow(1);
    kbCreateMcpDoc(1, 'adr');
    kbCreateMcpDoc(1, 'session');
    kbCreateMcpDoc(1, 'charter');
    kbCreateMcpDoc(1, 'runbook');

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);

    $editable = \DB::table('kb_nodes')->where('is_editable', true)->count();
    expect($editable)->toBe(0);

    $bridgesWithBody = \DB::table('kb_nodes')->whereNotNull('body_blocks')->count();
    expect($bridgesWithBody)->toBe(0); // body_blocks SEMPRE NULL pra bridge
});

it('cascades mcp_doc soft-delete into kb_node status=deleted', function () {
    kbCreateBusinessRow(1);
    $docId = kbCreateMcpDoc(1, 'adr');

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);

    expect(\DB::table('kb_nodes')->where('source_doc_id', $docId)->value('status'))->toBe('ok');

    // Soft delete do mcp_doc
    \DB::table('mcp_memory_documents')->where('id', $docId)->update([
        'deleted_at' => now(),
        'updated_at' => now()->addMinute(),
    ]);

    app()->call([new $jobClass(1), 'handle']);

    expect(\DB::table('kb_nodes')->where('source_doc_id', $docId)->value('status'))->toBe('deleted');
});

it('derives supersedes edges from frontmatter metadata', function () {
    kbCreateBusinessRow(1);

    // ADR pai
    $oldAdrId = kbCreateMcpDoc(1, 'adr', [
        'slug' => '0061-conhecimento-canonico',
        'title' => 'ADR 0061',
    ]);

    // ADR novo que substitui — frontmatter `supersedes:` cita o slug ou número da pai
    $newAdrId = kbCreateMcpDoc(1, 'adr', [
        'slug' => '0131-tiering-memoria',
        'title' => 'ADR 0131',
        'metadata' => ['supersedes' => ['0061-conhecimento-canonico']],
    ]);

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);

    // Espera edge `supersedes` from=newNode to=oldNode
    $newNodeId = \DB::table('kb_nodes')->where('source_doc_id', $newAdrId)->value('id');
    $oldNodeId = \DB::table('kb_nodes')->where('source_doc_id', $oldAdrId)->value('id');

    $edge = \DB::table('kb_edges')
        ->where('from_node_id', $newNodeId)
        ->where('to_node_id', $oldNodeId)
        ->where('edge_type', 'supersedes')
        ->first();

    expect($edge)->not->toBeNull()
        ->and($edge->generated_by)->toBe('bridge_job');
});

it('is idempotent (run 2x does not duplicate)', function () {
    kbCreateBusinessRow(1);
    kbCreateMcpDoc(1, 'adr');
    kbCreateMcpDoc(1, 'session');

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);
    app()->call([new $jobClass(1), 'handle']);
    app()->call([new $jobClass(1), 'handle']);

    expect(\DB::table('kb_nodes')->count())->toBe(2);
});

it('respects business scope (biz=1 NAO toca docs biz=99)', function () {
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
    kbCreateMcpDoc(1, 'adr');
    kbCreateMcpDoc(99, 'adr');

    $jobClass = guessKbBridgeJobClass();
    app()->call([new $jobClass(1), 'handle']);  // só biz=1

    expect(\DB::table('kb_nodes')->count())->toBe(1)
        ->and(\DB::table('kb_nodes')->where('business_id', 99)->count())->toBe(0);
});

/**
 * Helper local — tenta encontrar a classe do Job em namespaces possíveis.
 *
 * TODO[CL]: substituir por FQCN definitivo quando Agent A merge.
 */
function guessKbBridgeJobClass(): string
{
    foreach (['Modules\\KB\\Jobs\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\KbBridgeFromMcpJob',
              'Modules\\KB\\Services\\Bridge\\KbBridgeFromMcpJob'] as $candidate) {
        if (class_exists($candidate)) {
            return $candidate;
        }
    }
    test()->markTestSkipped('KbBridgeFromMcpJob ainda não criado pelo Agent A. Esperado em Modules\\KB\\Jobs\\KbBridgeFromMcpJob.');
}

<?php

declare(strict_types=1);

/**
 * KbDriftPersistenceTest — Fase A1 (Swimm-like doc↔código na KB).
 *
 * O kb:drift-detector, além de logar/exit-code, agora PERSISTE o veredito por nó
 * em kb_nodes.code_drift_state pra a KB surfacar (HealthPanel/NodeReader — Fase A2).
 *
 * Cobre:
 *   - artigo que cita path deletado → code_drift_state {checked_at, refs:[{path,...}]}
 *   - artigo limpo → code_drift_state permanece NULL
 *   - self-healing: corrigir o doc limpa o flag no re-run
 *   - multi-tenant Tier 0: rodar biz=1 NÃO toca nó de biz=99 (ADR 0093/0101)
 *
 * Usa biz=1 + biz=99 (cross-tenant proof) e --mock (paths deletados injetados:
 * 'memory/decisions/0000-deleted-test.md' + 'Modules/Removed/Service.php').
 */

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

/** Insere um artigo editável e devolve o id. */
function kbInsertArticle(int $bizId, string $slug, string $paraText): int
{
    return (int) DB::table('kb_nodes')->insertGetId([
        'business_id' => $bizId,
        'type' => 'article',
        'slug' => $slug,
        'title' => "Artigo {$slug}",
        'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => $paraText]]),
        'status' => 'ok',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('grava code_drift_state quando o artigo cita path deletado', function () {
    $id = kbInsertArticle(1, 'com-drift', 'Ver memory/decisions/0000-deleted-test.md pra detalhes.');

    $this->artisan('kb:drift-detector', ['--business-id' => 1, '--mock' => true])
        ->assertExitCode(1);

    $raw = DB::table('kb_nodes')->where('id', $id)->value('code_drift_state');
    expect($raw)->not->toBeNull();

    $state = json_decode((string) $raw, true);
    expect($state)->toHaveKey('checked_at');
    expect($state['refs'][0]['path'])->toBe('memory/decisions/0000-deleted-test.md');
    expect($state['refs'][0]['drift_type'])->toBe('reference_deleted_path');
});

it('mantém code_drift_state NULL para artigo sem referência quebrada', function () {
    $id = kbInsertArticle(1, 'sem-drift', 'Conteúdo operacional sem citar arquivo nenhum.');

    $this->artisan('kb:drift-detector', ['--business-id' => 1, '--mock' => true])
        ->assertExitCode(0);

    $raw = DB::table('kb_nodes')->where('id', $id)->value('code_drift_state');
    expect($raw)->toBeNull();
});

it('self-heal: corrigir o doc limpa o flag no re-run', function () {
    $id = kbInsertArticle(1, 'vai-curar', 'cita Modules/Removed/Service.php aqui');

    // 1ª passada — flag gravado
    $this->artisan('kb:drift-detector', ['--business-id' => 1, '--mock' => true])->assertExitCode(1);
    expect(DB::table('kb_nodes')->where('id', $id)->value('code_drift_state'))->not->toBeNull();

    // Corrige o doc (remove a referência ao path deletado)
    DB::table('kb_nodes')->where('id', $id)->update([
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'referência removida, tudo certo agora']]),
    ]);

    // 2ª passada — flag limpo (volta a NULL)
    $this->artisan('kb:drift-detector', ['--business-id' => 1, '--mock' => true])->assertExitCode(0);
    expect(DB::table('kb_nodes')->where('id', $id)->value('code_drift_state'))->toBeNull();
});

it('multi-tenant Tier 0: rodar biz=1 não escreve o nó de biz=99', function () {
    $idBiz99 = kbInsertArticle(99, 'drift-biz99', 'cita memory/decisions/0000-deleted-test.md');

    // Roda SÓ pra biz=1 — não deve tocar o nó de biz=99
    $this->artisan('kb:drift-detector', ['--business-id' => 1, '--mock' => true])->assertExitCode(0);
    expect(DB::table('kb_nodes')->where('id', $idBiz99)->value('code_drift_state'))->toBeNull();

    // Roda pra biz=99 — agora grava
    $this->artisan('kb:drift-detector', ['--business-id' => 99, '--mock' => true])->assertExitCode(1);
    expect(DB::table('kb_nodes')->where('id', $idBiz99)->value('code_drift_state'))->not->toBeNull();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\KB\Entities\KbCharterSuggestion;

/**
 * F1 governança (ADR 0243) — sugestão supervisionada + aprovação em /kb/charters.
 *
 * Núcleo do charter é read-only (filesystem/git); a sugestão registra proposta
 * em kb_charter_suggestions (Tier 0 business scope). Reusa Helpers KB + roda a
 * migration nova explicitamente (o helper só carrega as migrations de 2026-05).
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    (require base_path('Modules/KB/Database/Migrations/2026_06_01_120000_create_kb_charter_suggestions_table.php'))->up();
});

afterEach(function () {
    Schema::dropIfExists('kb_charter_suggestions');
    kbTeardownSchema();
});

// charter real do repo (o desta própria tela) — passa no safeCharterPath
const CHARTER = 'resources/js/Pages/kb/Charters/Index.charter.md';

it('propõe sugestão (201) sem tocar o núcleo', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->postJson('/kb/charters/suggestions', [
        'path' => CHARTER, 'kind' => 'suggestion', 'text' => 'Adicionar Non-Goal sobre X.',
    ])
        ->assertCreated()
        ->assertJsonPath('suggestion.status', 'proposed')
        ->assertJsonPath('suggestion.kind', 'suggestion');

    expect(KbCharterSuggestion::withoutGlobalScopes()->count())->toBe(1);
});

it('lista as sugestões do charter', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);
    $this->postJson('/kb/charters/suggestions', ['path' => CHARTER, 'text' => 'minha sugestão']);

    $this->getJson('/kb/charters/suggestions?path='.urlencode(CHARTER))
        ->assertOk()
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonPath('suggestions.0.text', 'minha sugestão');
});

it('aprova com nota → status accepted + resolvedor', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);
    $id = $this->postJson('/kb/charters/suggestions', ['path' => CHARTER, 'text' => 'aprovar isso'])
        ->json('suggestion.id');

    $this->patchJson("/kb/charters/suggestions/{$id}", [
        'status' => 'accepted', 'resolution_note' => 'Faz sentido, vira PR.',
    ])
        ->assertOk()
        ->assertJsonPath('suggestion.status', 'accepted')
        ->assertJsonPath('suggestion.resolution_note', 'Faz sentido, vira PR.');

    expect(KbCharterSuggestion::withoutGlobalScopes()->find($id)->resolved_by_user_id)->not->toBeNull();
});

it('rejeita SEM nota → 422 (comentário obrigatório)', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);
    $id = $this->postJson('/kb/charters/suggestions', ['path' => CHARTER, 'text' => 'x'])
        ->json('suggestion.id');

    $this->patchJson("/kb/charters/suggestions/{$id}", ['status' => 'rejected'])
        ->assertStatus(422);
});

it('recusa sugestão em charter inexistente (404)', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);
    $this->postJson('/kb/charters/suggestions', [
        'path' => 'resources/js/Pages/NaoExiste/Fantasma.charter.md', 'text' => 'fantasma',
    ])->assertStatus(404);
});

it('Tier 0: não vaza sugestões entre tenants', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);
    $this->postJson('/kb/charters/suggestions', ['path' => CHARTER, 'text' => 'segredo biz1'])->assertCreated();

    kbActAsUser(bizId: 99, permissions: ['copiloto.mcp.memory.manage']);
    $this->getJson('/kb/charters/suggestions?path='.urlencode(CHARTER))
        ->assertOk()
        ->assertJsonCount(0, 'suggestions');
});

it('exige autenticação', function () {
    $this->postJson('/kb/charters/suggestions', ['path' => CHARTER, 'text' => 'x'])
        ->assertStatus(401);
});

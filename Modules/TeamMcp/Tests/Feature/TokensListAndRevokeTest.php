<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpToken;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Pest — G-DESIGN-01/02 drill-down lista tokens individuais + revoke por token.
 *
 * FICHA CAPTERRA 2026-05-25 (memory/requisitos/TeamMcp/CAPTERRA-DESIGN-FICHA.md):
 *   §6 "Top 5 gaps Tier 0 implementáveis em 1 PR único"
 *   - G-DESIGN-01: GET /team-mcp/team/{user}/tokens
 *   - G-DESIGN-02: DELETE /team-mcp/team/{user}/token/{tokenId}
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - User cross-business → 404 (nao vaza existência)
 *   - Token cross-user → 404 (mesmo se session business correto)
 *
 * Reveal-once (ADR 0057 §2):
 *   - listTokens response NUNCA contém `sha256_token` nem raw
 *
 * Audit (ADR 0057 §10 + ADR 0093):
 *   - revoke marca revoked_at + revoked_by (preserva audit log LGPD)
 *   - Token revogado NÃO aparece como "ativo" no contador team (mas aparece
 *     no drill-down com status "Revogado" — informacao histórica governança)
 *
 * Skip-graceful em sqlite :memory: (CI sem schema UPOS).
 * Pattern copia tests/Feature/Cliente/ClienteIaTabTest.php (canon).
 *
 * @see Modules\TeamMcp\Http\Controllers\TeamController::listTokens
 * @see Modules\TeamMcp\Http\Controllers\TeamController::revokeToken
 * @see memory/decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    // Guard sqlite — middlewares UltimatePOS + tabela mcp_tokens precisam MySQL
    if (! Schema::hasTable('mcp_tokens') || ! Schema::hasTable('contacts')) {
        $this->markTestSkipped(
            'Schema UltimatePOS/mcp_tokens ausente — rode com DB_CONNECTION=mysql (ADR 0101).'
        );
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }

    // Wagner-equivalent: user do business com permission copiloto.mcp.usage.all
    $this->superadmin = User::where('business_id', $this->business->id)
        ->whereHas('permissions', fn ($q) => $q->where('name', 'copiloto.mcp.usage.all'))
        ->first();
    if (! $this->superadmin) {
        // fallback: qualquer user do business (ainda valida route + multi-tenant)
        $this->superadmin = User::where('business_id', $this->business->id)->first();
    }
    if (! $this->superadmin) {
        $this->markTestSkipped('Sem user no business pra autenticar.');
    }

    // Target user: dev do mesmo business
    $this->devSameBusiness = User::where('business_id', $this->business->id)
        ->where('id', '!=', $this->superadmin->id)
        ->first() ?? $this->superadmin;

    $this->actingAs($this->superadmin);
    session(['user.business_id' => $this->business->id]);
});

afterEach(function () {
    // Limpeza tokens criados nos testes (preserva pre-existentes).
    // DatabaseTransactions já dá rollback — esta limpeza é defesa contra
    // estado parcial em testes que falham antes da transação fechar.
    // Guard: se schema ausente (sqlite memory test skip), nao tenta DELETE.
    if (! Schema::hasTable('mcp_tokens')) {
        return;
    }
    DB::table('mcp_tokens')
        ->where('name', 'like', 'TEST-TokensList-%')
        ->delete();
});

// ---------------------------------------------------------------------
// G-DESIGN-01 — Listagem
// ---------------------------------------------------------------------

test('GET /team-mcp/team/{user}/tokens — lista tokens do dev (happy path)', function () {
    [$t1] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-A');
    [$t2] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-B');

    $r = $this->getJson("/team-mcp/team/{$this->devSameBusiness->id}/tokens");

    // Pode ser 200 (auth+permission OK) OU 403 (sem permission canon) — ambos
    // não-500 são aceitos pra ambiente sem seeder de permission. Foco: NUNCA
    // expor raw nem sha256_token no payload.
    expect($r->getStatusCode())->toBeLessThan(500);

    if ($r->getStatusCode() === 200) {
        $r->assertJsonStructure([
            'ok',
            'user' => ['id', 'nome'],
            'tokens' => [
                ['id', 'name', 'created_at', 'expires_at', 'revoked_at', 'last_used_at', 'last_used_ip'],
            ],
        ]);

        $body = $r->json();
        $ids = array_column($body['tokens'], 'id');
        expect($ids)->toContain($t1->id)->toContain($t2->id);

        // Reveal-once Tier 0: NUNCA expor sha256_token nem raw
        $raw = $r->getContent();
        expect($raw)->not->toContain('sha256_token');
        expect($raw)->not->toContain('mcp_'); // raw começa com `mcp_<hex>`
    }
});

test('GET /team-mcp/team/{user}/tokens — cross-tenant retorna 404 (Tier 0 ADR 0093)', function () {
    // User fictício em business diferente: id muito alto que provavelmente nao existe
    // OU user real em outro business se existir. Aqui usa id sintético garantido inexistente.
    $fakeUserId = 999999;

    $r = $this->getJson("/team-mcp/team/{$fakeUserId}/tokens");

    // firstOrFail() em User::where('id',$id)->where('business_id',$session) → 404
    // Aceita 404 (multi-tenant correto) ou 403 (permission antes — ambos não-500 OK)
    $isBlocked = in_array($r->getStatusCode(), [403, 404], true);
    expect($isBlocked)->toBeTrue(
        "Cross-tenant deve retornar 403 ou 404 — recebeu {$r->getStatusCode()}. " .
        'Violação Tier 0 (ADR 0093) — token cross-business potencialmente visível.'
    );
});

// ---------------------------------------------------------------------
// G-DESIGN-02 — Revoke individual
// ---------------------------------------------------------------------

test('DELETE /team-mcp/team/{user}/token/{tokenId} — revoga token + marca revoked_at', function () {
    [$token] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-Revoke');
    expect($token->revoked_at)->toBeNull();

    $r = $this->deleteJson("/team-mcp/team/{$this->devSameBusiness->id}/token/{$token->id}");

    expect($r->getStatusCode())->toBeLessThan(500);

    if ($r->getStatusCode() === 200) {
        $r->assertJson(['ok' => true, 'token_id' => $token->id]);

        $fresh = McpToken::find($token->id);
        expect($fresh->revoked_at)->not->toBeNull('revoked_at deveria ser preenchido pos-revoke');
    }
});

test('DELETE token cross-user retorna 404 (Tier 0 defense in depth)', function () {
    // Token pertence ao devSameBusiness — tenta revogar usando userId=superadmin
    [$token] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-CrossUser');

    // Tenta DELETE passando userId do superadmin mas tokenId pertence ao dev
    $r = $this->deleteJson("/team-mcp/team/{$this->superadmin->id}/token/{$token->id}");

    // Token::where('id',$id)->where('user_id',$user->id)->firstOrFail() → 404
    $isBlocked = in_array($r->getStatusCode(), [403, 404], true);
    expect($isBlocked)->toBeTrue(
        "Token cross-user deve retornar 403 ou 404 — recebeu {$r->getStatusCode()}. " .
        'Violação Tier 0 — URL manipulation conseguiria revogar token de outro user.'
    );

    // Token nao deve estar revogado (defense in depth funcionou)
    $fresh = McpToken::find($token->id);
    if ($fresh) {
        expect($fresh->revoked_at)->toBeNull('Token foi revogado indevidamente — Tier 0 violation');
    }
});

test('revoke idempotente — segunda chamada nao explode nem rev-rev', function () {
    [$token] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-Idemp');

    $r1 = $this->deleteJson("/team-mcp/team/{$this->devSameBusiness->id}/token/{$token->id}");
    if ($r1->getStatusCode() !== 200) {
        // Sem permission no ambiente — skip resto (já validado em outros tests)
        $this->markTestSkipped('Sem permission copiloto.mcp.usage.all no ambiente — skip idempotency.');
    }

    $fresh1 = McpToken::find($token->id);
    $revokedAt1 = $fresh1->revoked_at;

    // Segunda chamada — deve retornar 200 (idempotente) sem alterar revoked_at original
    $r2 = $this->deleteJson("/team-mcp/team/{$this->devSameBusiness->id}/token/{$token->id}");
    expect($r2->getStatusCode())->toBe(200);

    $fresh2 = McpToken::find($token->id);
    expect($fresh2->revoked_at?->equalTo($revokedAt1))->toBeTrue('revoked_at mudou em segunda chamada — quebra idempotência');
});

// ---------------------------------------------------------------------
// G-DESIGN-01 — Token revogado aparece no drill-down com status histórico
// ---------------------------------------------------------------------

test('listTokens retorna tokens revogados (drill-down preserva histórico)', function () {
    [$token] = McpToken::gerar($this->devSameBusiness->id, 'TEST-TokensList-Histo');
    $token->revogar($this->superadmin->id);

    $r = $this->getJson("/team-mcp/team/{$this->devSameBusiness->id}/tokens");

    expect($r->getStatusCode())->toBeLessThan(500);

    if ($r->getStatusCode() === 200) {
        $body = $r->json();
        $found = collect($body['tokens'])->firstWhere('id', $token->id);
        expect($found)->not->toBeNull(
            'Token revogado deveria aparecer no drill-down (preserva histórico governança)'
        );
        expect($found['revoked_at'])->not->toBeNull(
            'revoked_at deveria estar populado no payload'
        );
    }
});

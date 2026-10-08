<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpQuota;
use Modules\Jana\Entities\Mcp\McpToken;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Equipe (`/team-mcp/team`) · Tier 0 — o id da URL tem de ser do negócio da sessão.
 *
 * Decisão [W] D14 (2026-10-07, playbook Forja thread 15): `gerarToken`, `gerarDxt` e
 * `atualizarQuota` conferem que o `userId` da URL é do negócio da sessão e respondem
 * 404 quando não é. A rota legacy `DELETE /team-mcp/team/token/{token}` sai: ela
 * revogava por `tokenId` sem conferir dono nem negócio, o que contradiz o Anti-hook do
 * charter. A revogação que fica é a escopada (`/team/{user}/token/{tokenId}`, UC-EQP-04).
 *
 * Tenant 98 (fictício, ADR 0358) × 99 (adversário). NUNCA biz=4.
 * Só dá veredito na lane MySQL `forja-pest.yml`; em sqlite pula.
 *
 * @see Modules\Forja\Http\Controllers\TeamController
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite'
        || ! Schema::hasTable('mcp_tokens') || ! Schema::hasTable('mcp_quotas')) {
        $this->markTestSkipped('Exige MySQL com mcp_tokens/mcp_quotas — lane forja-pest.yml.');
    }

    $business = $this->seededTenant();                      // biz=98
    $this->adversario = $this->seededSupportClientTenant(); // biz=99
    $this->operador = $this->usuarioComPermissoes(['jana.mcp.usage.all'], $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $this->operador->id,
    ]);

    $this->doTime = User::factory()->create(['business_id' => $business->id]);
    $this->alheio = User::factory()->create(['business_id' => $this->adversario->id]);
});

it('gerarToken: usuário de outro negócio dá 404 e nenhum token é emitido; do próprio negócio dá 200', function () {
    $this->actingAs($this->operador)
        ->postJson("/team-mcp/team/{$this->doTime->id}/token")
        ->assertStatus(200);

    $this->actingAs($this->operador)
        ->postJson("/team-mcp/team/{$this->alheio->id}/token")
        ->assertStatus(404);

    expect(McpToken::withTrashed()->where('user_id', $this->alheio->id)->count())->toBe(0);
});

it('gerarDxt: usuário de outro negócio dá 404 e nenhum token é emitido', function () {
    $this->actingAs($this->operador)
        ->post("/team-mcp/team/{$this->alheio->id}/dxt")
        ->assertStatus(404);

    expect(McpToken::withTrashed()->where('user_id', $this->alheio->id)->count())->toBe(0);
});

it('atualizarQuota: usuário de outro negócio dá 404 e nenhuma quota é gravada; do próprio negócio grava', function () {
    $payload = ['period' => 'daily', 'limit_brl' => 1, 'block_on_exceed' => true];

    $this->actingAs($this->operador)
        ->postJson("/team-mcp/team/{$this->doTime->id}/quota", $payload)
        ->assertStatus(200);
    expect(McpQuota::where('user_id', $this->doTime->id)->count())->toBe(1);

    $this->actingAs($this->operador)
        ->postJson("/team-mcp/team/{$this->alheio->id}/quota", $payload)
        ->assertStatus(404);
    expect(McpQuota::where('user_id', $this->alheio->id)->count())->toBe(0);
});

it('a rota legacy DELETE /team-mcp/team/token/{token} não existe e o token de outro negócio segue ativo', function () {
    expect(Route::has('team-mcp.team.token.revogar'))->toBeFalse();

    [$token] = McpToken::gerar($this->alheio->id, 'T15-CT98-alheio');

    $this->actingAs($this->operador)
        ->deleteJson("/team-mcp/team/token/{$token->id}")
        ->assertStatus(404);

    expect(McpToken::find($token->id)?->revoked_at)->toBeNull('token de outro negócio foi revogado');
});

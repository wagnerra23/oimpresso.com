<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpToken;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Equipe · contrato da tela `/team-mcp/team` (governança de tokens MCP do time).
 *
 * Cobre os UC de `Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.casos.md`.
 * Os UC saem do charter (Restrições Tier 0, Anti-hooks, Métricas) + SPEC do TeamMcp
 * (US-TEAM-002/003) + SDD do hub (CU-TEAM-01/02/09) + ADR 0057/0093 — não do `.tsx`.
 * O `TeamController` só confirma o comportamento.
 *
 * DUAS FORÇAS (ADR 0264 G-7):
 *   - pernas de REGISTRO (rota, middleware) rodam em qualquer driver, inclusive na
 *     lane sqlite `PHP / Pest (Unit)`;
 *   - pernas de REQUEST (403, isolamento por business, reveal-once, revogação) exigem
 *     a stack UltimatePOS → só dão veredito na lane `forja-pest.yml`. Em sqlite elas
 *     PULAM, e skip não é cobertura.
 *
 * Mais firme que o `TokensListAndRevokeTest` de propósito: aquele aceita 403 como
 * "ok" e só asserta dentro de `if (200)`, então passa sem provar nada quando falta
 * permissão. Aqui o operador é criado COM a permissão, e o status esperado é exato.
 *
 * Tenant: o canônico de teste (biz=98, ADR 0358) via `seededTenant()`; o "outro
 * business" é qualquer business ≠ esse e ≠ 4 (o seed do CI cria o biz=2). NUNCA biz=4.
 *
 * @see Modules\Forja\Http\Controllers\TeamController
 * @see memory/decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const EQP_PERMISSION = 'jana.mcp.usage.all';

/** Rotas `team-mcp.team.*` como o router as enxerga. */
function eqpRotas(): \Illuminate\Support\Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r): bool => str_starts_with((string) $r->getName(), 'team-mcp.team.'));
}

function eqpExigeSchemaMysql(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped(
            'SQLite-incompatível: middlewares UltimatePOS exigem schema MySQL com '.
            'business/users/permissions. Esta perna só dá veredito na lane forja-pest.yml.'
        );
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('mcp_tokens')) {
        test()->markTestSkipped('Schema ausente (users/mcp_tokens) — rode com DB_CONNECTION=mysql.');
    }
}

/**
 * Operador NOVO no tenant de teste com exatamente as permissões pedidas — sem `Admin#`
 * (o `Gate::before` liberaria tudo e o caso negativo viraria decorativo).
 *
 * @param  list<string>  $permissoes
 */
function eqpOperador(array $permissoes): User
{
    eqpExigeSchemaMysql();

    try {
        $business = test()->seededTenant(); // biz=98 (ADR 0358) — NUNCA biz=4
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tenant canônico ausente: '.$e->getMessage());
    }

    $user = test()->usuarioComPermissoes($permissoes, $business);

    session([
        'user.business_id' => $business->id,
        'business.id'      => $business->id,
        'user.id'          => $user->id,
    ]);

    return $user;
}

/** Um dev do MESMO business do operador (alvo legítimo das ações da tela). */
function eqpDevMesmoBusiness(User $operador): User
{
    return User::factory()->create(['business_id' => $operador->business_id]);
}

/** Um usuário de OUTRO business (alvo que a tela nunca pode alcançar). */
function eqpUsuarioOutroBusiness(User $operador): User
{
    $outro = (int) DB::table('business')
        ->where('id', '!=', $operador->business_id)
        ->where('id', '!=', 4)
        ->value('id');

    if ($outro === 0) {
        test()->markTestSkipped('Sem segundo business no banco pra provar isolamento (o seed do CI cria o biz=2).');
    }

    return User::factory()->create(['business_id' => $outro]);
}

// ---------------------------------------------------------------------------
// UC-EQP-01 — a rota abre a tela (a Page existe)
// ---------------------------------------------------------------------------

it('UC-EQP-01 · a rota da tela está registrada e aponta pro TeamController@index', function () {
    $route = Route::getRoutes()->getByName('team-mcp.team.index');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('team-mcp/team');
    expect($route->getActionName())->toEndWith('TeamController@index');
});

it('UC-EQP-01 · o componente Inertia que o controller renderiza existe em disco', function () {
    $controller = file_get_contents(base_path('Modules/Forja/Http/Controllers/TeamController.php'));

    expect($controller)->toContain("Inertia::render('team-mcp/Team/Index'");
    expect(file_exists(base_path('Modules/Forja/Resources/js/Pages/team-mcp/Team/Index.tsx')))->toBeTrue(
        'O controller renderiza team-mcp/Team/Index mas o .tsx não existe — Inertia 500.'
    );
});

// ---------------------------------------------------------------------------
// UC-EQP-02 — acesso: auth + jana.mcp.usage.all em TODA rota da tela
// ---------------------------------------------------------------------------

it('UC-EQP-02 · toda rota team-mcp.team.* exige auth + can:jana.mcp.usage.all no registro', function () {
    $rotas = eqpRotas();

    // index, token.gerar, dxt.gerar, tokens.index, token.revoke, token.revogar,
    // quota.update, export.csv. Bem menos que isso = alguém sumiu com rota da tela.
    expect($rotas->count())->toBeGreaterThanOrEqual(6);

    foreach ($rotas as $r) {
        $mw = $r->gatherMiddleware();

        expect(in_array('auth', $mw, true))->toBeTrue("Rota {$r->getName()} sem `auth`.");

        $exige = collect($mw)->contains(fn ($m): bool => is_string($m) && str_contains($m, EQP_PERMISSION));
        expect($exige)->toBeTrue(
            "Rota {$r->getName()} não exige `".EQP_PERMISSION.'`. Esta tela emite e revoga '.
            'credencial MCP (ADR 0057) — sem a trava, qualquer funcionário logado gera token. '.
            'Middleware visto: '.json_encode($mw)
        );
    }
});

it('UC-EQP-02 · autenticado SEM jana.mcp.usage.all leva 403 na tela e ao tentar gerar token', function () {
    $semPermissao = eqpOperador([]);
    $alvo = eqpDevMesmoBusiness($semPermissao);

    $this->actingAs($semPermissao)->get('/team-mcp/team')->assertStatus(403);

    $antes = McpToken::withTrashed()->where('user_id', $alvo->id)->count();
    $this->actingAs($semPermissao)->postJson("/team-mcp/team/{$alvo->id}/token")->assertStatus(403);
    expect(McpToken::withTrashed()->where('user_id', $alvo->id)->count())->toBe($antes);
});

// ---------------------------------------------------------------------------
// UC-EQP-03 [T0] — drill-down de tokens não alcança usuário de outro business
// ---------------------------------------------------------------------------

it('UC-EQP-03 · listar tokens de usuário de OUTRO business dá 404; do mesmo business dá 200', function () {
    $operador = eqpOperador([EQP_PERMISSION]);
    $doTime = eqpDevMesmoBusiness($operador);
    $alheio = eqpUsuarioOutroBusiness($operador);

    McpToken::gerar($alheio->id, 'EQP-CT98-alheio');

    // Controle positivo: a rota funciona pro próprio business.
    $this->actingAs($operador)->getJson("/team-mcp/team/{$doTime->id}/tokens")->assertStatus(200);

    // O caso Tier 0: nem a existência do usuário de outro business vaza (ADR 0093).
    $this->actingAs($operador)->getJson("/team-mcp/team/{$alheio->id}/tokens")->assertStatus(404);
});

// ---------------------------------------------------------------------------
// UC-EQP-04 [T0] — revogar só alcança token do próprio usuário do próprio business
// ---------------------------------------------------------------------------

it('UC-EQP-04 · revogar token de outro business ou de outro usuário dá 404 e o token segue ativo', function () {
    $operador = eqpOperador([EQP_PERMISSION]);
    $doTime = eqpDevMesmoBusiness($operador);
    $outroDoTime = eqpDevMesmoBusiness($operador);
    $alheio = eqpUsuarioOutroBusiness($operador);

    [$tokenAlheio] = McpToken::gerar($alheio->id, 'EQP-CT98-alheio');
    [$tokenDoTime] = McpToken::gerar($doTime->id, 'EQP-CT98-time');

    // Outro business, pela URL do próprio dono: o usuário nem é encontrado.
    $this->actingAs($operador)
        ->deleteJson("/team-mcp/team/{$alheio->id}/token/{$tokenAlheio->id}")
        ->assertStatus(404);

    // Mesmo business, mas o token é de OUTRA pessoa que a da URL (manipulação de URL).
    $this->actingAs($operador)
        ->deleteJson("/team-mcp/team/{$outroDoTime->id}/token/{$tokenDoTime->id}")
        ->assertStatus(404);

    expect(McpToken::find($tokenAlheio->id)?->revoked_at)->toBeNull('token de outro business foi revogado');
    expect(McpToken::find($tokenDoTime->id)?->revoked_at)->toBeNull('token revogado pela URL de outro usuário');
});

// ---------------------------------------------------------------------------
// UC-EQP-05 [T0] — reveal-once: o raw aparece uma vez, nunca mais
// ---------------------------------------------------------------------------

it('UC-EQP-05 · gerar devolve o raw uma vez; o banco guarda só o hash e o drill-down não mostra nenhum dos dois', function () {
    $operador = eqpOperador([EQP_PERMISSION]);
    $dev = eqpDevMesmoBusiness($operador);

    $r = $this->actingAs($operador)
        ->postJson("/team-mcp/team/{$dev->id}/token", ['note' => 'EQP-CT98-reveal'])
        ->assertStatus(200);

    $raw = (string) $r->json('token_raw');
    $tokenId = (int) $r->json('token_id');

    expect($raw)->not->toBe('');

    // O banco guarda o hash do raw — e o raw em si não está em coluna nenhuma da linha.
    $linha = (array) DB::table('mcp_tokens')->where('id', $tokenId)->first();
    expect($linha)->not->toBe([]);
    expect($linha['sha256_token'] ?? null)->toBe(hash('sha256', $raw));
    expect(in_array($raw, array_map('strval', array_values($linha)), true))->toBeFalse(
        'o raw do token está gravado em claro em mcp_tokens'
    );

    // O drill-down lista o token, sem o raw e sem o hash (ADR 0057 §2).
    $lista = $this->actingAs($operador)->getJson("/team-mcp/team/{$dev->id}/tokens")->assertStatus(200);
    expect(array_column($lista->json('tokens'), 'id'))->toContain($tokenId);

    $corpo = $lista->getContent();
    expect(str_contains($corpo, $raw))->toBeFalse('o drill-down devolveu o raw do token');
    expect(str_contains($corpo, hash('sha256', $raw)))->toBeFalse('o drill-down devolveu o hash do token');
});

// ---------------------------------------------------------------------------
// UC-EQP-06 — revogar é lógico: registro preservado, com quem e quando
// ---------------------------------------------------------------------------

it('UC-EQP-06 · revogar grava revoked_at + revoked_by, preserva a linha e o drill-down mostra como revogado', function () {
    $operador = eqpOperador([EQP_PERMISSION]);
    $dev = eqpDevMesmoBusiness($operador);

    [$token] = McpToken::gerar($dev->id, 'EQP-CT98-revoke');

    $this->actingAs($operador)
        ->deleteJson("/team-mcp/team/{$dev->id}/token/{$token->id}")
        ->assertStatus(200)
        ->assertJsonPath('token_id', $token->id);

    $linha = DB::table('mcp_tokens')->where('id', $token->id)->first();
    expect($linha)->not->toBeNull('a revogação apagou a linha — o audit LGPD perde o registro');
    expect($linha->revoked_at)->not->toBeNull();
    expect((int) $linha->revoked_by)->toBe((int) $operador->id);

    $lista = $this->actingAs($operador)->getJson("/team-mcp/team/{$dev->id}/tokens")->assertStatus(200);
    $doToken = collect($lista->json('tokens'))->firstWhere('id', $token->id);
    expect($doToken)->not->toBeNull('o token revogado sumiu do drill-down — o histórico de governança se perde');
    expect($doToken['revoked_at'])->not->toBeNull();
});

<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

uses(Tests\TestCase::class);

/**
 * Smoke scaffold do Modules/TeamMcp (US-TEAM-001).
 *
 * Garante que:
 *   1. Modulo aparece registrado em nWidart
 *   2. ServiceProvider classe existe
 *   3. Rotas web /team-mcp foram registradas (TeamController + InstallController)
 *   4. Entities canônicas + Services + Controllers carregam
 *
 * Refs: ADR 0011 padrao Jana/Repair/Project, ADR 0081 Identity Mesh,
 *       ADR 0053 MCP server, skill criar-modulo.
 *
 * NUNCA usar biz=4 (ROTA LIVRE producao) — ADR 0101.
 * Wagner aqui e superadmin/L0 dev = cross-tenant (mcp_actors sem business_id).
 */

it('cenario 1: modulo TeamMcp aparece registrado em nWidart', function () {
    $module = Module::find('TeamMcp');
    expect($module)->not->toBeNull('Modules/TeamMcp deveria estar registrado em nWidart');
    expect($module->getName())->toBe('TeamMcp');
});

it('cenario 2: TeamMcpServiceProvider classe existe', function () {
    expect(class_exists(\Modules\TeamMcp\Providers\TeamMcpServiceProvider::class))
        ->toBeTrue('Providers/TeamMcpServiceProvider.php deveria existir');
});

it('cenario 3: rota team-mcp.team.index existe', function () {
    expect(\Route::has('team-mcp.team.index'))
        ->toBeTrue('Rota team-mcp.team.index (TeamController@index) deveria existir');
});

it('cenario 4: rota team-mcp.team.token.gerar existe', function () {
    expect(\Route::has('team-mcp.team.token.gerar'))
        ->toBeTrue('Rota team-mcp.team.token.gerar (TeamController@gerarToken) deveria existir');
});

it('cenario 5: rota team-mcp.team.token.revogar existe', function () {
    expect(\Route::has('team-mcp.team.token.revogar'))
        ->toBeTrue('Rota team-mcp.team.token.revogar (TeamController@revogarToken) deveria existir');
});

it('cenario 6: rota team-mcp.install.index existe', function () {
    expect(\Route::has('team-mcp.install.index'))
        ->toBeTrue('Rota team-mcp.install.index (InstallController@index) deveria existir');
});

it('cenario 7: Entity McpActor classe carrega', function () {
    expect(class_exists(\Modules\TeamMcp\Entities\McpActor::class))
        ->toBeTrue('Entities/McpActor.php (ADR 0081 Identity Mesh) deveria carregar');
});

it('cenario 8: ActorResolver service classe carrega', function () {
    expect(class_exists(\Modules\TeamMcp\Services\ActorResolver::class))
        ->toBeTrue('Services/ActorResolver.php deveria carregar');
});

it('cenario 9: TeamController classe carrega', function () {
    expect(class_exists(\Modules\TeamMcp\Http\Controllers\TeamController::class))
        ->toBeTrue('Http/Controllers/TeamController.php deveria carregar');
});

it('cenario 10: rota team-mcp.cc.index (KB Claude Code sessions) existe', function () {
    expect(\Route::has('team-mcp.cc.index'))
        ->toBeTrue('Rota team-mcp.cc.index (MEM-CC-UI-1) deveria existir');
});

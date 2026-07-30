<?php

declare(strict_types=1);

use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * GUARD — incidente 2026-07-29 (4ª volta da mesma classe).
 *
 * `RoleController@update` (core UltimatePOS) faz
 * `$role->syncPermissions($request->input('permissions'))`, que é DESTRUTIVO:
 * apaga toda permission que não veio no POST do form. O form de
 * `/roles/{id}/edit` só renderiza o que os módulos declaram em
 * `user_permissions()`.
 *
 * Enquanto os `jana.mcp.*` não estavam declarados lá, **qualquer save de
 * qualquer role apagava a família inteira**. Em 29/07 um save na role
 * `Operacional#1` (biz=1) zerou os 17 scopes e derrubou o MCP dos 4 users do
 * time — token válido devolvendo `403 no_permission` no gate `jana.mcp.use`.
 *
 * Este teste trava a paridade catálogo ⇄ tela. Sem DB, determinístico.
 */
it('expõe TODO scope do catálogo MCP como checkbox da tela de roles (senão o save apaga)', function () {
    $doCatalogo = array_map(
        static fn (array $s): string => $s['slug'],
        McpScopesSeeder::catalogo()
    );

    $daTela = array_column((new DataController())->user_permissions(), 'value');

    // Controle de sanidade: o catálogo não pode estar vazio, senão o teste
    // passaria por não-execução (verde tautológico).
    expect($doCatalogo)->not->toBeEmpty();

    $invisiveis = array_values(array_diff($doCatalogo, $daTela));

    expect($invisiveis)->toBe([]);
});

it('não deixa o catálogo encolher em silêncio', function () {
    // Piso medido em 2026-07-30 (prod: 17 permissions `jana.mcp.*`).
    // Cair abaixo disso significa que alguém removeu scope do catálogo —
    // decisão que exige ADR, não commit distraído.
    expect(count(McpScopesSeeder::catalogo()))->toBeGreaterThanOrEqual(17);
});

it('marca todo scope MCP como default=false (aparecer na tela não é conceder)', function () {
    $slugsMcp = array_map(
        static fn (array $s): string => $s['slug'],
        McpScopesSeeder::catalogo()
    );

    $ligadosPorPadrao = array_values(array_filter(
        (new DataController())->user_permissions(),
        static fn (array $p): bool => in_array($p['value'], $slugsMcp, true) && $p['default'] !== false
    ));

    expect($ligadosPorPadrao)->toBe([]);
});

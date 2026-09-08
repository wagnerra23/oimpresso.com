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
 *
 * ── EMENDA 2026-09-07: a exigência deixou de ser "TODO scope" ────────────────
 *
 * A cláusula original era `catálogo ⊆ tela`, sem exceção. Ela colidia de frente
 * com o Tier 0: 5 dos 22 scopes são `admin_only` (`jana.mcp.usage.all`,
 * `jana.mcp.memory.manage`, `jana.mcp.projects.manage`, `jana.cc.read.all`,
 * `jana.cc.curate`), e exigi-los na tela de `/roles/{id}/edit` — que pede
 * `roles.update`, permission de admin de BUSINESS — os tornava auto-concedíveis.
 * Confirmado em produção: biz=164 tem os 5 numa role com 5 usuários.
 *
 * A cláusula foi estreitada para `catálogo − admin_only ⊆ tela`. Isso NÃO
 * afrouxa a defesa de 2026-07-29 — ela mudou de lugar e ficou mais forte:
 * `RoleController@__preservaNaoOfertadas` reúne ao POST tudo o que o papel já
 * tem e o form não oferece, então nem os `admin_only` (agora fora da tela) nem
 * qualquer permission de módulo desativado somem num save. Antes a proteção
 * dependia de o checkbox vir MARCADO no POST — desmarcar apagava; agora não
 * depende do POST.
 *
 * O outro lado (não-concessão) é contrato em `McpScopeAdminOnlyNaoAutoConcedivelTest`;
 * o comportamento com DB real, em `tests/Feature/Roles/RoleAdminOnlyScopeGuardTest.php`.
 */
it('expõe todo scope NÃO-admin_only como checkbox da tela de roles (senão o save apaga)', function () {
    $ofertaveis = array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter(
            McpScopesSeeder::catalogo(),
            static fn (array $s): bool => ($s['admin_only'] ?? false) !== true
        )
    ));

    $daTela = array_column((new DataController())->user_permissions(), 'value');

    // Controles de sanidade: sem eles o teste passaria por não-execução
    // (verde tautológico) se o catálogo ou a tela viessem vazios.
    expect($ofertaveis)->not->toBeEmpty();
    expect($daTela)->not->toBeEmpty();

    $invisiveis = array_values(array_diff($ofertaveis, $daTela));

    expect($invisiveis)->toBe([]);
});

it('e o recorte tirado da tela é EXATAMENTE o admin_only — nem um scope a mais', function () {
    // Sem este caso, o filtro do `mcpScopePermissions` poderia comer scopes
    // legítimos e o teste acima continuaria verde (ele só olha os ofertáveis).
    // Aqui a conta fecha nos dois sentidos: 22 = ofertados + admin_only.
    $catalogo = McpScopesSeeder::catalogo();

    $adminOnly = array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter($catalogo, static fn (array $s): bool => ($s['admin_only'] ?? false) === true)
    ));

    $slugs = array_map(static fn (array $s): string => $s['slug'], $catalogo);
    $daTela = array_column((new DataController())->user_permissions(), 'value');

    $ausentes = array_values(array_diff($slugs, $daTela));
    sort($ausentes);
    sort($adminOnly);

    expect($adminOnly)->not->toBeEmpty();
    expect($ausentes)->toBe($adminOnly);
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

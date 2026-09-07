<?php

declare(strict_types=1);

use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * ACHADO Tier 0 — scope `admin_only` e auto-concedivel pelo admin do business.
 *
 * O catalogo do `McpScopesSeeder` marca 5 scopes como `admin_only => true`,
 * um deles descrito no proprio catalogo como "Apenas Wagner/admin":
 * `jana.mcp.usage.all`. Essa permission e o UNICO gate de:
 *
 *   - `/governance/qualidade-ia` (QualidadeIaController, `can:` no construtor)
 *   - 8 telas do hub de engenharia da Forja (Forja, Scorecard, Team, Roadmap,
 *     TasksAdmin, Search, Trabalho, Aprovacoes — todas `can:jana.mcp.usage.all`)
 *
 * O vetor, medido controlador a controlador em `origin/main`:
 *
 *   1. `DataController@mcpScopePermissions` faz `array_map` sobre o catalogo
 *      INTEIRO, sem filtrar `admin_only` — todo scope vira checkbox em
 *      `/roles/{id}/edit`.
 *   2. Essa tela exige `roles.update`, permission normal de admin de business
 *      (Camada 3 do multi-tenant) — nao de superadmin.
 *   3. `RoleController@__somenteDoCatalogo` NAO barra: ele descarta apenas
 *      permission FORA do catalogo (`PermissionCatalog::intrusas`), e
 *      `jana.mcp.usage.all` esta DENTRO dele.
 *   4. `syncPermissions` concede.
 *   5. As rotas do grupo `governance` nao tem gate de modulo — a Camada 1
 *      (`hasThePermissionInSubscription`) so governa o item de sidebar, nao a
 *      URL. Acesso direto funciona.
 *
 * Por que o desenho atual e assim, e por que a correcao NAO e "some com o
 * checkbox": o guard irmao `McpScopesVisiveisNoRoleEditTest` existe por causa
 * do incidente 2026-07-29 — `syncPermissions` e destrutivo, entao scope que
 * nao aparece no form e apagado a cada save de qualquer role. Tirar os 5 da
 * tela sem tratar isso reintroduz aquele incidente. As duas defesas estao em
 * conflito real, e reconciliar as duas e decisao [W] (Tier 0 multi-tenant).
 *
 * Este teste NAO propoe a correcao — ele prova que o buraco existe, que e o
 * que a proposta #6950 declarou faltar ("hipotese forte, nao achado fechado:
 * falta o teste vermelho", §5 2026-07-15).
 *
 * Determinístico, sem DB — igual ao guard irmao.
 */
it('nao expoe scope admin_only como checkbox auto-concedivel pelo admin do business', function () {
    $adminOnly = array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter(
            McpScopesSeeder::catalogo(),
            static fn (array $s): bool => ($s['admin_only'] ?? false) === true
        )
    ));

    // Controle positivo: sem nenhum `admin_only` no catalogo este teste
    // passaria por NAO-EXECUCAO — verde tautologico (§5 2026-07-24 / LC-13).
    expect($adminOnly)->not->toBeEmpty();

    $daTela = array_column((new DataController())->user_permissions(), 'value');

    // Controle positivo do outro lado: a tela precisa estar devolvendo algo,
    // senao a intersecao seria vazia por ausencia de dado, nao por seguranca.
    expect($daTela)->not->toBeEmpty();

    $expostos = array_values(array_intersect($adminOnly, $daTela));

    expect($expostos)->toBe([]);
});

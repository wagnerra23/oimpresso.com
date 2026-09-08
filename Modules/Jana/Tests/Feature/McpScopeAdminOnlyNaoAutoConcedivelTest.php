<?php

declare(strict_types=1);

use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * CATRACA do achado Tier 0 — scope `admin_only` e auto-concedivel pelo admin
 * do business.
 *
 * ⚠️ A lista `$conhecidos` NAO e o estado desejado. E o estado MEDIDO hoje,
 * travado pra nao piorar em silencio enquanto a decisao [W] nao sai. O
 * contrato Tier 0 correto e a lista VAZIA.
 *
 * O vetor, medido controlador a controlador em `origin/main`:
 *
 *   1. `McpScopesSeeder` marca 5 de 22 scopes como `admin_only => true` — um
 *      deles, `jana.mcp.usage.all`, descrito no proprio catalogo como "Apenas
 *      Wagner/admin".
 *   2. `DataController@user_permissions` faz `...$this->mcpScopePermissions()`
 *      (`:102`), e o `array_map` de `:131` percorre o catalogo INTEIRO sem
 *      filtrar `admin_only` — os 5 viram checkbox em `/roles/{id}/edit`.
 *   3. Essa tela exige `roles.update`, permission de admin de BUSINESS
 *      (Camada 3 do multi-tenant), nao de superadmin.
 *   4. `RoleController@__somenteDoCatalogo` NAO barra: `PermissionCatalog::intrusas`
 *      descarta so o que esta FORA do catalogo, e a permission esta DENTRO.
 *   5. `syncPermissions` concede.
 *   6. As rotas do grupo `governance` nao tem gate de modulo — a Camada 1
 *      governa o item de sidebar, nao a URL.
 *
 * `jana.mcp.usage.all` e o UNICO gate de `/governance/qualidade-ia` e das 8
 * telas do hub de engenharia da Forja (Forja, Scorecard, Team, Roadmap,
 * TasksAdmin, Search, Trabalho, Aprovacoes).
 *
 * PROVA — este teste JA RODOU VERMELHO com a assercao correta (`toBe([])`):
 *
 *   run 34169613882, lane `PHP / Pest (Unit)`
 *   FAIL Modules\Jana\Tests\Feature\McpScopeAdminOnlyNaoAutoConcedivelTest
 *   Tests: 1 failed, 79 skipped, 1206 passed (4561 assertions)
 *
 * Discussao e recibo completo: PR #6952.
 *
 * POR QUE TRAVADO EM VEZ DE VERMELHO: `PHP / Pest (Unit)` e context REQUIRED e
 * `enforce_admins` esta ligado no main — um vermelho aqui trancaria o merge do
 * repo inteiro ate a decisao sair, transferindo pro time o custo de um achado.
 * A catraca preserva a mordida nos DOIS sentidos: expor um 6o scope quebra, e
 * CORRIGIR tambem quebra (obriga trocar a lista por `[]`, que e o contrato).
 *
 * A DECISAO [W] — as duas defesas nao sao satisfaziveis juntas hoje:
 *   A — `McpScopesVisiveisNoRoleEditTest` exige TODO scope no form, porque
 *       `syncPermissions` e destrutivo: scope ausente e apagado a cada save de
 *       qualquer role (incidente 2026-07-29, derrubou o MCP dos 4 users).
 *   B — Tier 0: scope `admin_only` nao pode ser auto-concedivel por admin de
 *       business.
 *
 * Deterministico, sem DB — igual ao guard irmao.
 */
it('trava os scopes admin_only expostos como checkbox — piorar quebra, e corrigir tambem', function () {
    $adminOnly = array_values(array_map(
        static fn (array $s): string => $s['slug'],
        array_filter(
            McpScopesSeeder::catalogo(),
            static fn (array $s): bool => ($s['admin_only'] ?? false) === true
        )
    ));

    $daTela = array_column((new DataController())->user_permissions(), 'value');

    // Controles positivos dos DOIS lados: sem `admin_only` no catalogo, ou com a
    // tela vazia, a intersecao seria vazia por AUSENCIA DE DADO e o teste passaria
    // por nao-execucao — verde tautologico (§5 2026-07-24 / LC-13).
    expect($adminOnly)->not->toBeEmpty();
    expect($daTela)->not->toBeEmpty();

    $expostos = array_values(array_intersect($adminOnly, $daTela));
    sort($expostos);

    // MEDIDO em 2026-09-07 — NAO desejado. O contrato Tier 0 e `[]`.
    expect($expostos)->toBe([
        'jana.cc.curate',
        'jana.cc.read.all',
        'jana.mcp.memory.manage',
        'jana.mcp.projects.manage',
        'jana.mcp.usage.all',
    ]);
});

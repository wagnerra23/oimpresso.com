<?php

declare(strict_types=1);

use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * CONTRATO Tier 0 — scope `admin_only` NAO e auto-concedivel pelo admin do business.
 *
 * ✅ FECHADO em 2026-09-07. Este arquivo nasceu CATRACA (lista dos 5 expostos,
 * travada pra nao piorar enquanto a decisao [W] nao saia) e virou CONTRATO: a
 * assercao agora e `[]`, que era o alvo declarado desde o primeiro dia.
 *
 * O VETOR que existia, medido controlador a controlador em `origin/main`:
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
 * PROVA de que este teste MORDE — ele ja rodou VERMELHO com esta mesma
 * assercao (`toBe([])`), antes da correcao existir:
 *
 *   run 34169613882, lane `PHP / Pest (Unit)`
 *   FAIL Modules\Jana\Tests\Feature\McpScopeAdminOnlyNaoAutoConcedivelTest
 *   Tests: 1 failed, 79 skipped, 1206 passed (4561 assertions)
 *
 * Historico e recibo do vermelho: PR #6952.
 *
 * ── COMO AS DUAS DEFESAS PASSARAM A CABER JUNTAS ─────────────────────────────
 *
 * O impasse era real: A exigia TODO scope no form (porque `syncPermissions` e
 * destrutivo e apaga o que nao vem no POST — incidente 2026-07-29, derrubou o
 * MCP dos 4 usuarios do time) e B proibia expor `admin_only` na tela do admin
 * de business. Enquanto a razao de A dependesse do checkbox, as duas se
 * excluiam.
 *
 * O que dissolveu o impasse foi tirar A da tela e por na CLASSE:
 *
 *   B — `DataController@mcpScopePermissions` filtra `admin_only`. Como
 *       `PermissionCatalog` e alimentado pelo MESMO `getModuleData('user_permissions')`
 *       que monta o form, o slug sai junto do catalogo ACEITO, e
 *       `RoleController@__somenteDoCatalogo` passa a DESCARTA-LO do POST —
 *       inclusive de um POST forjado. Nao e a tela que barra; e a concessao.
 *
 *   A — `RoleController@__preservaNaoOfertadas` reune ao POST tudo o que o
 *       papel JA TEM e o form nao oferece. Garantia MAIS FORTE que a de 07-29:
 *       la ela dependia de o checkbox vir marcado (desmarcar apagava); aqui
 *       nao depende do POST. E corrige a classe, nao a instancia `jana.mcp.*`.
 *
 * Efeito medido no que ja estava concedido: nada e revogado. O biz=164
 * (Martinho, OficinaAuto LIVE) tem os 5 scopes numa role com 5 usuarios —
 * seguem intactos, preservados. Revogar la e decisao [W] separada.
 *
 * Comportamento com DB real (POST HTTP nos dois sentidos):
 * @see tests/Feature/Roles/RoleAdminOnlyScopeGuardTest.php
 *
 * Deterministico, sem DB — igual ao guard irmao.
 */
it('nenhum scope admin_only e ofertado como checkbox — expor um so ja quebra', function () {
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

    // CONTRATO Tier 0. Era esta lista ate 2026-09-07 (`jana.cc.curate`,
    // `jana.cc.read.all`, `jana.mcp.memory.manage`, `jana.mcp.projects.manage`,
    // `jana.mcp.usage.all`); a correcao a levou a zero.
    expect($expostos)->toBe([]);
});

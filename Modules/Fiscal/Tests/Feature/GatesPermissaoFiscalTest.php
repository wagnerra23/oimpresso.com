<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Gate de permissão por sub-feature — as 4 rotas do cockpit Fiscal que NÃO tinham
 * teste HTTP do 403.
 *
 * ÂNCORA DE CONTRATO (não deriva do código — deriva da regra escrita):
 *   R-FISCAL-003 · memory/requisitos/Fiscal/SPEC.md §3 (Gherkin)
 *     "Given um usuário com permission fiscal.access mas NÃO fiscal.nfe.view
 *      When ele acessa GET /fiscal/nfe
 *      Then deve receber 403 Forbidden"
 *   CU-FISC-13 · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md §6.3
 *
 * POR QUE EXISTE: o desenho do módulo é gate POR SUB-FEATURE (6 permissões
 * `fiscal.*` distintas, provisionadas por `fiscal:habilitar-business`). Antes deste
 * arquivo, só `/fiscal` (CockpitControllerTest) e `/fiscal/nfse`
 * (NfseCockpitControllerTest) tinham prova HTTP do 403; as outras 4 rotas confiavam
 * na leitura do Controller. Promover `fiscal.access` a "vê tudo do fiscal" seria uma
 * regressão Tier 0 silenciosa — nenhum erro apareceria.
 *
 * CONTROLE ANTI-VÁCUO: cada caso tem um par positivo (superadmin) provando que a rota
 * EXISTE e responde. Sem ele, um 403 poderia vir de rota ausente, middleware ou
 * qualquer outra coisa — e o teste "passaria" medindo a coisa errada.
 * Limite honesto do controle: ele afirma "não é 403", não "é 200" — a asserção forte
 * do render já é feita pelos testes dedicados de cada tela.
 *
 * ONDE RODA (as 3 portas — nunca deduzir de uma só):
 *   · roda em algum lugar?  phpunit.xml lista ./Modules/Fiscal/Tests/Feature e o
 *     scripts/tests/shards-plan.mjs enumera o dir → suíte noturna CT 100 (MySQL real).
 *   · roda no PR?           .github/workflows/modules-pest.yml (matrix Fiscal, SQLite)
 *                           — mas SKIPa aqui, porque o schema NfeBrasil exige MySQL.
 *   · bloqueia merge?       NÃO. `Pest Fiscal` não está em
 *                           governance/required-checks-baseline.json. A lane required
 *                           (`PHP / Pest (NfeBrasil · MySQL)`) é catraca por prova
 *                           verde — o ratchet-up deste arquivo é proposta ao [W]
 *                           (SDD §8.3), depois do primeiro verde no CT 100.
 *
 * ADR 0093 (multi-tenant Tier 0) · ADR 0101 (biz=1, NUNCA biz=4) · ADR 0062 (CT 100).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeBrasil/Fiscal requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

/** Usuário biz=1 sem nenhuma permissão fiscal (ADR 0101 — nunca biz=4). */
function fiscalUserSemPermissao(): \App\User
{
    return \App\User::factory()->create(['business_id' => 1]);
}

/** Usuário biz=1 superadmin — controle positivo (a rota existe e responde). */
function fiscalSuperadmin(): \App\User
{
    $u = \App\User::factory()->create(['business_id' => 1]);
    $u->givePermissionTo('superadmin');

    return $u;
}

it('UC-FNFE-08 · GET /fiscal/nfe aborta 403 sem fiscal.nfe.view nem superadmin', function () {
    $this->actingAs(fiscalUserSemPermissao());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $this->get('/fiscal/nfe')->assertStatus(403);
});

it('UC-FNFE-08 · controle: superadmin NÃO recebe 403 em /fiscal/nfe (a rota existe)', function () {
    $this->actingAs(fiscalSuperadmin());
    session(['business.id' => 1, 'user.business_id' => 1]);

    expect($this->get('/fiscal/nfe')->status())->not->toBe(403);
});

it('UC-FDFE-05 · GET /fiscal/dfe aborta 403 sem fiscal.dfe.manage nem superadmin', function () {
    $this->actingAs(fiscalUserSemPermissao());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $this->get('/fiscal/dfe')->assertStatus(403);
});

it('UC-FDFE-05 · controle: superadmin NÃO recebe 403 em /fiscal/dfe (a rota existe)', function () {
    $this->actingAs(fiscalSuperadmin());
    session(['business.id' => 1, 'user.business_id' => 1]);

    expect($this->get('/fiscal/dfe')->status())->not->toBe(403);
});

it('UC-FEVT-04 · GET /fiscal/eventos aborta 403 sem fiscal.access nem superadmin', function () {
    $this->actingAs(fiscalUserSemPermissao());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $this->get('/fiscal/eventos')->assertStatus(403);
});

it('UC-FEVT-04 · controle: superadmin NÃO recebe 403 em /fiscal/eventos (a rota existe)', function () {
    $this->actingAs(fiscalSuperadmin());
    session(['business.id' => 1, 'user.business_id' => 1]);

    expect($this->get('/fiscal/eventos')->status())->not->toBe(403);
});

it('UC-FCFG-03 · GET /fiscal/config aborta 403 sem fiscal.config.edit nem superadmin', function () {
    $this->actingAs(fiscalUserSemPermissao());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $this->get('/fiscal/config')->assertStatus(403);
});

it('UC-FCFG-03 · controle: superadmin NÃO recebe 403 em /fiscal/config (a rota existe)', function () {
    $this->actingAs(fiscalSuperadmin());
    session(['business.id' => 1, 'user.business_id' => 1]);

    expect($this->get('/fiscal/config')->status())->not->toBe(403);
});

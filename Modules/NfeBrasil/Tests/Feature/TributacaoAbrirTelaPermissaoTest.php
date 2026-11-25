<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — abrir as telas de regra e de import também exige a permissão fiscal.
// Contrato das telas: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md — UC-NFRF-11
//                     resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md  — UC-NFIM-14
// Os casos derivam dos charters (§Backend: `nfe.tributacao.manage` para create|store|edit|update e
// para o import) e da decisão do dono (pendência 4 da Tributação, 2026-11-24) — não do código.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Antes: `TributacaoController@create`, `@edit` e `ImportRegrasController@show` renderizavam para
 * qualquer usuário do tenant; só o salvar/importar pedia `nfe.tributacao.manage`. Quem não tinha a
 * permissão abria o formulário, preenchia e levava 403 no fim.
 *
 * Cada caso tem os dois lados no mesmo tenant e na mesma URL: sem a permissão → 403; com ela →
 * 200 com o componente da tela. O usuário sem a permissão tem OUTRA permissão fiscal
 * (`nfebrasil.consult.view`), pra o 403 não vir de "usuário sem papel nenhum".
 *
 * MySQL-only · tenant fictício 98 via `seededTenant()` (ADR 0358). Usuários novos via
 * `usuarioComPermissoes`, pra não herdar `Admin#` e o `Gate::before`.
 */
function nfapBiz(): int
{
    return test()->seededTenant()->id;
}

function nfapEntrar(array $permissoes): void
{
    test()->actingAs(test()->usuarioComPermissoes($permissoes));
    test()->withSession(['business.id' => nfapBiz(), 'user.business_id' => nfapBiz()]);
}

function nfapLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->where('business_id', nfapBiz())->delete();
    }
    DB::table('nfe_fiscal_rules')->where('business_id', nfapBiz())->where('ncm', '49111090')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Precisa do schema MySQL do UltimatePOS + NfeBrasil.');
    }
    nfapLimpar();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'mysql' && Schema::hasTable('nfe_fiscal_rules')) {
        nfapLimpar();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-11 · Abrir a tela de regra (nova ou edição) exige a permissão fiscal
// ---------------------------------------------------------------------------------------
it('UC-NFRF-11 · abrir nova regra e edição pede nfe.tributacao.manage', function () {
    // A regra existe no tenant: o 403 da edição não é o 404 de regra inexistente/alheia.
    nfapEntrar(['nfe.tributacao.manage']);
    $this->post('/nfe-brasil/tributacao/regras', [
        'ncm'             => '49111090',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'cst'             => null,
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $id = (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', nfapBiz())->where('ncm', '49111090')->value('id');
    expect($id)->toBeGreaterThan(0);

    // Sem a permissão: as duas telas recusam.
    nfapEntrar(['nfebrasil.consult.view']);
    $this->get('/nfe-brasil/tributacao/regras/create')->assertForbidden();
    $this->get("/nfe-brasil/tributacao/regras/{$id}/edit")->assertForbidden();

    // CONTROLE POSITIVO — com a permissão, as mesmas URLs abrem o formulário.
    nfapEntrar(['nfe.tributacao.manage']);
    $this->get('/nfe-brasil/tributacao/regras/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NfeBrasil/Tributacao/RegraForm')->where('regra', null));
    $this->get("/nfe-brasil/tributacao/regras/{$id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NfeBrasil/Tributacao/RegraForm')->where('regra.id', $id));
});

// ---------------------------------------------------------------------------------------
// UC-NFIM-14 · Abrir a tela de import por CSV exige a permissão fiscal
// ---------------------------------------------------------------------------------------
it('UC-NFIM-14 · abrir o import por CSV pede nfe.tributacao.manage', function () {
    nfapEntrar(['nfebrasil.consult.view']);
    $this->get('/nfe-brasil/tributacao/import')->assertForbidden();

    // CONTROLE POSITIVO — com a permissão, a tela abre com as colunas obrigatórias.
    nfapEntrar(['nfe.tributacao.manage']);
    $this->get('/nfe-brasil/tributacao/import')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('NfeBrasil/Tributacao/ImportCsv')->has('colunas_obrigatorias'));
});

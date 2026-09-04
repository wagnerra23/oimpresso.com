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

/**
 * Usuário biz=1 com `fiscal.config.edit` mas SEM `fiscal.config.ambiente`.
 *
 * É o caso que dá sentido ao gate separado: quem pode editar a configuração
 * NÃO pode, por isso, trocar o ambiente de emissão. Um teste só com "usuário
 * sem permissão nenhuma" não distinguiria os dois gates.
 */
function fiscalUserComEditSemAmbiente(): \App\User
{
    \Spatie\Permission\Models\Permission::findOrCreate('fiscal.config.edit', 'web');
    $u = \App\User::factory()->create(['business_id' => 1]);
    $u->givePermissionTo('fiscal.config.edit');

    return $u;
}

it('UC-FCFG-06 · POST ambiente aborta 403 com fiscal.config.edit mas sem fiscal.config.ambiente', function () {
    $this->actingAs(fiscalUserComEditSemAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    // A recusa é do SERVIDOR. A tela travar o campo é conforto; sem este 403, um
    // POST direto (curl, devtools, extensão) trocaria o ambiente de emissão.
    $this->post('/nfe-brasil/configuracao/certificado/ambiente', ['ambiente' => 1])
        ->assertStatus(403);
});

it('UC-FCFG-06 · POST upload de certificado aborta 403 sem fiscal.config.ambiente', function () {
    $this->actingAs(fiscalUserComEditSemAmbiente());
    session(['business.id' => 1, 'user.business_id' => 1]);

    $this->post('/nfe-brasil/configuracao/certificado', [])
        ->assertStatus(403);
});

it('UC-FCFG-06 · controle: superadmin NÃO recebe 403 no POST ambiente (a rota existe e o gate passa)', function () {
    $this->actingAs(fiscalSuperadmin());
    session(['business.id' => 1, 'user.business_id' => 1]);

    // CONTROLE POSITIVO SEM MUTAÇÃO: posta o ambiente que JÁ está gravado. O
    // controller sai cedo ("já estava configurado nesse valor"), então isto prova
    // que a rota existe e o gate deixou passar — sem trocar o ambiente de ninguém.
    $atual = (int) (DB::table('business')->where('id', 1)->value('ambiente') ?? 2);

    expect($this->post('/nfe-brasil/configuracao/certificado/ambiente', ['ambiente' => $atual])->status())
        ->not->toBe(403);

    // E o ambiente segue intacto — o controle positivo não pode ter efeito colateral.
    expect((int) DB::table('business')->where('id', 1)->value('ambiente'))->toBe($atual);
});

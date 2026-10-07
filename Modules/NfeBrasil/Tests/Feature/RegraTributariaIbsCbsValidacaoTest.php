<?php

declare(strict_types=1);

// @covers-us US-FISCAL-021 — a regra tributária grava os 5 campos de IBS/CBS (NT 2025.002).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md — UC-NFRF-05 · 06 · 07 · 08
// Os casos derivam do contrato (US-FISCAL-021 + migration 2026_05_26_000001 + NT 2025.002 +
// MotorTributarioService::aplicarRegra, que já lê os 5) — não do FormRequest (§5 2026-06-05).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Playbook Fiscal thread 04 · a porta de gravação de IBS/CBS.
 *
 * Antes: `TributacaoController::store` grava `$request->validated()` e `::update` grava
 * `$regra->update($request->validated())`, mas o `UpsertRegraTributariaRequest` não declarava
 * nenhum dos 5 campos — então eles saíam do `validated()` e a regra ficava com IBS/CBS nulo.
 *
 * Toda leitura de valor é do BANCO, nunca da resposta: o defeito era exatamente o dado sumir
 * entre a resposta e a linha.
 *
 * MySQL-only · tenant fictício 98 via `seededTenant()` (ADR 0358) — nunca biz=1 (WR2, empresa
 * real: no CT 100 a base é clone de prod) nem biz=4 (ROTA LIVRE). O usuário é novo e tem só
 * `nfe.tributacao.manage` (`usuarioComPermissoes`), pra não herdar `Admin#` e o `Gate::before`.
 */

function ibsvBiz(): int
{
    return test()->seededTenant()->id;
}

/** Payload base válido (regime Simples → CSOSN), sem IBS/CBS. */
function ibsvPayload(array $overrides = []): array
{
    return array_merge([
        'ncm'             => '49019900',
        'uf_origem'       => 'SP',
        'uf_destino'      => null,
        'cfop'            => '5102',
        'csosn'           => '102',
        'cst'             => null,
        'aliquota_icms'   => 0.18,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
    ], $overrides);
}

/** Os 5 campos de IBS/CBS válidos (o exemplo do UC-NFRF-05). */
function ibsvCampos(): array
{
    return [
        'c_class_trib' => '000001',
        'cst_ibs'      => '000',
        'cst_cbs'      => '000',
        'aliquota_ibs' => 0.001,
        'aliquota_cbs' => 0.009,
    ];
}

function ibsvLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->where('business_id', ibsvBiz())->delete();
    }
    DB::table('nfe_fiscal_rules')->where('business_id', ibsvBiz())->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function ibsvLogar(): void
{
    $user = test()->usuarioComPermissoes(['nfe.tributacao.manage']);

    test()->actingAs($user);
    test()->withSession(['business.id' => ibsvBiz(), 'user.business_id' => ibsvBiz()]);
}

function ibsvContar(): int
{
    return (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', ibsvBiz())
        ->whereNull('deleted_at')
        ->count();
}

/** A linha como está no banco, com os 5 campos normalizados pra comparar. */
function ibsvLinha(string $ncm): array
{
    $r = DB::table('nfe_fiscal_rules')
        ->where('business_id', ibsvBiz())
        ->where('ncm', $ncm)
        ->whereNull('deleted_at')
        ->first();

    expect($r)->not->toBeNull();

    return [
        'c_class_trib' => $r->c_class_trib,
        'cst_ibs'      => $r->cst_ibs,
        'cst_cbs'      => $r->cst_cbs,
        'aliquota_ibs' => $r->aliquota_ibs !== null ? (float) $r->aliquota_ibs : null,
        'aliquota_cbs' => $r->aliquota_cbs !== null ? (float) $r->aliquota_cbs : null,
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: lê as colunas IBS/CBS reais da migration 2026_05_26_000001.');
    }
    if (! Schema::hasTable('nfe_fiscal_rules') || ! Schema::hasColumn('nfe_fiscal_rules', 'c_class_trib')) {
        $this->markTestSkipped('nfe_fiscal_rules sem as colunas IBS/CBS — rode as migrations do módulo.');
    }

    ibsvLimpar();

    Event::fake([
        \Modules\NfeBrasil\Events\FiscalRuleCreated::class,
        \Modules\NfeBrasil\Events\FiscalRuleUpdated::class,
        \Modules\NfeBrasil\Events\FiscalRuleDeleted::class,
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_fiscal_rules')) {
        return;
    }
    ibsvLimpar();
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-05 · A regra grava e relê os 5 campos de IBS/CBS  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-05 · regra grava e relê c_class_trib, cst_ibs, cst_cbs e alíquotas IBS/CBS', function () {
    ibsvLogar();

    $this->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '49019900'] + ibsvCampos()))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ibsvLinha('49019900'))->toBe(ibsvCampos());

    // CONTROLE POSITIVO — regra SEM IBS/CBS (Simples/legado) continua sendo criada: códigos nulos e
    // alíquotas no default 0 da migration 2026_05_26_000001 (`->default(0)`).
    $this->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '61091000']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ibsvLinha('61091000'))->toBe([
        'c_class_trib' => null,
        'cst_ibs'      => null,
        'cst_cbs'      => null,
        'aliquota_ibs' => 0.0,
        'aliquota_cbs' => 0.0,
    ]);
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-07 · Editar a regra também grava IBS/CBS (não só criar)  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-07 · update grava e preserva IBS/CBS', function () {
    ibsvLogar();

    $id = (int) DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id'     => ibsvBiz(),
        'ncm'             => '22021000',
        'uf_origem'       => 'SP',
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => 0.12,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $this->put("/nfe-brasil/tributacao/regras/{$id}", ibsvPayload(['ncm' => '22021000'] + ibsvCampos()))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ibsvLinha('22021000'))->toBe(ibsvCampos());

    // CONTROLE POSITIVO — editar só o ICMS (o formulário não manda os 5) não zera o que já foi gravado.
    $this->put("/nfe-brasil/tributacao/regras/{$id}", ibsvPayload(['ncm' => '22021000', 'aliquota_icms' => 0.25]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect((float) DB::table('nfe_fiscal_rules')->where('id', $id)->value('aliquota_icms'))->toBe(0.25);
    expect(ibsvLinha('22021000'))->toBe(ibsvCampos());
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-06 · Formato de IBS/CBS inválido é recusado sem gravar  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-06 · formato inválido de IBS/CBS é recusado e não grava', function () {
    ibsvLogar();

    $invalidos = [
        'c_class_trib' => ['c_class_trib' => '00001'],                 // 5 dígitos
        'aliquota_cbs' => ['aliquota_cbs' => 9],                       // "9%" digitado como inteiro
        'cst_ibs'      => ['cst_ibs' => null, 'cst_cbs' => '000'],     // cClassTrib sem CST do IBS
    ];

    foreach ($invalidos as $campo => $erro) {
        $antes = ibsvContar();

        $this->from('/nfe-brasil/tributacao/regras/create')
            ->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '84439100'] + array_merge(ibsvCampos(), $erro)))
            ->assertSessionHasErrors($campo);

        expect(ibsvContar())->toBe($antes);
    }

    // CONTROLE POSITIVO — o mesmo payload corrigido grava. Sem isto o "não gravou" acima
    // poderia vir de outro campo do payload, não da validação de IBS/CBS.
    $this->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '84439100'] + ibsvCampos()))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ibsvLinha('84439100'))->toBe(ibsvCampos());
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-08 · Os campos de IBS/CBS aparecem e salvam pela tela  [fiscal]  (thread 05)
// Lado do servidor: reabrir a edição traz os 5 valores do banco na prop `regra`. Sem isso a tela
// abriria a seção "Reforma tributária" vazia e o "Atualizar" gravaria nulo por cima. O lado da
// tela (o form envia os 5) é o e2e `e2e/nfe-tributacao-regra.spec.ts`.
// ---------------------------------------------------------------------------------------
it('UC-NFRF-08 · a edição reabre com os 5 campos de IBS/CBS gravados', function () {
    ibsvLogar();

    $this->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '39219019'] + ibsvCampos()))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $id = (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', ibsvBiz())->where('ncm', '39219019')->value('id');

    $props = null;
    $this->get("/nfe-brasil/tributacao/regras/{$id}/edit")
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props']['regra'] ?? [];
        });

    $reabertos = array_intersect_key($props, ibsvCampos());
    ksort($reabertos);
    $esperado = ibsvLinha('39219019');
    ksort($esperado);

    expect($reabertos)->toBe($esperado);
    expect($reabertos['c_class_trib'] ?? null)->toBe('000001');

    // CONTROLE POSITIVO — uma regra sem IBS/CBS reabre com códigos nulos e alíquotas 0: a prop
    // lê o banco, não devolve um valor fixo.
    $this->post('/nfe-brasil/tributacao/regras', ibsvPayload(['ncm' => '48211000']))
        ->assertSessionHasNoErrors();

    $outro = (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', ibsvBiz())->where('ncm', '48211000')->value('id');

    $this->get("/nfe-brasil/tributacao/regras/{$outro}/edit")
        ->assertOk()
        ->assertInertia(function ($page) {
            $r = $page->toArray()['props']['regra'] ?? [];
            expect(array_key_exists('c_class_trib', $r))->toBeTrue();
            expect($r['c_class_trib'])->toBeNull();
            expect($r['aliquota_ibs'])->toBe(0.0);
            expect($r['aliquota_cbs'])->toBe(0.0);
        });
});

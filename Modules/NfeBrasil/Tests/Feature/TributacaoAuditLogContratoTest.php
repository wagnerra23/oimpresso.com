<?php

declare(strict_types=1);

// @covers-us US-NFE-062 — as mutações da tributação gravam quem fez em `activity_log`.
// Contrato: SPEC NfeBrasil US-NFE-062 (aceite: "Toda mensagem inclui `business_id` em properties" e
// "Pest test valida que `activity_log` recebe entry após mutation") + Index.charter.md §Goals
// (AuditLog em `toggleAutoEmission`/`store`/`update`/`destroy`, com o log-name de cada um) +
// RegraForm.charter.md ("`store`/`update` gravam `activity('nfe.tributacao')` com business_id + NCM/UF").
// Casos: Tributacao/Index.casos.md UC-NFTR-83 · Tributacao/RegraForm.casos.md UC-NFRF-13.
// Irmão do TributacaoTemplateAplicarTest (UC-NFTR-17), que prova o mesmo para o aplicar template.

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeFiscalRule;

uses(Tests\TestCase::class);

/**
 * Cada caso mede as linhas de `activity_log` com id MAIOR que o último antes da ação — assim o
 * resultado não depende de limpeza nem de log deixado por outro teste. E todo caso que afirma uma
 * AUSÊNCIA de log tem antes a prova de que a mesma rota grava quando deve (controle positivo).
 *
 * MySQL-only · tenant fictício 98 via `seededTenant()` (ADR 0358); o vizinho é o 99
 * (`seededSupportClientTenant()`). NUNCA biz=4. Usuário novo via `usuarioComPermissoes`, sem `Admin#`.
 */
const NFAL_NCM = '48211000';
const NFAL_TOGGLE_URL = '/nfe-brasil/tributacao/auto-emission/toggle';

function nfalBiz(): int
{
    return test()->seededTenant()->id;
}

function nfalVizinho(): int
{
    return test()->seededSupportClientTenant()->id;
}

function nfalUltimoLog(): int
{
    return (int) DB::table('activity_log')->max('id');
}

/** Logs `nfe.tributacao` com a descrição dada, gravados depois de `$desde`. */
function nfalLogs(int $desde, string $descricao): array
{
    return DB::table('activity_log')
        ->where('id', '>', $desde)
        ->where('log_name', 'nfe.tributacao')
        ->where('description', $descricao)
        ->orderBy('id')
        ->get()
        ->all();
}

function nfalPayload(array $sobre = []): array
{
    return array_merge([
        'ncm'             => NFAL_NCM,
        'uf_origem'       => 'SP',
        'uf_destino'      => 'RJ',
        'cfop'            => '6102',
        'csosn'           => '102',
        'cst'             => null,
        'aliquota_icms'   => 0.12,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
    ], $sobre);
}

function nfalLimparRegras(): void
{
    $bizs = [nfalBiz(), nfalVizinho()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
        DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('business_id', $bizs)
            ->whereIn('fiscal_rule_id',DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)
                ->where('ncm', NFAL_NCM)->pluck('id'))
            ->delete();
    }
    DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->where('ncm', NFAL_NCM)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function nfalEntrar(): User
{
    $user = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    test()->actingAs($user);
    test()->withSession(['business.id' => nfalBiz(), 'user.business_id' => nfalBiz()]);

    return $user;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-only: tenant real + activity_log (ADR 0358; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_fiscal_rules') || ! Schema::hasTable('nfe_business_configs')
        || ! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Tabelas do NfeBrasil/activity_log ausentes.');
    }
    nfalLimparRegras();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'mysql' && Schema::hasTable('nfe_fiscal_rules')) {
        nfalLimparRegras();
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-13 · Criar, editar e apagar regra gravam quem fez, com o business_id  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-13 · criar, editar e apagar regra gravam activity com autor e business_id', function () {
    $user = nfalEntrar();
    $biz = nfalBiz();

    // (1) store → `regra.created`, com NCM e UFs nas properties (não há `performedOn`).
    $antes = nfalUltimoLog();
    $this->post('/nfe-brasil/tributacao/regras', nfalPayload())
        ->assertSessionHasNoErrors()->assertRedirect();

    $id = (int) DB::table('nfe_fiscal_rules')->where('business_id', $biz)->where('ncm', NFAL_NCM)->value('id');
    expect($id)->toBeGreaterThan(0);  // anti-vácuo: a regra de fato nasceu

    $criados = nfalLogs($antes, 'regra.created');
    expect($criados)->toHaveCount(1)
        ->and((int) $criados[0]->causer_id)->toBe($user->id)
        ->and(json_decode($criados[0]->properties, true))->toMatchArray([
            'business_id' => $biz, 'ncm' => NFAL_NCM, 'uf_origem' => 'SP', 'uf_destino' => 'RJ',
        ]);

    // (2) update → `regra.updated`, sobre a regra (ou a versão nova dela — R-NFE-019).
    $antes = nfalUltimoLog();
    $this->put("/nfe-brasil/tributacao/regras/{$id}", nfalPayload(['cfop' => '6101']))
        ->assertSessionHasNoErrors()->assertRedirect();

    $editados = nfalLogs($antes, 'regra.updated');
    $daRegra = DB::table('nfe_fiscal_rules')
        ->where(fn ($q) => NfeFiscalRule::temVersionamento()
            ? $q->where('id', $id)->orWhere('versao_origem_id', $id)
            : $q->where('id', $id))
        ->pluck('id')->map(fn ($v) => (int) $v)->all();
    expect($editados)->toHaveCount(1)
        ->and((int) $editados[0]->causer_id)->toBe($user->id)
        ->and($editados[0]->subject_type)->toBe((new NfeFiscalRule())->getMorphClass())
        ->and($daRegra)->toContain((int) $editados[0]->subject_id)
        ->and(json_decode($editados[0]->properties, true))->toMatchArray(['business_id' => $biz]);

    // (3) destroy → `regra.deleted`, sobre a regra vigente.
    $vigente = (int) DB::table('nfe_fiscal_rules')->whereIn('id', $daRegra)
        ->whereNull('deleted_at')->orderByDesc('id')->value('id');
    $antes = nfalUltimoLog();
    $this->delete("/nfe-brasil/tributacao/regras/{$vigente}")->assertRedirect();

    expect(DB::table('nfe_fiscal_rules')->where('id', $vigente)->value('deleted_at'))->not->toBeNull();
    $apagados = nfalLogs($antes, 'regra.deleted');
    expect($apagados)->toHaveCount(1)
        ->and((int) $apagados[0]->causer_id)->toBe($user->id)
        ->and((int) $apagados[0]->subject_id)->toBe($vigente)
        ->and(json_decode($apagados[0]->properties, true))->toMatchArray(['business_id' => $biz]);
});

it('UC-NFRF-13 · apagar regra de outro business é 404 e não grava log', function () {
    nfalEntrar();

    $alheia = (int) DB::table('nfe_fiscal_rules')->insertGetId(array_merge(nfalPayload(), [
        'business_id' => nfalVizinho(), 'created_at' => now(), 'updated_at' => now(),
    ]));

    $antes = nfalUltimoLog();
    $this->delete("/nfe-brasil/tributacao/regras/{$alheia}")->assertNotFound();

    expect(DB::table('nfe_fiscal_rules')->where('id', $alheia)->value('deleted_at'))->toBeNull();
    expect(nfalLogs($antes, 'regra.deleted'))->toBeEmpty();
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-83 · Ligar/desligar a emissão automática grava quem fez  [T0] [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-83 · o toggle de emissão automática grava activity com autor, business_id e o valor', function () {
    $user = nfalEntrar();
    $biz = nfalBiz();

    $existia = DB::table('nfe_business_configs')->where('business_id', $biz)->first();
    if ($existia === null) {
        DB::table('nfe_business_configs')->insert([
            'business_id' => $biz, 'regime' => 'simples', 'auto_emission_enabled' => false,
            'tributacao_default' => json_encode(['cfop' => '5102', 'csosn' => '102', 'ncm_default' => '49111090']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    try {
        $antes = nfalUltimoLog();
        $this->post(NFAL_TOGGLE_URL, ['enabled' => true])->assertRedirect()->assertSessionHas('success');
        $this->post(NFAL_TOGGLE_URL, ['enabled' => false])->assertRedirect()->assertSessionHas('success');

        $logs = nfalLogs($antes, 'auto_emission.toggled');
        $configId = (int) DB::table('nfe_business_configs')->where('business_id', $biz)->value('id');
        expect($logs)->toHaveCount(2)
            ->and((int) $logs[0]->causer_id)->toBe($user->id)
            ->and((int) $logs[0]->subject_id)->toBe($configId)
            ->and(json_decode($logs[0]->properties, true))->toMatchArray(['business_id' => $biz, 'enabled' => true])
            ->and(json_decode($logs[1]->properties, true))->toMatchArray(['business_id' => $biz, 'enabled' => false]);
    } finally {
        if ($existia === null) {
            DB::table('nfe_business_configs')->where('business_id', $biz)->delete();
        } else {
            DB::table('nfe_business_configs')->where('id', $existia->id)
                ->update(['auto_emission_enabled' => $existia->auto_emission_enabled]);
        }
    }
});

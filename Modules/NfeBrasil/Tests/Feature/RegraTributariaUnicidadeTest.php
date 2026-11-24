<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — a chave da regra (NCM + UF origem + UF destino) não se repete.
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md — UC-NFRF-10
// Âncora: RegraForm.charter.md §Pendências ("Confirmar validação de unicidade da chave no
// FormRequest") + MotorTributarioService::buscarRegra, que com duas regras na mesma chave
// pega a primeira pelo id. O caso deriva do contrato do motor, não do FormRequest (§5 2026-06-05).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * MySQL-only · tenant fictício 98 via `seededTenant()` (ADR 0358) e o 99 como a outra empresa.
 * Nunca biz=1 nem biz=4. O usuário é novo e tem só `nfe.tributacao.manage`.
 */
function unqBiz(): int
{
    return test()->seededTenant()->id;
}

function unqBizOutro(): int
{
    return test()->seededSupportClientTenant()->id;
}

function unqPayload(array $overrides = []): array
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

/** Regra gravada direto no banco, como a que o CSV ou uma tela antiga teria deixado. */
function unqRegra(int $businessId, string $ncm, ?string $ufDestino = null, float $icms = 0.12): int
{
    return (int) DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id'     => $businessId,
        'ncm'             => $ncm,
        'uf_origem'       => 'SP',
        'uf_destino'      => $ufDestino,
        'cfop'            => '5102',
        'csosn'           => '102',
        'aliquota_icms'   => $icms,
        'aliquota_pis'    => 0.0065,
        'aliquota_cofins' => 0.03,
        'aliquota_ipi'    => 0,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

function unqLimpar(): void
{
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ([unqBiz(), unqBizOutro()] as $biz) {
        if (Schema::hasTable('nfe_fiscal_rule_tax_rate_links')) {
            DB::table('nfe_fiscal_rule_tax_rate_links')->where('business_id', $biz)->delete();
        }
        DB::table('nfe_fiscal_rules')->where('business_id', $biz)->delete();
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function unqLogar(): void
{
    test()->actingAs(test()->usuarioComPermissoes(['nfe.tributacao.manage']));
    test()->withSession(['business.id' => unqBiz(), 'user.business_id' => unqBiz()]);
}

/** Regras vigentes do tenant com aquela chave (o que o motor enxerga). */
function unqVigentes(string $ncm, ?string $ufDestino = null): int
{
    return (int) DB::table('nfe_fiscal_rules')
        ->where('business_id', unqBiz())
        ->where('ncm', $ncm)
        ->where('uf_origem', 'SP')
        ->when($ufDestino === null, fn ($q) => $q->whereNull('uf_destino'), fn ($q) => $q->where('uf_destino', $ufDestino))
        ->whereNull('deleted_at')
        ->when(Schema::hasColumn('nfe_fiscal_rules', 'valida_ate'), fn ($q) => $q->whereNull('valida_ate'))
        ->count();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: a chave da regra é lida do schema real de nfe_fiscal_rules.');
    }
    if (! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Tabela nfe_fiscal_rules ausente — rode as migrations do módulo.');
    }

    unqLimpar();

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
    unqLimpar();
});

// ---------------------------------------------------------------------------------------
// UC-NFRF-10 · Duas regras com a mesma chave não convivem  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFRF-10 · criar regra com a chave de outra já cadastrada é recusado sem gravar', function () {
    unqLogar();
    unqRegra(unqBiz(), '49019900');

    $this->post('/nfe-brasil/tributacao/regras', unqPayload(['cfop' => '5101', 'aliquota_icms' => 0.07]))
        ->assertSessionHasErrors('ncm');

    expect(unqVigentes('49019900'))->toBe(1);
    expect((float) DB::table('nfe_fiscal_rules')->where('business_id', unqBiz())->where('ncm', '49019900')->value('aliquota_icms'))
        ->toBe(0.12);

    // CONTROLE POSITIVO — a mesma regra para uma UF de destino específica é outra chave
    // (Nível 2 do cascade, ao lado do Nível 3 "todas as UFs") e grava.
    $this->post('/nfe-brasil/tributacao/regras', unqPayload(['uf_destino' => 'RJ', 'cfop' => '6102']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect(unqVigentes('49019900', 'RJ'))->toBe(1);

    // E a segunda para RJ já é duplicata.
    $this->post('/nfe-brasil/tributacao/regras', unqPayload(['uf_destino' => 'RJ', 'cfop' => '6102']))
        ->assertSessionHasErrors('ncm');
    expect(unqVigentes('49019900', 'RJ'))->toBe(1);
});

it('UC-NFRF-10 · editar uma regra para a chave de outra é recusado; manter a própria chave passa', function () {
    unqLogar();
    unqRegra(unqBiz(), '49019900');
    $outra = unqRegra(unqBiz(), '22021000');

    $this->put("/nfe-brasil/tributacao/regras/{$outra}", unqPayload(['ncm' => '49019900']))
        ->assertSessionHasErrors('ncm');

    expect(unqVigentes('49019900'))->toBe(1);
    expect(unqVigentes('22021000'))->toBe(1);

    // CONTROLE POSITIVO — editar a regra sem mudar a chave não conta ela mesma como duplicata.
    $this->put("/nfe-brasil/tributacao/regras/{$outra}", unqPayload(['ncm' => '22021000', 'aliquota_icms' => 0.25]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect(unqVigentes('22021000'))->toBe(1);
});

it('UC-NFRF-10 · regra apagada ou de outra empresa não bloqueia a chave', function () {
    unqLogar();

    $apagada = unqRegra(unqBiz(), '49019900');
    DB::table('nfe_fiscal_rules')->where('id', $apagada)->update(['deleted_at' => now()]);
    unqRegra(unqBizOutro(), '61091000');

    $this->post('/nfe-brasil/tributacao/regras', unqPayload(['ncm' => '49019900']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    $this->post('/nfe-brasil/tributacao/regras', unqPayload(['ncm' => '61091000']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(unqVigentes('49019900'))->toBe(1);
    expect(unqVigentes('61091000'))->toBe(1);
});

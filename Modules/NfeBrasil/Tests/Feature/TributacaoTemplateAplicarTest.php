<?php

declare(strict_types=1);

// @covers-us US-NFE-TPL-001 + US-NFE-062 — aplicar template exige NCM padrão e registra quem
// aplicou (playbook Fiscal thread 21 · UC-NFTR-17). Contrato: auditoria 2026-05 bug #3
// (template levava o `ncm_default` junto → empresa "configurada" que não emitia) +
// Index.charter.md §AuditLog (o aplicar era o único caminho sem `activity()`) + UC-NFTR-03.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\NfeBrasil\Services\Tributacao\TributacaoTemplateService;

uses(Tests\TestCase::class);

const TAT_TEMPLATE = 'industria-grafica-simples-sp';

function tatLimpar(int $biz): void
{
    DB::table('nfe_fiscal_rules')->where('business_id', $biz)->delete();
    DB::table('nfe_business_configs')->where('business_id', $biz)->delete();
    DB::table('activity_log')->where('log_name', 'nfe.tributacao')
        ->where('description', 'template.aplicado')
        ->where('properties', 'like', '%"business_id":'.$biz.'%')
        ->delete();
}

function tatAtividades(int $biz): int
{
    return DB::table('activity_log')->where('log_name', 'nfe.tributacao')
        ->where('description', 'template.aplicado')
        ->where('properties', 'like', '%"business_id":'.$biz.'%')
        ->count();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tenant real + activity_log (ADR 0358; ver nfebrasil-pest.yml).');
    }
    if (! Schema::hasTable('nfe_business_configs') || ! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Tabelas do NfeBrasil/activity_log ausentes.');
    }

    // biz=99: empresa FICTÍCIA (helper cria se faltar). Sem NCM padrão no cadastro — o caso
    // precisa da empresa que de fato não tem NCM.
    $this->biz = $this->seededSupportClientTenant()->id;
    DB::table('business')->where('id', $this->biz)->update(['ncm_padrao' => '']);
    tatLimpar($this->biz);
    $this->user = $this->usuarioComPermissoes(['nfe.tributacao.manage'], \App\Business::find($this->biz));
});

afterEach(function () {
    if (isset($this->biz)) {
        tatLimpar($this->biz);
    }
});

it('UC-NFTR-17 · aplicar exige NCM padrão e loga', function () {
    $regra = DB::table('nfe_fiscal_rules')->insertGetId([
        'business_id' => $this->biz, 'ncm' => '49019900', 'uf_origem' => 'SP', 'uf_destino' => null,
        'cfop' => '5102', 'csosn' => '102', 'aliquota_icms' => 0.18, 'aliquota_pis' => 0,
        'aliquota_cofins' => 0, 'aliquota_ipi' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $regraAntes = DB::table('nfe_fiscal_rules')->where('id', $regra)->first();

    // (1) Sem NCM em lugar nenhum → 422 pela rota, e a config NÃO nasce.
    $this->actingAs($this->user)
        ->postJson('/nfe-brasil/tributacao/templates/'.TAT_TEMPLATE.'/aplicar')
        ->assertStatus(422)
        ->assertJsonValidationErrors('ncm_default');
    expect(DB::table('nfe_business_configs')->where('business_id', $this->biz)->count())->toBe(0);

    // (2) `00000000` (o placeholder do ConfigDefault) não é NCM — e não cai pro fallback.
    $svc = app(TributacaoTemplateService::class);
    expect(fn () => $svc->aplicar($this->biz, TAT_TEMPLATE, '00000000'))->toThrow(ValidationException::class);
    expect(DB::table('nfe_business_configs')->where('business_id', $this->biz)->count())->toBe(0);
    expect(tatAtividades($this->biz))->toBe(0);

    // (3) NCM válido → aplica, grava o NCM no default, preserva a regra NCM e loga o autor.
    $r = $svc->aplicar($this->biz, TAT_TEMPLATE, '49111090');
    expect($r['criou'])->toBeTrue()
        ->and($r['config']->tributacao_default['ncm_default'])->toBe('49111090')
        ->and($r['config']->tributacao_default['cfop'])->toBe('5101');
    expect(DB::table('nfe_fiscal_rules')->where('id', $regra)->first())->toEqual($regraAntes);

    $log = DB::table('activity_log')->where('log_name', 'nfe.tributacao')
        ->where('description', 'template.aplicado')->orderByDesc('id')->first();
    expect($log)->not->toBeNull()
        ->and((int) $log->causer_id)->toBe($this->user->id)
        ->and(json_decode($log->properties, true))->toMatchArray([
            'business_id' => $this->biz, 'slug' => TAT_TEMPLATE, 'ncm_default' => '49111090',
        ]);

    // (4) Controle positivo: re-aplicar o mesmo template segue idempotente — e o NCM que já
    //     está na config vale como padrão (o POST da tela de hoje não manda NCM).
    $this->actingAs($this->user)
        ->post('/nfe-brasil/tributacao/templates/'.TAT_TEMPLATE.'/aplicar')
        ->assertRedirect()
        ->assertSessionHas('success', 'Template já estava aplicado — nada a fazer.');
    expect(tatAtividades($this->biz))->toBe(1)
        ->and(DB::table('nfe_business_configs')->where('business_id', $this->biz)->count())->toBe(1);

    // (5) Trocar de template NÃO apaga o NCM (o bug #3).
    $outro = $svc->aplicar($this->biz, 'industria-grafica-presumido-sp');
    expect($outro['mudou'])->toBeTrue()
        ->and($outro['config']->tributacao_default['ncm_default'])->toBe('49111090');
    expect(tatAtividades($this->biz))->toBe(2);
});

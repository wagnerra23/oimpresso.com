<?php

declare(strict_types=1);

// @covers-us R-NFSE-001 — PIS/COFINS/CSLL 4,65% de tomador PJ, prestador no regime normal; dispensa até dez reais (Lei 10.833/2003 arts. 30–31).
// @covers-us R-NFSE-002 — prestador no Simples não retém (Lei 10.833/2003 art. 32, III), com aviso pro contador.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\RetencoesNfseService;

uses(Tests\TestCase::class);

/**
 * Playbook Fiscal thread 23 · UC-TRB-20. Números do aceite = bateria C21/C22
 * do protótipo. Tenant 98 (ADR 0358) · 99 como adversário cross-tenant.
 * Valores em centavos.
 */
const NFSR_BIZ = 98;
const NFSR_BIZ_OUTRO = 99;

beforeEach(function () {
    $this->svc = new RetencoesNfseService();
});

it('R-NFSE-001 serviço de 1.500 reais pra PJ, prestador normal → retém 69,75 reais (bateria C21)', function () {
    $r = $this->svc->calcular(150000, true, 'lucro_presumido');

    expect($r['retem'])->toBeTrue();
    expect($r['pis'])->toBe(975);
    expect($r['cofins'])->toBe(4500);
    expect($r['csll'])->toBe(1500);
    expect($r['total'])->toBe(6975);
    expect($r['aviso'])->toBeNull();
});

it('R-NFSE-001 serviço de 150 reais → 4,65% = 6,98 reais ≤ 10 reais → dispensado, com aviso', function () {
    $r = $this->svc->calcular(15000, true, 'lucro_real');

    expect($r['total'])->toBe(698);
    expect($r['dispensada'])->toBeTrue();
    expect($r['retem'])->toBeFalse();
    expect($r['pis'] + $r['cofins'] + $r['csll'])->toBe(0);
    expect($r['aviso'])->toBe(RetencoesNfseService::AVISO_DISPENSA);
});

it('R-NFSE-001 fronteira do § 3º: 10 reais exatos dispensa; 1 centavo acima retém', function () {
    // 21.506 c × 4,65% = 1000,03 → arredonda 1000 (PIS 140 + COFINS 645 + CSLL 215) → "igual a" 10 reais dispensa.
    $igual = $this->svc->calcular(21506, true, 'lucro_presumido');
    expect($igual['total'])->toBe(1000);
    expect($igual['dispensada'])->toBeTrue();

    $acima = $this->svc->calcular(21530, true, 'lucro_presumido');
    expect($acima['total'])->toBe(1001);
    expect($acima['retem'])->toBeTrue();
});

it('R-NFSE-001 controle positivo: tomador PF não retém; serviço fora do art. 30 não retém', function () {
    $pf = $this->svc->calcular(150000, false, 'lucro_presumido');
    expect($pf['retem'])->toBeFalse();
    expect($pf['total'])->toBe(0);
    expect($pf['aviso'])->toBeNull();

    $fora = $this->svc->calcular(150000, true, 'lucro_presumido', servicoSujeito: false);
    expect($fora['retem'])->toBeFalse();
    expect($fora['total'])->toBe(0);
});

it('R-NFSE-002 prestador optante pelo Simples → nenhuma retenção e aviso "confirmar com o contador" (bateria C22)', function () {
    foreach (['simples', 'mei'] as $regime) {
        $r = $this->svc->calcular(150000, true, $regime);

        expect($r['retem'])->toBeFalse();
        expect($r['pis'] + $r['cofins'] + $r['csll'] + $r['total'])->toBe(0);
        expect($r['aviso'])->toBe(RetencoesNfseService::AVISO_SIMPLES);
    }
    expect(RetencoesNfseService::AVISO_SIMPLES)->toContain('contador');
});

it('R-NFSE-002 controle positivo: o mesmo serviço com regime normal faz a retenção aparecer', function () {
    expect($this->svc->calcular(150000, true, 'simples')['retem'])->toBeFalse();
    expect($this->svc->calcular(150000, true, 'lucro_real')['total'])->toBe(6975);
});

it('R-NFSE-002 regime ausente não chuta: sem retenção e aviso de configuração', function () {
    $r = $this->svc->calcular(150000, true, null);

    expect($r['retem'])->toBeFalse();
    expect($r['aviso'])->toBe(RetencoesNfseService::AVISO_SEM_REGIME);
});

describe('Tier 0 — o regime vem da config do PRÓPRIO tenant (98 × 99)', function () {
    beforeEach(function () {
        if (! Schema::hasTable('nfe_business_configs')) {
            $this->markTestSkipped('nfe_business_configs ausente — rode as migrations do NfeBrasil.');
        }
        DB::table('nfe_business_configs')->whereIn('business_id', [NFSR_BIZ, NFSR_BIZ_OUTRO])->delete();
        $agora = now();
        DB::table('nfe_business_configs')->insert([
            ['business_id' => NFSR_BIZ, 'regime' => 'simples', 'tributacao_default' => '{}', 'created_at' => $agora, 'updated_at' => $agora],
            ['business_id' => NFSR_BIZ_OUTRO, 'regime' => 'lucro_presumido', 'tributacao_default' => '{}', 'created_at' => $agora, 'updated_at' => $agora],
        ]);
    });

    afterEach(function () {
        if (Schema::hasTable('nfe_business_configs')) {
            DB::table('nfe_business_configs')->whereIn('business_id', [NFSR_BIZ, NFSR_BIZ_OUTRO])->delete();
        }
    });

    it('R-NFSE-002 biz 98 (Simples) não herda o regime normal do biz 99, e vice-versa', function () {
        $r98 = $this->svc->calcularParaBusiness(NFSR_BIZ, 150000, true);
        $r99 = $this->svc->calcularParaBusiness(NFSR_BIZ_OUTRO, 150000, true);

        expect($r98['retem'])->toBeFalse();
        expect($r98['aviso'])->toBe(RetencoesNfseService::AVISO_SIMPLES);

        expect($r99['retem'])->toBeTrue();
        expect($r99['total'])->toBe(6975);
    });
});

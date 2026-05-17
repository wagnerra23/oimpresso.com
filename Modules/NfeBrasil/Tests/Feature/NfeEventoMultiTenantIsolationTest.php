<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEvento;

uses(Tests\TestCase::class);

/**
 * Wave 18 D1/D2 — Isolamento multi-tenant Tier 0 do Model NfeEvento.
 *
 * NfeEvento registra eventos SEFAZ (cancelamento 110111, CCe 110110, manifestação
 * 210200-210240) aplicados a uma NfeEmissao. Vazar cross-tenant aqui = expor
 * justificativa de cancelamento + cStat fiscal de outro CNPJ.
 *
 * ADR 0093: global scope business_id Tier 0 IRREVOGÁVEL (trait HasBusinessScope).
 * ADR 0101: NUNCA usar biz=4 (ROTA LIVRE — Larissa cliente real prod) em tests.
 *
 * Wave 18 D7: este Model agora tem LogsActivity — toda mudança em
 * tipo/status/cstat_evento gera entrada em activity_log scoped a 'nfe_evento'.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see Modules/NfeBrasil/Models/NfeEvento.php
 */

const NFE_EVT_BIZ_WAGNER = 1;
const NFE_EVT_BIZ_FICTICIO = 99;
const NFE_EVT_TAG = 'WAVE18-EVT-ISO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEvento requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_eventos')) {
        $this->markTestSkipped('nfe_eventos table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }
    if (! Schema::hasTable('nfe_eventos')) {
        return;
    }
    try {
        NfeEvento::withoutGlobalScopes()
            ->whereIn('business_id', [NFE_EVT_BIZ_WAGNER, NFE_EVT_BIZ_FICTICIO])
            ->where('justificativa', 'like', '%'.NFE_EVT_TAG.'%')
            ->delete();
    } catch (\Throwable) {
        // best-effort
    }
});

// ------------------------------------------------------------------
// Contract: business_id NOT NULL + scope ativa
// ------------------------------------------------------------------

it('nfe_eventos tem coluna business_id NOT NULL', function () {
    expect(Schema::hasColumn('nfe_eventos', 'business_id'))->toBeTrue();
    $col = collect(DB::select('SHOW COLUMNS FROM nfe_eventos LIKE ?', ['business_id']))->first();
    expect($col)->not->toBeNull();
    expect($col->Null)->toBe('NO');
});

it('NfeEvento biz=99 NÃO aparece quando session ativa é biz=1', function () {
    $eventoId = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => NFE_EVT_BIZ_FICTICIO,
        'emissao_id'    => 1, // FK não strict aqui — só testa scope
        'tipo'          => '110111',
        'justificativa' => 'Cancelamento teste isolamento '.NFE_EVT_TAG,
        'status'        => 'autorizado',
        'cstat_evento'  => '135',
        'payload_json'  => json_encode(['tag' => NFE_EVT_TAG]),
        'created_at'    => now(),
    ]);

    session(['business.id' => NFE_EVT_BIZ_WAGNER]);

    $vaza = NfeEvento::where('id', $eventoId)->first();
    expect($vaza)->toBeNull();

    // Confirma que existe via withoutGlobalScopes
    $real = NfeEvento::withoutGlobalScopes()->where('id', $eventoId)->first();
    expect($real)->not->toBeNull();
    expect((int) $real->business_id)->toBe(NFE_EVT_BIZ_FICTICIO);
    expect($real->tipo)->toBe('110111');
});

it('NfeEvento biz=1 aparece quando session ativa é biz=1', function () {
    $eventoId = DB::table('nfe_eventos')->insertGetId([
        'business_id'   => NFE_EVT_BIZ_WAGNER,
        'emissao_id'    => 1,
        'tipo'          => '110110', // CCe
        'justificativa' => 'CCe teste '.NFE_EVT_TAG,
        'status'        => 'autorizado',
        'cstat_evento'  => '135',
        'payload_json'  => json_encode(['tag' => NFE_EVT_TAG]),
        'created_at'    => now(),
    ]);

    session(['business.id' => NFE_EVT_BIZ_WAGNER]);

    $evt = NfeEvento::where('id', $eventoId)->first();
    expect($evt)->not->toBeNull();
    expect((int) $evt->business_id)->toBe(NFE_EVT_BIZ_WAGNER);
    expect($evt->isAutorizado())->toBeTrue();
});

// ------------------------------------------------------------------
// Append-only contrato: UPDATED_AT desabilitado por força CONFAZ
// ------------------------------------------------------------------

it('NfeEvento é append-only (UPDATED_AT desabilitado)', function () {
    // Contrato fiscal: evento não tem updated_at — reprocessamento gera novo registro
    expect(NfeEvento::UPDATED_AT)->toBeNull();
});

// ------------------------------------------------------------------
// Cross-tenant: NÃO vaza eventos de outro tenant em query agregada
// ------------------------------------------------------------------

it('NfeEvento count() por session biz=99 NÃO conta eventos do biz=1', function () {
    // Cria 2 eventos biz=1 + 1 evento biz=99
    DB::table('nfe_eventos')->insert([
        [
            'business_id' => NFE_EVT_BIZ_WAGNER, 'emissao_id' => 1, 'tipo' => '110111',
            'justificativa' => 'biz=1 evt A '.NFE_EVT_TAG, 'status' => 'autorizado',
            'cstat_evento' => '135', 'payload_json' => '{}', 'created_at' => now(),
        ],
        [
            'business_id' => NFE_EVT_BIZ_WAGNER, 'emissao_id' => 2, 'tipo' => '110111',
            'justificativa' => 'biz=1 evt B '.NFE_EVT_TAG, 'status' => 'autorizado',
            'cstat_evento' => '135', 'payload_json' => '{}', 'created_at' => now(),
        ],
        [
            'business_id' => NFE_EVT_BIZ_FICTICIO, 'emissao_id' => 1, 'tipo' => '110110',
            'justificativa' => 'biz=99 evt C '.NFE_EVT_TAG, 'status' => 'autorizado',
            'cstat_evento' => '135', 'payload_json' => '{}', 'created_at' => now(),
        ],
    ]);

    session(['business.id' => NFE_EVT_BIZ_FICTICIO]);
    $contagemBiz99 = NfeEvento::where('justificativa', 'like', '%'.NFE_EVT_TAG.'%')->count();

    // Apenas 1 evento aparece (do biz=99)
    expect($contagemBiz99)->toBe(1);

    session(['business.id' => NFE_EVT_BIZ_WAGNER]);
    $contagemBiz1 = NfeEvento::where('justificativa', 'like', '%'.NFE_EVT_TAG.'%')->count();

    // 2 eventos visíveis pro biz=1
    expect($contagemBiz1)->toBe(2);
});

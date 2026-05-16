<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use Modules\Officeimpresso\Entities\LicencaLog;

uses(Tests\TestCase::class);

/**
 * Isolamento multi-tenant Tier 0 das Entities do modulo Officeimpresso.
 *
 * Officeimpresso é bridge legacy Delphi WR Sistemas → oimpresso Laravel.
 * Os Models nao registram global scope (Controllers filtram via session('user.business_id')),
 * portanto o teste valida ISOLAMENTO POR business_id COLUNAR — o invariante de que dados
 * de um business so aparecem em queries scoped por business_id, conforme padrao herdado UltimatePOS.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa producao) — ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (ficticio, sem dados reais).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite: schemas Officeimpresso herdam estrutura MySQL UltimatePOS.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: tabelas Officeimpresso requerem schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('licenca_computador')) {
        $this->markTestSkipped('Tabela licenca_computador ausente — rode migrate primeiro');
    }
    if (! Schema::hasTable('licenca_log')) {
        $this->markTestSkipped('Tabela licenca_log ausente — rode migrate primeiro');
    }
});

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// Licenca_Computador (computador desktop licenciado bridge Delphi)
// ------------------------------------------------------------------

it('Licenca_Computador biz=1 nao aparece em query scoped por biz=99', function () {
    // SUPERADMIN: inserção direta sem session pra teste cross-tenant determinístico
    $licenca = Licenca_Computador::create([
        'business_id' => BIZ_WAGNER,
        'hd'          => 'HD-TESTE-ISOL-991',
        'user_win'    => 'usr_teste',
        'hostname'    => 'pc-teste-isol-991',
        'bloqueado'   => 0,
    ]);

    // Query scoped pelo business ficticio NAO deve enxergar a licenca do biz=1
    $resultado = Licenca_Computador::where('business_id', BIZ_FICTICIO)
        ->where('id', $licenca->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    Licenca_Computador::where('hd', 'HD-TESTE-ISOL-991')->delete();
});

it('Licenca_Computador biz=1 aparece em query scoped por biz=1', function () {
    // SUPERADMIN: inserção direta de teste
    $licenca = Licenca_Computador::create([
        'business_id' => BIZ_WAGNER,
        'hd'          => 'HD-TESTE-ISOL-992',
        'user_win'    => 'usr_teste',
        'hostname'    => 'pc-teste-isol-992',
        'bloqueado'   => 0,
    ]);

    $resultado = Licenca_Computador::where('business_id', BIZ_WAGNER)
        ->where('id', $licenca->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->hostname)->toBe('pc-teste-isol-992');
})->afterEach(function () {
    Licenca_Computador::where('hd', 'HD-TESTE-ISOL-992')->delete();
});

// ------------------------------------------------------------------
// LicencaLog (append-only log de eventos auth/acesso)
// ------------------------------------------------------------------

it('LicencaLog biz=1 nao aparece em query scoped por biz=99', function () {
    // SUPERADMIN: inserção direta de teste — append-only, sem business scope global
    $log = LicencaLog::create([
        'business_id' => BIZ_WAGNER,
        'event'       => 'login_attempt',
        'source'      => 'desktop_audit',
        'ip'          => '10.0.0.99',
        'user_agent'  => 'pest-teste-isol-991',
        'created_at'  => now(),
    ]);

    $resultado = LicencaLog::where('business_id', BIZ_FICTICIO)
        ->where('id', $log->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    LicencaLog::where('user_agent', 'pest-teste-isol-991')->delete();
});

it('LicencaLog biz=1 aparece em query scoped por biz=1', function () {
    // SUPERADMIN: inserção direta de teste
    $log = LicencaLog::create([
        'business_id' => BIZ_WAGNER,
        'event'       => 'login_success',
        'source'      => 'desktop_audit',
        'ip'          => '10.0.0.1',
        'user_agent'  => 'pest-teste-isol-992',
        'created_at'  => now(),
    ]);

    $resultado = LicencaLog::where('business_id', BIZ_WAGNER)
        ->where('id', $log->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->event)->toBe('login_success');
    expect($resultado->first()->user_agent)->toBe('pest-teste-isol-992');
})->afterEach(function () {
    LicencaLog::where('user_agent', 'pest-teste-isol-992')->delete();
});

it('LicencaLog mantem business_id como column obrigatoria (fillable)', function () {
    // Garante que a Entity nao removeu accidentamente business_id da fillable list — guard regressivo
    $log = new LicencaLog;
    expect($log->getFillable())->toContain('business_id');
});

it('Licenca_Computador mantem business_id como column obrigatoria (fillable)', function () {
    $licenca = new Licenca_Computador;
    expect($licenca->getFillable())->toContain('business_id');
});

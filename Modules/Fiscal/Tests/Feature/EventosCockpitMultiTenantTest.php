<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEvento;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave Eventos Fiscal — isolation Tier 0 + mapeamento de tipos canônicos SEFAZ.
 *
 * NfeEvento = append-only log (UPDATED_AT = null). HasBusinessScope ADR 0093.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEvento requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_eventos')) {
        $this->markTestSkipped('nfe_eventos table missing');
    }
});

it('mapa de TIPOS cobre os 7 códigos SEFAZ canônicos esperados pelo cockpit', function () {
    $tipos = \Modules\Fiscal\Http\Controllers\EventosController::TIPOS;

    expect($tipos)
        ->toHaveKeys(['110110', '110111', '110140', '210200', '210210', '210220', '210240'])
        ->and($tipos['110110']['kind'])->toBe('cce')
        ->and($tipos['110111']['kind'])->toBe('cancel')
        ->and($tipos['110140']['kind'])->toBe('epec')
        ->and($tipos['210200']['kind'])->toBe('manifest');
});

it('NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped', function () {
    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeEvento::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Global scope HasBusinessScope deve esconder cross-tenant');
});

it('NfeEvento é append-only (UPDATED_AT = null) — eventos não devem ser editados', function () {
    expect(NfeEvento::UPDATED_AT)->toBeNull();
});

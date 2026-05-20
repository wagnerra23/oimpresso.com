<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeRecebido;

uses(Tests\TestCase::class);

/**
 * PR #3 Wave DF-e Fiscal — isolation Tier 0 (ADR 0093) + ADR 0101 biz=1.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeDfeRecebido requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_dfe_recebidos')) {
        $this->markTestSkipped('nfe_dfe_recebidos table missing');
    }
});

it('NfeDfeRecebido HasBusinessScope esconde cross-tenant da listagem DF-e', function () {
    session(['business.id' => 1, 'user.business_id' => 1]);

    $crossTenantCount = NfeDfeRecebido::query()
        ->where('business_id', '!=', 1)
        ->count();

    expect($crossTenantCount)->toBe(0, 'Global scope deve esconder cross-tenant');
});

it('STATUS constants estão definidas — Controller depende delas pra filtros', function () {
    expect(NfeDfeRecebido::STATUS_PENDENTE)->toBe('pendente')
        ->and(NfeDfeRecebido::STATUS_CIENCIA)->toBe('ciencia')
        ->and(NfeDfeRecebido::STATUS_CONFIRMADA)->toBe('confirmada')
        ->and(NfeDfeRecebido::STATUS_DESCONHECIDA)->toBe('desconhecida')
        ->and(NfeDfeRecebido::STATUS_NAO_REALIZADA)->toBe('nao_realizada');
});

it('isPendenteManifestacao retorna true pra status PENDENTE e CIENCIA', function () {
    $pendente = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE]);
    $ciencia  = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CIENCIA]);
    $conf     = new NfeDfeRecebido(['status_manifestacao' => NfeDfeRecebido::STATUS_CONFIRMADA]);

    expect($pendente->isPendenteManifestacao())->toBeTrue()
        ->and($ciencia->isPendenteManifestacao())->toBeTrue()
        ->and($conf->isPendenteManifestacao())->toBeFalse();
});

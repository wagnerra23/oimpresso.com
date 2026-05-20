<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * PR #2 Wave NFS-e Fiscal — isolation Tier 0 (ADR 0093) + ADR 0101 biz=1.
 *
 * NfseEmissao = modelo 56 nacional NT 2024-001. HasBusinessScope global scope.
 */

const NFSE_COCKPIT_BIZ_WAGNER   = 1;
const NFSE_COCKPIT_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfseEmissao requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('nfse_emissoes table missing — rode Modules/NfeBrasil migrate primeiro');
    }
});

it('NfseEmissao HasBusinessScope esconde cross-tenant da listagem do cockpit Nfse', function () {
    session(['business.id' => NFSE_COCKPIT_BIZ_WAGNER, 'user.business_id' => NFSE_COCKPIT_BIZ_WAGNER]);

    // Conta atual biz=1 (sem criar — só verifica que query é scoped)
    $countBiz1 = NfseEmissao::query()->count();
    $countCrossTenant = NfseEmissao::withoutGlobalScopes()
        ->where('business_id', '!=', NFSE_COCKPIT_BIZ_WAGNER)
        ->count();

    // Cross-tenant NÃO deve aparecer na query padrão
    expect($countBiz1)->toBeGreaterThanOrEqual(0)
        ->and(NfseEmissao::query()->where('business_id', '!=', NFSE_COCKPIT_BIZ_WAGNER)->count())
        ->toBe(0, 'Cross-tenant não pode aparecer na query scoped');
});

it('STATUS constants estão definidas no Model — Controller depende delas', function () {
    expect(NfseEmissao::STATUS_AUTHORIZED)->toBe('authorized')
        ->and(NfseEmissao::STATUS_REJECTED)->toBe('rejected')
        ->and(NfseEmissao::STATUS_PENDING)->toBe('pending')
        ->and(NfseEmissao::STATUS_SENT)->toBe('sent')
        ->and(NfseEmissao::STATUS_CANCELLED)->toBe('cancelled');
});

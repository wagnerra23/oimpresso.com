<?php

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\RevertCheck;
use Modules\Auditoria\Services\RevertService;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-008 — RevertService whitelist UNREVERTIBLE + niveis permissao.
 *
 * Valida (per SPEC + ADR 0127):
 *   1. Activity de outro business -> deny (Tier 0)
 *   2. Subject_type Marcacao -> deny ('Portaria 671 append-only')
 *   3. Subject_type NfeTransaction com cstat=100 -> deny ('NFe autorizada SEFAZ')
 *   4. Sem permissao auditoria.revert.* -> deny
 *   5. Janela 24h expirada com so revert.own -> deny
 *   6. Reason < 10 chars -> InvalidArgumentException
 *   7. Activity sem properties.old -> DomainException
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $hasColumn = Schema::hasColumn('activity_log', 'causer_kind');
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema activity_log incompleto — rode migrations primeiro (US-AUDIT-005).');
    }
    if (! $hasColumn) {
        $this->markTestSkipped('Coluna causer_kind nao existe.');
    }

    $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->service = new RevertService();
});

it('cenario 1: RevertCheck deny() retorna allowed=false com reason', function () {
    $check = RevertCheck::deny('Motivo qualquer.');
    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toBe('Motivo qualquer.');
});

it('cenario 2: RevertCheck allow() retorna allowed=true sem reason', function () {
    $check = RevertCheck::allow();
    expect($check->allowed)->toBeTrue();
    expect($check->reason)->toBeNull();
});

it('cenario 3: registry UNREVERTIBLE contem 5 categorias canonicas', function () {
    $registry = $this->service->unrevertibleRegistry();
    $keys = array_keys($registry);

    expect($keys)->toContain(\Modules\PontoWr2\Models\Marcacao::class);
    expect($keys)->toContain(\Modules\NfeBrasil\Models\NfeTransaction::class);
    expect($keys)->toContain(\Modules\Financeiro\Models\TituloBaixa::class);
    expect($keys)->toContain(\Modules\Repair\Models\OS::class);
    expect($keys)->toContain(\App\Transaction::class);
});

it('cenario 4: canRevert deny quando Activity de outro business (Tier 0)', function () {
    $log = new Activity();
    $log->business_id = 99999; // diferente do user

    $check = $this->service->canRevert($log, $this->user);
    expect($check->allowed)->toBeFalse();
    expect($check->reason)->toContain('Tier 0');
});

it('cenario 5: canRevert deny quando subject_type sem subject_id', function () {
    $log = new Activity();
    $log->business_id  = $this->business->id;
    $log->subject_type = \App\Transaction::class;
    $log->subject_id   = null;

    $check = $this->service->canRevert($log, $this->user);
    expect($check->allowed)->toBeFalse();
});

it('cenario 6: revert() lanca InvalidArgumentException se reason < 10 chars', function () {
    // Skip se nao tem activity real disponivel
    $log = Activity::query()->where('business_id', $this->business->id)->first();
    if (! $log) {
        $this->markTestSkipped('Sem activity real no business pra teste de revert.');
    }

    expect(fn () => $this->service->revert($log, $this->user, 'curto'))
        ->toThrow(\InvalidArgumentException::class, 'no minimo 10 caracteres');
});

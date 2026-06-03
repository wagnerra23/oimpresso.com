<?php

declare(strict_types=1);

use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\Reconcilers\DeployReconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;

uses(Tests\TestCase::class);

/**
 * DeployReconciler (ADR 0237) — faceta 'deploy' do loop jana:reconcile.
 *
 * Detecta SHA deployado (CT 100) != origin/main HEAD — o "1302 commits atrás"
 * silencioso. ALERTA-ONLY: deploy é humano/CI, healable SEMPRE false, NUNCA
 * auto-deploya. Núcleo puro `analisar()` + SHAs injetáveis = testável sem ssh/git.
 */

it('implementa Reconciler, name()=deploy, registrado em copiloto.reconcilers', function () {
    $r = new DeployReconciler();

    expect($r)->toBeInstanceOf(Reconciler::class)
        ->and($r->name())->toBe('deploy')
        ->and($r->description())->toBeString()->not->toBe('')
        ->and($r->tags())->toContain('deploy')
        // o config Jana é merged como copiloto.* (JanaServiceProvider) — ADR 0237
        ->and((array) config('copiloto.reconcilers'))->toContain(DeployReconciler::class);
});

it('analisar: SHAs iguais → synced (sem drift)', function () {
    expect((new DeployReconciler())->analisar('abc1234def', 'abc1234def'))->toBeEmpty();
});

it('analisar: short × full do mesmo commit → synced (prefix match)', function () {
    // observed short, desired full do mesmo commit: NÃO é drift.
    expect((new DeployReconciler())->analisar('744e86439edc0000000000000000000000000000', '744e864'))
        ->toBeEmpty();
});

it('analisar: SHAs diferentes → drift NÃO-healable (alerta-only)', function () {
    $drifts = (new DeployReconciler())->analisar('aaaaaaa', 'bbbbbbb');

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0])->toBeInstanceOf(ReconcileDrift::class)
        ->and($drifts[0]->target)->toBe('deploy')
        ->and($drifts[0]->healable)->toBeFalse()   // deploy é humano/CI — nunca auto-cura
        ->and($drifts[0]->healed)->toBeFalse()
        ->and($drifts[0]->desired)->toBe('aaaaaaa')
        ->and($drifts[0]->observed)->toBe('bbbbbbb')
        ->and($drifts[0]->detail)->toContain('NÃO executa deploy');
});

it('analisar: drift com commitsBehind → mensagem cita "N commits atrás"', function () {
    $drifts = (new DeployReconciler())->analisar('aaaaaaa', 'bbbbbbb', 1302);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->detail)->toContain('1302 commits atrás')
        ->and($drifts[0]->healable)->toBeFalse();
});

it('analisar: observed null (não dá pra ler o que roda) → drift não-healable não-verificável', function () {
    $drifts = (new DeployReconciler())->analisar('abc1234', null);

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->healable)->toBeFalse()
        ->and($drifts[0]->observed)->toBe('desconhecido')
        ->and($drifts[0]->detail)->toContain('não verificável');
});

it('analisar: desired null (main desconhecido) → drift não-healable, reverifica no próximo push', function () {
    $drifts = (new DeployReconciler())->analisar(null, 'abc1234');

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->healable)->toBeFalse()
        ->and($drifts[0]->desired)->toBe('desconhecido')
        ->and($drifts[0]->detail)->toContain('próximo push');
});

it('reconcile: SHAs injetados iguais → ReconcileResult inSync (driftCount 0)', function () {
    $r = new DeployReconciler(
        observedShaResolver: static fn (): ?string => 'deadbeef1234',
        desiredShaResolver: static fn (): ?string => 'deadbeef1234',
    );

    $result = $r->reconcile();

    expect($result)->toBeInstanceOf(ReconcileResult::class)
        ->and($result->name)->toBe('deploy')
        ->and($result->inSync)->toBeTrue()
        ->and($result->driftCount)->toBe(0)
        ->and($result->healedCount)->toBe(0)
        ->and($result->metadata['heal_supported'] ?? null)->toBeFalse();
});

it('reconcile: SHAs injetados diferentes → drift reportado, NUNCA curado mesmo com heal=true', function () {
    $r = new DeployReconciler(
        observedShaResolver: static fn (): ?string => 'old00000aaaa',
        desiredShaResolver: static fn (): ?string => 'new11111bbbb',
        commitsBehindResolver: static fn (?string $d, ?string $o): int => 42,
    );

    // Mesmo pedindo heal=true, deploy NÃO é auto-curado (alerta-only, R10).
    $result = $r->reconcile(['heal' => true]);

    expect($result->inSync)->toBeFalse()
        ->and($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0)                 // NUNCA cura deploy
        ->and($result->drifts[0]->healable)->toBeFalse()
        ->and($result->drifts[0]->healed)->toBeFalse()
        ->and($result->drifts[0]->detail)->toContain('42 commits atrás')
        ->and($result->metadata['observed'] ?? null)->toBe('old00000aaaa')
        ->and($result->metadata['desired'] ?? null)->toBe('new11111bbbb');
});

it('reconcile é idempotente: 2× com os mesmos SHAs = mesmo resultado', function () {
    $r = new DeployReconciler(
        observedShaResolver: static fn (): ?string => 'old00000aaaa',
        desiredShaResolver: static fn (): ?string => 'new11111bbbb',
    );

    $a = $r->reconcile();
    $b = $r->reconcile();

    expect($a->inSync)->toBe($b->inSync)
        ->and($a->driftCount)->toBe($b->driftCount)
        ->and($a->healedCount)->toBe($b->healedCount)
        ->and($a->drifts[0]->toArray())->toBe($b->drifts[0]->toArray());
});

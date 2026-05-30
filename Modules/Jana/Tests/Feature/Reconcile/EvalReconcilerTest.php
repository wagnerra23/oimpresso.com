<?php

declare(strict_types=1);

/**
 * EvalReconcilerTest — faceta 'eval' do loop jana:reconcile (ADR 0237).
 *
 * Determinístico, SEM rede / SEM DB / SEM rodar eval: exercita o núcleo puro
 * `analisar(?float $score, float $threshold)` direto + o caminho end-to-end
 * `reconcile()` com a observação da eval INJETADA via closure no construtor
 * (`new EvalReconciler(fn () => [...])`). Nada toca o artefato real
 * governance/jana-ragas-baseline.json.
 *
 * Cobre os 3 estados canônicos da task:
 *   - score ACIMA do threshold      → synced (inSync, 0 drift).
 *   - score ABAIXO do threshold     → 1 drift "regrediu" (healable=false).
 *   - score null (eval ausente)     → 1 drift "gate cego" (healable=false).
 * + invariantes: idempotência, healable sempre false (alerta-only), metadata canon,
 *   leitura defensiva de baseline placeholder/malformado (= gate cego, não crash).
 *
 * @see Modules\Jana\Services\Reconcile\Reconcilers\EvalReconciler
 * @see Modules\Jana\Contracts\Reconciler
 */

use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\Reconcilers\EvalReconciler;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Tests\TestCase;

uses(TestCase::class);

// Local-dev worktree autoload fix (mesmo padrão de ReconcileCommandTest):
// `vendor` é symlink pro main repo, então o autoload aponta pro main repo Modules/
// (não a worktree). Quando a classe existe SÓ na worktree, o autoload falha —
// registramos um autoloader que tenta o path da worktree primeiro pra esta classe.
spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, 'Modules\\Jana\\Services\\Reconcile\\Reconcilers\\EvalReconciler')) {
        return;
    }
    $relative = str_replace(['Modules\\Jana\\', '\\'], ['', DIRECTORY_SEPARATOR], $class) . '.php';
    $candidate = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relative;
    if (is_file($candidate)) {
        require_once $candidate;
    }
}, true, true);

/**
 * Helper: monta o shape do artefato canary (governance/jana-ragas-baseline.json)
 * com um faithfulness "real" (calibrado) — mode!=placeholder, last_updated setado.
 *
 * @return array<string, mixed>
 */
function evalArtefatoCalibrado(float $faithfulness): array
{
    return [
        '_meta' => ['schema_version' => '1.0'],
        'metrics' => [
            'faithfulness' => [
                'value' => $faithfulness,
                'last_updated' => '2026-05-30T06:00:00Z',
                'evaluated_questions' => 100,
                'mode' => 'real',
            ],
            'answer_relevancy' => [
                'value' => 0.80,
                'last_updated' => '2026-05-30T06:00:00Z',
                'evaluated_questions' => 100,
                'mode' => 'real',
            ],
        ],
    ];
}

// ───────────────────────── núcleo puro analisar() ──────────────────────────

it('analisar: score ACIMA do threshold → synced (nenhum drift)', function () {
    $drifts = (new EvalReconciler())->analisar(0.85, 0.70);

    expect($drifts)->toBe([]);
});

it('analisar: score IGUAL ao threshold → synced (régua é inclusiva)', function () {
    $drifts = (new EvalReconciler())->analisar(0.70, 0.70);

    expect($drifts)->toBe([]);
});

it('analisar: score ABAIXO do threshold → 1 drift NÃO-curável de regressão', function () {
    $drifts = (new EvalReconciler())->analisar(0.55, 0.70);

    expect($drifts)->toHaveCount(1);

    /** @var ReconcileDrift $d */
    $d = $drifts[0];
    expect($d->healable)->toBeFalse()
        ->and($d->healed)->toBeFalse()
        ->and($d->target)->toBe('eval:faithfulness')
        ->and($d->observed)->toContain('0.55')
        ->and($d->desired)->toContain('0.70')
        ->and($d->detail)->toContain('abaixo do threshold');
});

it('analisar: score NULL (eval ausente) → 1 drift NÃO-curável "gate cego"', function () {
    $drifts = (new EvalReconciler())->analisar(null, 0.70);

    expect($drifts)->toHaveCount(1);

    /** @var ReconcileDrift $d */
    $d = $drifts[0];
    expect($d->healable)->toBeFalse()
        ->and($d->target)->toBe('eval:faithfulness')
        ->and($d->observed)->toContain('gate cego')
        ->and($d->detail)->toContain('CEGO');
});

// ─────────────────── reconcile() com observarEval injetado ──────────────────

it('reconcile: artefato com score alto → inSync, 0 drift, metadata canon', function () {
    $reconciler = new EvalReconciler(fn (): array => evalArtefatoCalibrado(0.88));

    $result = $reconciler->reconcile();

    expect($result)->toBeInstanceOf(ReconcileResult::class)
        ->and($result->name)->toBe('eval')
        ->and($result->inSync)->toBeTrue()
        ->and($result->driftCount)->toBe(0)
        ->and($result->healedCount)->toBe(0);

    expect($result->metadata['observed_score'])->toBe(0.88)
        ->and($result->metadata['eval_present'])->toBeTrue()
        ->and($result->metadata['heal_supported'])->toBeFalse()
        ->and($result->metadata['metric'])->toBe('faithfulness')
        ->and($result->metadata)->toHaveKey('desired_threshold');
});

it('reconcile: artefato com score baixo → drift, NÃO cura nem com heal=true', function () {
    // threshold default config/ragas.php faithfulness = 0.70; score 0.50 < 0.70.
    $reconciler = new EvalReconciler(fn (): array => evalArtefatoCalibrado(0.50));

    $result = $reconciler->reconcile(['heal' => true]);

    expect($result->inSync)->toBeFalse()
        ->and($result->driftCount)->toBe(1)
        ->and($result->healedCount)->toBe(0); // alerta-only: heal não cura

    expect($result->drifts[0]->healable)->toBeFalse()
        ->and($result->drifts[0]->healed)->toBeFalse()
        ->and($result->metadata['observed_score'])->toBe(0.50);
});

it('reconcile: observarEval devolve null (artefato ausente) → drift gate cego', function () {
    $reconciler = new EvalReconciler(fn (): ?array => null);

    $result = $reconciler->reconcile();

    expect($result->inSync)->toBeFalse()
        ->and($result->driftCount)->toBe(1)
        ->and($result->metadata['eval_present'])->toBeFalse()
        ->and($result->metadata['observed_score'])->toBeNull();

    expect($result->drifts[0]->detail)->toContain('CEGO');
});

it('reconcile: baseline PLACEHOLDER zerado → gate cego (não "índice 100% quebrado")', function () {
    // Shape REAL do governance/jana-ragas-baseline.json recém-criado: value 0 + mode placeholder.
    $placeholder = [
        'metrics' => [
            'faithfulness' => [
                'value' => 0.0,
                'last_updated' => null,
                'evaluated_questions' => 0,
                'mode' => 'placeholder',
            ],
        ],
    ];
    $reconciler = new EvalReconciler(fn (): array => $placeholder);

    $result = $reconciler->reconcile();

    // value=0 placeholder NÃO vira score 0 (que falsamente falharia o threshold com
    // alarme máximo) — vira null = gate cego (honesto: eval nunca rodou pra valer).
    expect($result->metadata['observed_score'])->toBeNull()
        ->and($result->driftCount)->toBe(1)
        ->and($result->drifts[0]->observed)->toContain('gate cego');
});

it('reconcile: artefato malformado (sem metrics) → gate cego, sem crash', function () {
    $reconciler = new EvalReconciler(fn (): array => ['lixo' => true]);

    $result = $reconciler->reconcile();

    expect($result->metadata['observed_score'])->toBeNull()
        ->and($result->driftCount)->toBe(1);
});

// ───────────────────────────── invariantes ─────────────────────────────────

it('idempotência: reconcile() 2× dá o mesmo resultado (toArray idêntico)', function () {
    $reconciler = new EvalReconciler(fn (): array => evalArtefatoCalibrado(0.50));

    $a = $reconciler->reconcile()->toArray();
    $b = $reconciler->reconcile()->toArray();

    // duration_ms é wall-clock e pode variar — compara tudo exceto ele.
    unset($a['duration_ms'], $b['duration_ms']);
    expect($a)->toBe($b);
});

it('contrato: name/description/tags estáveis e coerentes (alerta-only)', function () {
    $reconciler = new EvalReconciler();

    expect($reconciler->name())->toBe('eval')
        ->and($reconciler->description())->toBeString()->not->toBeEmpty()
        ->and($reconciler->tags())->toContain('eval')
        ->and($reconciler->tags())->toContain('alert_only');
});

it('TODO drift desta faceta é alerta-only (healable=false) em qualquer cenário', function () {
    $reconciler = new EvalReconciler();

    $cenarios = [
        $reconciler->analisar(0.40, 0.70), // regressão
        $reconciler->analisar(null, 0.70), // gate cego
    ];

    foreach ($cenarios as $drifts) {
        foreach ($drifts as $d) {
            expect($d->healable)->toBeFalse();
        }
    }
});

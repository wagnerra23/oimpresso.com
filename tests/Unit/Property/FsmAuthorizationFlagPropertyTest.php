<?php

declare(strict_types=1);

use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Faker\Factory as FakerFactory;

/**
 * US-TEST-001 — Property-Based Testing PILOTO (invariante Tier 0 FSM).
 *
 * Estado-da-arte 2026 (PBT): em vez de 1 exemplo input→output, define-se uma
 * PROPRIEDADE/invariante e geram-se centenas de casos aleatórios atacando-a.
 * Pesquisa: +23–37% pass@1 vs TDD example-based; pega edge cases que exemplo
 * manual não imagina. Aqui sem dependência nova — Faker (já no projeto) gera
 * os casos. Ver memory/sessions/2026-06-05-arte-programacao-autonoma-rapida-qualidade.md §9.
 *
 * INVARIANTE TIER 0 sob teste — FsmAuthorizationFlag (consume-once):
 *   É a peça que autoriza UPDATE em current_stage_id no FSM canon (ADR 0143).
 *   Se ela vazar (autoriza 2×, ou autoriza chave errada, ou consume sem mark
 *   retorna true), a guard GuardsFsmTransitions abre → mutação de estado sem
 *   passar pelo ExecuteStageActionService → quebra a auditoria append-only.
 *
 * Por que é PURA (roda sem app/DB): a flag é singleton estático per-request.
 *   Logo este property test roda em tests/Unit (PHPUnit puro, sem boot Laravel).
 *
 * @see app/Domain/Fsm/Support/FsmAuthorizationFlag.php
 * @see app/Domain/Fsm/Concerns/GuardsFsmTransitions.php
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 * @see memory/proibicoes.md §"FSM Pipeline Canônico"
 */

const PBT_CASES = 200; // nº de casos aleatórios gerados por propriedade

/** Gera um par (modelClass, modelId) plausível — id int|string conforme assinatura real. */
function fsmFlagRandomKey(\Faker\Generator $f): array
{
    $class = 'App\\Models\\' . ucfirst($f->lexify('??????')) . '\\' . ucfirst($f->lexify('?????'));
    $id = $f->boolean(70)
        ? $f->numberBetween(1, 2_000_000)        // id numérico (caso comum)
        : (string) $f->uuid();                    // id string (ULID/UUID — assinatura aceita)

    return [$class, $id];
}

beforeEach(fn () => FsmAuthorizationFlag::reset());
afterEach(fn () => FsmAuthorizationFlag::reset());

it('P1 — consume SEM mark retorna sempre false (fail-secure)', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        [$class, $id] = fsmFlagRandomKey($f);

        expect(FsmAuthorizationFlag::consume($class, $id))
            ->toBeFalse("consume sem mark deveria negar — {$class}:{$id}");
    }
});

it('P2 — mark então consume retorna true (autoriza a transição)', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        [$class, $id] = fsmFlagRandomKey($f);

        FsmAuthorizationFlag::mark($class, $id);

        expect(FsmAuthorizationFlag::consume($class, $id))
            ->toBeTrue("mark→consume deveria autorizar — {$class}:{$id}");
    }
});

it('P3 — consume-once: segundo consume sem novo mark é sempre false', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        [$class, $id] = fsmFlagRandomKey($f);

        FsmAuthorizationFlag::mark($class, $id);
        FsmAuthorizationFlag::consume($class, $id);            // consome a única autorização

        expect(FsmAuthorizationFlag::consume($class, $id))
            ->toBeFalse("flag deveria ser consume-once — {$class}:{$id}");
    }
});

it('P4 — mark é idempotente: N marks NÃO viram N autorizações (não é contador)', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        [$class, $id] = fsmFlagRandomKey($f);
        $marks = $f->numberBetween(2, 10);

        for ($m = 0; $m < $marks; $m++) {
            FsmAuthorizationFlag::mark($class, $id);
        }

        // Após N marks, exatamente UMA autorização existe.
        expect(FsmAuthorizationFlag::consume($class, $id))->toBeTrue();
        expect(FsmAuthorizationFlag::consume($class, $id))
            ->toBeFalse("após {$marks} marks deveria haver só 1 autorização — {$class}:{$id}");
    }
});

it('P5 — isolamento de chave: mark(A) nunca autoriza consume(B) quando A≠B', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        [$classA, $idA] = fsmFlagRandomKey($f);

        // Gera B garantidamente diferente de A.
        do {
            [$classB, $idB] = fsmFlagRandomKey($f);
        } while ("{$classB}:{$idB}" === "{$classA}:{$idA}");

        FsmAuthorizationFlag::mark($classA, $idA);

        // B nunca foi marcado → negado.
        expect(FsmAuthorizationFlag::consume($classB, $idB))
            ->toBeFalse("mark(A) vazou pra B — A={$classA}:{$idA} B={$classB}:{$idB}");

        // A continua autorizado (consume(B) não roubou a flag de A).
        expect(FsmAuthorizationFlag::consume($classA, $idA))
            ->toBeTrue("flag de A foi corrompida pelo consume de B — {$classA}:{$idA}");
    }
});

it('P6 — reset() limpa TODAS as autorizações pendentes', function () {
    $f = FakerFactory::create();

    for ($i = 0; $i < PBT_CASES; $i++) {
        $keys = [];
        $k = $f->numberBetween(1, 8);

        for ($j = 0; $j < $k; $j++) {
            [$class, $id] = fsmFlagRandomKey($f);
            FsmAuthorizationFlag::mark($class, $id);
            $keys[] = [$class, $id];
        }

        FsmAuthorizationFlag::reset();

        foreach ($keys as [$class, $id]) {
            expect(FsmAuthorizationFlag::consume($class, $id))
                ->toBeFalse("reset() deveria ter limpado — {$class}:{$id}");
        }
    }
});

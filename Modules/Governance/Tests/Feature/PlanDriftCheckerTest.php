<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Governance\Services\Checkers\PlanDriftChecker;

uses(Tests\TestCase::class);

/**
 * PlanDriftChecker — o `jana:plan-drift` passa a ter invocador de 1ª classe.
 *
 * HERMÉTICO por desenho: a conversão relatório→DriftFinding é PURA (sem DB, sem
 * filesystem, sem MCP), então os casos exercitam `findingsFromReport()` direto; os
 * caminhos de borda do `check()` mockam a facade Artisan. Nenhum teste depende de
 * `mcp_tasks` — a regra de drift em si já tem dono e teste próprios
 * ({@see Modules\Jana\Tests\Feature\Mcp\PlanDriftCommandTest}), e duplicá-la aqui seria
 * um 2º medidor.
 *
 * @see Modules\Governance\Services\Checkers\PlanDriftChecker
 */
function planDriftCheckerReport(array $findings, array $extra = []): string
{
    return json_encode(array_merge([
        'ok' => count($findings) === 0,
        'planos' => 16,
        'linked' => 27,
        'fail' => count(array_filter($findings, fn ($f) => $f['level'] === 'fail')),
        'warn' => count(array_filter($findings, fn ($f) => $f['level'] === 'warn')),
        'findings' => $findings,
    ], $extra));
}

function planDriftFakeArtisan(string $output): void
{
    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn($output);
}

it('implementa DriftChecker + está registrado em governance.drift_checkers (roda no governance:audit)', function () {
    // Duas pernas de propósito: o `config()` prova que o container VÊ o checker (é o que
    // o registry lê em runtime), e o arquivo prova a declaração CANÔNICA — o comentário
    // do próprio config diz que a lista aceita "override por ENV ou config:cache", então
    // só o config() poderia ficar verde com a lista canônica vazia.
    $declarado = require base_path('Modules/Governance/Config/config.php');

    expect(new PlanDriftChecker())->toBeInstanceOf(Modules\Governance\Contracts\DriftChecker::class)
        ->and((array) config('governance.drift_checkers'))->toContain(PlanDriftChecker::class)
        ->and($declarado['drift_checkers'])->toContain(PlanDriftChecker::class);
});

it('enforcement é warn, NUNCA block — não pode derrubar o required `--fail-on=block`', function () {
    // BITE invertido: se alguém promover a 'block' sem mordida provada (ADR 0336),
    // este caso fica vermelho ANTES de o PR scan required começar a reprovar merge alheio.
    expect((new PlanDriftChecker())->enforcement())->toBe('warn')
        ->and((new PlanDriftChecker())->cadence())->toBe('daily');
});

it('órfão reverso (status null) → finding low, com ação de registrar no PLANS-INDEX', function () {
    $f = (new PlanDriftChecker())->findingsFromReport(json_decode(planDriftCheckerReport([[
        'plan' => '(sem plano registrado)', 'slug' => 'programa-ondas', 'status' => null,
        'counts' => ['total' => 10, 'open' => 10, 'moving' => 3, 'done' => 0, 'cancelled' => 0],
        'level' => 'warn', 'issue' => '10 task(s) com parent_plan=programa-ondas, mas nenhum plano declara esse slug no Índice',
    ]]), true));

    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('low')
        ->and($f[0]->target)->toBe('programa-ondas')
        ->and($f[0]->target_type)->toBe('plano')
        ->and($f[0]->evidence['category'])->toBe('orfao_reverso')
        ->and($f[0]->message)->toContain('PLANS-INDEX.md');
});

it('ligação fantasma (level fail) → finding high', function () {
    $f = (new PlanDriftChecker())->findingsFromReport(json_decode(planDriftCheckerReport([[
        'plan' => 'PLANO-X', 'slug' => 'plano-x', 'status' => 'em-execução',
        'counts' => ['total' => 0, 'open' => 0, 'moving' => 0, 'done' => 0, 'cancelled' => 0],
        'level' => 'fail', 'issue' => 'em-execução mas 0 tasks com esse parent_plan',
    ]]), true));

    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('high')
        ->and($f[0]->evidence['category'])->toBe('ligacao_fantasma');
});

it('status divergente em plano DECLARADO → finding medium (não confunde com órfão)', function () {
    $f = (new PlanDriftChecker())->findingsFromReport(json_decode(planDriftCheckerReport([[
        'plan' => 'PLANO-Y', 'slug' => 'plano-y', 'status' => 'concluído',
        'counts' => ['total' => 4, 'open' => 2, 'moving' => 0, 'done' => 2, 'cancelled' => 0],
        'level' => 'warn', 'issue' => 'concluído mas 2 task(s) ainda aberta(s) no MCP',
    ]]), true));

    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('medium')
        ->and($f[0]->evidence['category'])->toBe('status_divergente')
        ->and($f[0]->evidence['plan_status'])->toBe('concluído');
});

it('MORDE: relatório com achados → drifted, ok=false, com contagem por categoria', function () {
    planDriftFakeArtisan(planDriftCheckerReport([
        ['plan' => 'A', 'slug' => 'a', 'status' => 'em-execução', 'counts' => [], 'level' => 'fail', 'issue' => 'i'],
        ['plan' => '(sem plano registrado)', 'slug' => 'b', 'status' => null, 'counts' => [], 'level' => 'warn', 'issue' => 'i'],
    ]));

    $r = (new PlanDriftChecker())->check();

    expect($r->ok)->toBeFalse()
        ->and($r->drift_count)->toBe(2)
        ->and($r->metadata['category_counts']['ligacao_fantasma'])->toBe(1)
        ->and($r->metadata['category_counts']['orfao_reverso'])->toBe(1)
        ->and($r->metadata['planos'])->toBe(16);
});

it('SOLTA: relatório sem achados → clean (controle negativo do caso acima)', function () {
    planDriftFakeArtisan(planDriftCheckerReport([]));

    $r = (new PlanDriftChecker())->check();

    expect($r->ok)->toBeTrue()->and($r->drift_count)->toBe(0);
});

it('comando pulou (MCP offline / índice ausente) → clean COM reason — ⊘ honesto, não lobo nem verde mudo', function () {
    planDriftFakeArtisan(json_encode([
        'ok' => true, 'skipped' => true, 'reason' => 'mcp_tasks vazia (MCP offline / não sincronizado)',
        'planos' => 0, 'fail' => 0, 'warn' => 0, 'findings' => [],
    ]));

    $r = (new PlanDriftChecker())->check();

    expect($r->ok)->toBeTrue()
        ->and($r->drift_count)->toBe(0)
        ->and($r->metadata['skipped'])->toBeTrue()
        ->and($r->metadata['reason'])->toContain('mcp_tasks vazia');
});

it('JSON inválido → clean com error (degrada gracioso, nunca lança)', function () {
    planDriftFakeArtisan('erro fatal não-JSON na saída');

    $r = (new PlanDriftChecker())->check();

    expect($r->ok)->toBeTrue()->and($r->metadata['error'])->toBe('invalid_json');
});

it('comando lança → clean com a exceção em metadata (checker NUNCA propaga)', function () {
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('DB offline'));

    $r = (new PlanDriftChecker())->check();

    expect($r->ok)->toBeTrue()->and($r->metadata['error'])->toBe('DB offline');
});

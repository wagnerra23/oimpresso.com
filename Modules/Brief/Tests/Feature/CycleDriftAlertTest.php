<?php

declare(strict_types=1);

use Modules\Brief\Mcp\Tools\BriefFetchTool;

uses(Tests\TestCase::class);

/**
 * CycleDriftAlertTest — detector de drift 3-vias (fix/cycle-drift-3way).
 *
 * O alerta antigo lumpava "commit sem task" com "commit em outro cycle" → gritava
 * "pivot!" mesmo quando o problema era só falta de `Refs: US-XXX`. Estes testes
 * cobrem o helper PURO `formatCycleDriftAlert` (sem DB): a mensagem deve nomear a
 * causa dominante e adaptar a sugestão.
 *
 * Multi-tenant Tier 0: brief é repo-wide, sem business_id — formatação pura.
 */

it('quando a causa dominante é rastreio (sem task), sugere linkar — não pivot', function () {
    // 0 on-cycle, 2 em outro cycle, 200 sem task linkada de 202 total.
    $msg = BriefFetchTool::formatCycleDriftAlert(0, 2, 200, 202, 0, 'CYCLE-08');

    expect($msg)
        ->toContain('só 0/202 commits/PRs')
        ->toContain('0% alinhados')
        ->toContain('200 sem task de cycle linkada')
        ->toContain('linke com `Refs: US-XXX`')          // sugestão de rastreio
        ->not->toContain('cycles-close --rollover');     // NÃO sugere pivot
});

it('quando a causa dominante é outro cycle, sugere rollover (pivot real)', function () {
    // 5 on-cycle, 80 em outro cycle, 15 sem task de 100 total.
    $msg = BriefFetchTool::formatCycleDriftAlert(5, 80, 15, 100, 5, 'CYCLE-08');

    expect($msg)
        ->toContain('80 em tasks de OUTRO cycle')
        ->toContain('cycles-close --rollover')           // sugere pivot
        ->toContain('5% alinhados');
});

it('omite a linha de uma categoria quando ela é zero', function () {
    // Sem nada em outro cycle: a linha "OUTRO cycle" não deve aparecer.
    $msg = BriefFetchTool::formatCycleDriftAlert(1, 0, 99, 100, 1, 'CYCLE-08');

    expect($msg)
        ->toContain('99 sem task de cycle linkada')
        ->not->toContain('OUTRO cycle');
});

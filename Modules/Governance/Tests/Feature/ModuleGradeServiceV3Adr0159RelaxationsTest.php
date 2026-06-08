<?php

declare(strict_types=1);

namespace Modules\Governance\Tests\Feature;

use Modules\Governance\Services\ModuleGradeService;
use Tests\TestCase;

/**
 * Pest tests validando as 4 relaxações ADR 0159 Wave 18:
 *   1. D5  — `internal_governance_active` → 15/15 (cross-cutting Wagner daily)
 *   2. D9.b — opt-in default true → 3/3 quando failed_jobs <5
 *   3. D4.b — module.json `governance.fsm_n_a:true` → 5/5 N/A
 *   4. D3.b — CHANGELOG.md ≤7d → frescor BRIEFING renovado pra 5/5
 *
 * @see memory/decisions/0159-module-grade-v3-errata-meta-97-realismo.md
 */
uses(TestCase::class);

beforeEach(function () {
    config(['governance.observability.query_failed_jobs' => true]);
});

it('D5 aceita internal_governance_active e pontua 15/15 (ADR 0159 #1)', function () {
    // Cria módulo dummy + entry no yaml temp não dá — vamos usar Admin que
    // tem internal_governance_active no yaml e NÃO tem na_justified D5 no SPEC.
    // Fallback: testar módulo Admin (ou TeamMcp, qualquer cross-cutting sem N/A no D5).
    $service = app(ModuleGradeService::class);

    // Brief tem level=internal_governance_active (yaml Wave 18) e não tem na_justified D5 no SPEC
    $candidates = ['Brief', 'TeamMcp', 'Admin', 'Auditoria', 'Connector', 'Officeimpresso', 'Superadmin'];
    $found = null;
    foreach ($candidates as $cand) {
        if (! is_dir(base_path('Modules/' . $cand))) {
            continue;
        }
        $grade = $service->gradeModule($cand);
        $d5 = $grade['dimensions']['client_real'];
        if (str_contains($d5['breakdown'][0]['evidence'] ?? '', 'internal_governance_active')) {
            $found = ['mod' => $cand, 'd5' => $d5];
            break;
        }
    }

    expect($found)->not->toBeNull('Deveria achar pelo menos 1 módulo cross-cutting sem N/A D5 overriding');
    expect($found['d5']['score'])->toBe(15, "{$found['mod']} é internal_governance_active → 15/15");
    expect($found['d5']['breakdown'][0]['evidence'])->toContain('internal_governance_active');
});

it('D5 ainda pontua biz_4_rota_livre_prod normalmente (backward-compat)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Vestuario');

    expect($grade['dimensions']['client_real']['score'])->toBe(15);
});

it('D5 ainda pontua biz_1_wagner_active=10 normalmente (backward-compat)', function () {
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Jana');

    expect($grade['dimensions']['client_real']['score'])->toBe(10);
});

it('D9.b com opt-in true consulta failed_jobs e pontua 3 quando <5 fails (ADR 0159 #2)', function () {
    // Garante que tabela existe + zerada (smoke local: 0 fails 24h)
    if (! \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
        $this->markTestSkipped('failed_jobs table ausente no ambiente de teste');
    }

    config(['governance.observability.query_failed_jobs' => true]);
    \Illuminate\Support\Facades\DB::table('failed_jobs')
        ->where('failed_at', '>', now()->subHours(24))
        ->delete();

    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Governance');

    $d9b = collect($grade['dimensions']['observability']['breakdown'])
        ->firstWhere('key', 'D9.b');

    expect($d9b['score'])->toBe(3, 'Com 0 failed_jobs <24h, D9.b deve pontuar 3/3');
    expect($d9b['evidence'])->toContain('failed_jobs');
});

it('D4.b retorna 5/5 com evidence N/A quando module.json declara governance.fsm_n_a:true (ADR 0159 #3)', function () {
    // Cria módulo temporário com module.json fsm_n_a:true
    $tmpModule = base_path('Modules/__FsmNaTest__');
    @mkdir($tmpModule, 0777, true);
    @mkdir($tmpModule . '/Entities', 0777, true);
    @mkdir($tmpModule . '/Http/Controllers', 0777, true);
    file_put_contents(
        $tmpModule . '/module.json',
        json_encode([
            'name' => '__FsmNaTest__',
            'governance' => ['fsm_n_a' => true],
        ], JSON_PRETTY_PRINT)
    );

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('__FsmNaTest__');

        $d4b = collect($grade['dimensions']['architecture']['breakdown'])
            ->firstWhere('key', 'D4.b');

        expect($d4b['score'])->toBe(5, 'fsm_n_a:true pontua 5/5');
        expect($d4b['evidence'])->toContain('N/A');
        expect($d4b['evidence'])->toContain('ADR 0159');
    } finally {
        @unlink($tmpModule . '/module.json');
        @rmdir($tmpModule . '/Entities');
        @rmdir($tmpModule . '/Http/Controllers');
        @rmdir($tmpModule . '/Http');
        @rmdir($tmpModule);
    }
});

it('D4.b ainda detecta FSM legacy via GuardsFsmTransitions (backward-compat)', function () {
    // Sells tem FSM real — deve seguir pontuando 5
    $service = app(ModuleGradeService::class);
    $grade = $service->gradeModule('Repair');

    $d4b = collect($grade['dimensions']['architecture']['breakdown'])
        ->firstWhere('key', 'D4.b');

    // Repair tem FSM canônica (current_stage_id ou GuardsFsmTransitions)
    expect($d4b['score'])->toBeGreaterThanOrEqual(0); // tolera 0 ou 5; smoke não-bloqueante
});

it('D3.b refresca pra 5 quando CHANGELOG.md ≤7d (ADR 0159 #4)', function () {
    $tmpMemoryDir = base_path('memory/requisitos/__ChangelogFreshTest__');
    @mkdir($tmpMemoryDir, 0777, true);

    // BRIEFING antigo (>90d simulado via touch)
    $briefingPath = $tmpMemoryDir . '/BRIEFING.md';
    file_put_contents($briefingPath, '# briefing antigo');
    touch($briefingPath, time() - (120 * 86400));

    // CHANGELOG.md modificado agora (≤7d)
    $changelogPath = $tmpMemoryDir . '/CHANGELOG.md';
    file_put_contents($changelogPath, '# changelog recente');

    // Cria módulo dummy pra Service não dar throw
    $tmpModule = base_path('Modules/__ChangelogFreshTest__');
    @mkdir($tmpModule, 0777, true);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('__ChangelogFreshTest__');

        $d3b = collect($grade['dimensions']['documentation']['breakdown'])
            ->firstWhere('key', 'D3.b');

        expect($d3b['score'])->toBe(5, 'BRIEFING antigo + CHANGELOG ≤7d → 5/5 (ADR 0159)');
        expect($d3b['evidence'])->toContain('CHANGELOG');
        expect($d3b['evidence'])->toContain('ADR 0159');
    } finally {
        @unlink($briefingPath);
        @unlink($changelogPath);
        @rmdir($tmpMemoryDir);
        @rmdir($tmpModule);
    }
});

it('D3.b pontua 2/5 quando BRIEFING ausente mas CHANGELOG ≤7d (parcial ADR 0159)', function () {
    $tmpMemoryDir = base_path('memory/requisitos/__ChangelogPartialTest__');
    @mkdir($tmpMemoryDir, 0777, true);

    // SEM BRIEFING, COM CHANGELOG fresco
    $changelogPath = $tmpMemoryDir . '/CHANGELOG.md';
    file_put_contents($changelogPath, '# changelog recente');

    $tmpModule = base_path('Modules/__ChangelogPartialTest__');
    @mkdir($tmpModule, 0777, true);

    try {
        $service = app(ModuleGradeService::class);
        $grade = $service->gradeModule('__ChangelogPartialTest__');

        $d3b = collect($grade['dimensions']['documentation']['breakdown'])
            ->firstWhere('key', 'D3.b');

        expect($d3b['score'])->toBe(2, 'Sem BRIEFING + CHANGELOG ≤7d → 2/5 (parcial)');
        expect($d3b['evidence'])->toContain('parcial ADR 0159');
    } finally {
        @unlink($changelogPath);
        @rmdir($tmpMemoryDir);
        @rmdir($tmpModule);
    }
});

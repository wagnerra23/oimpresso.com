<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Database\Seeders\ForjaDemoTicketsSeeder;
use Modules\TeamMcp\Services\Forja\ForjaQuadroService;

uses(Tests\TestCase::class);

/**
 * Forja · aba Quadro (board F0→F3.5) — ForjaQuadroService::build().
 *
 * Cobertura (Onda Forja — código novo sem teste):
 *   1. build($projectId) com FORJA semeado → fases: 6 colunas key/label/cards,
 *      cards com display_id/title/tipo/onda.
 *   2. build(null) → 6 colunas com cards vazios (esqueleto do board, sem fantasma).
 *
 * Padrão era-sqlite (espelha AcceptanceRefTest): schema sintético + activitylog OFF.
 *
 * @see Modules\TeamMcp\Services\Forja\ForjaQuadroService
 * @see memory/decisions/0070-jira-style-task-management.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético incompatível com MySQL persistente (floor SDD).');
    }

    config(['activitylog.enabled' => false]);

    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_jira_projects');

    Schema::create('mcp_jira_projects', function ($t) {
        $t->bigIncrements('id');
        $t->string('key', 20)->unique();
        $t->string('name', 120)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('active');
        $t->string('icon', 40)->nullable();
        $t->unsignedInteger('next_task_number')->default(1);
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 40)->nullable();
        $t->unsignedBigInteger('project_id')->nullable();
        $t->string('module', 60)->nullable();
        $t->string('title', 255)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('type', 20)->nullable();
        $t->string('owner', 60)->nullable();
        $t->string('priority', 10)->nullable();
        $t->json('custom_fields')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_tasks');
    Schema::dropIfExists('mcp_jira_projects');
});

/** Fases canônicas do board, na ordem do pipeline (espelha o Service). */
function forjaFasesEsperadas(): array
{
    return ['F0', 'F1', 'F1.5', 'F2', 'F3', 'F3.5'];
}

// -------------------------------------------------------------------------
// 1. build() com FORJA semeado — board com 6 colunas + cards
// -------------------------------------------------------------------------

it('build() devolve fases: 6 colunas na ordem canônica com key/label/cards', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $out = app(ForjaQuadroService::class)->build($projectId);

    expect($out)->toBeArray()->toHaveKey('fases');
    expect($out['fases'])->toBeArray()->toHaveCount(6);

    // Ordem + presença das fases.
    expect(array_column($out['fases'], 'key'))->toBe(forjaFasesEsperadas());

    foreach ($out['fases'] as $col) {
        expect($col)->toHaveKeys(['key', 'label', 'cards']);
        expect($col['label'])->toBeString()->not->toBe('');
        expect($col['cards'])->toBeArray();

        foreach ($col['cards'] as $card) {
            expect($card)->toHaveKeys(['display_id', 'title', 'tipo', 'onda']);
            expect($card['display_id'])->toBeString()->not->toBe('');
            expect($card['title'])->toBeString();
        }
    }
});

it('build() distribui os cards do seeder pelas colunas (board não fica vazio)', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $out = app(ForjaQuadroService::class)->build($projectId);

    // active() inclui backlog/todo/doing/review/blocked (exclui done/cancelled).
    // O seeder não tem done/cancelled → todos os 14 tickets viram cards.
    $totalCards = array_sum(array_map(fn ($c) => count($c['cards']), $out['fases']));
    expect($totalCards)->toBe(14);

    // FORJA-137 tem forja_fase=F0 → deve cair na coluna F0.
    $colF0 = collect($out['fases'])->firstWhere('key', 'F0');
    $idsF0 = array_column($colF0['cards'], 'display_id');
    expect($idsF0)->toContain('FORJA-137');
});

it('build() joga proposta sem fase (forja_fase null) no fallback F0', function () {
    (new ForjaDemoTicketsSeeder())->run();
    $projectId = (int) DB::table('mcp_jira_projects')->where('key', 'FORJA')->value('id');

    $out = app(ForjaQuadroService::class)->build($projectId);

    // FORJA-152 é proposta em triagem (fase=null no seeder) → fallback F0.
    $colF0 = collect($out['fases'])->firstWhere('key', 'F0');
    $idsF0 = array_column($colF0['cards'], 'display_id');
    expect($idsF0)->toContain('FORJA-152');
});

// -------------------------------------------------------------------------
// 2. build(null) — board esqueleto (6 colunas, cards vazios)
// -------------------------------------------------------------------------

it('build(null) → 6 colunas com cards vazios (sem dado fantasma)', function () {
    $out = app(ForjaQuadroService::class)->build(null);

    expect($out)->toHaveKey('fases');
    expect($out['fases'])->toHaveCount(6);
    expect(array_column($out['fases'], 'key'))->toBe(forjaFasesEsperadas());

    foreach ($out['fases'] as $col) {
        expect($col)->toHaveKeys(['key', 'label', 'cards']);
        expect($col['cards'])->toBe([], "coluna {$col['key']} deveria vir vazia sem project FORJA");
    }
});

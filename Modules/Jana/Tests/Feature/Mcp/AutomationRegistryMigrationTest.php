<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * ADR 0234 (Onda 1.1) — smoke das migrations reais (não o schema hand-rolled
 * do AutomationRegistrySyncTest). Verifica up() + idempotência (hasTable guard)
 * + down() + FK contra o SQLite :memory: do harness.
 */
function loadMigration(string $file)
{
    return require base_path("Modules/Jana/Database/Migrations/{$file}");
}

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('mcp_automation_runs');
    Schema::dropIfExists('mcp_automations');
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_automation_runs');
    Schema::dropIfExists('mcp_automations');
});

test('migration up() cria mcp_automations com colunas da ADR 0234', function () {
    $m = loadMigration('2026_05_29_100001_create_mcp_automations_table.php');
    $m->up();

    expect(Schema::hasTable('mcp_automations'))->toBeTrue()
        ->and(Schema::hasColumns('mcp_automations', [
            'id', 'slug', 'business_id', 'tipo', 'gatilho', 'descricao',
            'arquivo', 'owner', 'governed_by_adr', 'enabled',
            'last_run_at', 'last_status', 'last_detail', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('migration up() é idempotente (rodar 2x não quebra — hasTable guard)', function () {
    $m = loadMigration('2026_05_29_100001_create_mcp_automations_table.php');
    $m->up();
    $m->up(); // não deve lançar (guard Schema::hasTable)

    expect(Schema::hasTable('mcp_automations'))->toBeTrue();
});

test('migration down() dropa mcp_automations', function () {
    $m = loadMigration('2026_05_29_100001_create_mcp_automations_table.php');
    $m->up();
    $m->down();

    expect(Schema::hasTable('mcp_automations'))->toBeFalse();
});

test('migration runs up()/down() + idempotência', function () {
    $automations = loadMigration('2026_05_29_100001_create_mcp_automations_table.php');
    $runs = loadMigration('2026_05_29_100002_create_mcp_automation_runs_table.php');

    $automations->up();
    $runs->up();
    $runs->up(); // idempotente

    expect(Schema::hasTable('mcp_automation_runs'))->toBeTrue()
        ->and(Schema::hasColumns('mcp_automation_runs', [
            'id', 'automation_id', 'ran_at', 'status', 'detail', 'actor',
        ]))->toBeTrue();

    $runs->down();
    expect(Schema::hasTable('mcp_automation_runs'))->toBeFalse();
});

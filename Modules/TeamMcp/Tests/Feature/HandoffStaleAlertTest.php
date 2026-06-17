<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\TeamMcp\Entities\CoworkHandoff;

uses(Tests\TestCase::class);

/**
 * PR-4 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — GUARD do handoff:stale-alert.
 *
 * Provas ("Pronto quando" do PR-4 — anti feedback-void):
 *   1. pending > N dias → 1 notificação due_soon pro ops, listando o slug velho;
 *      pending fresco e applied NÃO entram.
 *   2. idempotência: rodar 2× no mesmo dia → 1 notificação (sem spam).
 *   3. zero stale → zero notificação.
 *   4. --days respeitado (10d não pega um de 5d).
 *
 * Helpers com nomes ÚNICOS (prefixo stale*) pra não colidir com HandoffIngestTest /
 * HandoffToolsTest no mesmo processo Pest. Tabelas sintéticas sqlite-friendly.
 *
 * @see Modules\TeamMcp\Console\Commands\HandoffStaleAlertCommand
 */

function staleMkCoworkTable(): void
{
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
    Schema::create('cowork_handoffs', function ($t) {
        $t->bigIncrements('id');
        $t->string('slug', 120);
        $t->unsignedInteger('version')->default(1);
        $t->string('tela', 160)->default('');
        $t->string('status', 16)->default('pending');
        $t->string('audited_against', 40)->nullable();
        $t->longText('body_md');
        $t->json('files_json');
        $t->char('source_hash', 64);
        $t->char('sig', 64);
        $t->string('created_by', 40)->default('CC');
        $t->timestamp('created_at')->nullable();
        $t->timestamp('applied_at')->nullable();
        $t->string('applied_by', 60)->nullable();
        $t->text('pr_url')->nullable();
        $t->json('gate_status')->nullable();
        $t->unique(['slug', 'version']);
        $t->index('status');
    });
}

function staleMkInboxTable(): void
{
    if (Schema::hasTable('mcp_inbox_notifications')) {
        Schema::drop('mcp_inbox_notifications');
    }
    Schema::create('mcp_inbox_notifications', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('user_id');
        $t->string('type', 32);
        $t->string('task_id', 40)->nullable();
        $t->unsignedBigInteger('actor_id')->nullable();
        $t->text('body')->nullable();
        $t->json('payload')->nullable();
        $t->timestamp('read_at')->nullable();
        $t->timestamps();
    });
}

function staleMkPending(string $slug, int $ageDays, string $status = 'pending'): void
{
    CoworkHandoff::create([
        'slug'        => $slug,
        'version'     => 1,
        'tela'        => 'Atendimento/CaixaUnificada',
        'status'      => $status,
        'body_md'     => '## ONDA A',
        'files_json'  => ['x.css'],
        'source_hash' => str_repeat('a', 64),
        'sig'         => str_repeat('b', 64),
        'created_by'  => 'CC',
        'created_at'  => now()->subDays($ageDays),
    ]);
}

const STALE_OPS_USER = 99;

beforeEach(function () {
    staleMkCoworkTable();
    staleMkInboxTable();
    config(['admin.wagner_user_id' => STALE_OPS_USER]);
});

afterEach(function () {
    foreach (['cowork_handoffs', 'mcp_inbox_notifications'] as $tbl) {
        if (Schema::hasTable($tbl)) {
            Schema::drop($tbl);
        }
    }
});

it('pending > 3d → 1 notificação due_soon pro ops; fresco e applied NÃO entram', function () {
    staleMkPending('velho', ageDays: 5);
    staleMkPending('fresco', ageDays: 1);
    staleMkPending('ja-aplicado', ageDays: 9, status: 'applied');

    $this->artisan('handoff:stale-alert')->assertExitCode(0);

    $notes = McpInboxNotification::where('user_id', STALE_OPS_USER)->get();
    expect($notes)->toHaveCount(1);
    expect($notes[0]->type)->toBe('due_soon');
    expect($notes[0]->body)->toContain('velho');
    expect($notes[0]->body)->not->toContain('fresco');
    expect($notes[0]->body)->not->toContain('ja-aplicado');
});

it('idempotência: 2 runs no mesmo dia → 1 notificação (sem spam)', function () {
    staleMkPending('velho', ageDays: 5);

    $this->artisan('handoff:stale-alert')->assertExitCode(0);
    $this->artisan('handoff:stale-alert')->assertExitCode(0);

    expect(McpInboxNotification::where('user_id', STALE_OPS_USER)->count())->toBe(1);
});

it('zero stale → zero notificação', function () {
    staleMkPending('fresco', ageDays: 1);

    $this->artisan('handoff:stale-alert')->assertExitCode(0);

    expect(McpInboxNotification::count())->toBe(0);
});

it('--days=10 não pega pending de 5d', function () {
    staleMkPending('cinco-dias', ageDays: 5);

    $this->artisan('handoff:stale-alert', ['--days' => 10])->assertExitCode(0);

    expect(McpInboxNotification::count())->toBe(0);
});

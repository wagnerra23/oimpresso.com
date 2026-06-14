<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\GitTaskLinkerService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->svc = new GitTaskLinkerService();
});

it('extrai refs de commit message com 1 verb', function () {
    $msg = "feat(copiloto): add semantic cache fixes COPI-42";
    $refs = $this->svc->extractRefsFromMessage($msg);

    expect($refs)->toHaveCount(1);
    expect($refs[0]['verb'])->toBe('fixes');
    expect($refs[0]['key'])->toBe('COPI');
    expect($refs[0]['number'])->toBe(42);
});

it('extrai múltiplos refs incluindo aliases', function () {
    $msg = "Closes COPI-1 fix nfse-2 refs FIN-99 resolves: INFRA-7";
    $refs = $this->svc->extractRefsFromMessage($msg);

    expect($refs)->toHaveCount(4);
    $verbs = array_column($refs, 'verb');
    expect($verbs)->toContain('closes', 'fixes', 'refs', 'resolves');
});

it('ignora msg sem refs válidos', function () {
    expect($this->svc->extractRefsFromMessage('chore: bump deps'))->toBe([]);
    expect($this->svc->extractRefsFromMessage(''))->toBe([]);
    expect($this->svc->extractRefsFromMessage('lowercase fixes copi-42'))->toBe([]);
});

// Bug #1 (memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md) — regex puro (sem DB)
// vive em GitTaskLinkerRegexTest.php. Aqui só o end-to-end (precisa de RefreshDatabase).

it('Bug#1: end-to-end — commit parentético (US-X-N) em main fecha task como done', function () {
    $proj = McpProject::create(['key' => 'WA', 'name' => 'Whatsapp', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier(); // ex: WA-1

    $task = McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'WA',
        'title' => 'Test bracket close',
        'status' => 'doing',
        'priority' => 'p0',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    // Mesmo formato real produzido por commits do Wagner ([W])
    $idNumber = (int) explode('-', $id)[1];
    $idPadded = str_pad((string) $idNumber, 3, '0', STR_PAD_LEFT);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => 'abc1234567890',
            'message' => "feat(whatsapp): mídia outbound (US-WA-{$idPadded}) [W] (#707)",
            'author' => ['username' => 'wagner'],
            'timestamp' => now()->toIso8601String(),
        ]],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $stats = $this->svc->handlePushEvent($payload);

    expect($stats['links_created'])->toBe(1);
    expect($stats['tasks_updated'])->toBe(1);
    expect($task->fresh()->status)->toBe('done');
    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('linka commit fixes COPI-1 e atualiza status pra done quando push em main', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    $task = McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test fixes',
        'status' => 'doing',
        'priority' => 'p1',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => 'abc1234567890',
            'message' => "fix(copiloto): patch fixes {$id}",
            'author' => ['username' => 'wagner'],
            'timestamp' => now()->toIso8601String(),
        ]],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $stats = $this->svc->handlePushEvent($payload);

    expect($stats['links_created'])->toBe(1);
    expect($stats['tasks_updated'])->toBe(1);
    expect($task->fresh()->status)->toBe('done');
    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('linka commit fixes em branch feature pra status review (não main)', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test feature branch',
        'status' => 'doing',
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'ref' => "refs/heads/{$id}-feature-x",
        'commits' => [[
            'id' => 'abc1234567890',
            'message' => "feat: closes {$id}",
            'author' => ['username' => 'wagner'],
            'timestamp' => now()->toIso8601String(),
        ]],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $this->svc->handlePushEvent($payload);

    expect(McpTask::where('identifier', $id)->first()->status)->toBe('review');
});

it('refs sem fix-verb cria link mas não muda status', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test refs only',
        'status' => 'doing',
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => 'abc1234567890',
            'message' => "docs: update README refs {$id}",
            'author' => ['username' => 'wagner'],
            'timestamp' => now()->toIso8601String(),
        ]],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $stats = $this->svc->handlePushEvent($payload);

    expect($stats['links_created'])->toBe(1);
    expect($stats['tasks_updated'])->toBe(0); // refs not fixes — no status change
    expect(McpTask::where('identifier', $id)->first()->status)->toBe('doing');
});

it('idempotente: mesmo commit_sha + task + action = no-op no segundo run', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test idempotência',
        'status' => 'doing',
        'priority' => 'p2',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => 'abc1234567890',
            'message' => "fix: closes {$id}",
            'author' => ['username' => 'wagner'],
            'timestamp' => now()->toIso8601String(),
        ]],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $first = $this->svc->handlePushEvent($payload);
    $second = $this->svc->handlePushEvent($payload);

    expect($first['links_created'])->toBe(1);
    expect($second['links_created'])->toBe(0);
});

it('PR opened mete task em review', function () {
    $proj = McpProject::create(['key' => 'NFSE', 'name' => 'NFSe', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'NFSE',
        'title' => 'Test PR opened',
        'status' => 'doing',
        'priority' => 'p1',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'action' => 'opened',
        'pull_request' => [
            'number' => 42,
            'title' => "feat: closes {$id}",
            'body' => 'Long description.',
            'merged' => false,
            'head' => ['ref' => "{$id}-branch"],
            'user' => ['login' => 'eliana'],
            'updated_at' => now()->toIso8601String(),
        ],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $stats = $this->svc->handlePullRequestEvent($payload);

    expect($stats['links_created'])->toBe(1);
    expect($stats['tasks_updated'])->toBe(1);
    expect(McpTask::where('identifier', $id)->first()->status)->toBe('review');
});

it('PR merged em main mete task em done', function () {
    $proj = McpProject::create(['key' => 'COPI', 'name' => 'Copiloto', 'status' => 'active']);
    $id = $proj->allocateNextIdentifier();
    McpTask::create([
        'task_id' => $id,
        'identifier' => $id,
        'project_id' => $proj->id,
        'module' => 'COPI',
        'title' => 'Test PR merged',
        'status' => 'review',
        'priority' => 'p0',
        'estimate_unit' => 'points',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
    ]);

    $payload = [
        'action' => 'closed',
        'pull_request' => [
            'number' => 42,
            'title' => "fix: fixes {$id}",
            'body' => '',
            'merged' => true,
            'head' => ['ref' => "{$id}-branch"],
            'user' => ['login' => 'wagner'],
            'updated_at' => now()->toIso8601String(),
        ],
        'repository' => ['full_name' => 'wagnerra23/oimpresso.com'],
    ];

    $stats = $this->svc->handlePullRequestEvent($payload);

    expect($stats['tasks_updated'])->toBe(1);
    expect(McpTask::where('identifier', $id)->first()->status)->toBe('done');
    expect(McpTask::where('identifier', $id)->first()->completed_at)->not->toBeNull();
});

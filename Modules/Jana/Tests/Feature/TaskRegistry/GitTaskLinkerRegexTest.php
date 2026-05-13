<?php

declare(strict_types=1);

use Modules\Jana\Services\TaskRegistry\GitTaskLinkerService;

/**
 * Bug #1 (memory/requisitos/Jana/BUGS-MCP-SYNC-2026-05-13.md) — convenção real
 * oimpresso usa parentético "(US-XXX-NNN)" em commits ~99% das vezes; regex antiga
 * só aceitava verb explícito "closes/fixes <KEY>-<NUM>" → 100% PRs ficavam todo
 * pós-merge. Suite cobre só extractRefsFromMessage() (regex puro, sem DB).
 *
 * Suite end-to-end (com RefreshDatabase) vive em GitTaskLinkerServiceTest.php.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->svc = new GitTaskLinkerService();
});

it('Bug#1: casa padrão parentético (US-WA-042) — convenção real oimpresso', function () {
    $refs = $this->svc->extractRefsFromMessage("feat(whatsapp): mídia outbound (US-WA-042) [W] (#707)");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['verb'])->toBe('bracket');
    expect($refs[0]['key'])->toBe('WA');
    expect($refs[0]['number'])->toBe(42);
});

it('Bug#1: casa (US-SELL-029) e ignora PR number (#707)', function () {
    $refs = $this->svc->extractRefsFromMessage("feat(sells): Grade (US-SELL-029) [F] (#707)");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['key'])->toBe('SELL');
    expect($refs[0]['number'])->toBe(29);
});

it('Bug#1: casa padrão colchete [US-NFE-061]', function () {
    $refs = $this->svc->extractRefsFromMessage("[US-NFE-061] feat: emissão NFCe");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['key'])->toBe('NFE');
    expect($refs[0]['number'])->toBe(61);
});

it('Bug#1: casa bracket sem prefix US- (compat legacy)', function () {
    $refs = $this->svc->extractRefsFromMessage("feat(copi): refactor (COPI-42) [W]");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['key'])->toBe('COPI');
    expect($refs[0]['number'])->toBe(42);
});

it('Bug#1: regressão — closes US-NFE-061 com US- prefix continua funcionando', function () {
    $refs = $this->svc->extractRefsFromMessage("fix: Closes US-NFE-061");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['verb'])->toBe('closes');
    expect($refs[0]['key'])->toBe('NFE');
    expect($refs[0]['number'])->toBe(61);
});

it('Bug#1: regressão — fixes COPI-42 (legacy sem US- prefix) continua funcionando', function () {
    $refs = $this->svc->extractRefsFromMessage("fix(copiloto): patch fixes COPI-42");

    expect($refs)->toHaveCount(1);
    expect($refs[0]['verb'])->toBe('fixes');
    expect($refs[0]['key'])->toBe('COPI');
    expect($refs[0]['number'])->toBe(42);
});

it('Bug#1: ignora padrões inválidos — "Closes  " vazio, PR number puro (#999), chore', function () {
    expect($this->svc->extractRefsFromMessage('Closes  '))->toBe([]);
    expect($this->svc->extractRefsFromMessage('feat: foo (#999)'))->toBe([]);
    expect($this->svc->extractRefsFromMessage('chore: bump deps'))->toBe([]);
    expect($this->svc->extractRefsFromMessage(''))->toBe([]);
});

it('Bug#1: múltiplos refs no mesmo commit', function () {
    $refs = $this->svc->extractRefsFromMessage("feat: (US-WA-042) depends on (US-NFE-061), closes COPI-1");

    expect($refs)->toHaveCount(3);
    $keys = array_column($refs, 'key');
    expect($keys)->toContain('WA', 'NFE', 'COPI');
});

<?php

/**
 * ADR 0076 (Fase 1+2) — testes anti-regressão do SkillsService.
 *
 * Cobertura:
 *   - listFromFilesystem() retorna ≥ 10 skills (sanity check do glob)
 *   - findBySlug em skill conhecida retorna frontmatter + body
 *   - findBySlug com slug inexistente retorna null
 *   - findBySlug rejeita slug malformado (proteção path traversal)
 *
 * NÃO testa o caminho DB aqui (precisaria migration + seed em SQLite, e
 * migrations UltimatePOS quebram em SQLite — gotcha conhecido). Caminho DB
 * fica coberto em SkillsControllerTest com Schema::create direto.
 */

use Modules\ADS\Services\SkillsService;

it('lista skills do filesystem (sanity check ≥ 10)', function () {
    $service = new SkillsService();
    $skills = $service->listAll();

    expect($skills)->toBeArray();
    expect(count($skills))->toBeGreaterThanOrEqual(10,
        'Esperado pelo menos 10 skills em .claude/skills/. Achei: '.count($skills));

    // Cada skill deve ter os campos obrigatórios
    foreach ($skills as $s) {
        expect($s)->toHaveKeys(['slug', 'name', 'description', 'git_path', 'body_chars', 'source']);
        expect($s['slug'])->toBeString()->not->toBeEmpty();
        expect($s['body_chars'])->toBeGreaterThan(0);
    }
});

it('findBySlug retorna skill conhecida com frontmatter + body', function () {
    $service = new SkillsService();
    $skill = $service->findBySlug('sidebar-menu-arch');

    expect($skill)->not->toBeNull('Skill sidebar-menu-arch deveria existir');
    expect($skill['slug'])->toBe('sidebar-menu-arch');
    expect($skill['frontmatter'])->toBeArray();
    expect($skill['frontmatter']['name'] ?? null)->not->toBeNull();
    expect($skill['body'])->toBeString()->not->toBeEmpty();
    expect($skill['git_path'])->toContain('sidebar-menu-arch');
});

it('findBySlug retorna null pra slug inexistente', function () {
    $service = new SkillsService();
    $skill = $service->findBySlug('skill-que-nao-existe-zzz');

    expect($skill)->toBeNull();
});

it('findBySlug rejeita slug malformado (path traversal)', function () {
    $service = new SkillsService();

    expect($service->findBySlug('../etc/passwd'))->toBeNull();
    expect($service->findBySlug('foo/bar'))->toBeNull();
    expect($service->findBySlug('UPPERCASE'))->toBeNull();
    expect($service->findBySlug(''))->toBeNull();
    expect($service->findBySlug('-starts-with-dash'))->toBeNull();
});

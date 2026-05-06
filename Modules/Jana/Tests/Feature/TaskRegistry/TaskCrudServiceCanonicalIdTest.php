<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * Trava o bug de mapping module→prefix descoberto em 2026-05-06 quando
 * /comparativo RecurringBilling tentou criar US e o tool gerou
 * US-RECURRINGBILLING-001 (não bate com US-RB-NNN no SPEC).
 *
 * Fix: TaskCrudService::detectarPrefixoSpec() lê o prefixo curto do
 * próprio SPEC.md ao invés de usar strtoupper($module).
 */

function tcSpecDir(string $module): string
{
    $dir = base_path("memory/requisitos/{$module}");
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    return $dir;
}

function tcWriteSpec(string $module, string $body): string
{
    $dir = tcSpecDir($module);
    $path = $dir . '/SPEC.md';
    file_put_contents($path, $body);
    return $path;
}

afterEach(function () {
    foreach (['__TestCanonicalA', '__TestCanonicalB'] as $m) {
        $dir = base_path("memory/requisitos/{$m}");
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

it('detecta prefixo curto do SPEC.md ao invés de usar nome do módulo', function () {
    // SPEC.md usa prefixo "RB" — módulo se chama "__TestCanonicalA" (uppercased
    // seria TESTCANONICALA, gerando IDs que não casariam)
    tcWriteSpec('__TestCanonicalA', "# SPEC\n\n### US-RB-001 · Algo\n\n### US-RB-007 · Outro\n");

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    $next = $reflect->invoke($svc, '__TestCanonicalA');

    expect($next)->toBe('US-RB-008'); // detectou RB + max(007)+1
});

it('cai no fallback strtoupper quando SPEC não tem US-XX-NNN', function () {
    tcWriteSpec('__TestCanonicalB', "# SPEC vazio sem stories ainda\n");

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    $next = $reflect->invoke($svc, '__TestCanonicalB');

    // Sem prefixo curto → usa strtoupper($module) e começa em 001
    expect($next)->toBe('US-__TESTCANONICALB-001');
});

it('considera SPEC quando DB está atrás do SPEC (out-of-sync)', function () {
    // DB sem nada do módulo; SPEC.md já tem US-RB-040 escrito à mão
    tcWriteSpec('__TestCanonicalA', "# SPEC\n\n### US-RB-001 · X\n\n### US-RB-040 · Y\n");

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    expect($reflect->invoke($svc, '__TestCanonicalA'))->toBe('US-RB-041');
});

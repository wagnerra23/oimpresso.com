<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

uses(Tests\TestCase::class);

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

it('considera placeholders em bullets (não só headers) — regressão US-WA-053 / ADR 0134', function () {
    // Cenário real: SPEC tem story detalhada + placeholders bullet em "out of scope".
    // 2026-05-11 deu drift: tasks-create gerou US-WA-053 ignorando bullet
    // "- US-WA-053 — Mover conversa" que existia há 1 dia no SPEC.
    // Fix: regex agora pega ### E - bullets.
    $body = "# SPEC\n\n"
          . "### US-RB-001 · Story detalhada\n\n"
          . "### US-RB-010 · Outra detalhada\n\n"
          . "## Out of scope\n\n"
          . "- US-RB-053 — placeholder bullet\n"
          . "- US-RB-054 — outro placeholder\n"
          . "- US-RB-056 — pulou 055 de propósito\n";
    tcWriteSpec('__TestCanonicalA', $body);

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    // max(headers=010, bullets=056) + 1 = 057
    expect($reflect->invoke($svc, '__TestCanonicalA'))->toBe('US-RB-057');
});

it('regex bullet ignora menções inline em prose (não confunde com declarações)', function () {
    // "ver US-RB-099" no meio de parágrafo NÃO é declaração de ID.
    // Só ^### ou ^- + US-XX-NNN contam.
    $body = "# SPEC\n\n"
          . "### US-RB-005 · Story\n\n"
          . "Aqui a gente menciona US-RB-099 no meio do texto.\n"
          . "Outra linha refere a `US-RB-100` inline (inline code).\n";
    tcWriteSpec('__TestCanonicalA', $body);

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    expect($reflect->invoke($svc, '__TestCanonicalA'))->toBe('US-RB-006');
});

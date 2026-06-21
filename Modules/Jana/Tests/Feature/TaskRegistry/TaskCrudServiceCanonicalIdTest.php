<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

/**
 * Bootstrap mínimo de `mcp_tasks` pros testes que precisam de rows reais no DB.
 * Só cria se ausente — em MySQL real a tabela já existe (guard). Em sqlite :memory:
 * (CI/local) cria o suficiente pro alocador rodar. Padrão idêntico ao shim de
 * activity_log/contacts em tests/Pest.php. Usa string (não enum) pra evitar
 * CHECK constraints do sqlite. Seeds via DB::table — sem disparar LogsActivity.
 */
function tcEnsureMcpTasksTable(): void
{
    if (Schema::hasTable('mcp_tasks')) {
        return;
    }
    Schema::create('mcp_tasks', function ($t) {
        $t->bigIncrements('id');
        $t->string('task_id', 40)->unique();
        $t->string('identifier', 40)->nullable();
        $t->string('module', 80)->nullable();
        $t->string('title', 255)->nullable();
        $t->text('description')->nullable();
        $t->string('status', 20)->default('todo');
        $t->string('owner', 60)->nullable();
        $t->string('sprint', 40)->nullable();
        $t->string('priority', 8)->nullable();
        $t->string('source_path', 500)->nullable();
        $t->timestamp('parsed_at')->nullable();
        $t->timestamps();
    });
}

beforeEach(function () {
    tcEnsureMcpTasksTable();
    // Prefixo fabricado ZZORPH não existe em dado real — isolamento entre runs.
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZORPH-%')->delete();
});

afterEach(function () {
    DB::table('mcp_tasks')->where('task_id', 'LIKE', 'US-ZZORPH-%')->delete();
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

// ─── Rows órfãs no DB (incidente US-RB-052 · 2026-06-20) ───────────────────
//
// O risco real: uma US-* existe SÓ no DB (criada ad-hoc / direto no DB / resíduo
// de sync antigo) e nunca no SPEC. Se o alocador olhar só o SPEC, reusa o ID; o
// webhook sync então casa por task_id e UPDATE-a a órfã (ADR 0144), sobrescrevendo
// title/description em silêncio. A defesa é max(DB, SPEC) + guarda de colisão.

it('pula row órfã no DB ausente do SPEC (regressão incidente US-RB-052 · 2026-06-20)', function () {
    // SPEC vai só até NNN-051 (prefixo fabricado ZZORPH pra não colidir com dado real).
    tcWriteSpec('__TestCanonicalA', "# SPEC\n\n### US-ZZORPH-001 · X\n\n### US-ZZORPH-051 · Y\n");

    // Órfã criada DIRETO no DB, acima do max do SPEC, nunca declarada no SPEC.
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-ZZORPH-052',
        'module' => '__TestCanonicalA',
        'title' => 'Órfã do wagner (nunca no SPEC)',
        'status' => 'todo',
        'source_path' => 'ad-hoc',
        'parsed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    // Sem a defesa: max(nSpec=051)+1 = 052 → colide com a órfã.
    // Com a defesa: max(nDb=052, nSpec=051)+1 = 053.
    expect($reflect->invoke($svc, '__TestCanonicalA'))->toBe('US-ZZORPH-053');
});

it('nunca devolve um task_id que já existe no DB (guarda de colisão)', function () {
    tcWriteSpec('__TestCanonicalA', "# SPEC\n\n### US-ZZORPH-010 · X\n");

    // Bloco órfão no DB acima do SPEC, com buraco em 013. Mesmo cancelled conta —
    // ID não recicla.
    foreach (['US-ZZORPH-011', 'US-ZZORPH-012', 'US-ZZORPH-014'] as $id) {
        DB::table('mcp_tasks')->insert([
            'task_id' => $id,
            'module' => '__TestCanonicalA',
            'title' => 'órfã',
            'status' => 'cancelled',
            'source_path' => 'ad-hoc',
            'parsed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $svc = new TaskCrudService();
    $reflect = (new ReflectionClass(TaskCrudService::class))
        ->getMethod('gerarProximoIdCanonical');
    $reflect->setAccessible(true);

    // max(nDb=014, nSpec=010)+1 = 015, e 015 está livre.
    $next = $reflect->invoke($svc, '__TestCanonicalA');
    expect($next)->toBe('US-ZZORPH-015')
        ->and(DB::table('mcp_tasks')->where('task_id', $next)->exists())->toBeFalse();
});

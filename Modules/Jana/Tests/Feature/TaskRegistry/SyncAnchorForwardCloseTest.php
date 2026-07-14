<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\SpecAnchorClassifier;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class);

/**
 * ADR 0337 (emenda cirúrgica à 0144) — forward-close por âncora verificada.
 *
 * O DB é canon de estado vivo (ADR 0144), MAS a âncora `**Implementado em:**
 * ...verificado@sha` é a fonte de done-ness (ADR 0302/0273). Quando o SPEC declara
 * `status: done` E a âncora é `anchored_ok`, o sync CARREGA o veredito do git pro
 * card (todo/doing/review → done) — só fecha-pra-frente, nunca reabre. Fecha o
 * split-brain que deixou a US-FIN-031 8 dias `todo` no MCP com o código já em prod.
 *
 * Cobertura em 2 modos (espelha TaskParserPreservaEstadoVivoTest / ADR 0144):
 *  - Unit (sem DB) — travam o classificador puro + o gatilho puro. Rodam em qualquer lugar.
 *  - Integração (skip local) — o forward-close real via syncAll. Rodam em CT 100/MySQL
 *    (RefreshDatabase com SQLite quebra na migration legacy ALTER TABLE MODIFY ENUM).
 */

// ─── Unit: SpecAnchorClassifier (núcleo puro, path-existence injetada) ───────

function anchorBlock(string $anchorLine): string
{
    return "\n> owner: wagner · status: done · priority: p1\n\n{$anchorLine}\nDescrição qualquer.\n";
}

it('classifica anchored_ok + captura sha quando âncora canônica e paths existem', function () {
    $c = new SpecAnchorClassifier();
    $p1 = 'resources/js/Pages/Financeiro/Unificado/Index.tsx';
    $p2 = 'Modules/Financeiro/Http/Controllers/UnificadoController.php';
    $linha = "**Implementado em:** `{$p1}` · `{$p2}` · verificado@ec17185 (2026-07-06) — checkbox + `bulk_*` audit";

    $out = $c->classify(anchorBlock($linha), fn (string $p): bool => in_array($p, [$p1, $p2], true));

    expect($out['state'])->toBe('anchored_ok')
        ->and($out['sha'])->toBe('ec17185')
        // a nota livre depois da data tem `bulk_*` (backtick SEM '/') — NÃO vira path
        ->and($out['paths'])->toBe([$p1, $p2]);
});

it('classifica anchored_dead quando um path da âncora não existe no disco', function () {
    $c = new SpecAnchorClassifier();
    $vivo = 'Modules/Financeiro/X.php';
    $morto = 'Modules/Financeiro/NaoExiste.php';
    $linha = "**Implementado em:** `{$vivo}` · `{$morto}` · verificado@abc1234 (2026-07-06)";

    $out = $c->classify(anchorBlock($linha), fn (string $p): bool => $p === $vivo);

    expect($out['state'])->toBe('anchored_dead')
        ->and($out['sha'])->toBeNull();
});

it('classifica pendente / parcial / placeholder / sem_campo — nenhum habilita close', function () {
    $c = new SpecAnchorClassifier();
    $always = fn (string $p): bool => true;

    expect($c->classify(anchorBlock('**Implementado em:** _pendente_'), $always)['state'])->toBe('pendente');
    expect($c->classify(anchorBlock('**Implementado em:** _parcial_ · `Modules/X/Y.php` · verificado@abc1234 (2026-07-06)'), $always)['state'])->toBe('parcial');
    expect($c->classify(anchorBlock('**Implementado em:** _[path]_ a criar'), $always)['state'])->toBe('placeholder');
    expect($c->classify("\n> status: done\n\nSem âncora aqui.\n", $always)['state'])->toBe('sem_campo');
});

it('classifica anchored_dead quando preenchido fora da forma canônica (sem verificado@)', function () {
    $c = new SpecAnchorClassifier();
    // paths existem mas falta o token `verificado@<sha> (data)` → não-confiável pro close
    $out = $c->classify(anchorBlock('**Implementado em:** `Modules/X/Y.php`'), fn (string $p): bool => true);

    expect($out['state'])->toBe('anchored_dead')
        ->and($out['sha'])->toBeNull();
});

// ─── Unit: gatilho puro deveFecharPorAncora (sem I/O) ────────────────────────

it('deveFecharPorAncora: fecha só com card ativo + SPEC done + anchored_ok + sha', function () {
    $svc = new TaskParserService();

    // ✓ casos que FECHAM (qualquer estado ativo)
    expect($svc->deveFecharPorAncora('todo', 'done', 'anchored_ok', 'ec17185'))->toBeTrue();
    expect($svc->deveFecharPorAncora('doing', 'done', 'anchored_ok', 'ec17185'))->toBeTrue();
    expect($svc->deveFecharPorAncora('review', 'done', 'anchored_ok', 'ec17185'))->toBeTrue();
    expect($svc->deveFecharPorAncora('blocked', 'done', 'anchored_ok', 'ec17185'))->toBeTrue();

    // ✗ nunca reabre estado terminal do DB (ADR 0144)
    expect($svc->deveFecharPorAncora('done', 'done', 'anchored_ok', 'ec17185'))->toBeFalse();
    expect($svc->deveFecharPorAncora('cancelled', 'done', 'anchored_ok', 'ec17185'))->toBeFalse();

    // ✗ SPEC não declara done → não fecha (âncora sozinha não basta; é a decisão humana)
    expect($svc->deveFecharPorAncora('todo', 'todo', 'anchored_ok', 'ec17185'))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', null, 'anchored_ok', 'ec17185'))->toBeFalse();

    // ✗ âncora não-verificada → não fecha (fail-closed)
    expect($svc->deveFecharPorAncora('todo', 'done', 'pendente', null))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'done', 'parcial', null))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'done', 'anchored_dead', null))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'done', 'anchored_ok', null))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'done', 'anchored_ok', ''))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'done', null, null))->toBeFalse();
});

it('relatorio de sync expõe o contador fechadas_por_ancora', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))->getMethod('relatorio');
    $reflect->setAccessible(true);

    $rel = $reflect->invoke($svc, 3, 1, 1, 0, 2, 0, ['X' => 3]);

    expect($rel)->toHaveKey('fechadas_por_ancora')
        ->and($rel['fechadas_por_ancora'])->toBe(2);
});

// ─── Integração (skip local — rodam em CT 100/MySQL) ─────────────────────────

function faWriteSpec(string $module, string $body): string
{
    $dir = base_path("memory/requisitos/{$module}");
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    $path = $dir . '/SPEC.md';
    file_put_contents($path, $body);

    return $path;
}

function faCleanup(): void
{
    foreach (['ADR0337A', 'ADR0337B', 'ADR0337C'] as $m) {
        $dir = base_path("memory/requisitos/__TestAdr0337_{$m}");
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
}

it('integração: fecha card todo quando SPEC declara done + âncora anchored_ok', function () {
    afterEach(fn () => faCleanup());
    $module = '__TestAdr0337_ADR0337A';

    // âncora aponta pra arquivos que EXISTEM no repo (this test file + o service)
    $p1 = 'Modules/Jana/Services/TaskRegistry/TaskParserService.php';
    $p2 = 'Modules/Jana/Services/TaskRegistry/SpecAnchorClassifier.php';
    faWriteSpec($module, <<<MD
    ### US-ADR0337A-1 · Feature já entregue

    > owner: wagner · status: done · priority: p1

    **Implementado em:** `{$p1}` · `{$p2}` · verificado@abc1234 (2026-07-06) — entregue

    Descrição.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);

    // card nasce done? não — simula o split-brain: card ficou preso em todo
    McpTask::where('task_id', 'US-ADR0337A-1')->update(['status' => 'todo', 'completed_at' => null, 'acceptance_ref' => null]);

    $rel = $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0337A-1')->first();
    expect($task->status)->toBe('done')
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->acceptance_ref)->toContain('abc1234')
        ->and($rel['fechadas_por_ancora'])->toBeGreaterThanOrEqual(1);
})->skip('requer MySQL — UltimatePOS migration ALTER TABLE MODIFY ENUM não roda em SQLite. Rodar em CT 100 (Tailscale). Unit acima trava o núcleo.');

it('integração: NÃO fecha quando âncora é _pendente_ mesmo com SPEC status done', function () {
    afterEach(fn () => faCleanup());
    $module = '__TestAdr0337_ADR0337B';

    faWriteSpec($module, <<<MD
    ### US-ADR0337B-2 · Diz done mas âncora pendente

    > owner: wagner · status: done · priority: p1

    **Implementado em:** _pendente_

    Descrição.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);
    McpTask::where('task_id', 'US-ADR0337B-2')->update(['status' => 'todo']);

    $rel = $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0337B-2')->first();
    expect($task->status)->toBe('todo')
        ->and($rel['fechadas_por_ancora'])->toBe(0);
})->skip('requer MySQL — vide nota anterior.');

it('integração: NÃO reabre card done quando âncora fica pendente (só fecha-pra-frente)', function () {
    afterEach(fn () => faCleanup());
    $module = '__TestAdr0337_ADR0337C';

    faWriteSpec($module, <<<MD
    ### US-ADR0337C-3 · Já done no DB

    > owner: wagner · status: todo · priority: p1

    **Implementado em:** _pendente_

    Descrição.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);
    McpTask::where('task_id', 'US-ADR0337C-3')->update(['status' => 'done']);

    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0337C-3')->first();
    expect($task->status)->toBe('done');
})->skip('requer MySQL — vide nota anterior.');

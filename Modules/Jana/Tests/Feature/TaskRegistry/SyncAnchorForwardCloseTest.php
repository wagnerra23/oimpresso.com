<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\SpecAnchorClassifier;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class);

/**
 * ADR 0355 (supersede 0302 + 0337) — forward-close por âncora verificada.
 *
 * O DB é canon de estado vivo (ADR 0144), MAS a âncora `**Implementado em:**
 * ...verificado@sha` é a fonte ÚNICA de done-ness (ADR 0273/0302 → 0355).
 *
 * ATÉ 2026-07-28 o gatilho exigia TAMBÉM `status: done` no SPEC. A ADR 0355 removeu:
 * a 0302 havia ABOLIDO esse campo ("deixa de existir", "US nova nasce sem status:")
 * enquanto a 0337 o EXIGIA — logo, US nascida CERTA sob a 0302 era incapaz, por
 * construção, de fechar sob a 0337. Efeito medido: 85 US ancoradas paradas.
 *
 * A regra hoje tem 4 condições, e o 2º sinal deixou de ser algo que o autor ESCREVE
 * pra ser algo que a máquina VERIFICA:
 *   1. card ativo (nunca reabre)          3. checkbox `- [ ]` aberto VETA
 *   2. âncora anchored_ok + sha           4. forward-only pela data da âncora
 * O DoD é FALSIFICADOR, nunca confirmador: um `[x]` é ato de 1 caractere sem revisor
 * (commit 7ebe9ea5d7 marcou `[x]` numa linha cujo texto diz "parcial … não há
 * autoprint"). Desprovar é barato e honesto; provar não.
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

it('deveFecharPorAncora: fecha com card ativo + anchored_ok + sha + zero DoD aberto + forward-only', function () {
    $svc = new TaskParserService();
    $hoje = TaskParserService::FORWARD_CLOSE_DESDE;

    // ✓ FECHAM — qualquer estado ativo, âncora verificada, nada aberto, data no recorte
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', $hoje, 0))->toBeTrue();
    expect($svc->deveFecharPorAncora('doing', 'anchored_ok', 'ec17185', $hoje, 0))->toBeTrue();
    expect($svc->deveFecharPorAncora('review', 'anchored_ok', 'ec17185', $hoje, 0))->toBeTrue();
    expect($svc->deveFecharPorAncora('blocked', 'anchored_ok', 'ec17185', '2026-09-01', 0))->toBeTrue();

    // ✗ NUNCA reabre estado terminal do DB (ADR 0144 via 0355 §6)
    expect($svc->deveFecharPorAncora('done', 'anchored_ok', 'ec17185', $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('cancelled', 'anchored_ok', 'ec17185', $hoje, 0))->toBeFalse();

    // ✗ âncora não-verificada → não fecha (fail-closed; o sinal positivo é o único)
    expect($svc->deveFecharPorAncora('todo', 'pendente', null, $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'parcial', null, $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_dead', null, $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', null, $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', '', $hoje, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', null, null, $hoje, 0))->toBeFalse();

    // ✗ DoD ABERTO veta, mesmo com tudo o mais perfeito (ADR 0355 §3 — falsificador)
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', $hoje, 1))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', $hoje, 9))->toBeFalse();

    // ✗ FORWARD-ONLY: âncora anterior à decisão não fecha — o legado é backlog
    //   enumerado, não fechamento em massa por mudança de regra (ADR 0355 §4)
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', '2026-07-27', 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', '2026-07-02', 0))->toBeFalse();
    // data ausente/vazia = fail-closed
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', null, 0))->toBeFalse();
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', '', 0))->toBeFalse();
});

it('deveFecharPorAncora: o `status:` do SPEC NÃO tem leitor (ADR 0355 §2)', function () {
    $svc = new TaskParserService();
    $hoje = TaskParserService::FORWARD_CLOSE_DESDE;

    // A assinatura não recebe mais `specStatus` — este teste trava a REGRESSÃO de
    // alguém re-introduzir o campo que a 0302 aboliu e a 0337 exigia (a contradição
    // que travou 85 US). Se voltar, a aridade muda e isto quebra.
    $params = (new ReflectionMethod(TaskParserService::class, 'deveFecharPorAncora'))->getParameters();
    $nomes = array_map(static fn ($p) => $p->getName(), $params);

    expect($nomes)->not->toContain('specStatus')
        ->and($nomes)->toBe(['dbStatus', 'anchorState', 'anchorSha', 'anchorData', 'dodAberto']);

    // E o comportamento: âncora sozinha (sem declaração nenhuma) FECHA.
    expect($svc->deveFecharPorAncora('todo', 'anchored_ok', 'ec17185', $hoje, 0))->toBeTrue();
});

it('SpecAnchorClassifier devolve a data da âncora (ADR 0355 §4 — recorte forward-only)', function () {
    $c = new SpecAnchorClassifier();
    $ok = $c->classify(anchorBlock('**Implementado em:** `composer.json` · verificado@abc1234 (2026-07-28)'), static fn () => true);

    expect($ok['state'])->toBe('anchored_ok')
        ->and($ok['data'])->toBe('2026-07-28');

    // shape uniforme: quem não ancora devolve data null (aditivo, não quebra consumidor)
    $pend = $c->classify(anchorBlock('**Implementado em:** _pendente_'), static fn () => true);
    expect($pend)->toHaveKey('data')->and($pend['data'])->toBeNull();
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

it('integração: fecha card todo com âncora anchored_ok dentro do recorte forward-only', function () {
    afterEach(fn () => faCleanup());
    $module = '__TestAdr0337_ADR0337A';

    // âncora aponta pra arquivos que EXISTEM no repo (this test file + o service)
    $p1 = 'Modules/Jana/Services/TaskRegistry/TaskParserService.php';
    $p2 = 'Modules/Jana/Services/TaskRegistry/SpecAnchorClassifier.php';
    faWriteSpec($module, <<<MD
    ### US-ADR0337A-1 · Feature já entregue

    > owner: wagner · status: done · priority: p1

    **Implementado em:** `{$p1}` · `{$p2}` · verificado@abc1234 (2026-07-28) — entregue

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

it('integração: NÃO fecha quando âncora é _pendente_ (âncora é o único sinal positivo)', function () {
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

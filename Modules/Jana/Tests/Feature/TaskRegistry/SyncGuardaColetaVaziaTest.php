<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class);

/**
 * Guarda de coleta vazia no cancelamento em massa do TaskRegistry.
 *
 * O DEFEITO (irmão do que o #6845 fechou no `IndexarMemoryGitParaDb`, com a forma
 * INVERTIDA): em `syncAllInternal`, quando `$reportadasNoSync` está vazio o
 * `whereNotIn('task_id', ...)` é PULADO, e o que sobra no WHERE (`status` +
 * `source_path` + `module`) casa o conjunto inteiro — que o `foreach` cancela.
 * Lá o perigo era `whereNotIn('slug', [])` virar `where 1 = 1`; aqui é a cláusula
 * sumir. Mesma classe, portas opostas.
 *
 * ALCANÇABILIDADE (medida em prod Hostinger, 2026-09-05, sondas somente-leitura):
 *   - sync COMPLETO com coleta vazia cancelaria 662 de 662 tasks do escopo. Nunca
 *     aconteceu: o maior cluster de cancelamento num minuto na história da tabela
 *     é 24 (2026-05-09 20:29) — um sync completo vazio deixaria ~662 num minuto só.
 *   - `--module=jana` (minúsculo) cancelaria 40; `--module=financeiro`, 35.
 *
 * O caminho do `--module` é o alcançável, por uma assimetria que ninguém tinha
 * escrito: a comparação de diretório em PHP é case-SENSITIVE, e `mcp_tasks.module`
 * é `utf8mb4_unicode_ci` — case-INSENSITIVE (provado em prod: `SELECT 'jana' =
 * 'Jana'` devolve 1). A opção `--module` não tem validação nenhuma.
 *
 * O QUE ESTES TESTES DEFENDEM, nas duas pernas que o fix exige:
 *   (1) coleta vazia → NADA cancelado + sinal de erro;
 *   (2) CONTROLE NEGATIVO → o cancelamento legítimo de órfã CONTINUA funcionando
 *       (sem ele, a guarda poderia virar "nunca cancela", que é o outro extremo).
 *
 * O controle negativo tem DUAS formas de propósito, porque a guarda ingênua
 * ("coleta vazia ⇒ aborta") teria falso-positivo medido: 8 dos 61 SPECs de prod
 * (Brief, _DesignSystem, Estoque, FinanceiroAvancado, Garantia, ProductCatalogue,
 * Suporte, VozDoCliente) têm ZERO US legitimamente. Sync parcial de um deles TEM
 * de seguir cancelando — é o `deve cancelar órfã quando o SPEC existe SEM US`.
 *
 * Refs: PR #6845 (o irmão) · ADR 0070 (mcp_tasks é cache repo-wide, sem tenant)
 *       ADR 0144 (DB canon de estado vivo, SPEC template).
 */

// ─── Fixtures ────────────────────────────────────────────────────────────────

const GCV_PREFIXO = '__GuardaColetaVazia';

/** Módulos-fixture criados sob memory/requisitos/ (limpos no afterEach). */
function gcvModulos(): array
{
    return [
        GCV_PREFIXO . 'Caixa',
        GCV_PREFIXO . 'Orfa',
        GCV_PREFIXO . 'SemUs',
    ];
}

function gcvEscreverSpec(string $modulo, string $corpo): void
{
    $dir = base_path('memory/requisitos/' . $modulo);
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    file_put_contents($dir . '/SPEC.md', $corpo);
}

function gcvCriarTask(string $taskId, string $modulo, string $status = 'todo'): McpTask
{
    return McpTask::create([
        'task_id' => $taskId,
        'module' => $modulo,
        'title' => 'Fixture ' . $taskId,
        'status' => $status,
        'source_path' => 'memory/requisitos/' . $modulo . '/SPEC.md#' . $taskId,
        'parsed_at' => now(),
    ]);
}

afterEach(function () {
    foreach (gcvModulos() as $m) {
        $dir = base_path('memory/requisitos/' . $m);
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
    // Limpeza explícita (não dependemos de transação): estes testes escrevem em
    // mcp_tasks, que é cache repo-wide e não é isolado por tenant.
    McpTask::where('task_id', 'LIKE', 'US-GCV%')->delete();
});

// ─── Perna 1 — coleta vazia NÃO cancela e sinaliza erro ──────────────────────

it('sync parcial de módulo SEM diretório não cancela nada e lança', function () {
    // Este é o bite-test âncora, e é INDEPENDENTE de collation de propósito: o
    // módulo não existe no disco, mas existe no DB (diretório removido/renomeado,
    // ou `--module` errado). ANTES do fix, as duas tasks eram canceladas.
    $modulo = GCV_PREFIXO . 'NaoExisteNoDisco';
    expect(is_dir(base_path('memory/requisitos/' . $modulo)))->toBeFalse();

    gcvCriarTask('US-GCV-100', $modulo, 'todo');
    gcvCriarTask('US-GCV-101', $modulo, 'doing');

    $svc = new TaskParserService();

    expect(fn () => $svc->syncAll($modulo))
        ->toThrow(RuntimeException::class, 'cancelamento ABORTADO');

    expect(McpTask::where('task_id', 'US-GCV-100')->value('status'))->toBe('todo')
        ->and(McpTask::where('task_id', 'US-GCV-101')->value('status'))->toBe('doing');
});

it('sync parcial com a CAIXA errada do módulo não cancela nada e lança', function () {
    // O vetor de prod: o diretório existe e tem US, mas o `--module` veio com caixa
    // diferente. O PHP não casa o diretório (case-sensitive) e o WHERE casaria as
    // linhas (utf8mb4_unicode_ci). A guarda aborta ANTES do WHERE, então esta
    // asserção vale em MySQL e em SQLite — não depende da collation.
    $modulo = GCV_PREFIXO . 'Caixa';
    gcvEscreverSpec($modulo, "### US-GCV-200 · Alguma coisa\n\n> status: todo\n");
    gcvCriarTask('US-GCV-200', $modulo, 'doing');

    $svc = new TaskParserService();

    expect(fn () => $svc->syncAll(strtolower($modulo)))
        ->toThrow(RuntimeException::class, 'confira a CAIXA do nome');

    expect(McpTask::where('task_id', 'US-GCV-200')->value('status'))->toBe('doing');
});

// ─── Perna 2 — CONTROLE NEGATIVO: cancelamento legítimo segue funcionando ────

it('controle negativo: segue cancelando órfã quando o SPEC tem US', function () {
    $modulo = GCV_PREFIXO . 'Orfa';
    gcvEscreverSpec($modulo, "### US-GCV-300 · Continua no SPEC\n\n> status: todo\n");

    gcvCriarTask('US-GCV-300', $modulo, 'todo');   // está no SPEC → sobrevive
    gcvCriarTask('US-GCV-301', $modulo, 'todo');   // sumiu do SPEC → órfã, cancela

    $rel = (new TaskParserService())->syncAll($modulo);

    expect($rel['canceladas'])->toBe(1)
        ->and(McpTask::where('task_id', 'US-GCV-301')->value('status'))->toBe('cancelled')
        ->and(McpTask::where('task_id', 'US-GCV-300')->value('status'))->toBe('todo');
});

it('controle negativo: segue cancelando órfã quando o SPEC existe SEM nenhuma US', function () {
    // O falso-positivo que a guarda ingênua criaria. 8 dos 61 SPECs de prod estão
    // exatamente neste estado; sync parcial deles TEM de cancelar, não abortar.
    $modulo = GCV_PREFIXO . 'SemUs';
    gcvEscreverSpec($modulo, "# SPEC sem nenhuma US\n\nSó prosa, nenhum heading US-*.\n");
    gcvCriarTask('US-GCV-400', $modulo, 'todo');

    $rel = (new TaskParserService())->syncAll($modulo);

    expect($rel['canceladas'])->toBe(1)
        ->and(McpTask::where('task_id', 'US-GCV-400')->value('status'))->toBe('cancelled');
});

// ─── Predicado — o ramo do sync COMPLETO ─────────────────────────────────────
//
// Não é satélite: é o predicado que o chokepoint chama (os testes acima provam que
// ele é chamado de lá). Vive aqui porque o 2º disjunto não é alcançável pelo
// filesystem real — exigiria `memory/requisitos` inteiro sem nenhuma US, e a
// árvore de verdade tem 53 SPECs com US.

function gcvDeveAbortar(?string $apenasModulo, int $specs, array $reportadas): bool
{
    $m = (new ReflectionClass(TaskParserService::class))->getMethod('deveAbortarColeta');
    $m->setAccessible(true);

    return $m->invoke(new TaskParserService(), $apenasModulo, $specs, $reportadas);
}

it('predicado: sync COMPLETO com SPECs lidos e zero US aborta (parser quebrado)', function () {
    expect(gcvDeveAbortar(null, 61, []))->toBeTrue();
});

it('predicado: sync COMPLETO com US reconhecidas NÃO aborta', function () {
    expect(gcvDeveAbortar(null, 61, ['US-X-1']))->toBeFalse();
});

it('predicado: sync PARCIAL de SPEC lido sem US NÃO aborta (os 8 legítimos)', function () {
    expect(gcvDeveAbortar('Estoque', 1, []))->toBeFalse();
});

it('predicado: zero SPEC lido aborta nos DOIS modos', function () {
    expect(gcvDeveAbortar(null, 0, []))->toBeTrue()
        ->and(gcvDeveAbortar('QualquerModulo', 0, []))->toBeTrue();
});

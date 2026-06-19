<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

/**
 * PR-fix — fiação do comando design:dossie (resolução de paths/page_id), que os testes
 * unit do Assembler não cobrem. Codifica os 3 furos achados no smoke CT100:
 *   - tela aninhada (`Sub/Index`) não pode virar subpasta em RUNBOOK-/decisoes;
 *   - page_id deriva do `page:` do charter (e do cowork-map) quando falta `page_id:`;
 *   - a pasta do protótipo vem do cowork-map (Sells→vendas), não do nome da tela.
 * Tudo via fixture em tempdir (`jana.dossie_root`) — não toca o repo real, sem git/LLM.
 */

beforeEach(function () {
    $dir = sys_get_temp_dir() . '/dossie_cmd_' . uniqid();
    File::makeDirectory($dir, 0o755, recursive: true);
    test()->root = $dir;
    test()->out = $dir . '/_out.md';
    config(['jana.dossie_root' => $dir]);
});

afterEach(function () {
    if (isset(test()->root) && File::isDirectory(test()->root)) {
        File::deleteDirectory(test()->root);
    }
});

function dossieSeed(string $rel, string $content): void
{
    $abs = test()->root . '/' . $rel;
    File::ensureDirectoryExists(dirname($abs));
    File::put($abs, $content);
}

function dossieRun(string $module, string $tela): string
{
    $code = Artisan::call('design:dossie', ['--module' => $module, '--tela' => $tela, '--out' => test()->out]);
    expect($code)->toBe(0);

    return File::get(test()->out);
}

$charterCaixa = <<<'MD'
---
page: /atendimento/caixa-unificada
component: resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
---

## Mission

Inbox unificada de atendimento omnichannel.

## Goals

- Centralizar canais num só lugar

## Non-Goals

- ❌ Não vazar dados entre tenants
MD;

test('tela aninhada: slug normaliza `/` → `-` (RUNBOOK achado, sem path quebrado)', function () use ($charterCaixa) {
    dossieSeed('resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md', $charterCaixa);
    dossieSeed('memory/requisitos/Atendimento/RUNBOOK-caixaunificada-index.md', "# RUNBOOK caixa\nreceita.");

    $out = dossieRun('Atendimento', 'CaixaUnificada/Index');

    expect($out)
        ->toContain('RUNBOOK-caixaunificada-index.md') // slug normalizado achou o arquivo
        ->not->toContain('caixaunificada/index'); // nunca o path quebrado com `/`
});

test('page_id deriva do `page:` quando o charter não tem `page_id:`', function () use ($charterCaixa) {
    dossieSeed('resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md', $charterCaixa);

    $out = dossieRun('Atendimento', 'CaixaUnificada/Index');

    // antes do fix saía `atendimento-caixaunificada/index` (slug feio com `/`)
    expect($out)->toContain('page_id: atendimento-caixa-unificada');
});

test('page_id vem do cowork-map quando charter não tem page_id nem page:', function () {
    dossieSeed('resources/js/Pages/Macros/Index.charter.md', "---\nowner: wagner\n---\n\n## Mission\n\nMacros.\n");
    dossieSeed('prototipo-ui/cowork-map.json', json_encode([
        'screens' => ['macros' => ['module' => 'Macros', 'page_id' => 'atendimento-macros', 'routes' => []]],
    ]));

    $out = dossieRun('Macros', 'Index');

    expect($out)->toContain('page_id: atendimento-macros');
});

test('pasta do protótipo vem do cowork-map (decisoes achado por chave, não nome da tela)', function () {
    dossieSeed('resources/js/Pages/Sells/Index.charter.md', "---\npage_id: sells-index\n---\n\n## Mission\n\nVendas.\n");
    dossieSeed('prototipo-ui/cowork-map.json', json_encode([
        'screens' => ['vendas' => ['module' => 'Sells', 'page_id' => 'sells-index', 'routes' => []]],
    ]));
    dossieSeed('prototipo-ui/prototipos/vendas/decisoes.md', "# decisoes vendas\n- adotado X");

    $out = dossieRun('Sells', 'Index');

    // tela=Index, mas o protótipo é prototipos/vendas/ — resolvido via cowork-map
    expect($out)->toContain('prototipo-ui/prototipos/vendas/decisoes.md');
});

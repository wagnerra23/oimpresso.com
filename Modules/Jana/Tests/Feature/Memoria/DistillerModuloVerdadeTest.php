<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Services\Memoria\DistillerModuloVerdade;
use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * PR-C do keystone distiller-módulo-verdade (ADR 0291 D-B/D-E · peça 2).
 *
 * Testa o motor "diário → manual" SEM git e SEM prod: eventos injetados +
 * Ai::fakeAgent (LLM determinístico, sem custo) + BRIEFING em dir temporário.
 * Cobre: escreve com carimbo+proveniência · dry-run não escreve · PII recusa ·
 * sem-eventos não chama LLM · sobrescreve (não append) · proveniência no rodapé.
 */

$NOW = '2026-06-19';

function distillerSvc(): DistillerModuloVerdade
{
    return new DistillerModuloVerdade(new PiiRedactor());
}

function evento(string $type, string $ref, ?string $date, array $modules, string $title = ''): array
{
    return compact('type', 'ref', 'date', 'modules', 'title');
}

beforeEach(function () {
    $dir = sys_get_temp_dir() . '/distiller_verdade_' . uniqid();
    File::makeDirectory($dir, 0o755, recursive: true);
    test()->dir = $dir;
    test()->path = $dir . '/BRIEFING.md';
});

afterEach(function () {
    if (isset(test()->dir) && File::isDirectory(test()->dir)) {
        File::deleteDirectory(test()->dir);
    }
});

test('destila e escreve BRIEFING com carimbo + corpo + proveniência no rodapé', function () use ($NOW) {
    Ai::fakeAgent(AnonymousAgent::class, [
        "## Estado atual\nFinanceiro fechando a bridge Sells→fin_titulos.\n\n## Gaps\nBackfill retroativo.",
    ]);
    $events = [evento('session', 'memory/sessions/2026-06-15-bridge.md', '2026-06-15', ['Financeiro'], 'fix bridge')];

    $r = distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    expect($r['status'])->toBe('written');
    expect($r['written'])->toBeTrue();
    expect(File::exists(test()->path))->toBeTrue();

    $c = File::get(test()->path);
    expect($c)->toContain('distilled_at: "2026-06-19"')
        ->toContain('distilled_by: jana:distill-module-truth')
        // campos required do briefing.schema.json (senão memory-schema-gate reprova)
        ->toContain('module: Financeiro')
        ->toContain('status: ')
        ->toContain('updated_at: "2026-06-19"')
        ->toContain('Financeiro fechando a bridge')
        ->toContain('## Proveniência (destilado de)')
        ->toContain('2026-06-15-bridge.md');

    // proveniência fica no RODAPÉ (depois do corpo), não inline
    expect(strpos($c, 'Financeiro fechando a bridge'))->toBeLessThan(strpos($c, '## Proveniência'));
});

test('frontmatter tem os 3 required do schema (module/status/updated_at) — sem porta anterior usa default do enum', function () use ($NOW) {
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nok"]);
    $events = [evento('session', 's.md', '2026-06-15', ['Financeiro'])];

    distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    $c = File::get(test()->path);
    expect($c)->toContain('module: Financeiro')
        ->toContain('updated_at: "2026-06-19"')
        // sem porta prévia → default conservador do enum
        ->toContain('status: em-construcao');
});

test('preserva o status: da porta anterior (não rebaixa módulo em produção ao re-destilar)', function () use ($NOW) {
    File::put(test()->path, "---\nmodule: Financeiro\nstatus: producao\nstatus_nota: live via ROTA LIVRE\nupdated_at: \"2026-01-01\"\n---\n\n# velho");
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nnovo estado destilado"]);
    $events = [evento('session', 's.md', '2026-06-15', ['Financeiro'])];

    distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    $c = File::get(test()->path);
    expect($c)->toContain('status: producao')      // preservou o anterior, não caiu pro default
        ->toContain('updated_at: "2026-06-19"')    // mas carimba a data nova
        ->toContain('novo estado destilado');
});

test('dry-run calcula mas NÃO escreve', function () use ($NOW) {
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nok"]);
    $events = [evento('session', 's.md', '2026-06-15', ['Financeiro'])];

    $r = distillerSvc()->destilar('Financeiro', $events, test()->path, null, true, $NOW);

    expect($r['status'])->toBe('dry');
    expect($r['written'])->toBeFalse();
    expect(File::exists(test()->path))->toBeFalse();
    expect($r['content'])->toContain('distilled_at');
});

test('PII no output da LLM → recusa e preserva a porta original', function () use ($NOW) {
    // LLM "vazou" um CPF sintético — o distiller tem que RECUSAR e não sobrescrever.
    Ai::fakeAgent(AnonymousAgent::class, [
        "## Estado atual\nCliente CPF 123.456.789-09 em aberto.", // pii-allowlist (PII sintética pra testar a recusa)
    ]);
    File::put(test()->path, "PORTA ORIGINAL PRESERVADA");
    $events = [evento('session', 's.md', '2026-06-15', ['Financeiro'])];

    $r = distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    expect($r['status'])->toBe('refused_pii');
    expect($r['written'])->toBeFalse();
    expect($r['pii'])->toHaveKey('CPF');
    expect(File::get(test()->path))->toBe('PORTA ORIGINAL PRESERVADA');
});

test('sem eventos relevantes → no_events, não escreve', function () use ($NOW) {
    Ai::fakeAgent(AnonymousAgent::class, ['NUNCA DEVERIA SER CHAMADO']);
    $events = [evento('session', 's.md', '2026-06-15', ['Crm'])]; // outro módulo

    $r = distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    expect($r['status'])->toBe('no_events');
    expect(File::exists(test()->path))->toBeFalse();
});

test('sobrescreve a porta (mutável, não append)', function () use ($NOW) {
    File::put(test()->path, "VELHO CONTEUDO QUE DEVE SUMIR");
    Ai::fakeAgent(AnonymousAgent::class, ["## Estado atual\nnovo conteudo destilado"]);
    $events = [evento('session', 's.md', '2026-06-15', ['Financeiro'])];

    distillerSvc()->destilar('Financeiro', $events, test()->path, null, false, $NOW);

    $c = File::get(test()->path);
    expect($c)->toContain('novo conteudo destilado')
        ->not->toContain('VELHO CONTEUDO QUE DEVE SUMIR');
});

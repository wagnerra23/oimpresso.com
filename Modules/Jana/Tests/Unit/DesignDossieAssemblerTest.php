<?php

declare(strict_types=1);

use Modules\Jana\Services\Memoria\DesignDossieAssembler;

uses(Tests\TestCase::class);

/**
 * PR-1 da estação de ingestão de design — o assembler PURO do dossiê.
 *
 * Testa que: monta as 8 seções lendo o curado existente; marca AUSENTE o que falta;
 * extrai Mission/Goals do charter + related_adrs do frontmatter; lista persona e
 * feedback; e é DETERMINÍSTICO (re-run idêntico — sem timestamp no corpo). Puro,
 * sem FS/LLM/DB.
 */

function charterFixture(): string
{
    return <<<'MD'
    ---
    page: /sells
    page_id: sells-index
    parent_module: Sells
    related_adrs:
      - 0093-multi-tenant-isolation-tier-0
      - 0190-primary-button-roxo-universal-295
    ---

    # Page Charter — /sells

    ## Mission

    Tela cockpit de vendas do business.

    ## Goals

    - PageHeader v3 com primary roxo
    - 4 KPI cards via Inertia::defer

    ## Non-Goals

    - NÃO emitir SEFAZ sem clique humano

    ## Automation Anti-hooks

    - Tier 0 IRREVOGÁVEL: business_id em toda query

    ## UX Targets

    - p95 first-paint < 800ms
    MD;
}

function dossieCtx(array $over = []): array
{
    return array_replace_recursive([
        'tela' => 'Index',
        'module' => 'Sells',
        'page_id' => 'sells-index',
        'sources' => [
            'charter' => ['path' => 'resources/js/Pages/Sells/Index.charter.md', 'content' => charterFixture()],
            'casos' => ['path' => 'resources/js/Pages/Sells/Index.casos.md', 'content' => null], // greenfield
            'decisoes' => ['path' => 'prototipo-ui/prototipos/vendas/decisoes.md', 'content' => null],
            'runbook' => ['path' => 'memory/requisitos/Sells/RUNBOOK-index.md', 'content' => '# RUNBOOK'],
            'visual_comparison' => ['path' => 'memory/requisitos/Sells/Sells-visual-comparison.md', 'content' => '# VC'],
            'briefing' => ['path' => 'memory/requisitos/Sells/BRIEFING.md', 'content' => '# BRIEFING'],
        ],
        'personas' => ['primary' => 'larissa-rota-livre', 'secondary' => ['kamila-martinho']],
        'feedback' => [['path' => 'memory/reference/feedback-sells-x.md', 'content' => 'reclamação']],
        'padroes' => ['PT-01-Lista'],
    ], $over);
}

test('monta as seções principais + cabeçalho da tela', function () {
    $d = DesignDossieAssembler::assemble(dossieCtx());
    expect($d)
        ->toContain('DOSSIÊ DE TELA — Sells/Index')
        ->toContain('page_id: sells-index')
        ->toContain('1. O que já foi decidido')
        ->toContain('2. Anti-hooks / Tier 0')
        ->toContain('7. Reclamações / feedback')
        ->toContain('8. Persona(s) da tela')
        ->toContain('## Proveniência (fontes lidas)');
});

test('extrai Mission/Goals/Anti-hooks do charter', function () {
    $d = DesignDossieAssembler::assemble(dossieCtx());
    expect($d)
        ->toContain('Tela cockpit de vendas do business')
        ->toContain('4 KPI cards via Inertia::defer')
        ->toContain('Tier 0 IRREVOGÁVEL: business_id em toda query');
});

test('padronizações = related_adrs do charter + PTs passados', function () {
    $d = DesignDossieAssembler::assemble(dossieCtx());
    expect($d)
        ->toContain('0093-multi-tenant-isolation-tier-0')
        ->toContain('0190-primary-button-roxo-universal-295')
        ->toContain('PT-01-Lista');
});

test('marca AUSENTE a fonte que não existe (casos greenfield)', function () {
    $d = DesignDossieAssembler::assemble(dossieCtx());
    expect($d)
        ->toContain('casos:') // na proveniência
        ->toContain('(AUSENTE)')
        ->toContain('Index.casos.md');
});

test('lista persona e feedback', function () {
    $d = DesignDossieAssembler::assemble(dossieCtx());
    expect($d)
        ->toContain('larissa-rota-livre')
        ->toContain('kamila-martinho')
        ->toContain('feedback-sells-x.md');
});

test('é DETERMINÍSTICO — re-run idêntico (sem timestamp no corpo)', function () {
    expect(DesignDossieAssembler::assemble(dossieCtx()))
        ->toBe(DesignDossieAssembler::assemble(dossieCtx()));
});

test('section() extrai o bloco da seção', function () {
    $s = DesignDossieAssembler::section(charterFixture(), 'Mission');
    expect($s)->toContain('**Mission**')->toContain('Tela cockpit de vendas do business');
    expect(DesignDossieAssembler::section(charterFixture(), 'Inexistente'))->toBeNull();
});

test('frontmatterList() lê related_adrs', function () {
    $adrs = DesignDossieAssembler::frontmatterList(charterFixture(), 'related_adrs');
    expect($adrs)->toBe([
        '0093-multi-tenant-isolation-tier-0',
        '0190-primary-button-roxo-universal-295',
    ]);
});

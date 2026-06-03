<?php

declare(strict_types=1);

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\DesignDocsFreshnessChecker;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * DesignDocsFreshnessChecker (ADR 0236 máquina 4 "freshness gate") — estende o
 * freshness-gate do framework de drift (ADR 0220 / 0216) PROS DOCS DE DESIGN.
 *
 * Garante que um doc de design (prototipo-ui/*.md + _DesignSystem/*.md) NÃO fique
 * citando um ADR superseded/deprecated como se vigente, nem com next_review vencido.
 *
 * O núcleo `analisarDoc()` é PURO (conteúdo + mapa de lifecycle + "hoje" injetados),
 * testável sem tocar disco/rede — mesmo padrão de DeployDriftChecker::analisar e
 * MeilisearchSettingsDriftChecker::driftsDoIndice.
 */

// "Hoje" fixo pros testes — determinístico, nunca usa wall-clock.
$hoje = new DateTimeImmutable('2026-05-30');

// Mapa de lifecycle injetado (espelha o real: 0190 superseded por 0235; 0235 ativo;
// UI-0013 ativo; 0099 deprecated/PT "arquivado"; 0190-PT "substituido").
$lifecycleMap = [
    '0190' => 'superseded',
    '0235' => 'ativo',
    '0094' => 'aceito',
    'UI-0013' => 'ativo',
    '0099' => 'arquivado',   // PT deprecated/archived
];

it('implementa o contrato DriftChecker (plugável no framework, não bespoke)', function () {
    $checker = new DesignDocsFreshnessChecker();
    expect($checker)->toBeInstanceOf(DriftChecker::class)
        ->and($checker->name())->toBe('design_docs_freshness')
        ->and($checker->severity())->toBe('medium')
        ->and($checker->enforcement())->toBe('warn')
        ->and($checker->cadence())->toBe('daily')
        ->and($checker->tags())->toContain('design_system');
});

it('está registrado em governance.drift_checkers (roda no governance:audit)', function () {
    expect((array) config('governance.drift_checkers'))
        ->toContain(DesignDocsFreshnessChecker::class);
});

it('doc citando só ADR vigente → sem finding', function () use ($lifecycleMap, $hoje) {
    $content = "# Briefing\n\nSeguir [ADR 0235](../x.md) e ADR 0094. Camada base ADR UI-0013.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/X.md', $content, $lifecycleMap, $hoje);
    expect($f)->toBeEmpty();
});

it('doc citando ADR superseded como vigente → finding medium (caso central)', function () use ($lifecycleMap, $hoje) {
    // Cenário real: briefing cita "ADR 0190" mas 0190 foi superseded por 0235.
    $content = "# Primer\n\nO primary segue ADR 0190 (roxo 295).\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/CLAUDE_COWORK_PRIMER.md', $content, $lifecycleMap, $hoje);

    expect($f)->toHaveCount(1)
        ->and($f[0])->toBeInstanceOf(DriftFinding::class)
        ->and($f[0]->severity)->toBe('medium')
        ->and($f[0]->target)->toBe('prototipo-ui/CLAUDE_COWORK_PRIMER.md')
        ->and($f[0]->target_type)->toBe('design_doc')
        ->and($f[0]->message)->toContain('ADR 0190')
        ->and($f[0]->evidence['category'])->toBe('dead_adr_ref')
        ->and($f[0]->evidence['adr_key'])->toBe('0190')
        ->and($f[0]->evidence['lifecycle'])->toBe('superseded');
});

it('ADR morto citado COMO HISTÓRICO (marcador superseded/substituído/aposentado) → SEM finding (anti-falso-positivo)', function () use ($lifecycleMap, $hoje) {
    // O caso real que motivou o refino: o INDEX-DESIGN-MEMORIAS cita 0190 CORRETAMENTE
    // rotulado como aposentado. Não pode virar falso-positivo diário no governance:audit.
    $linhasHistoricas = [
        "| Cor | **ADR 0235 — roxo** | ADR 0190 (`superseded`) · azul de marca |",
        'ADR 0190 foi substituído por ADR 0235.',
        'ADR 0190 (aposentado — não-usar).',
    ];
    foreach ($linhasHistoricas as $linha) {
        $f = (new DesignDocsFreshnessChecker())->analisarDoc('x.md', $linha . "\n", $lifecycleMap, $hoje);
        expect($f)->toBeEmpty("Menção histórica não deveria flagar: {$linha}");
    }
});

it('doc com 0190 histórico E vigente (linhas distintas) → flaga (≥1 menção vigente basta)', function () use ($lifecycleMap, $hoje) {
    $content = "ADR 0190 (superseded) na tabela de reconciliação.\nMas o primary ainda segue ADR 0190 hoje.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('x.md', $content, $lifecycleMap, $hoje);
    expect($f)->toHaveCount(1)->and($f[0]->evidence['adr_key'])->toBe('0190');
});

it('lifecycle PT "arquivado"/"substituido" também conta como morto', function () use ($lifecycleMap, $hoje) {
    $content = "Ver ADR 0099 (catálogo legacy).\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('memory/requisitos/_DesignSystem/X.md', $content, $lifecycleMap, $hoje);
    expect($f)->toHaveCount(1)
        ->and($f[0]->evidence['lifecycle'])->toBe('arquivado');
});

it('ADR morto citado N vezes no mesmo doc → 1 finding (dedupe por doc)', function () use ($lifecycleMap, $hoje) {
    $content = "ADR 0190 aqui. Mais ADR 0190 ali. E [ADR 0190](x.md) no link.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/X.md', $content, $lifecycleMap, $hoje);
    expect($f)->toHaveCount(1)->and($f[0]->evidence['adr_key'])->toBe('0190');
});

it('namespace UI-NNNN não colide com NNNN de memory/decisions', function () use ($hoje) {
    // UI-0013 vigente, mas 0013 (decisions) está superseded — citar "ADR UI-0013"
    // NÃO pode disparar pelo lifecycle do 0013 numérico.
    $map = ['0013' => 'superseded', 'UI-0013' => 'ativo'];
    $content = "Camada base: ADR UI-0013.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/X.md', $content, $map, $hoje);
    expect($f)->toBeEmpty();

    // E o inverso: citar "ADR 0013" (numérico, superseded) dispara.
    $content2 = "Ver ADR 0013.\n";
    $f2 = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/X.md', $content2, $map, $hoje);
    expect($f2)->toHaveCount(1)->and($f2[0]->evidence['adr_key'])->toBe('0013');
});

it('next_review vencido no frontmatter → finding low', function () use ($lifecycleMap, $hoje) {
    $content = "---\ndoc: X\nstatus: ativo\nnext_review: 2026-05-01\n---\n\n# X\nADR 0235 vigente.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('memory/requisitos/_DesignSystem/X.md', $content, $lifecycleMap, $hoje);

    expect($f)->toHaveCount(1)
        ->and($f[0]->severity)->toBe('low')
        ->and($f[0]->evidence['category'])->toBe('stale_review')
        ->and($f[0]->evidence['next_review'])->toBe('2026-05-01')
        ->and($f[0]->evidence['days_overdue'])->toBe(29)
        ->and($f[0]->message)->toContain('next_review vencido');
});

it('next_review no futuro → sem finding (e tolera comentário inline YAML)', function () use ($lifecycleMap, $hoje) {
    $content = "---\ndoc: X\nnext_review: 2026-08-09  # trimestral\n---\n\n# X\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('memory/requisitos/_DesignSystem/X.md', $content, $lifecycleMap, $hoje);
    expect($f)->toBeEmpty();
});

it('next_review == hoje → sem finding (não vencido)', function () use ($lifecycleMap, $hoje) {
    $content = "---\nnext_review: 2026-05-30\n---\n\n# X\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('x.md', $content, $lifecycleMap, $hoje);
    expect($f)->toBeEmpty();
});

it('doc sem frontmatter e sem ADR morto → sem finding', function () use ($lifecycleMap, $hoje) {
    $content = "# PROTOCOL\n\n> Última revisão: 2026-05-09\n\nTexto livre, ADR 0094 vigente.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/PROTOCOL.md', $content, $lifecycleMap, $hoje);
    expect($f)->toBeEmpty();
});

it('combina as 2 categorias: ADR morto + next_review vencido no mesmo doc', function () use ($lifecycleMap, $hoje) {
    $content = "---\nnext_review: 2026-01-01\n---\n\n# X\nO primary segue ADR 0190.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('prototipo-ui/X.md', $content, $lifecycleMap, $hoje);

    $cats = array_map(fn (DriftFinding $x) => $x->evidence['category'], $f);
    expect($f)->toHaveCount(2)
        ->and($cats)->toContain('dead_adr_ref')
        ->and($cats)->toContain('stale_review');
});

it('é determinístico: rodar analisarDoc 2× → resultado idêntico (idempotência)', function () use ($lifecycleMap, $hoje) {
    $content = "---\nnext_review: 2026-01-01\n---\n\nADR 0190 + ADR 0099.\n";
    $checker = new DesignDocsFreshnessChecker();
    $a = array_map(fn (DriftFinding $x) => $x->toArray(), $checker->analisarDoc('x.md', $content, $lifecycleMap, $hoje));
    $b = array_map(fn (DriftFinding $x) => $x->toArray(), $checker->analisarDoc('x.md', $content, $lifecycleMap, $hoje));
    expect($a)->toBe($b);
});

it('findings não carregam business_id (drift repo-wide, ADR 0216)', function () use ($lifecycleMap, $hoje) {
    $content = "ADR 0190.\n";
    $f = (new DesignDocsFreshnessChecker())->analisarDoc('x.md', $content, $lifecycleMap, $hoje);
    expect($f[0]->business_id)->toBeNull();
});

it('check() roda contra o repo real e devolve DriftCheckResult determinístico', function () {
    $checker = new DesignDocsFreshnessChecker();
    // "now" no passado distante → nenhum next_review vence falso-positivamente;
    // o que importa aqui é o contrato do DTO + idempotência sobre o repo real.
    $r1 = $checker->check(['now' => '2000-01-01']);
    $r2 = $checker->check(['now' => '2000-01-01']);

    expect($r1)->toBeInstanceOf(DriftCheckResult::class)
        ->and($r1->name)->toBe('design_docs_freshness')
        ->and($r1->ok)->toBe($r1->drift_count === 0)
        ->and($r1->toArray())->toBe($r2->toArray()); // idempotente sobre o repo
});

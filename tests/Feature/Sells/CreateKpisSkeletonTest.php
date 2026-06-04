<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/Create.tsx · feature "kpis-skeleton".
 *
 * CRITÉRIO DE PRONTO (estado-alvo):
 *   Os 4 KPIs do topo da tela de venda (Itens · Total venda · Pago · Status pgto)
 *   devem renderizar um <Skeleton> (@/Components/ui/skeleton) durante o mount,
 *   em vez de mostrar "0" / "R$ 0,00" / "Aguardando" piscando antes da hidratação
 *   dos valores calculados (itensCount / totalGeral / totalPago / pagamentoStatus).
 *
 * Estilo: TESTE ESTRUTURAL (mesmo padrão de SaleSheetComponentTest.php e
 *   CustomerAutoApplyOnSelectTest.php) — lê o source via file_get_contents e
 *   afirma com toContain / toMatch. Não renderiza React.
 *
 * Test-first (RED): a feature ainda NÃO foi implementada. Hoje a seção dos KPIs
 *   (Create.tsx ~L928-998) renderiza os valores direto, sem Skeleton e sem guarda
 *   de mount. Portanto os it() abaixo ficam VERMELHOS agora — é o esperado.
 *   Ficam VERDES quando a feature for implementada.
 *
 * Regras duras (oimpresso): biz=1 sempre (ADR 0101) — não há query aqui (teste
 *   estrutural puro), então sem toque em Model/business_id. Comentários em PT-BR.
 */

const CREATE_PATH_KPIS = 'resources/js/Pages/Sells/Create.tsx';

function readCreateKpis(): string
{
    return file_get_contents(base_path(CREATE_PATH_KPIS));
}

/**
 * Helper: recorta o bloco da grade de KPIs (do comentário "KPI cards" até o
 * comentário "Conteúdo das seções") pra afirmar SOMENTE dentro da seção dos KPIs,
 * evitando falso-positivo de Skeleton usado em outra parte da tela.
 */
function readKpiSectionKpis(): string
{
    $src = readCreateKpis();
    $start = strpos($src, 'KPI cards');
    expect($start)->not->toBeFalse();
    $end = strpos($src, 'Conteúdo das seções', $start);
    if ($end === false) {
        $end = $start + 4000;
    }

    return substr($src, $start, $end - $start);
}

// ─── Import do componente Skeleton ───────────────────────────────────────────

it('kpis-skeleton — Create.tsx importa Skeleton de @/Components/ui/skeleton', function () {
    $src = readCreateKpis();
    expect($src)->toContain('@/Components/ui/skeleton');
    expect($src)->toMatch('/import\s*\{[^}]*\bSkeleton\b[^}]*\}\s*from\s*[\'"]@\/Components\/ui\/skeleton[\'"]/');
});

// ─── Guarda de mount (estado que distingue "montando" de "pronto") ───────────

it('kpis-skeleton — existe flag de mount (useState + useEffect) pra controlar o skeleton', function () {
    $src = readCreateKpis();
    // Estado booleano de mount/hidratação (ex.: mounted / isMounted / hydrated / kpisReady).
    expect($src)->toMatch('/useState[^;]*\b(mounted|isMounted|hydrated|ready|kpisReady)\b/i');
    // Liga a flag dentro de um useEffect (efeito de mount roda após primeira pintura).
    expect($src)->toMatch('/setMounted|setIsMounted|setHydrated|setReady|setKpisReady/i');
});

// ─── Skeleton DENTRO da seção dos 4 KPIs ─────────────────────────────────────

it('kpis-skeleton — a seção dos KPIs referencia <Skeleton>', function () {
    $kpis = readKpiSectionKpis();
    expect($kpis)->toContain('<Skeleton');
});

it('kpis-skeleton — Skeleton é renderizado condicionalmente pela flag de mount', function () {
    $kpis = readKpiSectionKpis();
    // Padrão condicional: !mounted ? <Skeleton .../> : <valor>  (ou mounted ? <valor> : <Skeleton>)
    expect($kpis)->toMatch('/(mounted|isMounted|hydrated|ready|kpisReady)[^<]*\?[^<]*<\s*Skeleton/i');
});

it('kpis-skeleton — KPI "Itens" usa Skeleton no lugar de itensCount durante o mount', function () {
    $kpis = readKpiSectionKpis();
    // Onde antes era só {itensCount}, agora há um Skeleton guardado pela flag de mount.
    expect($kpis)->toMatch('/(mounted|isMounted|hydrated|ready|kpisReady)[\s\S]{0,120}<\s*Skeleton[\s\S]{0,200}itensCount/i');
});

it('kpis-skeleton — KPI "Total venda" usa Skeleton no lugar de totalGeral durante o mount', function () {
    $kpis = readKpiSectionKpis();
    expect($kpis)->toMatch('/(mounted|isMounted|hydrated|ready|kpisReady)[\s\S]{0,120}<\s*Skeleton[\s\S]{0,200}totalGeral/i');
});

it('kpis-skeleton — KPI "Pago" usa Skeleton no lugar de totalPago durante o mount', function () {
    $kpis = readKpiSectionKpis();
    expect($kpis)->toMatch('/(mounted|isMounted|hydrated|ready|kpisReady)[\s\S]{0,120}<\s*Skeleton[\s\S]{0,200}totalPago/i');
});

it('kpis-skeleton — KPI "Status pgto" usa Skeleton no lugar de pagamentoStatus durante o mount', function () {
    $kpis = readKpiSectionKpis();
    expect($kpis)->toMatch('/(mounted|isMounted|hydrated|ready|kpisReady)[\s\S]{0,120}<\s*Skeleton[\s\S]{0,200}(pagamentoStatus|Aguardando)/i');
});

it('kpis-skeleton — há ao menos 4 Skeletons na seção (um por KPI)', function () {
    $kpis = readKpiSectionKpis();
    expect(substr_count($kpis, '<Skeleton'))->toBeGreaterThanOrEqual(4);
});

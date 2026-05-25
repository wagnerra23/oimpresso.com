<?php

declare(strict_types=1);

/**
 * Pest — FASE B (pós Wave Z-2 backend W2 `2f6f10fc8` · 2026-05-25) · ADR 0192
 * Integração Vendas × Oficina — VendaDerivadaCard evolution (items + fiscal).
 *
 * GUARD estrutural sobre `resources/js/Pages/Repair/ProducaoOficina/Index.tsx`
 * que garante que o card evoluído renderiza breakdown + fiscal + lista
 * expandível conforme contrato + NÃO regride pra placeholder em refactors.
 *
 * Implementação canônica FASE B:
 *  - TypeScript interfaces VendaItem + VendaItemsSummary + VendaFiscal
 *  - Breakdown grid 2-col (Peças / Serviços) + linha Subtotal/Desconto/Impostos
 *  - Badge fiscal 4 estados: autorizada (verde + DANFE link), pendente (amber),
 *    rejeitada (rose), null (slate "Sem nota fiscal")
 *  - Items list collapsed por default + disclosure expand button (▸/▾)
 *  - Cap 10 items visíveis + "+ N adicionais"
 *  - Prefix textual "Peça" / "Serviço" (ZERO emoji em UI · skill pageheader-canon)
 *  - Empty states tolerantes: items_list ausente/[] não renderiza breakdown nem
 *    disclosure; fiscal null renderiza só "Sem nota fiscal" sutil
 *  - Multi-tenant Tier 0 preservado (payload já scoped backend · frontend só lê)
 *  - Vocabulário shared cross-vertical preservado (zero termos automotivos)
 *
 * Refs:
 *  - resources/js/Pages/Repair/ProducaoOficina/Index.tsx (VendaDerivadaCard)
 *  - resources/js/Pages/Repair/ProducaoOficina/Index.charter.md (Goals FASE B)
 *  - Modules/Repair/Http/Controllers/ProducaoOficinaController.php (buildVendaDerivadaPayload)
 *  - Modules/Repair/Tests/Feature/ProducaoOficinaVendaDerivadaExpandedTest.php (backend W2 GUARDs)
 *  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 *  - memory/requisitos/Repair/ProducaoOficina-r3-venda-derivada-expanded-visual-comparison.md
 */

uses(Tests\TestCase::class);

const PRODUCAO_OFICINA_INDEX_TSX_FASE_B = 'resources/js/Pages/Repair/ProducaoOficina/Index.tsx';

function faseBReadIndex(): string
{
    // __DIR__ resolve relativo ao próprio teste — funciona em worktree OU root.
    // Pattern espelha W3 ProducaoOficinaOnda5CompartilharTest (mas mais robusto pra
    // worktrees onde base_path() pode apontar pra root project compartilhado).
    $worktreeRoot = realpath(__DIR__.'/../../../../');
    $path = $worktreeRoot.DIRECTORY_SEPARATOR.PRODUCAO_OFICINA_INDEX_TSX_FASE_B;
    return file_get_contents($path);
}

it('FASE B: interfaces TypeScript VendaItem + VendaItemsSummary + VendaFiscal definidas', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('interface VendaItem {')
        ->toContain('interface VendaItemsSummary {')
        ->toContain('interface VendaFiscal {')
        ->toContain("type: 'product' | 'service';")
        ->toContain("status: 'autorizada' | 'pendente' | 'rejeitada';");
});

it('FASE B: VendaDerivada interface tem fields opcionais items_list + items_summary + fiscal (backward compat Onda 5)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('items_list?: VendaItem[];')
        ->toContain('items_summary?: VendaItemsSummary;')
        ->toContain('fiscal?: VendaFiscal | null;');
});

it('FASE B: VendaDerivadaCard usa useState para itemsExpanded (collapsed por default)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('const [itemsExpanded, setItemsExpanded] = useState(false);')
        ->toContain("aria-expanded={itemsExpanded}");
});

it('FASE B: breakdown grid renderiza Peças + Serviços com count e total formatados BRL', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('Peças')
        ->toContain('Serviços')
        ->toContain('summary.products_count')
        ->toContain('summary.services_count')
        ->toContain('fmtBRL(summary.products_total)')
        ->toContain('fmtBRL(summary.services_total)');
});

it('FASE B: breakdown mostra linha Subtotal sempre + Desconto/Impostos condicionais (> 0)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('Subtotal')
        ->toContain('fmtBRL(subtotal)')
        ->toContain('summary.discount_total > 0')
        ->toContain('summary.tax_total > 0')
        ->toContain('Desconto')
        ->toContain('Impostos');
});

it('FASE B: badge fiscal renderiza 4 estados (autorizada/pendente/rejeitada/null)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain("fiscal === null")
        ->toContain('Sem nota fiscal')
        ->toContain("fiscal?.status === 'autorizada'")
        ->toContain("fiscal?.status === 'pendente'")
        ->toContain("fiscal?.status === 'rejeitada'")
        ->toContain('autorizada')
        ->toContain('pendente SEFAZ')
        ->toContain('rejeitada');
});

it('FASE B: badge fiscal autorizada tem botão DANFE com window.open + aria-label acessível', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('window.open(fiscal.danfe_url!')
        ->toContain('DANFE')
        ->toContain('aria-label={`Abrir DANFE da venda ${venda.invoice_no}`}');
});

it('FASE B: lista items usa prefix textual "Peça" / "Serviço" (ZERO emoji em UI · pageheader-canon)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain("item.type === 'service' ? 'Serviço' : 'Peça'");
    // Anti-regressão: não pode usar emoji 📦 ou 🛠 ou 🔧 pra type icon na lista items.
    expect($src)
        ->not->toContain('📦 ')
        ->not->toContain('🛠 ')
        ->not->toContain('🔧 ');
});

it('FASE B: items list tem cap 10 visíveis + sumário "+ N adicionais" (densidade drawer 480px)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('VISIBLE_ITEMS_CAP = 10')
        ->toContain('visibleItems')
        ->toContain('hiddenItemsCount')
        ->toContain('adicionais');
});

it('FASE B: disclosure button mostra ▸ collapsed e ▾ expanded (símbolos canônicos · não emoji)', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain("'▾' : '▸'")
        ->toContain('Ocultar')
        ->toContain('Ver');
});

it('FASE B: empty states tolerantes — hasBreakdown gateia render do breakdown e disclosure', function () {
    $src = faseBReadIndex();
    expect($src)
        ->toContain('const hasBreakdown = !!summary && itemsList.length > 0;')
        ->toContain('{hasBreakdown && summary && (')
        ->toContain('{hasBreakdown && (')
        ->toContain('venda.items_list ?? []')
        ->toContain('venda.fiscal ?? null');
});

it('FASE B: Onda 5 backward compat — keys core (id/invoice_no/final_total/transaction_date) intactas', function () {
    $src = faseBReadIndex();
    // Core fields preservados na interface VendaDerivada.
    expect($src)
        ->toContain('id: number;')
        ->toContain('invoice_no: string;')
        ->toContain('final_total: number;')
        ->toContain('transaction_date: string | null;');
});

it('FASE B: handlers Onda 5 + W3 preservados (handleAbrir / handleImprimirRecibo / handleCompartilhar)', function () {
    $src = faseBReadIndex();
    // GUARD anti-regressão dos handlers Onda 5+W3 que NÃO devem ser tocados.
    expect($src)
        ->toContain('const handleAbrir = ()')
        ->toContain("new CustomEvent('oimpresso:open-venda'")
        ->toContain('const handleImprimirRecibo = ()')
        ->toContain('/sells/${venda.id}/print')
        ->toContain('const handleCompartilhar = async ()')
        ->toContain('navigator.share');
});

it('FASE B: vocabulário shared cross-vertical — zero termos automotivos no card (ADR 0121 §P8)', function () {
    $src = faseBReadIndex();
    // Regex específico só dentro do componente VendaDerivadaCard (anti false-positive
    // com mock data automotivo que vive em outras seções).
    $cardStart = strpos($src, 'function VendaDerivadaCard');
    expect($cardStart)->not->toBeFalse();
    $cardSrc = substr($src, $cardStart);

    // Vocab proibido cross-vertical dentro do card:
    expect($cardSrc)
        ->not->toContain('placa')
        ->not->toContain('vehicle')
        ->not->toContain('mecanico')
        ->not->toContain('mecânico')
        ->not->toContain('elevador');
});

it('FASE B: multi-tenant Tier 0 preservado — frontend NÃO dispara queries (só lê payload scoped)', function () {
    $src = faseBReadIndex();
    $cardStart = strpos($src, 'function VendaDerivadaCard');
    expect($cardStart)->not->toBeFalse();
    $cardSrc = substr($src, $cardStart);

    // Anti-regressão: card não pode fazer router.get / useFetch / axios direto —
    // payload já vem hidratado do Controller (Onda 2 + W2 expand).
    expect($cardSrc)
        ->not->toContain('router.get(')
        ->not->toContain('router.post(')
        ->not->toContain('axios.')
        ->not->toContain('fetch(');
});

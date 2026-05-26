<?php

declare(strict_types=1);

/**
 * Pest STRUCTURAL — KB-9.75 P2 batch (Cowork bundle 2026-05-26).
 *
 * Cobre os gaps #8 (Recibo térmico 80mm) e #9 (Orçamento A4) do
 * `memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md`.
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/SaleReciboPrint80mm.tsx (NOVO)
 *  - resources/js/Pages/Sells/_components/SaleOrcamentoA4.tsx (NOVO)
 *  - resources/css/sells-kb975-print.css (NOVO — 80mm + A4 @page)
 *  - resources/js/Pages/Sells/Show.tsx (wire-up — dropdown + state printMode)
 */

const KB975_PRINT_RECIBO_PATH = 'resources/js/Pages/Sells/_components/SaleReciboPrint80mm.tsx';
const KB975_PRINT_ORCAMENTO_PATH = 'resources/js/Pages/Sells/_components/SaleOrcamentoA4.tsx';
const KB975_PRINT_CSS_PATH = 'resources/css/sells-kb975-print.css';
const KB975_PRINT_INERTIA_CSS = 'resources/css/inertia.css';
const KB975_PRINT_SHOW_PAGE = 'resources/js/Pages/Sells/Show.tsx';

it('SaleReciboPrint80mm.tsx existe', function () {
    expect(file_exists(base_path(KB975_PRINT_RECIBO_PATH)))->toBeTrue();
});

it('SaleReciboPrint80mm declara interface Props TS strict com headline + detail + onClose', function () {
    $source = file_get_contents(base_path(KB975_PRINT_RECIBO_PATH));
    expect($source)->toContain('interface Props');
    expect($source)->toContain('headline: Headline');
    expect($source)->toContain('detail: Detail');
    expect($source)->toContain('onClose: () => void');
});

it('SaleReciboPrint80mm dispara window.print() no botão Imprimir', function () {
    $source = file_get_contents(base_path(KB975_PRINT_RECIBO_PATH));
    expect($source)->toContain('window.print()');
});

it('SaleReciboPrint80mm adiciona body.vd-print-receipt pra @media print esconder app', function () {
    $source = file_get_contents(base_path(KB975_PRINT_RECIBO_PATH));
    expect($source)->toContain("classList.add('vd-print-receipt')");
    expect($source)->toContain("classList.remove('vd-print-receipt')");
});

it('SaleReciboPrint80mm renderiza estrutura canon vd-receipt-paper + vd-rcp-*', function () {
    $source = file_get_contents(base_path(KB975_PRINT_RECIBO_PATH));
    expect($source)->toContain('vd-receipt-paper');
    expect($source)->toContain('vd-rcp-brand');
    expect($source)->toContain('vd-rcp-itens');
    expect($source)->toContain('vd-rcp-row-total');
    expect($source)->toContain('vd-rcp-thanks');
});

it('SaleReciboPrint80mm tem fallback hardcoded de empresa (CNPJ + endereço + telefone)', function () {
    $source = file_get_contents(base_path(KB975_PRINT_RECIBO_PATH));
    expect($source)->toContain('DEFAULT_COMPANY');
    expect($source)->toContain('CNPJ');
});

it('SaleOrcamentoA4.tsx existe', function () {
    expect(file_exists(base_path(KB975_PRINT_ORCAMENTO_PATH)))->toBeTrue();
});

it('SaleOrcamentoA4 declara interface Props TS strict + validadeDias default 7', function () {
    $source = file_get_contents(base_path(KB975_PRINT_ORCAMENTO_PATH));
    expect($source)->toContain('interface Props');
    expect($source)->toContain('validadeDias?: number');
    expect($source)->toContain('validadeDias = 7');
});

it('SaleOrcamentoA4 deriva número Q-XXXX do invoice_no da venda', function () {
    $source = file_get_contents(base_path(KB975_PRINT_ORCAMENTO_PATH));
    expect($source)->toContain('orcNum');
    expect($source)->toContain("'Q-'");
});

it('SaleOrcamentoA4 adiciona body.vd-print-orc pra @media print esconder app', function () {
    $source = file_get_contents(base_path(KB975_PRINT_ORCAMENTO_PATH));
    expect($source)->toContain("classList.add('vd-print-orc')");
    expect($source)->toContain("classList.remove('vd-print-orc')");
});

it('SaleOrcamentoA4 renderiza estrutura canon vd-orc-page + tabela + assinaturas + footer', function () {
    $source = file_get_contents(base_path(KB975_PRINT_ORCAMENTO_PATH));
    expect($source)->toContain('vd-orc-page');
    expect($source)->toContain('vd-orc-brand');
    expect($source)->toContain('vd-orc-tbl');
    expect($source)->toContain('vd-orc-sign');
    expect($source)->toContain('vd-orc-ft');
});

it('SaleOrcamentoA4 tem condições comerciais default (prazo + arte + cancelamento)', function () {
    $source = file_get_contents(base_path(KB975_PRINT_ORCAMENTO_PATH));
    expect($source)->toContain('vd-orc-cond');
    expect($source)->toContain('Prazo de entrega');
    expect($source)->toContain('Cancelamento');
});

it('sells-kb975-print.css existe', function () {
    expect(file_exists(base_path(KB975_PRINT_CSS_PATH)))->toBeTrue();
});

it('sells-kb975-print.css escopa em .sells-cowork (coexiste com legacy printSaleReceipt.ts)', function () {
    $source = file_get_contents(base_path(KB975_PRINT_CSS_PATH));
    expect($source)->toContain('.sells-cowork .vd-receipt-paper');
    expect($source)->toContain('.sells-cowork .vd-orc-page');
});

it('sells-kb975-print.css declara @page 80mm pra recibo térmico', function () {
    $source = file_get_contents(base_path(KB975_PRINT_CSS_PATH));
    expect($source)->toContain('size: 80mm auto');
});

it('sells-kb975-print.css declara @page A4 pra orçamento', function () {
    $source = file_get_contents(base_path(KB975_PRINT_CSS_PATH));
    expect($source)->toContain('size: A4');
});

it('sells-kb975-print.css esconde resto da app via body.vd-print-receipt/orc', function () {
    $source = file_get_contents(base_path(KB975_PRINT_CSS_PATH));
    expect($source)->toContain('body.vd-print-receipt');
    expect($source)->toContain('body.vd-print-orc');
});

it('sells-kb975-print.css importada em inertia.css', function () {
    $source = file_get_contents(base_path(KB975_PRINT_INERTIA_CSS));
    expect($source)->toContain('sells-kb975-print.css');
});

it('Show.tsx importa SaleReciboPrint80mm + SaleOrcamentoA4', function () {
    $source = file_get_contents(base_path(KB975_PRINT_SHOW_PAGE));
    expect($source)->toContain("from './_components/SaleReciboPrint80mm'");
    expect($source)->toContain("from './_components/SaleOrcamentoA4'");
});

it('Show.tsx declara state printMode com type CoworkPrintMode', function () {
    $source = file_get_contents(base_path(KB975_PRINT_SHOW_PAGE));
    expect($source)->toContain('CoworkPrintMode');
    expect($source)->toContain('useState<CoworkPrintMode>(null)');
});

it('Show.tsx tem dropdown items "Recibo térmico (80mm)" e "Orçamento A4"', function () {
    $source = file_get_contents(base_path(KB975_PRINT_SHOW_PAGE));
    expect($source)->toContain('Recibo térmico (80mm)');
    expect($source)->toContain('Orçamento A4');
    expect($source)->toContain("handleCoworkPrint('recibo-80mm')");
    expect($source)->toContain("handleCoworkPrint('orcamento-a4')");
});

it('Show.tsx conditional render dos overlays (não bloqueia legacy printSaleReceipt)', function () {
    $source = file_get_contents(base_path(KB975_PRINT_SHOW_PAGE));
    // Modal só renderiza quando user pediu E detail deferred já chegou
    expect($source)->toContain("printMode === 'recibo-80mm' && props.detail");
    expect($source)->toContain("printMode === 'orcamento-a4' && props.detail");
    // Legacy NÃO removido — convive
    expect($source)->toContain('printSaleReceipt');
});

it('Show.tsx mantém atalhos E/P/Esc + dropdown legacy invoice/packing/delivery (sem regressão)', function () {
    $source = file_get_contents(base_path(KB975_PRINT_SHOW_PAGE));
    expect($source)->toContain("if (e.key === 'p' && permissions.print)");
    expect($source)->toContain("handlePrint('invoice')");
    expect($source)->toContain("handlePrint('packing_slip')");
    expect($source)->toContain("handlePrint('delivery_note')");
});

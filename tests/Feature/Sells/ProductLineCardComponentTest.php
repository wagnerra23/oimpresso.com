<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/_components/ProductLineCard.tsx (P0-1 RESPONSIVIDADE).
 *
 * Card vertical 1-col pra UMA linha de produto da venda (mobile <768px).
 * Substitui visualmente as 5 cols horizontais da tabela legacy em viewports estreitos.
 *
 * Anti-regressão das propriedades essenciais (dossier mãe
 * memory/sessions/2026-05-17-tela-venda-arte-responsivo.md §P0-1):
 *   - Touch targets >=44px (Apple HIG / Material 48dp) em inputs + delete button
 *   - aria-label no botão remover
 *   - <label htmlFor=...> em cada Input (não placeholder-only)
 *   - Discount input só aparece se permissions.editDiscount === true
 *   - Tokens canon: bg-card / text-foreground / text-muted-foreground / text-destructive
 *   - Sem cor crua bg-(gray|red|...) — charter anti-pattern
 *   - Sem font-bold em headers — usar font-semibold
 *   - Export do shape ProductRow (pra Create.tsx reusar quando integrar)
 *
 * Vitest ainda não está configurado no projeto (package.json: dev/build/typecheck apenas).
 * Pattern Pest "estrutural" lendo file_get_contents é o canon usado em SaleSheetComponentTest.
 * Quando Vitest for adicionado num próximo cycle, este teste pode coexistir ou ser migrado.
 */

const PRODUCT_LINE_CARD_PATH = 'resources/js/Pages/Sells/_components/ProductLineCard.tsx';

function readProductLineCard(): string
{
    return file_get_contents(base_path(PRODUCT_LINE_CARD_PATH));
}

it('ProductLineCard componente existe', function () {
    expect(file_exists(base_path(PRODUCT_LINE_CARD_PATH)))->toBeTrue();
});

// ─── Shape e tipagem ─────────────────────────────────────────────────────────

it('ProductLineCard exporta interface ProductRow (pra Create.tsx reusar)', function () {
    $source = readProductLineCard();
    expect($source)->toContain('export interface ProductRow');
    // Campos essenciais que espelham useForm({ products: [...] }) Create.tsx:163-171
    expect($source)->toContain('product_id: number');
    expect($source)->toContain('variation_id: number | null');
    expect($source)->toContain('name: string');
    expect($source)->toContain('sku: string');
    expect($source)->toContain('quantity: number');
    expect($source)->toContain('unit_price: number');
    expect($source)->toContain('discount: number');
});

it('ProductLineCard tem Props canônicas (product + index + permissions + onChange + onRemove)', function () {
    $source = readProductLineCard();
    expect($source)->toContain('product: ProductRow');
    expect($source)->toContain('index: number');
    expect($source)->toContain('permissions');
    expect($source)->toContain('editDiscount: boolean');
    expect($source)->toContain('editPrice: boolean');
    expect($source)->toContain('onChange');
    expect($source)->toContain('onRemove');
    expect($source)->toContain('taxes: Record<number, string>');
});

it('ProductLineCard exporta default', function () {
    $source = readProductLineCard();
    expect($source)->toContain('export default function ProductLineCard');
});

// ─── Touch targets >=44px (P0-2 prep + crítico mobile) ───────────────────────

it('ProductLineCard inputs têm altura touch-friendly h-11 (44px Apple HIG)', function () {
    $source = readProductLineCard();
    // Pelo menos 2 inputs (Qtd + Preço) com h-11
    $count = preg_match_all('/className="[^"]*\bh-11\b[^"]*"/', $source);
    expect($count)->toBeGreaterThanOrEqual(2);
});

it('ProductLineCard botão remover tem touch target 44x44 (h-11 w-11)', function () {
    $source = readProductLineCard();
    expect($source)->toMatch('/h-11 w-11/');
});

// ─── Acessibilidade ──────────────────────────────────────────────────────────

it('ProductLineCard botão remover tem aria-label dinâmico com nome do produto', function () {
    $source = readProductLineCard();
    expect($source)->toMatch('/aria-label=\{`Remover produto \$\{product\.name\}`\}/');
});

it('ProductLineCard usa <Label htmlFor=...> em cada Input (não placeholder-only)', function () {
    $source = readProductLineCard();
    // 1 htmlFor por Input visível (Qtd + Preço sempre; Desconto condicional). >=2 sempre.
    $count = preg_match_all('/htmlFor=\{?`?product-\$\{index\}-/', $source);
    expect($count)->toBeGreaterThanOrEqual(2);
});

it('ProductLineCard tem role="alert" pra erro de validação', function () {
    $source = readProductLineCard();
    expect($source)->toContain('role="alert"');
});

it('ProductLineCard tem role="group" + aria-label no container', function () {
    $source = readProductLineCard();
    expect($source)->toContain('role="group"');
    expect($source)->toMatch('/aria-label=\{`Produto \$\{index \+ 1\}/');
});

// ─── Discount condicional ────────────────────────────────────────────────────

it('ProductLineCard mostra Desconto apenas quando permissions.editDiscount === true', function () {
    $source = readProductLineCard();
    // Bloco condicional renderizando o input desconto
    expect($source)->toMatch('/\{permissions\.editDiscount && \(/');
    // Discount label/aria interno
    expect($source)->toContain('product-${index}-discount');
});

// ─── Callbacks ───────────────────────────────────────────────────────────────

it('ProductLineCard chama onRemove com index correto', function () {
    $source = readProductLineCard();
    expect($source)->toContain('onClick={() => onRemove(index)}');
});

it('ProductLineCard chama onChange ao editar quantity', function () {
    $source = readProductLineCard();
    expect($source)->toMatch("/onChange\(index, 'quantity', Number\(e\.target\.value\)\)/");
});

it('ProductLineCard chama onChange ao editar unit_price', function () {
    $source = readProductLineCard();
    expect($source)->toMatch("/onChange\(index, 'unit_price', Number\(e\.target\.value\)\)/");
});

it('ProductLineCard chama onChange ao editar discount', function () {
    $source = readProductLineCard();
    expect($source)->toMatch("/onChange\(index, 'discount', Number\(e\.target\.value\)\)/");
});

// ─── Tokens canon (anti-padrão cor crua) ─────────────────────────────────────

it('ProductLineCard usa tokens canon (bg-card / text-foreground / border-border)', function () {
    $source = readProductLineCard();
    expect($source)->toContain('bg-card');
    expect($source)->toContain('text-foreground');
    expect($source)->toContain('text-muted-foreground');
    expect($source)->toContain('text-destructive');
    expect($source)->toContain('border-border');
});

it('ProductLineCard NÃO usa cor crua bg-gray|bg-red|bg-blue (charter anti-pattern)', function () {
    $source = readProductLineCard();
    expect($source)->not->toMatch('/bg-(gray|red|blue|green|yellow|amber|emerald|rose)-\d+/');
});

it('ProductLineCard NÃO usa font-bold (charter — usar font-semibold)', function () {
    $source = readProductLineCard();
    expect($source)->not->toMatch('/\bfont-bold\b/');
});

// ─── Subtotal display ────────────────────────────────────────────────────────

it('ProductLineCard exibe Subtotal formatado em pt-BR / BRL', function () {
    $source = readProductLineCard();
    expect($source)->toContain('Subtotal');
    expect($source)->toContain("'pt-BR'");
    expect($source)->toContain("currency: 'BRL'");
    expect($source)->toContain('tabular-nums');
});

it('ProductLineCard calcula lineSubtotal como max(qty*price-discount, 0)', function () {
    $source = readProductLineCard();
    expect($source)->toMatch('/Math\.max\(\s*product\.quantity \* product\.unit_price - product\.discount,\s*0,?\s*\)/s');
});

// ─── Documentação inline (RUNBOOK mini topo) ─────────────────────────────────

it('ProductLineCard tem RUNBOOK inline topo com link pro dossier mãe', function () {
    $source = readProductLineCard();
    expect($source)->toContain('P0-1');
    expect($source)->toContain('2026-05-17-tela-venda-arte-responsivo');
});

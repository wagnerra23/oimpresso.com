<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Sells/Create.tsx auto-aplicar campos do cliente
 * ao trocar (R8 2026-05-28).
 *
 * Bug 2 do hotfix Larissa 2026-05-13 — Dor 2 do audit 2026-05-27:
 *   "Cliente trocado mas preço/prazo NÃO recalcula (preço diferenciado)"
 *
 *   Antes do fix: onSelect só chamava `setData('contact_id', c.id)`.
 *   Cliente VIP com selling_price_group_id=ATACADO continuava cobrando preço
 *   balcão. Prazo "30 dias" do cliente B2B não pré-preenchia. Endereço de
 *   entrega não vinha. Backend ContactController@getCustomers (linhas 2150-2176)
 *   já devolvia TODOS os campos — frontend descartava.
 *
 * Solução R8 (Create.tsx + CustomerSearchAutocomplete.tsx):
 *   1. Expandir tipo `CustomerSearchResult` com 4 campos novos
 *   2. Novo handler `handleCustomerSelect` que aplica:
 *      - contact_id (preserva)
 *      - pay_term_number/pay_term_type
 *      - shipping.address (via spread pra preservar outros campos shipping)
 *      - selling_price_group_id (delega pro `handlePriceGroupChange` R3 existente
 *        que recalcula unit_price das linhas via refetch /products/list)
 *   3. `handleCustomerClear` reseta pros defaults (walk-in + price_group default)
 *
 * Paridade Blade `customer_id.on('change')` em public/js/pos.js.
 */

const PAGE_PATH_R8 = 'resources/js/Pages/Sells/Create.tsx';
const COMP_PATH_R8 = 'resources/js/Pages/Sells/_components/CustomerSearchAutocomplete.tsx';

function readPageR8(): string
{
    return file_get_contents(base_path(PAGE_PATH_R8));
}

function readCompR8(): string
{
    return file_get_contents(base_path(COMP_PATH_R8));
}

// === Tipo expandido no componente ===

it('R8 — tipo CustomerSearchResult exporta selling_price_group_id', function () {
    $src = readCompR8();
    expect($src)->toContain('selling_price_group_id');
    expect($src)->toMatch('/selling_price_group_id\?:\s*number\s*\|\s*null/');
});

it('R8 — tipo CustomerSearchResult exporta pay_term_number + pay_term_type', function () {
    $src = readCompR8();
    expect($src)->toContain('pay_term_number?');
    expect($src)->toContain('pay_term_type?');
});

it('R8 — tipo CustomerSearchResult exporta shipping_address', function () {
    $src = readCompR8();
    expect($src)->toContain('shipping_address?');
});

// === Handler no Create.tsx ===

it('R8 — Create.tsx importa CustomerSearchResult type', function () {
    $src = readPageR8();
    expect($src)->toContain('type CustomerSearchResult');
});

it('R8 — Create.tsx tem handleCustomerSelect handler', function () {
    $src = readPageR8();
    expect($src)->toContain('handleCustomerSelect');
    expect($src)->toMatch('/const\s+handleCustomerSelect\s*=\s*\(c:\s*CustomerSearchResult\)/');
});

it('R8 — handleCustomerSelect chama setData contact_id', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerSelect');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 1500);
    expect($body)->toContain("setData('contact_id', c.id)");
});

it('R8 — handleCustomerSelect aplica pay_term_number quando presente', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerSelect');
    $body = substr($src, $start, 1500);
    expect($body)->toContain("setData('pay_term_number'");
    expect($body)->toContain('c.pay_term_number');
});

it('R8 — handleCustomerSelect aplica pay_term_type só se válido (days|months)', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerSelect');
    $body = substr($src, $start, 1500);
    expect($body)->toMatch("/c\\.pay_term_type\\s*===\\s*'days'/");
    expect($body)->toContain("c.pay_term_type === 'months'");
});

it('R8 — handleCustomerSelect aplica shipping.address via spread (preserva outros campos)', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerSelect');
    $body = substr($src, $start, 1500);
    expect($body)->toContain("setData('shipping', { ...data.shipping, address:");
});

it('R8 — handleCustomerSelect chama handlePriceGroupChange quando cliente tem grupo', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerSelect');
    $body = substr($src, $start, 1500);
    expect($body)->toContain('c.selling_price_group_id');
    expect($body)->toMatch('/handlePriceGroupChange\(c\.selling_price_group_id\)/');
});

// === Handler clear ===

it('R8 — Create.tsx tem handleCustomerClear handler', function () {
    $src = readPageR8();
    expect($src)->toContain('handleCustomerClear');
});

it('R8 — handleCustomerClear reseta pay_term pros defaults', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerClear');
    expect($start)->not->toBeFalse();
    $body = substr($src, $start, 800);
    expect($body)->toContain("setData('pay_term_number', '')");
    expect($body)->toContain("setData('pay_term_type', 'days')");
});

it('R8 — handleCustomerClear volta pra walkInCustomer.id', function () {
    $src = readPageR8();
    $start = strpos($src, 'const handleCustomerClear');
    $body = substr($src, $start, 800);
    expect($body)->toContain("setData('contact_id', props.walkInCustomer.id)");
});

// === Wiring no JSX ===

it('R8 — CustomerSearchAutocomplete usa handleCustomerSelect (não inline)', function () {
    $src = readPageR8();
    expect($src)->toContain('onSelect={handleCustomerSelect}');
    expect($src)->toContain('onClear={handleCustomerClear}');
});

it('R8 — onSelect NÃO usa mais inline (c) => setData(contact_id, c.id) padrão', function () {
    $src = readPageR8();
    // Negar a versão antiga inline
    expect($src)->not->toMatch('/onSelect=\{\(c\)\s*=>\s*setData\([\'"]contact_id[\'"],\s*c\.id\)\}/');
});

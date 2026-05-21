<?php

declare(strict_types=1);

// Wave C — US-CRM-065 Tab Vendas DataTable
// Restrição Tier 0 ADR 0093: backend SellController filtra business_id global scope.
// Teste estrutural — integração HTTP cobre SellsIndexTest.

test('SalesTab.tsx — estrutura mínima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function SalesTab')
        ->toContain('data-testid="sales-tab-root"')
        ->toContain('data-testid="sales-tab-skeleton"')
        ->toContain('SalesPaginator')
        ->not->toContain(': any');
});

test('SalesTab.tsx — todas 7 colunas requisitadas', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Data')
        ->toContain('Nº Fatura')
        ->toContain('>Total<')
        ->toContain('>Pago<')
        ->toContain('Pendente')
        ->toContain('>Status<')
        ->toContain('Ações');
});

test('SalesTab.tsx — filtros range datas + status pagamento + busca', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('data-testid="sales-start-date"')
        ->toContain('data-testid="sales-end-date"')
        ->toContain('data-testid="sales-payment-status"')
        ->toContain('data-testid="sales-search"')
        ->toContain('data-testid="sales-apply-btn"')
        ->toContain('data-testid="sales-clear-btn"')
        ->toContain("'paid'")
        ->toContain("'due'")
        ->toContain("'partial'")
        ->toContain("'overdue'");
});

test('SalesTab.tsx — status pagamento PT-BR + dark mode tokens', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("'Pago'")
        ->toContain("'A receber'")
        ->toContain("'Parcial'")
        ->toContain("'Vencido'")
        ->toContain('dark:bg-emerald-950')
        ->toContain('dark:bg-amber-950')
        ->toContain('dark:bg-blue-950')
        ->toContain('dark:bg-rose-950');
});

test('SalesTab.tsx — paginação server-side com only: sales (Inertia partial reload)', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("only: ['sales']")
        ->toContain('preserveScroll')
        ->toContain('preserveState')
        ->toContain('current_page')
        ->toContain('last_page')
        ->toContain('router.visit');
});

test('SalesTab.tsx — totals tfoot somente quando há dados', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Totais (')
        ->toContain('final_total')
        ->toContain('total_paid')
        ->toContain('total_due')
        ->toContain('tfoot');
});

test('SalesTab.tsx — empty state PT-BR', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/SalesTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Nenhuma venda encontrada.')
        ->toContain('Ajuste os filtros ou registre uma nova venda.')
        ->toContain('data-testid="sales-empty"');
});

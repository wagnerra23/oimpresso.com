<?php

declare(strict_types=1);
// @covers-us US-CRM-063

// Wave A — US-CRM-063 Tab Pagamentos
// Restrição Tier 0 ADR 0093: backend endpoint /contacts/payments/{id} já filtra por business_id global scope.
// Este teste é structural (Pest light) — não exercita HTTP. Render integration cobre Wave1ShowInertiaTest pós-wiring.

test('PaymentsTab.tsx — estrutura mínima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function PaymentsTab')
        ->toContain('contactId')
        ->toContain('/contacts/payments/')
        ->toContain('data-testid="payments-tab-root"')
        ->toContain('data-testid="payments-tab-skeleton"')
        ->toContain('PaymentRow')
        ->toContain('formatBRL')
        ->toContain('Nenhum pagamento registrado.')
        ->not->toContain(': any');
});

test('PaymentsTab.tsx — todas as 6 colunas requisitadas', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Data')
        ->toContain('Nº Ref')
        ->toContain('Valor')
        ->toContain('Método')
        ->toContain('Pago por')
        ->toContain('Ação');
});

test('PaymentsTab.tsx — métodos de pagamento PT-BR', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("'Dinheiro'")
        ->toContain("'Cartão'")
        ->toContain("'Cheque'")
        ->toContain("'Transferência'")
        ->toContain("'Pix'")
        ->toContain("'Boleto'");
});

test('PaymentsTab.tsx — child payments com identação', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    // Pagamentos parent-child são parte do legacy (TransactionPayment::with(child_payments))
    expect($contents)
        ->toContain('parent_id')
        ->toContain('parent_payment_ref_no')
        ->toContain('CornerDownRight');
});

test('PaymentsTab.tsx — empty state PT-BR + dark mode tokens', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('Nenhum pagamento registrado.')
        ->toContain('text-muted-foreground')
        ->toContain('bg-background')
        ->toContain('border-border');
});

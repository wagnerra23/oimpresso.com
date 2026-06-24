<?php

declare(strict_types=1);
// @covers-us US-CRM-067

// Wave E — US-CRM-067 Actions Menu dropdown + Add Discount Modal
// Restrição Tier 0 ADR 0093: ContactController::updateStatus + destroy + LedgerDiscountController::store
// filtram business_id global scope. Componente é puro client-side wiring.

test('ActionsMenu.tsx — estrutura mínima componente', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function ActionsMenu')
        ->toContain('data-testid="actions-menu-root"')
        ->toContain('DropdownMenu')
        ->toContain('AddDiscountModal')
        ->not->toContain(': any');
});

test('ActionsMenu.tsx — todas as ações requisitadas presentes', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        // Pagar
        ->toContain('data-testid="actions-pay-due"')
        ->toContain('Receber pagamento')
        // Excluir
        ->toContain('data-testid="actions-delete"')
        ->toContain('Excluir cliente')
        // Toggle status
        ->toContain('data-testid="actions-toggle-status"')
        ->toContain('Desativar cliente')
        ->toContain('Reativar cliente')
        // Atalhos
        ->toContain('data-testid="actions-shortcut-ledger"')
        ->toContain('data-testid="actions-shortcut-sales"')
        ->toContain('data-testid="actions-shortcut-purchases"')
        ->toContain('Ver extrato completo')
        ->toContain('Ver vendas')
        ->toContain('Ver compras')
        // Add discount
        ->toContain('data-testid="actions-add-discount-btn"')
        ->toContain('Aplicar desconto');
});

test('ActionsMenu.tsx — endpoints canon + CSRF token', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx';
    $contents = file_get_contents($tsxPath);

    // Template literals — busca substring sem aspas
    expect($contents)
        ->toContain('/contacts/update-status/')
        ->toContain('/contacts/${contactId}')
        ->toContain('/payments/pay-contact-due/')
        ->toContain('X-CSRF-TOKEN')
        ->toContain('_method')
        ->toContain('DELETE');
});

test('ActionsMenu.tsx — permission gates obrigatórios', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('permissions.pay_due')
        ->toContain('permissions.delete')
        ->toContain('permissions.toggle_status')
        ->toContain('permissions.add_discount');
});

test('ActionsMenu.tsx — atalhos dependem do tipo (customer/supplier/both)', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("contactType === 'customer' || contactType === 'both'")
        ->toContain("contactType === 'supplier' || contactType === 'both'");
});

test('AddDiscountModal.tsx — estrutura + form fields canon', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/AddDiscountModal.tsx';
    expect($tsxPath)->toBeReadableFile();

    $contents = file_get_contents($tsxPath);
    expect($contents)
        ->toContain('export default function AddDiscountModal')
        ->toContain('data-testid="discount-modal"')
        ->toContain('data-testid="discount-date-input"')
        ->toContain('data-testid="discount-amount-input"')
        ->toContain('data-testid="discount-note-input"')
        ->toContain('data-testid="discount-submit-btn"')
        ->toContain("'/ledger-discount'")
        ->toContain('X-CSRF-TOKEN')
        ->not->toContain(': any');
});

test('AddDiscountModal.tsx — sub_type sell_discount/purchase_discount only when both', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/AddDiscountModal.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain("'sell_discount'")
        ->toContain("'purchase_discount'")
        ->toContain("contactType === 'both'")
        ->toContain('data-testid="discount-subtype-select"');
});

test('AddDiscountModal.tsx — dark mode tokens + acessibilidade', function () {
    $tsxPath = __DIR__ . '/../../../../resources/js/Pages/Cliente/_show/AddDiscountModal.tsx';
    $contents = file_get_contents($tsxPath);

    expect($contents)
        ->toContain('dark:bg-rose-950')
        ->toContain('aria-label="Fechar"')
        ->toContain('role="alert"')
        ->toContain('htmlFor=');
});

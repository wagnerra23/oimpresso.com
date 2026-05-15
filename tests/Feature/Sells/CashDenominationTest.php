<?php

declare(strict_types=1);

/**
 * Pest test estrutural — CashDenominationModal (US-SELL-006 P0-4).
 *
 * Cobre contagem nota a nota (cash denomination) — paridade com modal Blade
 * legacy em `resources/views/sale_pos/partials/payment_row_form.blade.php`
 * (linhas 58-121). Dani conta dinheiro no fechamento de caixa físico.
 *
 * Refs:
 *   - RUNBOOK paridade Sells/create §3.6 item 52 + §5 P0-4
 *   - Brief P0-4 agent (Wagner 2026-05-14 noite, pré-canary Martinho 19/maio)
 *
 * Anti-padrões catalogados que este test pega:
 *   - Decimal precision (toFixed(2) obrigatório — sem isso, R$ 0.05 × 3 = 0.150000001)
 *   - Labels em PT-BR (Wagner exige — Lara/Dani não-técnicas)
 *   - Não remove features Create.tsx existentes (trauma "designer remove tudo")
 *   - Denominações BR canon (R$ 200/100/50/20/10/5/2 cédulas + 1/0.50/0.25/0.10/0.05 moedas)
 */

const MODAL_PATH = 'resources/js/Pages/Sells/_components/CashDenominationModal.tsx';
const CREATE_PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';

function readModal(): string
{
    return file_get_contents(base_path(MODAL_PATH));
}

function readCreatePage(): string
{
    return file_get_contents(base_path(CREATE_PAGE_PATH));
}

// =========================================================================
// 1. Componente existe e exporta default
// =========================================================================

it('CashDenominationModal existe em _components/', function () {
    expect(file_exists(base_path(MODAL_PATH)))->toBeTrue();
});

it('CashDenominationModal exporta default React component', function () {
    $source = readModal();
    expect($source)->toContain('export default function CashDenominationModal');
});

it('CashDenominationModal declara contract Props (open/onClose/onConfirm)', function () {
    $source = readModal();
    expect($source)->toContain('open: boolean');
    expect($source)->toContain('onClose: () => void');
    expect($source)->toMatch('/onConfirm:\\s*\\(totalCalculated:\\s*number\\)\\s*=>\\s*void/');
});

// =========================================================================
// 2. Denominações canon BR (cédulas + moedas)
// =========================================================================

it('CashDenominationModal lista as 7 cédulas BR (R$ 200/100/50/20/10/5/2)', function () {
    $source = readModal();
    // Constante CASH_DENOMINATIONS_BR exportada
    expect($source)->toContain('CASH_DENOMINATIONS_BR');
    // Cada cédula presente como objeto { value: N, kind: 'cedula' }
    foreach ([200, 100, 50, 20, 10, 5, 2] as $cedula) {
        expect($source)->toMatch("/value:\\s*{$cedula},\\s*kind:\\s*'cedula'/");
    }
});

it('CashDenominationModal lista as 5 moedas BR (R$ 1 / 0.50 / 0.25 / 0.10 / 0.05)', function () {
    $source = readModal();
    foreach (['1', '0.5', '0.25', '0.1', '0.05'] as $moeda) {
        // value: 0.5, kind: 'moeda' — quotando escape do ponto pra regex
        $escaped = preg_quote($moeda, '/');
        expect($source)->toMatch("/value:\\s*{$escaped},\\s*kind:\\s*'moeda'/");
    }
});

// =========================================================================
// 3. Cálculo total — real-time + decimal accuracy
// =========================================================================

it('CashDenominationModal calcula totalGeral via useMemo (real-time)', function () {
    $source = readModal();
    expect($source)->toContain('useMemo');
    expect($source)->toMatch('/const\\s+totalGeral\\s*=\\s*useMemo/');
});

it('CashDenominationModal usa toFixed(2) para decimal accuracy (anti R$ 0.05 × 3 = 0.150000001)', function () {
    $source = readModal();
    // Round-half-up obrigatório em totals
    expect($source)->toContain('toFixed(2)');
});

it('CashDenominationModal soma qty × denomValue corretamente (formula canon Blade)', function () {
    $source = readModal();
    // Padrão deve aparecer: total += qty * d.value
    expect($source)->toMatch('/total\\s*\\+=\\s*qty\\s*\\*\\s*d\\.value/');
});

// =========================================================================
// 4. Labels PT-BR (Wagner exige — Lara/Dani não-técnicas)
// =========================================================================

it('CashDenominationModal usa labels PT-BR ("Conferir notas" + "Confirmar")', function () {
    $source = readModal();
    expect($source)->toContain('Conferir notas');
    expect($source)->toContain('Confirmar');
});

it('CashDenominationModal usa label "Cancelar" PT-BR (não "Cancel")', function () {
    $source = readModal();
    expect($source)->toContain('Cancelar');
    expect($source)->not->toMatch('/>\\s*Cancel\\s*</'); // "Cancel" botão inglês
});

it('CashDenominationModal usa "Total" + "Qtd" + "Subtotal" PT-BR nos headers', function () {
    $source = readModal();
    expect($source)->toContain('Total');
    expect($source)->toContain('Qtd');
    expect($source)->toContain('Subtotal');
});

// =========================================================================
// 5. Confirmar dispara callback + fecha modal
// =========================================================================

it('CashDenominationModal handleConfirm chama onConfirm(totalGeral) e onClose()', function () {
    $source = readModal();
    expect($source)->toMatch('/onConfirm\\(totalGeral\\)/');
    expect($source)->toMatch('/onClose\\(\\)/');
});

it('CashDenominationModal aceita atalho Ctrl/Cmd+S pra confirmar', function () {
    $source = readModal();
    // Listener keydown verificando ctrlKey/metaKey + key='s'
    expect($source)->toContain("ctrlKey");
    expect($source)->toContain("metaKey");
    expect($source)->toMatch("/key\\.toLowerCase\\(\\)\\s*===?\\s*'s'/");
});

it('CashDenominationModal limpa contadores ao abrir (fresh count toda vez)', function () {
    $source = readModal();
    // useEffect que reseta state quando `open` muda pra true
    expect($source)->toMatch('/useEffect\\(\\(\\)\\s*=>\\s*\\{[^}]*if\\s*\\(open\\)[^}]*setCounts\\(\\{\\}\\)/s');
});

// =========================================================================
// 6. Integração em Create.tsx
// =========================================================================

it('Create.tsx importa CashDenominationModal de _components/', function () {
    $source = readCreatePage();
    expect($source)->toContain("from './_components/CashDenominationModal'");
});

it('Create.tsx tem state showCashDenomination', function () {
    $source = readCreatePage();
    expect($source)->toMatch('/showCashDenomination/');
    expect($source)->toMatch('/setShowCashDenomination/');
});

it('Create.tsx tem botão "Conferir notas" com ícone Calculator', function () {
    $source = readCreatePage();
    expect($source)->toContain('Conferir notas');
    expect($source)->toContain('Calculator');
    expect($source)->toContain('data-testid="open-cash-denomination"');
});

it('Create.tsx renderiza <CashDenominationModal> com props canon (open/onClose/onConfirm)', function () {
    $source = readCreatePage();
    expect($source)->toContain('<CashDenominationModal');
    expect($source)->toMatch('/open=\\{showCashDenomination\\}/');
    expect($source)->toMatch('/onClose=\\{[^}]*setShowCashDenomination\\(false\\)\\}/');
    expect($source)->toMatch('/onConfirm=\\{handleConfirmCashDenomination\\}/');
});

it('Create.tsx handleConfirmCashDenomination preenche amount na linha de pagamento alvo', function () {
    $source = readCreatePage();
    expect($source)->toContain('handleConfirmCashDenomination');
    // Confirma que escreve `amount` no payment da linha alvo (atualiza paid amount)
    expect($source)->toMatch('/amount:\\s*totalCalculated/');
});

it('Create.tsx prioriza pagamento method=cash como alvo do modal (fallback idx 0)', function () {
    $source = readCreatePage();
    // findIndex method === 'cash' OU fallback 0
    expect($source)->toMatch("/findIndex\\([^)]*method\\s*===?\\s*'cash'/");
});

// =========================================================================
// 7. Não remove features Create.tsx existentes (Wagner trauma)
// =========================================================================

it('Create.tsx mantém botão "Adicionar pagamento" (sem regressão)', function () {
    $source = readCreatePage();
    expect($source)->toContain('Adicionar pagamento');
});

it('Create.tsx mantém PaymentRow rendering existente (sem regressão)', function () {
    $source = readCreatePage();
    expect($source)->toContain('<PaymentRow');
    expect($source)->toContain('handlePaymentChange');
    expect($source)->toContain('handleRemovePayment');
});

it('Create.tsx mantém indicador de saldo de pagamento (falta/troco/exato)', function () {
    $source = readCreatePage();
    expect($source)->toContain("pagamentoStatus === 'falta'");
    expect($source)->toContain("pagamentoStatus === 'troco'");
    expect($source)->toContain("pagamentoStatus === 'exato'");
});

// =========================================================================
// 8. Acessibilidade + Tier 0
// =========================================================================

it('CashDenominationModal tem aria-labels acessíveis (Lei BR + WCAG)', function () {
    $source = readModal();
    expect($source)->toContain('aria-label');
    // Inputs de qty têm aria-label per-denomination
    expect($source)->toMatch('/aria-label=\\{`Quantidade de notas\\/moedas/');
});

it('CashDenominationModal NÃO usa cor crua não-semântica (canon ADR 0110)', function () {
    $source = readModal();
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('CashDenominationModal NÃO usa sessionStorage (canon GOTCHAS — só localStorage)', function () {
    $source = readModal();
    expect($source)->not->toContain('sessionStorage');
});

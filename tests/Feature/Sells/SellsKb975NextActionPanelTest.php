<?php

declare(strict_types=1);

/**
 * Pest STRUCTURAL — KB-9.75 P0 batch (Cowork bundle 2026-05-26).
 *
 * Cobre o gap #1 (VdNextActionPanel) e gap #5 (validações fiscais BR) do
 * `memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md`.
 *
 * Refs:
 *  - resources/js/Pages/Sells/_components/VdNextActionPanel.tsx (NOVO)
 *  - resources/js/Lib/validacoesFiscaisBr.ts (NOVO)
 *  - resources/css/sells-kb975-next-action.css (NOVO)
 *  - resources/js/Pages/Sells/Show.tsx (wire-up)
 */

const KB975_NEXTACTION_PATH = 'resources/js/Pages/Sells/_components/VdNextActionPanel.tsx';
const KB975_VALIDATIONS_PATH = 'resources/js/Lib/validacoesFiscaisBr.ts';
const KB975_CSS_PATH = 'resources/css/sells-kb975-next-action.css';
const KB975_INERTIA_CSS = 'resources/css/inertia.css';
const KB975_SHOW_PAGE = 'resources/js/Pages/Sells/Show.tsx';

it('VdNextActionPanel.tsx existe', function () {
    expect(file_exists(base_path(KB975_NEXTACTION_PATH)))->toBeTrue();
});

it('VdNextActionPanel declara Props com saleId obrigatório', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION_PATH));
    expect($source)->toContain('saleId: number');
});

it('VdNextActionPanel reusa endpoint /api/sells/{id}/fsm-actions (não duplica backend)', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION_PATH));
    expect($source)->toContain('/api/sells/');
    expect($source)->toContain('fsm-actions');
});

it('VdNextActionPanel dispatch custom events oimpresso:venda-{invoiced,paid} (glossário BR)', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION_PATH));
    // Glossário BR corrigido (gap #6): Faturar ≠ Receber pagamento
    expect($source)->toContain('oimpresso:venda-invoiced');
    expect($source)->toContain('oimpresso:venda-paid');
});

it('VdNextActionPanel renderiza gate fiscal quando ação bloqueada', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION_PATH));
    expect($source)->toContain('vd-next-gate');
    expect($source)->toContain('Emita NF-e ou NFS-e antes de faturar');
});

it('validacoesFiscaisBr.ts existe', function () {
    expect(file_exists(base_path(KB975_VALIDATIONS_PATH)))->toBeTrue();
});

it('validacoesFiscaisBr exporta validadores CPF/CNPJ DV real', function () {
    $source = file_get_contents(base_path(KB975_VALIDATIONS_PATH));
    expect($source)->toContain('export function validaCpf');
    expect($source)->toContain('export function validaCnpj');
    expect($source)->toContain('export function validaCpfOuCnpj');
    expect($source)->toContain('export function mascaraCpfCnpj');
});

it('validacoesFiscaisBr exporta validadores fiscais (NCM/CFOP/CST/CSOSN/ISS/email)', function () {
    $source = file_get_contents(base_path(KB975_VALIDATIONS_PATH));
    expect($source)->toContain('export function validaNcm');
    expect($source)->toContain('export function validaCfop');
    expect($source)->toContain('export function validaCst');
    expect($source)->toContain('export function validaCsosn');
    expect($source)->toContain('export function validaIss');
    expect($source)->toContain('export function validaEmail');
});

it('validaCfop verifica consistência UF (5xxx intra / 6xxx interestadual)', function () {
    $source = file_get_contents(base_path(KB975_VALIDATIONS_PATH));
    expect($source)->toContain('CfopContext');
    expect($source)->toContain('ufEmitente');
    expect($source)->toContain('ufDestinatario');
});

it('validaIss respeita LC 116/2003 (2% a 5%)', function () {
    $source = file_get_contents(base_path(KB975_VALIDATIONS_PATH));
    expect($source)->toContain('LC 116/2003');
    // Range hardcoded canon
    expect($source)->toMatch('/aliquota\s*<\s*2/');
    expect($source)->toMatch('/aliquota\s*>\s*5/');
});

it('sells-kb975-next-action.css existe', function () {
    expect(file_exists(base_path(KB975_CSS_PATH)))->toBeTrue();
});

it('sells-kb975-next-action.css escopa CSS em .sells-cowork .vendas-aplus (coexiste com legacy)', function () {
    $source = file_get_contents(base_path(KB975_CSS_PATH));
    expect($source)->toContain('.sells-cowork .vendas-aplus .vd-next');
});

it('sells-kb975-next-action.css importada em inertia.css', function () {
    $source = file_get_contents(base_path(KB975_INERTIA_CSS));
    expect($source)->toContain('sells-kb975-next-action.css');
});

it('Show.tsx importa VdNextActionPanel e FsmActionPanel', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain("from './_components/VdNextActionPanel'");
    expect($source)->toContain("from './_components/FsmActionPanel'");
});

it('Show.tsx wire VdNextActionPanel quando current_stage_key existe', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('<VdNextActionPanel');
    expect($source)->toContain('saleId={headline.id}');
    expect($source)->toContain('paymentStatus={headline.payment_status}');
});

it('Show.tsx wire FsmActionPanel com onTransition refresh Inertia partial reload', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('<FsmActionPanel');
    expect($source)->toContain("router.reload({ only: ['detail', 'headline'] })");
});

it('Show.tsx mantém atalhos E/P/Esc (sem regressão)', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain("if (e.key === 'e' && permissions.edit)");
    expect($source)->toContain("if (e.key === 'p' && permissions.print)");
    expect($source)->toContain("if (e.key === 'Escape')");
});

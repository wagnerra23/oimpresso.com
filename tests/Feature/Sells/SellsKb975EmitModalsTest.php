<?php

declare(strict_types=1);

/**
 * Pest STRUCTURAL — KB-9.75 P0 gaps #2/#3/#4 + P1 #7 (Cowork bundle 2026-05-26).
 *
 * Cobre:
 *  - VdNfeEmitModal.tsx (#2) — emit NF-e 3-step modal
 *  - VdNfseEmitModal.tsx (#3) — emit NFS-e 3-step modal
 *  - VdBulkEmitModal.tsx (#4) — bulk emit progress tricolor
 *  - Saved view "Aguardando faturamento" (#7) — Sells/Index.tsx SAVED_VIEWS
 *  - Wire-up Show.tsx — onOpenEmit callback + modais condicionais
 *
 * Refs:
 *  - memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md
 */

const KB975_NFE_MODAL_PATH = 'resources/js/Pages/Sells/_components/VdNfeEmitModal.tsx';
const KB975_NFSE_MODAL_PATH = 'resources/js/Pages/Sells/_components/VdNfseEmitModal.tsx';
const KB975_BULK_MODAL_PATH = 'resources/js/Pages/Sells/_components/VdBulkEmitModal.tsx';
const KB975_EMIT_CSS = 'resources/css/sells-kb975-emit-modals.css';
const KB975_INERTIA_CSS = 'resources/css/inertia.css';
const KB975_INDEX_PAGE = 'resources/js/Pages/Sells/Index.tsx';
const KB975_SHOW_PAGE = 'resources/js/Pages/Sells/Show.tsx';
const KB975_NEXTACTION = 'resources/js/Pages/Sells/_components/VdNextActionPanel.tsx';

// ─────────────────────────────────────────────────────────────
// VdNfeEmitModal — gap #2
// ─────────────────────────────────────────────────────────────

it('VdNfeEmitModal.tsx existe', function () {
    expect(file_exists(base_path(KB975_NFE_MODAL_PATH)))->toBeTrue();
});

it('VdNfeEmitModal declara 3 steps (review/preview/transmit)', function () {
    $source = file_get_contents(base_path(KB975_NFE_MODAL_PATH));
    expect($source)->toContain('Revisar fiscal');
    expect($source)->toContain('Preview XML');
    expect($source)->toContain('Transmissão');
});

it('VdNfeEmitModal usa validators fiscais BR (NCM/CFOP/CST)', function () {
    $source = file_get_contents(base_path(KB975_NFE_MODAL_PATH));
    expect($source)->toContain('validaNcm');
    expect($source)->toContain('validaCfop');
    expect($source)->toContain('validaCst');
});

it('VdNfeEmitModal dispatch oimpresso:venda-emitted-nfe ao autorizar (gap #14)', function () {
    $source = file_get_contents(base_path(KB975_NFE_MODAL_PATH));
    expect($source)->toContain('oimpresso:venda-emitted-nfe');
});

it('VdNfeEmitModal renderiza protocolo SEFAZ no resultado autorizado', function () {
    $source = file_get_contents(base_path(KB975_NFE_MODAL_PATH));
    expect($source)->toContain('Protocolo:');
    expect($source)->toContain('NF-e autorizada');
});

it('VdNfeEmitModal handle contingência SEFAZ (modo offline)', function () {
    $source = file_get_contents(base_path(KB975_NFE_MODAL_PATH));
    expect($source)->toContain("'contingency'");
    expect($source)->toContain('contingência');
});

// ─────────────────────────────────────────────────────────────
// VdNfseEmitModal — gap #3
// ─────────────────────────────────────────────────────────────

it('VdNfseEmitModal.tsx existe', function () {
    expect(file_exists(base_path(KB975_NFSE_MODAL_PATH)))->toBeTrue();
});

it('VdNfseEmitModal usa validador ISS (LC 116/2003)', function () {
    $source = file_get_contents(base_path(KB975_NFSE_MODAL_PATH));
    expect($source)->toContain('validaIss');
    expect($source)->toContain('LC 116/2003');
});

it('VdNfseEmitModal dispatch oimpresso:venda-emitted-nfse', function () {
    $source = file_get_contents(base_path(KB975_NFSE_MODAL_PATH));
    expect($source)->toContain('oimpresso:venda-emitted-nfse');
});

it('VdNfseEmitModal usa RPS (não NFe) — terminologia correta', function () {
    $source = file_get_contents(base_path(KB975_NFSE_MODAL_PATH));
    expect($source)->toContain('RPS');
    expect($source)->toContain('Prefeitura');
});

// ─────────────────────────────────────────────────────────────
// VdBulkEmitModal — gap #4
// ─────────────────────────────────────────────────────────────

it('VdBulkEmitModal.tsx existe', function () {
    expect(file_exists(base_path(KB975_BULK_MODAL_PATH)))->toBeTrue();
});

it('VdBulkEmitModal define 4 status (pending/running/ok/bad)', function () {
    $source = file_get_contents(base_path(KB975_BULK_MODAL_PATH));
    expect($source)->toContain("'pending'");
    expect($source)->toContain("'running'");
    expect($source)->toContain("'ok'");
    expect($source)->toContain("'bad'");
});

it('VdBulkEmitModal renderiza progress bar tricolor', function () {
    $source = file_get_contents(base_path(KB975_BULK_MODAL_PATH));
    expect($source)->toContain('vd-bulk-progress-bar');
    expect($source)->toContain('vd-bulk-progress-fill');
});

it('VdBulkEmitModal dispatch eventos por kind (nfe/nfse)', function () {
    $source = file_get_contents(base_path(KB975_BULK_MODAL_PATH));
    expect($source)->toContain('oimpresso:venda-emitted-');
});

// ─────────────────────────────────────────────────────────────
// CSS
// ─────────────────────────────────────────────────────────────

it('sells-kb975-emit-modals.css existe', function () {
    expect(file_exists(base_path(KB975_EMIT_CSS)))->toBeTrue();
});

it('sells-kb975-emit-modals.css tem tokens canon Cowork (.vd-emit-* + .vd-bulk-*)', function () {
    $source = file_get_contents(base_path(KB975_EMIT_CSS));
    expect($source)->toContain('.vd-emit-bd');
    expect($source)->toContain('.vd-emit-modal');
    expect($source)->toContain('.vd-emit-steps');
    expect($source)->toContain('.vd-bulk-progress-bar');
});

it('sells-kb975-emit-modals.css importada em inertia.css', function () {
    $source = file_get_contents(base_path(KB975_INERTIA_CSS));
    expect($source)->toContain('sells-kb975-emit-modals.css');
});

// ─────────────────────────────────────────────────────────────
// Saved view "Aguardando faturamento" — gap #7
// ─────────────────────────────────────────────────────────────

it('Sells/Index.tsx adiciona saved view "Aguardando faturamento"', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    expect($source)->toContain("'aguardando-faturamento'");
    expect($source)->toContain('Aguardando faturamento');
});

it('Saved view "aguardando-faturamento" filtra payment != paid AND fiscal_status NULL', function () {
    $source = file_get_contents(base_path(KB975_INDEX_PAGE));
    // Filter checa payment_status !== 'paid' AND fiscal_status null/undefined
    expect($source)->toMatch("/payment_status\s*!==\s*'paid'\s*&&.*fiscal_status\s*===\s*null/s");
});

// ─────────────────────────────────────────────────────────────
// VdNextActionPanel onOpenEmit prop — gate wire-up
// ─────────────────────────────────────────────────────────────

it('VdNextActionPanel declara prop onOpenEmit', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION));
    expect($source)->toContain('onOpenEmit?:');
    expect($source)->toContain("'nfe' | 'nfse'");
});

it('VdNextActionPanel renderiza botões emit NF-e/NFS-e no gate fiscal', function () {
    $source = file_get_contents(base_path(KB975_NEXTACTION));
    expect($source)->toContain('Emitir NF-e agora');
    expect($source)->toContain('Emitir NFS-e agora');
});

// ─────────────────────────────────────────────────────────────
// Show.tsx wire-up
// ─────────────────────────────────────────────────────────────

it('Show.tsx importa VdNfeEmitModal + VdNfseEmitModal', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('VdNfeEmitModal');
    expect($source)->toContain('VdNfseEmitModal');
});

it('Show.tsx tem state emitModalKind', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('emitModalKind');
    expect($source)->toContain("'nfe' | 'nfse' | null");
});

it('Show.tsx passa onOpenEmit pro VdNextActionPanel', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('onOpenEmit={(kind) => setEmitModalKind(kind)}');
});

it('Show.tsx renderiza VdNfeEmitModal + VdNfseEmitModal no fim do return', function () {
    $source = file_get_contents(base_path(KB975_SHOW_PAGE));
    expect($source)->toContain('<VdNfeEmitModal');
    expect($source)->toContain('<VdNfseEmitModal');
    expect($source)->toContain("emitModalKind === 'nfe'");
    expect($source)->toContain("emitModalKind === 'nfse'");
});

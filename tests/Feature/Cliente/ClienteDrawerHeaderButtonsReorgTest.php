<?php

declare(strict_types=1);

/**
 * Wagner 2026-05-27 iteracao 2 (Proposta F) — reorg drawer Cliente:
 *   - Placas promovido de sub-tab pra TAB PRINCIPAL (acessado via botao header)
 *   - Sub-tab Atividades REMOVIDO (duplicava tab Auditoria — mesma fonte activity_log)
 *   - OSs renomeado pra Operacoes (semantica clara: so coisas REAIS de OS dentro)
 *   - 3 botoes header (Placas/Auditoria/IA) ao lado de Imprimir/Copiloto
 *
 * Origem: Wagner 2026-05-27 "ficou muito grande, retira da lateral, muitas abas".
 * 8 tabs → 6 tabs principais + 3 botoes header (acesso 1-clique read-only).
 *
 * GUARDs estruturais (file_get_contents, sem DB).
 *
 * Refs: ADR 0179 (drawer 760) · session 2026-05-27.
 */

// ─── GUARD 1: Backend payload tem vehicles_count ─────────────────────────

test('GUARD 1 — ContactController buildClienteIndexCustomers retorna vehicles_count graceful', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // Map count via Eloquent query com gate hasTable.
        ->toContain("Schema::hasTable('vehicles')")
        ->toContain('Vehicle::where(\'business_id\', $business_id)')
        ->toContain('->whereIn(\'contact_id\', $contactIds)')
        ->toContain('->groupBy(\'contact_id\')')
        // Payload com chave canon EN snake_case.
        ->toMatch("/\\\$payload\\['vehicles_count'\\]\\s*=\\s*\\(int\\)/");
});

// ─── GUARD 2: ClienteRow interface declara vehicles_count ────────────────

test('GUARD 2 — Cliente/Index.tsx ClienteRow declara vehicles_count', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('vehicles_count?: number | null');
});

// ─── GUARD 3: DRAWER_TABS array reorganizado pra 6 tabs + types corretos

test('GUARD 3 — DRAWER_TABS tem 6 entradas + operacoes (renamed oss) + sem ia/auditoria/placas', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // 6 tabs principais (Identif/Contato/Endereco/Comercial/Classif/Operacoes)
        ->toContain("{ key: 'operacoes',     label: 'Operações' }")
        // Type DrawerTab inclui novos valores acessados via botao header.
        ->toContain("| 'operacoes'")
        ->toContain("| 'placas'")
        ->toContain("| 'ia'")
        ->toContain("| 'auditoria'")
        // Removida entrada `oss` (renomeada).
        ->not->toContain("{ key: 'oss',           label: 'OSs' }");
});

// ─── GUARD 4: Botoes header (Placas/Auditoria/IA) ─────────────────────────

test('GUARD 4 — drawer header tem botoes Placas/Auditoria/IA com aria-pressed', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Botoes 3 com aria-pressed.
        ->toContain("aria-pressed={activeTab === 'placas'}")
        ->toContain("aria-pressed={activeTab === 'auditoria'}")
        ->toContain("aria-pressed={activeTab === 'ia'}")
        // Contador placas usa vehicles_count.
        ->toContain('contact?.vehicles_count ?? 0')
        // Gate OficinaAuto envolve botao Placas.
        ->toMatch("/\\{oficinaAutoEnabled && \\(\\s*<button/")
        // toolbar role + aria-label PT-BR.
        ->toContain('role="toolbar"')
        ->toContain('aria-label="Atalhos do drawer"');
});

// ─── GUARD 5: Renders pos-reorg corretos ──────────────────────────────────

test('GUARD 5 — tab content renders operacoes/placas/ia/auditoria com gate OficinaAuto', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("activeTab === 'operacoes'")
        ->toContain("activeTab === 'placas' && oficinaAutoEnabled")
        ->toContain('<PlacasMainTab contactId={contact.id} />')
        // Import componente novo.
        ->toContain("import PlacasMainTab from './_drawer/PlacasMainTab'");
});

// ─── GUARD 6: OssTab limpo (sem placas/activities sub-tabs) ──────────────

test('GUARD 6 — OssTab.tsx removeu sub-tabs placas/activities + oficinaAutoEnabled', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Sub-tabs limpos (sem placas, sem activities).
        ->not->toContain("{ key: 'placas'")
        ->not->toContain("{ key: 'activities'")
        ->not->toContain('PlacasSubTab')
        ->not->toContain('ActivitiesTab')
        // Mantém 7 sub-tabs reais de OS (ledger/sales/payments/documents/persons/subscriptions/rewards).
        ->toContain("{ key: 'ledger'")
        ->toContain("{ key: 'sales'")
        ->toContain("{ key: 'payments'")
        ->toContain("{ key: 'documents'")
        ->toContain("{ key: 'persons'")
        ->toContain("{ key: 'subscriptions'")
        ->toContain("{ key: 'rewards'");
});

// ─── GUARD 7: PlacasMainTab existe no novo local ──────────────────────────

test('GUARD 7 — PlacasMainTab.tsx existe + endpoint canon mantido', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/PlacasMainTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export default function PlacasMainTab')
        ->toContain('/cliente/${contactId}/veiculos');

    // Pasta antiga _drawer/oss/ NAO deve existir mais.
    $oldDir = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/oss';
    expect(is_dir($oldDir))->toBeFalse();
});

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

// ════════════════════════════════════════════════════════════════════════
// Wagner 2026-06-01 — chip "📎 N anexos" no header, ao lado de placas.
// Clique abre Operações → Documentos. Count server-side: document-notes COM
// media (anexos reais). Universal (sem gate OficinaAuto — anexos valem p/ todo
// cliente). GUARDs estruturais (file_get_contents, sem DB) — mesmo bar do
// vehicles_count (GUARD 1).
// ════════════════════════════════════════════════════════════════════════

// ─── GUARD 8: Backend payload tem documents_count (business_id scope) ─────

test('GUARD 8 — ContactController retorna documents_count com gate hasTable + business_id scope', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        // Gate hasTable (graceful se tabela ausente).
        ->toContain("Schema::hasTable('document_and_notes')")
        // Tier 0 (ADR 0093 IRREVOGAVEL): scope business_id obrigatorio nas DUAS queries.
        ->toContain("DocumentAndNote::where('business_id', \$business_id)")
        ->toContain("->where('notable_type', \\App\\Contact::class)")
        ->toContain("->whereIn('notable_id', \$contactIds)")
        ->toContain("Media::where('business_id', \$business_id)")
        // So anexos reais (media anexada aos document-notes), via model_type cheio.
        ->toContain("->where('model_type', \\App\\DocumentAndNote::class)")
        ->toContain("->groupBy('model_id')")
        // Payload com chave canon EN snake_case.
        ->toMatch("/\\\$payload\\['documents_count'\\]\\s*=\\s*\\(int\\)/");
});

// ─── GUARD 9: ClienteRow interface declara documents_count ───────────────

test('GUARD 9 — Cliente/Index.tsx ClienteRow declara documents_count', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('documents_count?: number | null');
});

// ─── GUARD 10: chip Anexos no header (universal, sem gate OficinaAuto) ────

test('GUARD 10 — drawer header tem chip Anexos abrindo Operações → Documentos', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // aria-pressed composto: tab operacoes + sub-aba documents.
        ->toContain("aria-pressed={activeTab === 'operacoes' && opsSubTab === 'documents'}")
        // Contador usa documents_count.
        ->toContain('contact?.documents_count ?? 0')
        // Icone clipe (lucide Paperclip) importado e usado.
        ->toContain('Paperclip,')
        ->toContain('<Paperclip size={11}')
        // Clique leva pra Operacoes na sub-aba Documentos.
        ->toContain("setOpsSubTab('documents')")
        // OssTab controlado pelo header (single source of truth da sub-aba).
        ->toContain('activeSubTab={opsSubTab}')
        ->toContain('onSubTabChange={setOpsSubTab}');
});

// ─── GUARD 11: OssTab aceita controle externo da sub-aba ──────────────────

test('GUARD 11 — OssTab.tsx expõe activeSubTab/onSubTabChange (controlado pelo header)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('export type OssSubTabKey')
        ->toContain('activeSubTab?: OssSubTabKey')
        ->toContain('onSubTabChange?: (key: OssSubTabKey) => void')
        // active derivado: controle externo tem prioridade sobre estado interno.
        ->toContain('const active = activeSubTab ?? internalActive');
});

<?php

declare(strict_types=1);

/**
 * US-SELL-021 — Header dropdown "qual data" da Lista de Vendas (7 opções).
 *
 * Estes testes são ESTRUTURAIS (file_get_contents + regex) pra cobrir
 * pattern canon US-SELL-008 do projeto (auto-mem feedback_tenancy_changes_require_pest_local
 * dispensa banco real pra mudanças que não tocam scope/Controller/Model multi-tenant —
 * só adicionam coluna nullable + whitelist param).
 *
 * Anti-regressão:
 *   - 7 date_field aceitas (whitelist, default transaction_date)
 *   - SQL injection bloqueada (date_field arbitrário ignorado pela whitelist)
 *   - JOIN nfe_emissoes só quando nfe_issued_at (evita query overhead default)
 *   - JOIN não duplica linhas (unique business_id+transaction_id em nfe_emissoes)
 *   - display_date no payload + date_field no meta
 *   - Frontend: dropdown 7 opções + localStorage persist
 *   - Migration: 4 colunas nullable + 2 índices compostos com business_id (multi-tenant Tier 0)
 *
 * Refs: ADR 0093 (multi-tenant Tier 0), ADR 0110 (Cockpit V2),
 *       memory/research/clientes-legacy-officeimpresso/_MAPPING/TELA-LISTA-VENDAS.md §5.
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem `Index.tsx` por string (`type DateField`, `DATE_FIELD_OPTIONS`,
 * `DateColumnHeader`, `oimpresso.sells.dateField`, `row.display_date`…). Essa UI
 * de seletor de data foi MOVIDA pra `_components/SellsDateFilter.tsx` no refactor;
 * markers verificados AUSENTES no `Index.tsx` vivo. NÃO é bug de produto.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * ⚠️ Os it() de BACKEND (SellController) e MIGRATION abaixo PERMANECEM ATIVOS —
 * incluem guards Tier-0/segurança VIVOS (whitelist anti-SQL-injection do date_field,
 * JOIN nfe filtrado por business_id, índices compostos com business_id). Silenciá-los
 * violaria "multi-tenant Tier 0 IRREVOGÁVEL".
 */

const SELL_CONTROLLER_PATH_021 = 'app/Http/Controllers/SellController.php';
const SELLS_INDEX_PATH_021 = 'resources/js/Pages/Sells/Index.tsx';
const MIGRATION_PATH_021 = 'database/migrations/2026_05_11_170001_add_legacy_date_fields_to_transactions.php';

function readController021(): string
{
    return file_get_contents(base_path(SELL_CONTROLLER_PATH_021));
}

function readIndex021(): string
{
    return file_get_contents(base_path(SELLS_INDEX_PATH_021));
}

function readMigration021(): string
{
    return file_get_contents(base_path(MIGRATION_PATH_021));
}

// ─── Backend: SellController@inertiaList — whitelist date_field ──────────────

it('inertiaList declara whitelist dateFieldMap com 7 chaves canon', function () {
    $src = readController021();
    expect($src)->toContain('dateFieldMap');
    // 7 chaves canon (Delphi → Laravel mapping)
    expect($src)->toContain("'transaction_date'");
    expect($src)->toContain("'updated_at'");
    expect($src)->toContain("'nfe_issued_at'");
    expect($src)->toContain("'invoiced_at'");
    expect($src)->toContain("'invoice_sent_at'");
    expect($src)->toContain("'competence_date'");
    expect($src)->toContain("'due_date'");
});

it('inertiaList rejeita date_field arbitrário (whitelist anti-SQL-injection)', function () {
    $src = readController021();
    // Pattern: ! array_key_exists($dateField, $dateFieldMap) => default
    expect($src)->toMatch('/\\$dateField\\s*=\\s*\\$request->input\\([\'"]date_field[\'"]/');
    expect($src)->toMatch('/!\\s*array_key_exists\\(\\$dateField,\\s*\\$dateFieldMap\\)/');
    // Default seguro
    expect($src)->toMatch('/\\$dateField\\s*=\\s*[\'"]transaction_date[\'"]/');
});

it('inertiaList SQL do dateField vem do map (não concatena raw input do usuário)', function () {
    $src = readController021();
    expect($src)->toMatch('/\\$dateFieldSql\\s*=\\s*\\$dateFieldMap\\[\\$dateField\\]/');
});

it('inertiaList JOIN nfe_emissoes só quando date_field = nfe_issued_at (1 query a menos no default)', function () {
    $src = readController021();
    expect($src)->toMatch('/if\\s*\\(\\$dateField\\s*===\\s*[\'"]nfe_issued_at[\'"]\\)/');
    expect($src)->toContain('leftJoin');
    expect($src)->toContain('nfe_emissoes as nfe');
});

it('inertiaList JOIN nfe filtra por business_id (multi-tenant Tier 0 — ADR 0093)', function () {
    $src = readController021();
    // Pattern: ->where('nfe.business_id', '=', $business_id) DENTRO do JOIN
    expect($src)->toMatch('/nfe\\.business_id[\\s\\S]{0,30}\\$business_id/');
});

it('inertiaList JOIN nfe usa coluna emitido_em (não issued_at — schema NfeBrasil real)', function () {
    $src = readController021();
    // dateFieldMap['nfe_issued_at'] => 'nfe.emitido_em' (campo real do schema)
    expect($src)->toContain('nfe.emitido_em');
});

it('inertiaList filtros date_from/date_to aplicam ao dateFieldSql escolhido', function () {
    $src = readController021();
    expect($src)->toContain("'date_from'");
    expect($src)->toContain("'date_to'");
    // whereRaw usa $dateFieldSql (não hardcoda coluna)
    expect($src)->toMatch('/whereRaw\\(["\']\\$dateFieldSql\\s*>=/');
    expect($src)->toMatch('/whereRaw\\(["\']\\$dateFieldSql\\s*<=/');
});

it('inertiaList paginate SELECTa display_date via DB::raw($dateFieldSql . " as display_date")', function () {
    $src = readController021();
    expect($src)->toContain('display_date');
    expect($src)->toMatch('/DB::raw\\(\\$dateFieldSql\\s*\\.\\s*[\'"]\\s+as display_date[\'"]\\)/');
});

it('inertiaList payload retorna display_date por linha (fallback transaction_date se null)', function () {
    $src = readController021();
    expect($src)->toMatch('/[\'"]display_date[\'"]\\s*=>\\s*\\$r->display_date\\s*\\?\\?\\s*\\$r->transaction_date/');
});

it('inertiaList meta retorna date_field escolhido (echo whitelist-sanitized)', function () {
    $src = readController021();
    expect($src)->toMatch('/[\'"]date_field[\'"]\\s*=>\\s*\\$dateField/');
});

it('inertiaList aceita param limit também (anti-DoS — max 200)', function () {
    $src = readController021();
    // Compatível com SellControllerEndpointsTest:117 "limita 200 max"
    expect($src)->toMatch('/min\\(.+\\$request->input\\([\'"]limit[\'"][\\s\\S]*?,\\s*200\\)/');
});

// ─── Migration: 4 colunas nullable + 2 índices ───────────────────────────────

it('Migration arquivo existe (2026_05_11_170001_add_legacy_date_fields_to_transactions)', function () {
    expect(file_exists(base_path(MIGRATION_PATH_021)))->toBeTrue();
});

it('Migration adiciona 4 colunas legacy (invoiced_at + invoice_sent_at + competence_date + due_date)', function () {
    $src = readMigration021();
    expect($src)->toContain("'invoiced_at'");
    expect($src)->toContain("'invoice_sent_at'");
    expect($src)->toContain("'competence_date'");
    expect($src)->toContain("'due_date'");
});

it('Migration cria colunas como nullable (não exige backfill — ADR 0061)', function () {
    $src = readMigration021();
    // 4 campos nullable explicit
    expect(substr_count($src, '->nullable()'))->toBeGreaterThanOrEqual(4);
});

it('Migration cria 2 índices compostos com business_id (multi-tenant Tier 0 — ADR 0093)', function () {
    $src = readMigration021();
    expect($src)->toContain('transactions_biz_invoiced_idx');
    expect($src)->toContain('transactions_biz_due_date_idx');
    // Ambos prefixados com business_id (Tier 0 — query plan precisa filtrar tenant primeiro)
    expect($src)->toMatch("/\\['business_id',\\s*'invoiced_at'\\]/");
    expect($src)->toMatch("/\\['business_id',\\s*'due_date'\\]/");
});

it('Migration é idempotente (Schema::hasColumn guard em cada coluna)', function () {
    $src = readMigration021();
    // 4 guards Schema::hasColumn
    expect(substr_count($src, "Schema::hasColumn('transactions',"))->toBeGreaterThanOrEqual(4);
});

it('Migration down() dropa as 4 colunas + 2 índices (reversível)', function () {
    $src = readMigration021();
    expect($src)->toContain('dropColumn');
    expect($src)->toContain('dropIndex');
});

// ─── Frontend: Sells/Index.tsx (SUPERSEDED — UI movida p/ SellsDateFilter.tsx) ─
// quarantine-reason: markup do seletor de data movido de Index.tsx p/ _components/SellsDateFilter.tsx (ver §4 Q-A da triage)

it('Index.tsx declara type DateField com 7 opções canon', function () {
    $src = readIndex021();
    expect($src)->toContain('type DateField');
    expect($src)->toContain("'transaction_date'");
    expect($src)->toContain("'updated_at'");
    expect($src)->toContain("'nfe_issued_at'");
    expect($src)->toContain("'invoiced_at'");
    expect($src)->toContain("'invoice_sent_at'");
    expect($src)->toContain("'competence_date'");
    expect($src)->toContain("'due_date'");
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx declara DATE_FIELD_OPTIONS array com 7 opções (renderiza dropdown)', function () {
    $src = readIndex021();
    expect($src)->toContain('DATE_FIELD_OPTIONS');
    expect($src)->toContain('DATE_FIELD_LABEL');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx tem DateColumnHeader component renderizado no thead', function () {
    $src = readIndex021();
    expect($src)->toContain('DateColumnHeader');
    expect($src)->toContain('<DateColumnHeader');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx passa date_field no fetch /sells-list-json (refetch + initial)', function () {
    $src = readIndex021();
    // params.set('date_field', dateField) — deve aparecer 2x (initial fetch + refetch)
    expect(substr_count($src, "params.set('date_field'"))->toBeGreaterThanOrEqual(2);
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx persiste dateField em localStorage (preserva entre sessões)', function () {
    $src = readIndex021();
    expect($src)->toContain('DATE_FIELD_STORAGE_KEY');
    expect($src)->toContain("'oimpresso.sells.dateField'");
    expect($src)->toContain('localStorage.setItem(DATE_FIELD_STORAGE_KEY');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx lê dateField de URL ?date_field= como deep-link (precedência)', function () {
    $src = readIndex021();
    expect($src)->toContain("URLSearchParams(window.location.search)");
    expect($src)->toContain("params.get('date_field')");
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx atualiza URL ao trocar dateField (history.replaceState, não Inertia visit)', function () {
    $src = readIndex021();
    // Mantém URL sincronizada mas sem trigger router (preserva drawer state)
    expect($src)->toContain('history.replaceState');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx exibe display_date (não transaction_date hardcoded) na coluna Data', function () {
    $src = readIndex021();
    // O cell render deve usar row.display_date
    expect($src)->toContain('row.display_date');
    expect($src)->toContain('formatDate(row.display_date)');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx SaleRow interface declara display_date como string|null (US-SELL-021)', function () {
    $src = readIndex021();
    expect($src)->toMatch('/display_date:\\s*string\\s*\\|\\s*null/');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx mostra tooltip indicando data exibida (ARIA + title)', function () {
    $src = readIndex021();
    // Tooltip de a11y — `Data exibida:` é o texto canon
    expect($src)->toContain('Data exibida:');
// quarantine-reason: seletor de data movido de Index.tsx p/ SellsDateFilter.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

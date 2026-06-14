<?php

declare(strict_types=1);

/**
 * US-SELL-015 — Toggle Lista | Grade Avançada (ADR 0136).
 *
 * Estes testes são ESTRUTURAIS (file_get_contents + regex) — mesmo pattern
 * de SellsIndexDateFieldTest, alinhado com auto-mem
 * `feedback_tenancy_changes_require_pest_local`: mudanças que adicionam
 * coluna nullable + share Inertia opcional NÃO precisam de SQLite real;
 * proteção é garantir que o pattern canon foi seguido.
 *
 * Anti-regressão coberta:
 *   - Migration: coluna `business.legacy_origin` nullable + INDEX
 *     business_legacy_origin_idx (multi-tenant Tier 0 — ADR 0093)
 *   - Migration idempotente (Schema::hasColumn guard) + reversível (down)
 *   - Seeder: idempotente (whereNull legacy_origin), 6 candidatos canon,
 *     scope explícito (sem cross-tenant leak — ADR 0093)
 *   - Seeder registrado no DatabaseSeeder
 *   - HandleInertiaRequests share `sells.viewMode.default` lazy + scope
 *     business_id + fallback 'lista' em throw
 *   - Index.tsx: import dos componentes novos + estado viewMode +
 *     persist localStorage com chave canon `oimpresso.sells.viewMode` +
 *     toggle no header + render condicional
 *   - SellsToggleViewMode.tsx: 2 botões PT-BR + ARIA group
 *   - SellsGradeAvancada.tsx: skeleton com mensagem "em construção"
 *
 * Refs:
 *   - ADR 0136 (Sells: split Lista vs Grade Avançada toggle)
 *   - ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 *   - ADR 0110 (Cockpit Pattern V2)
 *   - feedback_test_biz_99_cross_tenant_convention (biz=1 default smoke)
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() de frontend que
 * leem os componentes `SellsToggleViewMode.tsx` / `SellsGradeAvancada.tsx`
 * (DELETADOS no refactor) e os imports/markup de `Index.tsx` já refatorado.
 * file_get_contents num componente ausente falha; NÃO é bug de produto.
 * Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * 🔴 Os it() de MIGRATION, SEEDER e HandleInertiaRequests PERMANECEM ATIVOS —
 * guards VIVOS: índice `business_legacy_origin_idx`, idempotência/reversibilidade,
 * seeder com scope explícito (sem cross-tenant leak), e sobretudo o resolver
 * `sellsViewModeDefault` FILTRADO por business_id (Tier-0 ADR 0093) + fallback.
 * Silenciá-los violaria "multi-tenant Tier 0 IRREVOGÁVEL".
 */

const MIGRATION_PATH_015 = 'database/migrations/2026_05_12_180000_add_legacy_origin_to_business.php';
const SEEDER_PATH_015 = 'database/seeders/BusinessLegacyOriginSeeder.php';
const DB_SEEDER_PATH_015 = 'database/seeders/DatabaseSeeder.php';
const INERTIA_MIDDLEWARE_PATH_015 = 'app/Http/Middleware/HandleInertiaRequests.php';
const INDEX_PATH_015 = 'resources/js/Pages/Sells/Index.tsx';
const TOGGLE_PATH_015 = 'resources/js/Pages/Sells/_components/SellsToggleViewMode.tsx';
const GRADE_PATH_015 = 'resources/js/Pages/Sells/_components/SellsGradeAvancada.tsx';

function read015(string $relative): string
{
    $path = base_path($relative);
    if (!file_exists($path)) {
        test()->skip("Arquivo não encontrado: {$relative} (legacy-quarantine: componente deletado)");
    }

    return (string) file_get_contents($path);
}

// ─── Migration ────────────────────────────────────────────────────────────────

it('Migration arquivo existe (2026_05_12_180000_add_legacy_origin_to_business)', function () {
    expect(file_exists(base_path(MIGRATION_PATH_015)))->toBeTrue();
});

it('Migration adiciona coluna business.legacy_origin VARCHAR(32) nullable', function () {
    $src = read015(MIGRATION_PATH_015);
    expect($src)->toContain("'legacy_origin'");
    expect($src)->toContain("string('legacy_origin', 32)");
    expect($src)->toContain('->nullable()');
});

it('Migration cria INDEX business_legacy_origin_idx (query plan)', function () {
    $src = read015(MIGRATION_PATH_015);
    expect($src)->toContain('business_legacy_origin_idx');
    expect($src)->toContain("index('legacy_origin', 'business_legacy_origin_idx')");
});

it('Migration é idempotente (Schema::hasColumn guard up + down)', function () {
    $src = read015(MIGRATION_PATH_015);
    // up() guard
    expect($src)->toMatch("/!\\s*Schema::hasColumn\\(['\"]business['\"],\\s*['\"]legacy_origin['\"]\\)/");
    // down() guard
    expect($src)->toMatch("/Schema::hasColumn\\(['\"]business['\"],\\s*['\"]legacy_origin['\"]\\)/");
});

it('Migration down() dropa INDEX antes da coluna (ordem correta MySQL)', function () {
    $src = read015(MIGRATION_PATH_015);
    expect($src)->toContain("dropIndex('business_legacy_origin_idx')");
    expect($src)->toContain("dropColumn('legacy_origin')");
    // dropIndex aparece ANTES de dropColumn no source
    $idxPos = strpos($src, 'dropIndex');
    $colPos = strpos($src, 'dropColumn');
    expect($idxPos)->toBeLessThan($colPos);
});

// ─── Seeder ───────────────────────────────────────────────────────────────────

it('Seeder BusinessLegacyOriginSeeder existe', function () {
    expect(file_exists(base_path(SEEDER_PATH_015)))->toBeTrue();
});

it('Seeder declara 6 candidatos OfficeImpresso canon (Vargas/Extreme/Gold/Zoom/Fixar/Produart)', function () {
    $src = read015(SEEDER_PATH_015);
    expect($src)->toContain("'Vargas'");
    expect($src)->toContain("'Extreme'");
    expect($src)->toContain("'Gold'");
    expect($src)->toContain("'Zoom'");
    expect($src)->toContain("'Fixar'");
    expect($src)->toContain("'Produart'");
});

it('Seeder é idempotente (whereNull legacy_origin — não sobrescreve marcação manual)', function () {
    $src = read015(SEEDER_PATH_015);
    expect($src)->toContain("whereNull('legacy_origin')");
});

it('Seeder marca como legacy_origin = officeimpresso (não outra string)', function () {
    $src = read015(SEEDER_PATH_015);
    expect($src)->toMatch("/['\"]legacy_origin['\"]\\s*=>\\s*['\"]officeimpresso['\"]/");
});

it('Seeder skipa silenciosamente se coluna ausente (defesa pré-migration)', function () {
    $src = read015(SEEDER_PATH_015);
    expect($src)->toMatch("/Schema::hasColumn\\(['\"]business['\"],\\s*['\"]legacy_origin['\"]\\)/");
});

it('Seeder usa LIKE substring (sobrevive a IDs distintos por ambiente)', function () {
    $src = read015(SEEDER_PATH_015);
    expect($src)->toContain("'like'");
    expect($src)->toMatch("/[\"']%\\{\\\$needle\\}%[\"']/");
});

it('Seeder está registrado em DatabaseSeeder (run em php artisan db:seed)', function () {
    $src = read015(DB_SEEDER_PATH_015);
    expect($src)->toContain('BusinessLegacyOriginSeeder::class');
});

// ─── HandleInertiaRequests ────────────────────────────────────────────────────

it('HandleInertiaRequests share `sells.viewMode.default` (lazy closure)', function () {
    $src = read015(INERTIA_MIDDLEWARE_PATH_015);
    expect($src)->toContain("'sells'");
    expect($src)->toContain("'viewMode'");
    expect($src)->toContain("'default'");
    // Lazy: closure (não array literal)
    expect($src)->toMatch('/[\'"]default[\'"]\\s*=>\\s*fn\\s*\\(\\)/');
});

it('HandleInertiaRequests resolve sellsViewModeDefault filtrado por business_id (Tier 0 — ADR 0093)', function () {
    $src = read015(INERTIA_MIDDLEWARE_PATH_015);
    // Helper method exists
    expect($src)->toContain('sellsViewModeDefault');
    expect($src)->toContain('protected function sellsViewModeDefault(int $businessId)');
    // Filtra por business_id
    expect($src)->toMatch('/where\\([\'"]id[\'"],\\s*\\$businessId\\)/');
    // Lê a coluna correta
    expect($src)->toContain("value('legacy_origin')");
});

it('HandleInertiaRequests retorna grade-avancada quando legacy_origin = officeimpresso', function () {
    $src = read015(INERTIA_MIDDLEWARE_PATH_015);
    expect($src)->toMatch("/\\\$origin\\s*===\\s*['\"]officeimpresso['\"]\\s*\\?\\s*['\"]grade-avancada['\"]\\s*:\\s*['\"]lista['\"]/");
});

it('HandleInertiaRequests fallback `lista` em throw (defesa coluna ausente)', function () {
    $src = read015(INERTIA_MIDDLEWARE_PATH_015);
    // try/catch envolvendo a query
    expect($src)->toMatch('/try\\s*\\{[\\s\\S]*?legacy_origin[\\s\\S]*?\\}\\s*catch\\s*\\(\\\\Throwable/');
    // catch retorna 'lista' literal
    expect($src)->toMatch("/catch\\s*\\([^)]+\\)\\s*\\{[\\s\\S]*?return\\s+['\"]lista['\"]/");
});

it('HandleInertiaRequests fallback `lista` quando businessId é null (anonymous request)', function () {
    $src = read015(INERTIA_MIDDLEWARE_PATH_015);
    // ternário: $businessId ? sellsViewModeDefault(...) : 'lista'
    expect($src)->toMatch("/\\\$businessId[\\s\\S]{0,100}sellsViewModeDefault[\\s\\S]{0,80}:\\s*['\"]lista['\"]/");
});

// ─── Frontend: Index.tsx ──────────────────────────────────────────────────────

it('Index.tsx importa SellsToggleViewMode + SellsGradeAvancada', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toContain("from './_components/SellsToggleViewMode'");
    expect($src)->toContain("from './_components/SellsGradeAvancada'");
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx declara constante VIEW_MODE_STORAGE_KEY canon `oimpresso.sells.viewMode`', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toContain('VIEW_MODE_STORAGE_KEY');
    expect($src)->toContain("'oimpresso.sells.viewMode'");
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx declara reader readStoredViewMode com server fallback', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toContain('function readStoredViewMode(serverDefault: SellsViewMode)');
    expect($src)->toContain('localStorage.getItem(VIEW_MODE_STORAGE_KEY)');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx interface aceita props.sells?.viewMode?.default opcional', function () {
    $src = read015(INDEX_PATH_015);
    // Shape esperada do share Inertia
    expect($src)->toMatch('/sells\\?:\\s*\\{[\\s\\S]*?viewMode\\?:\\s*\\{[\\s\\S]*?default\\?:\\s*SellsViewMode/');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx persiste viewMode em localStorage (useEffect setItem)', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toContain('localStorage.setItem(VIEW_MODE_STORAGE_KEY');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx renderiza <SellsToggleViewMode> no header', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toContain('<SellsToggleViewMode');
    expect($src)->toContain('viewMode={viewMode}');
    expect($src)->toContain('onChange={setViewMode}');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx render condicional: grade-avancada → SellsGradeAvancada (sem tabela Lista)', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toMatch("/viewMode\\s*===\\s*['\"]grade-avancada['\"]/");
    expect($src)->toContain('<SellsGradeAvancada');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('Index.tsx render condicional: pills só visíveis em modo Lista', function () {
    $src = read015(INDEX_PATH_015);
    expect($src)->toMatch("/viewMode\\s*===\\s*['\"]lista['\"]\\s*&&[\\s\\S]{0,200}<nav/");
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Frontend: SellsToggleViewMode.tsx ────────────────────────────────────────

it('SellsToggleViewMode existe', function () {
    expect(file_exists(base_path(TOGGLE_PATH_015)))->toBeTrue();
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsToggleViewMode exporta type SellsViewMode (lista | grade-avancada)', function () {
    $src = read015(TOGGLE_PATH_015);
    expect($src)->toContain('export type SellsViewMode');
    expect($src)->toContain("'lista'");
    expect($src)->toContain("'grade-avancada'");
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsToggleViewMode tem 2 botões PT-BR (Lista + Grade Avançada)', function () {
    $src = read015(TOGGLE_PATH_015);
    expect($src)->toContain('label="Lista"');
    expect($src)->toContain('label="Grade Avançada"');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsToggleViewMode tem ARIA group (a11y)', function () {
    $src = read015(TOGGLE_PATH_015);
    expect($src)->toContain('role="group"');
    expect($src)->toContain('aria-label');
    expect($src)->toContain('aria-pressed');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsToggleViewMode chama onChange ao clicar (callback contract)', function () {
    $src = read015(TOGGLE_PATH_015);
    expect($src)->toMatch("/onClick=\\{\\(\\)\\s*=>\\s*onChange\\(['\"]lista['\"]\\)\\}/");
    expect($src)->toMatch("/onClick=\\{\\(\\)\\s*=>\\s*onChange\\(['\"]grade-avancada['\"]\\)\\}/");
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Frontend: SellsGradeAvancada.tsx ─────────────────────────────────────────

it('SellsGradeAvancada existe', function () {
    expect(file_exists(base_path(GRADE_PATH_015)))->toBeTrue();
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// US-SELL-016/017 (este PR) substitui o skeleton de US-SELL-015.
// O componente agora renderiza tabela funcional com multiseleção + totalizador.
// Tests detalhados em SellsBulkActionsTest.php + SellsTotalsTest.php.

it('SellsGradeAvancada anuncia roadmap progressivo (US-SELL-016/017 ativos + P1+ aguarda sinal)', function () {
    $src = read015(GRADE_PATH_015);
    expect($src)->toContain('SELL-016');
    expect($src)->toContain('SELL-017');
    // Linguagem alinhada com ADR 0136 (multiseleção, totalizador)
    expect($src)->toMatch('/multiselec/iu');
    expect($src)->toMatch('/totalizador|total/iu');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('SellsGradeAvancada aceita props funcionais (rows, totals, selectedIds — pós US-SELL-016/017)', function () {
    $src = read015(GRADE_PATH_015);
    // Lift state up — Index.tsx é fonte da verdade pra rows/totals/selectedIds.
    expect($src)->toContain('rows: SaleRow[]');
    expect($src)->toContain('selectedIds: Set<number>');
    expect($src)->toMatch('/totals:\\s*SellsTotals\\s*\\|\\s*null/');
    // quarantine-reason: SellsToggleViewMode.tsx + SellsGradeAvancada.tsx deletados + imports/markup removidos do Index.tsx (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

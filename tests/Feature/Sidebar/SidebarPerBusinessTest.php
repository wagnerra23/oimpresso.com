<?php

declare(strict_types=1);

/**
 * US-UI-SIDEBAR-001 — Feature flag por business no sidebar AppShellV2
 * (config-driven via business.sidebar_hidden_groups).
 *
 * Estes testes são ESTRUTURAIS (file_get_contents + regex) — mesmo pattern
 * de GradeAvancadaToggleTest (US-SELL-015), alinhado com auto-mem
 * `feedback_tenancy_changes_require_pest_local`: mudanças que adicionam
 * coluna nullable + filter Inertia opcional NÃO precisam de SQLite real;
 * proteção é garantir que o pattern canon foi seguido.
 *
 * Anti-regressão coberta:
 *   - Migration: coluna `business.sidebar_hidden_groups` JSON nullable
 *     (multi-tenant Tier 0 — ADR 0093)
 *   - Migration idempotente (Schema::hasColumn guard) + reversível (down)
 *   - Seeder: idempotente (whereNull sidebar_hidden_groups), case piloto
 *     Martinho cadastrado, scope explícito (sem cross-tenant leak)
 *   - Seeder registrado no DatabaseSeeder
 *   - LegacyMenuAdapter: filter aplicado em build(), default safe (lista
 *     vazia → menu intacto), scope explícito por business_id do user
 *   - LegacyMenuAdapter: try/catch envolvendo query (coluna ausente OK)
 *   - LegacyMenuAdapter: mirror de SIDEBAR_GROUPS pra resolver chave→items
 *
 * Refs:
 *   - ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 *   - ADR 0105 (Cliente como sinal qualificado — Martinho)
 *   - ADR 0121 (Modular especializado por vertical)
 *   - Skill sidebar-menu-arch
 *   - memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md
 *   - feedback_test_biz_99_cross_tenant_convention (biz=1 default smoke)
 */

const MIGRATION_PATH_SIDEBAR_001  = 'database/migrations/2026_05_14_120000_add_sidebar_hidden_groups_to_business.php';
const SEEDER_PATH_SIDEBAR_001     = 'database/seeders/BusinessSidebarConfigSeeder.php';
const DB_SEEDER_PATH_SIDEBAR_001  = 'database/seeders/DatabaseSeeder.php';
const ADAPTER_PATH_SIDEBAR_001    = 'app/Services/LegacyMenuAdapter.php';
const FRONT_SIDEBAR_PATH_001      = 'resources/js/Components/cockpit/Sidebar.tsx';
const RUNBOOK_PATH_SIDEBAR_001    = 'memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md';

function readSidebar001(string $relative): string
{
    return file_get_contents(base_path($relative));
}

// ─── Migration ────────────────────────────────────────────────────────────────

it('Migration arquivo existe (2026_05_14_120000_add_sidebar_hidden_groups_to_business)', function () {
    expect(file_exists(base_path(MIGRATION_PATH_SIDEBAR_001)))->toBeTrue();
});

it('Migration adiciona coluna business.sidebar_hidden_groups JSON nullable', function () {
    $src = readSidebar001(MIGRATION_PATH_SIDEBAR_001);
    expect($src)->toContain("'sidebar_hidden_groups'");
    expect($src)->toContain("json('sidebar_hidden_groups')");
    expect($src)->toContain('->nullable()');
});

it('Migration é idempotente (Schema::hasColumn guard up + down)', function () {
    $src = readSidebar001(MIGRATION_PATH_SIDEBAR_001);
    expect($src)->toMatch("/!\\s*Schema::hasColumn\\(['\"]business['\"],\\s*['\"]sidebar_hidden_groups['\"]\\)/");
    expect($src)->toMatch("/Schema::hasColumn\\(['\"]business['\"],\\s*['\"]sidebar_hidden_groups['\"]\\)/");
});

it('Migration down() dropa a coluna', function () {
    $src = readSidebar001(MIGRATION_PATH_SIDEBAR_001);
    expect($src)->toContain("dropColumn('sidebar_hidden_groups')");
});

it('Migration declara comment explicando schema (case-insensitive + grupos/items)', function () {
    $src = readSidebar001(MIGRATION_PATH_SIDEBAR_001);
    // comment menciona case-insensitive ou similar pra docs vivas no banco
    expect($src)->toMatch('/->comment\\(/i');
    expect($src)->toMatch('/case-insensitive/i');
});

// ─── Seeder ───────────────────────────────────────────────────────────────────

it('Seeder BusinessSidebarConfigSeeder existe', function () {
    expect(file_exists(base_path(SEEDER_PATH_SIDEBAR_001)))->toBeTrue();
});

it('Seeder cadastra Martinho como caso piloto', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain("'Martinho'");
});

it('Seeder esconde grupos RH/estoque/conhecimento/governanca/plataforma pra Martinho', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain("'rh'");
    expect($src)->toContain("'estoque'");
    expect($src)->toContain("'conhecimento'");
    expect($src)->toContain("'governanca'");
    expect($src)->toContain("'plataforma'");
});

it('Seeder esconde items específicos (Reparar/Officeimpresso/Projeto/ADS) pra Martinho', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain("'Reparar'");
    expect($src)->toContain("'Officeimpresso'");
    expect($src)->toContain("'Projeto'");
    expect($src)->toContain("'ADS'");
});

it('Seeder é idempotente (whereNull sidebar_hidden_groups — não sobrescreve marcação manual)', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain("whereNull('sidebar_hidden_groups')");
});

it('Seeder skipa silenciosamente se coluna ausente (defesa pré-migration)', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toMatch("/Schema::hasColumn\\(['\"]business['\"],\\s*['\"]sidebar_hidden_groups['\"]\\)/");
});

it('Seeder usa LIKE substring (sobrevive a IDs distintos por ambiente)', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain("'like'");
    expect($src)->toMatch("/[\"']%\\{\\\$needle\\}%[\"']/");
});

it('Seeder serializa array via json_encode (não literal string)', function () {
    $src = readSidebar001(SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain('json_encode($hiddenList)');
});

it('Seeder está registrado em DatabaseSeeder', function () {
    $src = readSidebar001(DB_SEEDER_PATH_SIDEBAR_001);
    expect($src)->toContain('BusinessSidebarConfigSeeder::class');
});

// ─── LegacyMenuAdapter ────────────────────────────────────────────────────────

it('LegacyMenuAdapter importa DB e Schema facades', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    expect($src)->toContain('use Illuminate\Support\Facades\DB;');
    expect($src)->toContain('use Illuminate\Support\Facades\Schema;');
});

it('LegacyMenuAdapter chama applyHiddenGroupsFilter dentro de build()', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    expect($src)->toContain('applyHiddenGroupsFilter');
    // build() chama o filtro (em qualquer ordem antes do return)
    expect($src)->toMatch('/public function build\\(\\)[\\s\\S]*?applyHiddenGroupsFilter\\(\\$items\\)/');
});

it('LegacyMenuAdapter expõe mirror SIDEBAR_GROUPS (sincronia frontend↔backend)', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    expect($src)->toContain('sidebarGroupsMirror');
    // grupos canon estão presentes
    expect($src)->toContain("'rh'");
    expect($src)->toContain("'fiscal'");
    expect($src)->toContain("'plataforma'");
    expect($src)->toContain("'oficina'");
});

it('LegacyMenuAdapter resolve hiddenList scopado por business_id do user (Tier 0 — ADR 0093)', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    expect($src)->toContain('protected function hiddenList()');
    // Scope explícito por business_id
    expect($src)->toMatch('/where\\([\'"]id[\'"],\\s*\\$user->business_id\\)/');
    // Lê a coluna correta
    expect($src)->toContain("value('sidebar_hidden_groups')");
});

it('LegacyMenuAdapter hiddenList default safe (lista vazia em qualquer falha)', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    // try/catch envolve a query
    expect($src)->toMatch('/try\\s*\\{[\\s\\S]*?sidebar_hidden_groups[\\s\\S]*?\\}\\s*catch\\s*\\(\\\\?Throwable/');
    // Defesa: Schema::hasColumn guard antes do query
    expect($src)->toMatch("/Schema::hasColumn\\(['\"]business['\"],\\s*['\"]sidebar_hidden_groups['\"]\\)/");
});

it('LegacyMenuAdapter hiddenList retorna [] quando user/business_id ausente', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    // Guard: !user || empty(business_id)
    expect($src)->toMatch('/!\\s*\\$user\\s*\\|\\|\\s*empty\\(\\$user->business_id\\)/');
});

it('LegacyMenuAdapter applyHiddenGroupsFilter respeita default safe (lista vazia → items intactos)', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    expect($src)->toContain('protected function applyHiddenGroupsFilter(array $items)');
    // Early return se hidden vazio
    expect($src)->toMatch('/empty\\(\\$hidden\\)[\\s\\S]{0,80}return\\s+\\$items/');
});

it('LegacyMenuAdapter applyHiddenGroupsFilter expande chave de grupo em set de labels', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    // Match case-insensitive normalizado
    expect($src)->toContain('mb_strtolower');
    // Cobre os 2 casos: chave de grupo OU label direto
    expect($src)->toMatch('/isset\\(\\$groupsMirror\\[\\$entry\\]\\)/');
});

it('LegacyMenuAdapter cache per-request via static property (não polui requests futuros)', function () {
    $src = readSidebar001(ADAPTER_PATH_SIDEBAR_001);
    // static $cache pattern dentro de hiddenList()
    expect($src)->toMatch('/static\\s+\\$cache\\s*=\\s*null/');
});

// ─── Frontend mirror — SIDEBAR_GROUPS keys precisam casar com backend ────────

it('Frontend Sidebar.tsx tem todas as keys de grupo declaradas no mirror backend', function () {
    $front = readSidebar001(FRONT_SIDEBAR_PATH_001);
    $backend = readSidebar001(ADAPTER_PATH_SIDEBAR_001);

    // Keys canon que DEVEM existir em ambos os lados (frontend SIDEBAR_GROUPS
    // + backend sidebarGroupsMirror). Detecta drift se alguém adicionar grupo
    // num lado e esquecer do outro.
    $canonKeys = ['office', 'oficina', 'fin', 'estoque', 'fiscal', 'rh', 'conhecimento', 'rel', 'ia', 'governanca', 'plataforma'];
    foreach ($canonKeys as $key) {
        expect($front)->toContain("'{$key}'"); // frontend SIDEBAR_GROUPS key
        expect($backend)->toContain("'{$key}'"); // backend mirror key
    }
});

// ─── Documentation guard ──────────────────────────────────────────────────────

it('RUNBOOK existe documentando como ativar pra próximo cliente', function () {
    expect(file_exists(base_path(RUNBOOK_PATH_SIDEBAR_001)))->toBeTrue();
});

it('RUNBOOK menciona caso piloto Martinho + próximos candidatos OfficeImpresso', function () {
    $src = readSidebar001(RUNBOOK_PATH_SIDEBAR_001);
    expect($src)->toContain('Martinho');
    // Próximos candidatos OfficeImpresso (Vargas etc — ADR 0121)
    expect($src)->toMatch('/Vargas|Extreme|OfficeImpresso/i');
});

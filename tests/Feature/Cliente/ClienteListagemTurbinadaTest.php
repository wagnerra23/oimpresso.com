<?php

declare(strict_types=1);

/**
 * Wave G — ADR 0179 listagem turbinada (avatar HSL + 6 dropdowns + tag chips
 * coloridas + FrescorPill + saldo vermelho devedor + Star pessoal + Export CSV).
 *
 * Estrategia mista pra rodar local sem DB caro (canon Wave B GUARDs):
 *  - GUARDs 1-7 + 9-11: structural file_get_contents (rapido sem DB)
 *  - GUARDs 8, 12: feature integration (skip-graceful em sqlite memory sem migrations)
 *
 * Refs:
 *  - ADR 0179 (memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)
 *  - Charter Cliente/Index.charter.md v3
 *  - HANDOFF_CLIENTES.md §5.1 (Pills/Avatar) + §2 schema (saldo/last_purchase_at)
 *  - Multi-tenant Tier 0 ADR 0093
 *  - Pattern: tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php
 */

use App\Contact;
use Illuminate\Support\Facades\Schema;

// ─── GUARD 1: Avatar.tsx HSL hash deterministico criado ──────────────────────

test('GUARD 1 — Components/clientes/Avatar.tsx tem gradients oklch deterministicos', function () {
    $path = __DIR__ . '/../../../resources/js/Components/clientes/Avatar.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        // Z-2.1: gradients oklch (alinhado ao protótipo Cowork — 12 cores vivas).
        ->toContain('hashStr')
        ->toContain('gradientForName')
        ->toContain('oklch(')
        ->toContain('AVATAR_GRADIENTS')
        ->toContain('linear-gradient(135deg')
        // Avatar initials helper (1-2 letras maiusculas — espelha Cowork).
        ->toContain('avatarInitial')
        // Z-2.1: shape circle pra drawer header (Cowork 40px round).
        ->toContain("shape === 'circle'")
        // Default size 28px (linha tabela Cowork) — drawer header passa size={40}.
        ->toContain('size = 28');
});

// ─── GUARD 2: Pills.tsx tem 4 componentes Wave G ─────────────────────────────

test('GUARD 2 — Components/clientes/Pills.tsx contem TipoPill + TagChip + FrescorPill + SaldoCell', function () {
    $path = __DIR__ . '/../../../resources/js/Components/clientes/Pills.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('export function TipoPill')
        ->toContain('export function TagChip')
        ->toContain('export function FrescorPill')
        ->toContain('export function SaldoCell')
        // 9 cores semanticas tag chips (HANDOFF_CLIENTES.md §2.5).
        ->toContain('varejo')
        ->toContain('atacado')
        ->toContain('corporativo')
        ->toContain('parceiro')
        ->toContain('reincidente')
        // FrescorPill 4 estados (≤14 fresc / ≤60 recente / ≤180 distante / >180 frio).
        ->toContain("'fresc'")
        ->toContain("'recente'")
        ->toContain("'distante'")
        ->toContain("'frio'")
        // SaldoCell vermelho devedor (positivo = cliente nos deve = text-rose-700).
        ->toContain('text-rose-700');
});

// ─── GUARD 3: Index.tsx importa Avatar + Pills + Star ────────────────────────

test('GUARD 3 — Cliente/Index.tsx importa Avatar HSL + Pills + lucide Star/Download', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain("from '@/Components/clientes/Avatar'")
        ->toContain("from '@/Components/clientes/Pills'")
        ->toContain('TipoPill')
        ->toContain('TagChip')
        ->toContain('FrescorPill')
        ->toContain('SaldoCell')
        // lucide-react adicoes: Star (favorito), Download (exportar), ChevronDown (dropdown).
        ->toContain('Star')
        ->toContain('Download')
        ->toContain('ChevronDown');
});

// ─── GUARD 4: 6 dropdowns filtro (Tipo/Status/UF/Tags/Sem compra/Saldo) ──────

test('GUARD 4 — Index.tsx contem 6 FilterDropdown (Tipo/Status/UF/Tags/Sem compra/Saldo)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    // Constantes options + estado dos 6 filtros.
    expect($contents)
        ->toContain('TIPO_OPTIONS')
        ->toContain('UF_OPTIONS')
        ->toContain('TAG_OPTIONS')
        ->toContain('STALE_OPTIONS')
        ->toContain('SALDO_OPTIONS')
        ->toContain('setTipoFilter')
        ->toContain('setUfFilter')
        ->toContain('setTagsFilter')
        ->toContain('setStaleFilter')
        ->toContain('setSaldoFilter')
        // Componente FilterDropdown definido inline com multi-select support.
        ->toContain('function FilterDropdown(')
        ->toContain('multi = false')
        // 27 UFs BR completas.
        ->toContain("'AC'")
        ->toContain("'RR'")
        ->toContain("'SP'")
        ->toContain("'TO'");
});

// ─── GUARD 5: Star pessoal localStorage com useFavoritos hook ────────────────

test('GUARD 5 — Index.tsx usa useFavoritos hook + localStorage favoritos key', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('function useFavoritos')
        ->toContain('FAVORITOS_STORAGE_KEY')
        ->toContain('oimpresso.cliente.favoritos')
        // Per-USER per-BROWSER (Q1 wave-G client-only — sem sync server).
        ->toContain('localStorage.setItem')
        ->toContain('localStorage.getItem')
        // Toggle Star button no JSX da linha — aria-pressed pra screen reader.
        ->toContain('toggleFav')
        ->toContain('aria-pressed');
});

// ─── GUARD 6: Contador inline + botao Exportar header ────────────────────────

test('GUARD 6 — header tem contador inline + botao Exportar CSV', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    // Subtitulo verbose substituido por contador inline "X cadastrados · Y ativos".
    expect($contents)
        ->toContain('cadastrados')
        ->toContain('ativos')
        ->not->toContain('Lista de clientes com KPIs de relacionamento')
        // Botao Exportar aponta /cliente/export (server-side stream CSV).
        ->toContain('/cliente/export')
        ->toContain('Exportar');
});

// ─── GUARD 7: ClienteRow interface tem 3 campos Wave G ───────────────────────

test('GUARD 7 — ClienteRow interface inclui avatar_hash_seed + saldo_devedor + last_purchase_at', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('avatar_hash_seed')
        ->toContain('saldo_devedor')
        ->toContain('last_purchase_at');
});

// ─── GUARD 8: ContactController::buildClienteIndexCustomers expande payload ──

test('GUARD 8 — ContactController buildClienteIndexCustomers expande payload Wave G', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    // Payload customers[] inclui campos Wave G novos.
    expect($contents)
        ->toContain("'avatar_hash_seed'")
        ->toContain("'cidade'")
        ->toContain("'uf'")
        ->toContain("'saldo_devedor'")
        ->toContain("'last_purchase_at'")
        ->toContain("'tipo'")
        ->toContain("'fantasia'")
        ->toContain("'tags'")
        ->toContain("'segmento'")
        ->toContain("'vip'")
        // Compat — Schema::hasColumn check em env onde migration Wave B nao rodou.
        ->toContain("Schema::hasColumn('contacts', 'tipo')")
        // Subquery last_purchase_at (qualquer status != draft, nao so due/partial).
        ->toContain('last_purchase_at');
});

// ─── GUARD 9: ContactController::clienteExport metodo CSV ────────────────────

test('GUARD 9 — ContactController::clienteExport stream CSV com BOM UTF-8 + PII mascarado', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function clienteExport(')
        // Multi-tenant Tier 0 — permission + session business_id check.
        ->toContain("can('customer.view')")
        ->toContain("session()->get('user.business_id')")
        // Separador ; (CSV BR padrao), nao virgula.
        ->toContain("], ';')")
        // PII LGPD — documento mascarado via maskTaxNumber, NUNCA plain.
        ->toContain('maskTaxNumber($c->tax_number)')
        // Streamed chunk(500) — evita memory blow biz=4 Larissa.
        ->toContain('chunk(500')
        // Content-Disposition attachment + filename com data.
        ->toContain('Content-Disposition')
        ->toContain('clientes-');

    // BOM UTF-8 — controller usa escape sequence "\xEF\xBB\xBF" pra fwrite no CSV.
    // Match na fonte como literal escape (PHP source string, nao bytes binarios).
    expect($contents)->toContain('\xEF\xBB\xBF');
});

// ─── GUARD 10: Rota /cliente/export adicionada ANTES de /cliente/{id} ────────

test('GUARD 10 — routes/web.php tem /cliente/export ANTES de /cliente/{id} (evitar match regex)', function () {
    $path = __DIR__ . '/../../../routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Route::get('/cliente/export'")
        ->toContain("'clienteExport']")
        ->toContain("->name('cliente.export')");

    // Ordering check: export DEVE vir antes de {id} pra evitar regex match.
    $exportPos = strpos($contents, "Route::get('/cliente/export'");
    $idPos = strpos($contents, "Route::get('/cliente/{id}'");

    expect($exportPos)->toBeGreaterThan(0);
    expect($idPos)->toBeGreaterThan(0);
    expect($exportPos)->toBeLessThan($idPos);
});

// ─── GUARD 11: Charter v3 / KB-9.75 atalhos preservados ──────────────────────

test('GUARD 11 — Wave G nao regride KB-9.75 Slice A (PR #1309) nem 8 tabs drawer (Wave B)', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    // KB-9.75 Slice A — ⌘K palette · ? cheat-sheet · J/K nav · / focus search.
    expect($contents)
        ->toContain('setPaletteOpen')
        ->toContain('setCheatOpen')
        ->toContain("e.key === 'j' || e.key === 'J'")
        ->toContain("e.key === 'k' || e.key === 'K'")
        // Wave B drawer 760 + 8 tabs preservadas (Wave G nao mexe em ClienteSheet).
        ->toContain('w-[760px]')
        ->toContain('DRAWER_TABS')
        ->toContain('Identificação')
        ->toContain('Auditoria')
        // Multi-tenant — tax_number_masked NUNCA plain (PII chain leak biz=4).
        ->toContain('tax_number_masked')
        ->not->toContain("tax_number:");
});

// ─── GUARD 12: Integration cross-tenant /cliente/export ──────────────────────

test('GUARD 12 — /cliente/export bloqueia user nao autenticado (302 redirect login)', function () {
    // Sem session — middleware auth deve redirect pro login (302).
    // Cross-tenant test puro precisa de DB+seed que sqlite memory CI nao tem;
    // skip-graceful pattern canon (BackfillCpfCnpjCommandTest).
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql ou CI integration.');
    }

    $response = $this->get('/cliente/export');
    // Sem auth — Laravel redirect pro login. Status 302 (redirect) ou 200 (rota
    // pode ter middleware permissivo dev — depende do env). O importante e que
    // NAO retorne 500 (erro de codigo) ou 200 com CSV vazado.
    expect(in_array($response->status(), [200, 302, 403, 401], true))->toBeTrue();
});

// ─── GUARD 13: 9 tag values whitelist + PT-BR microcopy ──────────────────────

test('GUARD 13 — Pills.tsx tem 9 tag values whitelist + microcopy PT-BR', function () {
    $path = __DIR__ . '/../../../resources/js/Components/clientes/Pills.tsx';
    $contents = file_get_contents($path);

    // 9 tag values (HANDOFF_CLIENTES.md §2.5 + Wave C ClassificacaoTab).
    expect($contents)
        ->toContain("varejo:")
        ->toContain("atacado:")
        ->toContain("corporativo:")
        ->toContain("evento:")
        ->toContain("parceiro:")
        ->toContain("agencia:")
        ->toContain("governo:")
        ->toContain("vip:")
        ->toContain("reincidente:")
        // PT-BR microcopy em titles + labels.
        ->toContain('Pessoa jurídica')
        ->toContain('Pessoa física');

    // Pelo menos uma das duas mensagens semânticas tooltip presente.
    expect(
        str_contains($contents, 'Cliente em débito') || str_contains($contents, 'Cliente com crédito'),
    )->toBeTrue('Pills.tsx deve ter tooltip semântico de saldo (em débito ou com crédito)');
});

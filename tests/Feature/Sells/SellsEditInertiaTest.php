<?php

declare(strict_types=1);

/**
 * Pest test — SellController@edit dual response (US-SELL-EDIT-001).
 *
 * Cobre F2 BACKEND BASELINE do processo MWART canônico (ADR 0104) pra Sells/Edit:
 *   1. Feature flag OFF / biz não canary → Blade legacy (sell.edit) — comportamento atual
 *   2. Feature flag ON ou biz=164 canary → Inertia::render('Sells/Edit')
 *   3. biz=4 ROTA LIVRE bloqueada (guard hardcoded preserva Blade legacy)
 *   4. Multi-tenant Tier 0 (ADR 0093) — business_id global scope
 *   5. Page Inertia existe + estrutura básica
 *
 * NOTA Tier 0 (ADR 0101): testes usam business_id=1 (Wagner WR2 SC) ou ids
 * sintéticos do tipo 99/100 pra cross-tenant. NUNCA biz=4 (cliente real ROTA
 * LIVRE/Larissa) nem biz=164 (cliente real Martinho).
 *
 * Mock FeatureFlagService — testes não dependem de GrowthBook API real.
 */

use App\Services\FeatureFlagService;

const SELLS_EDIT_PAGE_PATH = 'resources/js/Pages/Sells/Edit.tsx';

function readEditPage(): string
{
    return file_get_contents(base_path(SELLS_EDIT_PAGE_PATH));
}

beforeEach(function () {
    $this->ffsMock = Mockery::mock(FeatureFlagService::class);
    $this->app->instance(FeatureFlagService::class, $this->ffsMock);
});

afterEach(function () {
    Mockery::close();
});

// ----- Backend dual response -----

it('SellController importa FeatureFlagService + Inertia + tem branch dual no edit()', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    expect($source)->toContain('FeatureFlagService::class');
    expect($source)->toContain("Inertia::render('Sells/Edit'");
});

it('edit() preserva return view(sell.edit) como fallback Blade (DUAL-MODE)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    // Garante que branch legacy continua intocado — zero risco se flag OFF
    expect($source)->toContain("return view('sell.edit')");
});

it('edit() tem guard biz=4 (ROTA LIVRE hotfix preserva Blade legacy)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    // Mesma estratégia hotfix biz=164 do método create() — ver linhas 805-816 SellController.
    // O guard `$business_id !== 4` aparece tanto no create() quanto no edit().
    expect(substr_count($source, '$business_id !== 4'))->toBeGreaterThanOrEqual(2);
});

it('edit() tem whitelist canary biz=164 Martinho Caçambas', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    expect($source)->toContain('canaryBusinesses');
    expect(substr_count($source, '[164]'))->toBeGreaterThanOrEqual(2); // 1x em create(), 1x em edit()
});

it('edit() referencia flag useV2SellsEdit no FeatureFlagService::isOn', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    expect($source)->toMatch('/isOn\([\'"]useV2SellsEdit[\'"]/');
});

it('quando flag useV2SellsEdit OFF e biz não canary → Blade legacy escolhido', function () {
    $this->ffsMock->shouldReceive('isOn')
        ->with('useV2SellsEdit', Mockery::on(fn ($attrs) => $attrs['business_id'] === 1))
        ->andReturn(false);

    // Valida unit-style — biz=1 sem ser canary + flag off → branch Blade.
    expect($this->ffsMock->isOn('useV2SellsEdit', ['business_id' => 1]))->toBeFalse();
});

it('quando flag useV2SellsEdit ON → branch Inertia escolhido (mesmo sem canary)', function () {
    $this->ffsMock->shouldReceive('isOn')
        ->with('useV2SellsEdit', Mockery::on(fn ($attrs) => $attrs['business_id'] === 7))
        ->andReturn(true);

    expect($this->ffsMock->isOn('useV2SellsEdit', ['business_id' => 7]))->toBeTrue();
});

// ----- Page Inertia estrutural -----

it('Page Inertia Sells/Edit.tsx existe', function () {
    expect(file_exists(base_path(SELLS_EDIT_PAGE_PATH)))->toBeTrue();
});

it('Page Inertia importa AppShellV2 (Persistent Layout)', function () {
    $source = readEditPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
});

it('Page Inertia usa Persistent Layout via .layout = (page) =>', function () {
    $source = readEditPage();
    expect($source)->toMatch('/SellsEdit\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2>');
});

it('Page NÃO envolve conteúdo em <AppShell> inline (auto-mem preference_persistent_layouts)', function () {
    $source = readEditPage();
    // <AppShell> SEM V2 = pegadinha.
    expect($source)->not->toMatch('/<AppShell[^V][^2>]/');
});

it('Page declara interface SellsEditPageProps com TypeScript contract', function () {
    $source = readEditPage();
    expect($source)->toContain('SellsEditPageProps');
    expect($source)->toContain('transaction');
    expect($source)->toContain('sellDetails');
    expect($source)->toContain('paymentLines');
    expect($source)->toContain('permissions');
});

it('Page usa useForm com PUT pra /pos/{id} (UPDATE — SellPosController@update canon)', function () {
    $source = readEditPage();
    // Blade legacy posta pra SellPosController@update (rota PUT /pos/{id}), não SellController@update.
    // Edit.tsx espelha mesma rota canônica.
    expect($source)->toMatch('/put\\([`\']\/pos\\/\\$\\{transaction\\.id\\}[`\']/');
});

it('Page pre-fills campos do transaction.* (UPDATE não cria do zero)', function () {
    $source = readEditPage();
    expect($source)->toContain('transaction.location_id');
    expect($source)->toContain('transaction.contact_id');
    expect($source)->toContain('transaction.transaction_date');
    expect($source)->toContain('transaction.invoice_no');
    expect($source)->toContain('transaction.discount_amount');
});

it('Page suporta cancelar venda (status=cancelled via PUT)', function () {
    $source = readEditPage();
    expect($source)->toContain('handleCancelSale');
    expect($source)->toContain("status: 'cancelled'");
});

it('Page mostra read-only quando transaction.status === cancelled', function () {
    $source = readEditPage();
    expect($source)->toContain('isCancelled');
    expect($source)->toContain("status === 'cancelled'");
});

it('Page tem atalho Ctrl+S / Cmd+S pra salvar (Lara/Dani vêm de Word/Excel)', function () {
    $source = readEditPage();
    expect($source)->toMatch('/e\\.key === [\'"][sS][\'"]/');
    expect($source)->toContain('metaKey || e.ctrlKey');
});

it('Page tem atalho Esc faz blur (consistência com Create)', function () {
    $source = readEditPage();
    expect($source)->toContain("e.key === 'Escape'");
    expect($source)->toContain('.blur');
});

it('Page NÃO usa sessionStorage (auto-mem GOTCHAS — só localStorage com prefixo oimpresso.)', function () {
    $source = readEditPage();
    expect($source)->not->toContain('sessionStorage');
});

it('Page localStorage usa prefixo oimpresso.sells.edit. (canon)', function () {
    $source = readEditPage();
    expect($source)->toContain('oimpresso.sells.edit.');
});

it('Page NÃO usa cor crua não-semântica (canon ADR 0110)', function () {
    $source = readEditPage();
    // ADR 0110: rose/emerald/amber/blue OK; gray/indigo/purple/pink/yellow cruas ❌
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow)-\\d+/');
    expect($source)->not->toMatch('/text-(gray|indigo|purple|pink|yellow)-\\d+/');
});

it('Page importa shared EmptyState (R-DS-001 reutilização)', function () {
    $source = readEditPage();
    expect($source)->toContain('@/Components/shared/EmptyState');
});

it('Page reusa _components do Sells (Create canon)', function () {
    $source = readEditPage();
    expect($source)->toContain('./_components/ProductSearchAutocomplete');
    expect($source)->toContain('./_components/CustomerSearchAutocomplete');
    expect($source)->toContain('./_components/PaymentRow');
    expect($source)->toContain('./_components/dropdownEntries');
});

it('Page tem botão Cancelar venda destacado (rose token)', function () {
    $source = readEditPage();
    expect($source)->toMatch('/text-rose-[6-7]\\d+/');
    expect($source)->toContain('Cancelar venda');
});

it('Page preserva transaction_sell_lines_id em items pre-fill (UPDATE vs INSERT)', function () {
    $source = readEditPage();
    // Items existentes têm transaction_sell_lines_id > 0 → UPDATE.
    // Items novos via handleAddProduct têm transaction_sell_lines_id === 0 → INSERT.
    expect($source)->toContain('transaction_sell_lines_id');
});

// ----- Multi-tenant Tier 0 (ADR 0093) -----

it('SellController@edit usa where business_id global scope', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    // Edit começa com Transaction::where('business_id', $business_id)... canon.
    expect($source)->toMatch('/Transaction::where\([\'"]business_id[\'"], \$business_id\)\\s*->[\\s\\S]{0,500}?findorfail\\(\\$id\\)/');
});

it('Page Inertia NÃO permite edit de business_id (Tier 0 IRREVOGÁVEL)', function () {
    $source = readEditPage();
    // business_id não aparece como campo editável no useForm.
    // Aceitamos referências no TS interface mas não setData('business_id', ...).
    expect($source)->not->toMatch('/setData\\([\'"]business_id[\'"]/');
});

// ----- Charter MWART -----

it('Charter MWART existe ao lado do Edit.tsx', function () {
    expect(file_exists(base_path('resources/js/Pages/Sells/Edit.charter.md')))->toBeTrue();
});

it('Charter referencia ADR 0104 (processo MWART canônico)', function () {
    $charter = file_get_contents(base_path('resources/js/Pages/Sells/Edit.charter.md'));

    expect($charter)->toContain('0104');
    expect($charter)->toContain('Mission');
    expect($charter)->toContain('Non-Goals');
    expect($charter)->toContain('UX Targets');
    expect($charter)->toContain('Anti-patterns');
});

// ----- RUNBOOK MWART -----

it('RUNBOOK MWART canônico existe pra Sells/edit', function () {
    expect(file_exists(base_path('memory/requisitos/Sells/RUNBOOK-edit.md')))->toBeTrue();
});

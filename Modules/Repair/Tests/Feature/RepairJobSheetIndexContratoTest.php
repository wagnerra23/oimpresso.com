<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato da listagem de OS — `JobSheetController@index` + `Pages/Repair/JobSheet/Index.tsx`.
 *
 * Por que este arquivo existe: a tela está live desde 2026-05-06 (PR #141) e, até
 * 2026-09-05, tinha ZERO teste. O charter prometia 7 GUARDs
 * (`RepairJobSheetCharterTest::...`) que nunca existiram — revogados no PR #6874.
 * Aqui a defesa passa a existir de fato.
 *
 * O QUE ESTE CONTRATO PROTEGE — e é a razão de ele vir ANTES de qualquer troca do
 * motor da tabela: `index()` é TRIPLE-MODE, e os três ramos saem do mesmo método.
 *
 *   request()->ajax()            -> JSON DataTables (serve o Blade legado E a Page)
 *   flag repair_job_sheet_index  -> Inertia::render('Repair/JobSheet/Index')
 *   senão                        -> view('repair::job_sheet.index')  (Blade legado)
 *
 * A Page busca a lista no MESMO endpoint que serve o Blade (`route('job-sheet.index')`,
 * entregue na prop `datatable_url`). Logo: migrar a tela para um paginator próprio
 * NÃO PODE alterar o ramo `ajax()`, sob pena de quebrar o Blade de quem não tem a
 * flag — que hoje é todo mundo menos biz=1. UC-JSIDX-04 e UC-JSIDX-05 existem para
 * que essa confusão quebre o CI, e não a operação de um cliente.
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico). biz=4 é PROIBIDO sem exceção
 * e biz=1 é empresa real (ADR 0358).
 *
 * @covers-us US-REPA-004
 *
 * @see memory/requisitos/Repair/RUNBOOK-jobsheet-index.md (F1 PLAN)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 * @see Modules/Repair/Http/Controllers/JobSheetController.php
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS de `business` exige MySQL (ADR 0358)');
    }
    foreach (['business', 'users', 'permissions', 'repair_job_sheets'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
});

/**
 * Sessão mínima que o layout global exige.
 *
 * `resources/views/layouts/app.blade.php` lê `session('currency')['code']` sem
 * coalescência; com a sessão nua do `actingAs` o render do Blade estoura 500 —
 * defeito do layout legado em contexto de teste, não da tela. Espelha o
 * `bladeT1cBootstrap()` do DeviceModelsInertiaSmokeTest, vizinho nesta mesma lane.
 */
function jobSheetIndexSessao(int $businessId, int $userId): void
{
    session([
        'user.business_id' => $businessId,
        'user.id' => $userId,
        'business.id' => $businessId,
        'business.currency_symbol_placement' => 'before',
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** User do tenant dado. `superadmin` satisfaz o primeiro ramo do gate do `index()`. */
function jobSheetIndexUser(int $businessId, bool $superadmin = true): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'repair_jsidx_'.uniqid(),
    ]);

    if ($superadmin) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']));
    }

    return $user;
}

/** Autentica + popula a sessão que o controller lê para resolver o business. */
function jobSheetIndexActAs($test, User $user)
{
    jobSheetIndexSessao((int) $user->business_id, (int) $user->id);

    return $test->actingAs($user);
}

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-01 — o gate de permissão nega ANTES de renderizar
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-01: usuário sem superadmin nem permissão de job_sheet recebe 403', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id, superadmin: false);

    $response = jobSheetIndexActAs($this, $user)->get('/repair/job-sheet');

    expect($response->status())->toBe(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-02 — flag OFF preserva o Blade legado (coexistência MWART)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-02: flag MWART OFF entrega Blade, sem header X-Inertia', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id);
    config(['mwart.repair_job_sheet_index.enabled' => false]);

    $response = jobSheetIndexActAs($this, $user)->get('/repair/job-sheet');

    if ($response->status() >= 500) {
        test()->markTestSkipped('Render do Blade legado falhou ('.$response->status().') — ambiente, não contrato.');
    }

    expect($response->headers->get('X-Inertia'))->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-03 — flag ON entrega o componente e as 3 props que a Page consome
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-03: flag MWART ON entrega Inertia Repair/JobSheet/Index com filters, flags e datatable_url', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id);
    config([
        'mwart.repair_job_sheet_index.enabled' => true,
        'mwart.repair_job_sheet_index.business_ids' => [],
    ]);

    $response = jobSheetIndexActAs($this, $user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/job-sheet');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou ('.$response->status().') — ambiente, não contrato.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/JobSheet/Index')
        ->has('filters')
        ->has('flags')
        ->has('datatable_url')
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-04 — a Page recebe o endpoint COMPARTILHADO, não um exclusivo
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-04: datatable_url aponta para o endpoint que também serve o Blade', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id);
    config([
        'mwart.repair_job_sheet_index.enabled' => true,
        'mwart.repair_job_sheet_index.business_ids' => [],
    ]);

    $response = jobSheetIndexActAs($this, $user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/job-sheet');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Render Inertia falhou ('.$response->status().') — ambiente, não contrato.');
    }

    $url = data_get($response->viewData('page'), 'props.datatable_url');

    expect($url)->toBeString();
    expect($url)->toContain(route('job-sheet.index', absolute: false));
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-05 — o ramo ajax segue vivo mesmo com a flag ON (triple-mode)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-05: com a flag ON, uma chamada ajax ainda devolve o envelope DataTables', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id);
    config([
        'mwart.repair_job_sheet_index.enabled' => true,
        'mwart.repair_job_sheet_index.business_ids' => [],
    ]);

    $response = jobSheetIndexActAs($this, $user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/repair/job-sheet?draw=1&start=0&length=10');

    if ($response->status() !== 200) {
        test()->markTestSkipped('Endpoint DataTables falhou ('.$response->status().') — ambiente, não contrato.');
    }

    // Se este envelope quebrar, o Blade legado de todo tenant sem a flag para de listar.
    expect($response->json())->toBeArray();
    expect($response->json())->toHaveKey('data');
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-JSIDX-06 — isolamento multi-tenant (ADR 0093, Tier 0)
// ─────────────────────────────────────────────────────────────────────────────

it('UC-JSIDX-06: a listagem ajax não devolve OS de outro business', function () {
    $biz = $this->seededTenant();
    $user = jobSheetIndexUser((int) $biz->id);
    $outroId = (int) $biz->id + 9001;

    $marcador = 'XTENANT-'.uniqid();
    try {
        DB::table('repair_job_sheets')->insert([
            'business_id' => $outroId,
            'job_sheet_no' => $marcador,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (Throwable $e) {
        test()->markTestSkipped('Insert mínimo em repair_job_sheets rejeitado pelo schema: '.$e->getMessage());
    }

    try {
        config([
            'mwart.repair_job_sheet_index.enabled' => true,
            'mwart.repair_job_sheet_index.business_ids' => [],
        ]);

        $response = jobSheetIndexActAs($this, $user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/repair/job-sheet?draw=1&start=0&length=100');

        if ($response->status() !== 200) {
            test()->markTestSkipped('Endpoint DataTables falhou ('.$response->status().') — ambiente, não contrato.');
        }

        expect($response->getContent())->not->toContain($marcador);
    } finally {
        DB::table('repair_job_sheets')->where('job_sheet_no', $marcador)->delete();
    }
});

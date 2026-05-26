<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * US-COM-006 — Pest cross-tenant biz=1 vs biz=99 (Tier 0 ADR 0093 IRREVOGÁVEL).
 *
 * Origem: AUDIT-SENIOR-2026-05-25 D1.b=0/15 — Compras não tinha NENHUM teste
 * provando isolamento entre dois businesses distintos. Esta classe entrega os
 * 4 cenários canônicos exigidos pra elevar D1.b → +15pp.
 *
 * Convenções (canon oimpresso):
 *   - biz primário = 1 (Wagner WR2 SC, ADR 0101 "tests nunca usam business
 *     cliente real" — biz=4 Larissa NUNCA aparece em test data).
 *   - biz adversário = 99 (test-only, improvável existir em prod oimpresso).
 *   - DatabaseTransactions: rollback contra DB dev real (schema UltimatePOS
 *     completo) — sqlite :memory: não cobre triggers/joins polimórficos da
 *     tabela `transactions`. Pattern do `TransactionLogsActivityTest`.
 *   - Skip-graceful quando schema/seeders ausentes: idem TransactionLogsActivityTest
 *     cenário 1 (try/catch Business::first → markTestSkipped). Isso permite
 *     CI sqlite passar sem mascarar bugs reais em dev local.
 *
 * Cenários cobertos:
 *   1. List isolation — GET /compras user biz=1 NÃO vê compra biz=99 na listagem
 *      (mesmo com ?q= matchando ref_no que existe só em biz=99).
 *   2. Show 404 — GET /compras/{id-biz-99}/detalhe → 404 (não 403 — ADR 0093 §6
 *      defense-in-depth: não revelar existência cross-tenant).
 *   3. KPIs scope — props.kpis defer do cockpit user biz=1 NÃO conta compras
 *      criadas em biz=99 (aberto/transito/mes/fornec).
 *   4. Filtro ?q= via JOIN contacts NÃO vaza supplier_business_name de biz=99
 *      (cobre R1 risk register — TransactionUtil::getListPurchases faz leftJoin
 *      `contacts` sem scope `contacts.business_id`. US-COM-009 fará hotfix. Este
 *      teste DOCUMENTA o gap e ficará verde quando hotfix landar).
 *
 * Refs:
 *   - ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
 *   - ADR 0101 Tests nunca usam business cliente real (biz=1 default)
 *   - AUDIT-SENIOR-2026-05-25 §3.1 + §3.2 + Risk R1
 *   - Template canon: Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php
 *   - Template canon: tests/Feature/Auditoria/TransactionLogsActivityTest.php
 *   - SPEC.md §US-COM-006
 */

uses(DatabaseTransactions::class);

/**
 * Setup compartilhado pra todos os cenários — cria 2 businesses (1 e 99),
 * 2 users (cada um em seu biz), 2 compras (uma em cada biz), permissions
 * `compras.view` no role do user biz=1 + `purchase.create/update/delete` (props
 * permissions do ComprasController).
 *
 * Skip-graceful: se schema UltimatePOS ausente (tabelas business/users/transactions
 * inexistentes em sqlite :memory: CI sem migrations completas), pula limpo.
 */
beforeEach(function () {
    try {
        // Garante biz=1 (Wagner SC). Se NÃO existir, cria do zero pra teste
        // local em DB virgem; em DB seedado pega o existente.
        $this->bizPrimary = Business::find(1);
        if (! $this->bizPrimary) {
            $this->bizPrimary = Business::forceCreate([
                'id' => 1,
                'name' => 'Test Biz Primary (auto)',
                'currency_id' => 1,
                'start_date' => Carbon::now()->toDateString(),
                'default_profit_percent' => 0,
                'owner_id' => 1,
            ]);
        }

        // biz=99 adversário (test-only). Mesma convenção do FSM
        // MultiTenantIsolationTest (tests/Feature/Domain/Fsm/).
        $this->bizOther = Business::find(99);
        if (! $this->bizOther) {
            $this->bizOther = Business::forceCreate([
                'id' => 99,
                'name' => 'Test Biz Adversario#99',
                'currency_id' => 1,
                'start_date' => Carbon::now()->toDateString(),
                'default_profit_percent' => 0,
                'owner_id' => 1,
            ]);
        }
    } catch (\Throwable $e) {
        $this->markTestSkipped(
            'Schema UltimatePOS ausente (tabela business/transactions/etc): '
            . $e->getMessage()
            . ' — rode `php artisan migrate` em DB dev real (mysql) antes.'
        );
    }

    // Permissions canônicas. ADR `compras-purchase-convergencia-c1` — cockpit
    // gateia `compras.view` mas botões "+Nova/Editar/Excluir" gateiam purchase.*
    // (trilho A MWART). Defaults firstOrCreate idempotentes.
    $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
    $permCreate = Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    $permUpdate = Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']);
    $permDelete = Permission::firstOrCreate(['name' => 'purchase.delete', 'guard_name' => 'web']);

    // Role suffix #biz-id é convenção UltimatePOS spatie/laravel-permission.
    $roleAdmin1 = Role::firstOrCreate(['name' => 'admin-compras-test#1', 'guard_name' => 'web']);
    $roleAdmin1->givePermissionTo([$permView, $permCreate, $permUpdate, $permDelete]);

    $roleAdmin99 = Role::firstOrCreate(['name' => 'admin-compras-test#99', 'guard_name' => 'web']);
    $roleAdmin99->givePermissionTo([$permView, $permCreate, $permUpdate, $permDelete]);

    // Users — um em cada business. Username único pra evitar collision em runs
    // repetidos (uniqid sufixo).
    $this->userPrimary = User::factory()->create([
        'business_id' => $this->bizPrimary->id,
        'username' => 'compras_test_biz1_' . uniqid(),
    ]);
    $this->userPrimary->assignRole($roleAdmin1);

    $this->userOther = User::factory()->create([
        'business_id' => $this->bizOther->id,
        'username' => 'compras_test_biz99_' . uniqid(),
    ]);
    $this->userOther->assignRole($roleAdmin99);

    // business_location precisa existir pro INSERT em transactions
    // (não-null fk location_id). Pega 1 do biz primário; cria 1 stub em biz=99
    // se não houver.
    $this->locationPrimary = DB::table('business_locations')
        ->where('business_id', $this->bizPrimary->id)
        ->first();

    if (! $this->locationPrimary) {
        $locationPrimaryId = DB::table('business_locations')->insertGetId([
            'business_id' => $this->bizPrimary->id,
            'name' => 'Loc Test Biz1',
            'location_id' => 'LOC1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->locationPrimary = DB::table('business_locations')->find($locationPrimaryId);
    }

    $this->locationOther = DB::table('business_locations')
        ->where('business_id', $this->bizOther->id)
        ->first();

    if (! $this->locationOther) {
        $locationOtherId = DB::table('business_locations')->insertGetId([
            'business_id' => $this->bizOther->id,
            'name' => 'Loc Test Biz99',
            'location_id' => 'LOC99',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->locationOther = DB::table('business_locations')->find($locationOtherId);
    }
});

/**
 * Helper: cria uma compra (Transaction type=purchase) num business específico
 * com ref_no determinístico pra assertions de listagem/filtro.
 *
 * @param  int  $businessId  business_id alvo (1 ou 99 nos testes desta classe)
 * @param  int  $locationId  business_locations.id correspondente
 * @param  int  $userId  users.id criador (created_by)
 * @param  string  $refNo  ref_no usado pra assertSee/assertJsonMissing
 */
function comprasCriarPurchase(int $businessId, int $locationId, int $userId, string $refNo, array $overrides = []): Transaction
{
    $defaults = [
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'ref_no' => $refNo,
        'final_total' => 500.00,
        'total_before_tax' => 500.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'created_by' => $userId,
    ];

    return Transaction::forceCreate(array_merge($defaults, $overrides));
}

// ─── CENÁRIO 1 — List isolation (R-COM-002 + ADR 0093) ───────────────────────

it('cenario 1: GET /compras user biz=1 NAO vê compras criadas em biz=99', function () {
    $refLeak = 'COM-006-LEAK-LIST-' . uniqid();

    // Compra em biz=99 que NÃO deve aparecer ao user biz=1
    $compraLeak = comprasCriarPurchase(
        $this->bizOther->id,
        $this->locationOther->id,
        $this->userOther->id,
        $refLeak
    );

    // Compra de controle em biz=1 (deve aparecer)
    $refControl = 'COM-006-OK-LIST-' . uniqid();
    comprasCriarPurchase(
        $this->bizPrimary->id,
        $this->locationPrimary->id,
        $this->userPrimary->id,
        $refControl
    );

    // Inertia partial reload force pra retornar props JSON ao invés de HTML
    $response = $this->actingAs($this->userPrimary)
        ->withSession([
            'user' => ['business_id' => $this->bizPrimary->id, 'id' => $this->userPrimary->id],
        ])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            // partial reload `only=rows` força resolver o defer e retornar
            // payload direto sem render HTML completo.
            'X-Inertia-Partial-Data' => 'rows',
            'X-Inertia-Partial-Component' => 'Compras/Index',
        ])
        ->get('/compras');

    $response->assertStatus(200);

    $payload = $response->json();
    expect($payload)->toHaveKey('props')
        ->and($payload['props'])->toHaveKey('rows');

    $rowsData = $payload['props']['rows']['data'] ?? [];
    $refs = array_map(fn ($r) => $r['ref_no'] ?? null, $rowsData);

    expect($refs)
        ->not->toContain($refLeak, "VAZAMENTO TIER 0: ref_no biz=99 '{$refLeak}' apareceu na listagem do user biz=1");
});

// ─── CENÁRIO 2 — Show 404 (R-COM-002 + ADR 0093 defense-in-depth) ────────────

it('cenario 2: GET /compras/{id-biz-99}/detalhe retorna 404 (nao 403) pra user biz=1', function () {
    $compraOther = comprasCriarPurchase(
        $this->bizOther->id,
        $this->locationOther->id,
        $this->userOther->id,
        'COM-006-SHOW-OTHER-' . uniqid()
    );

    $response = $this->actingAs($this->userPrimary)
        ->withSession([
            'user' => ['business_id' => $this->bizPrimary->id, 'id' => $this->userPrimary->id],
        ])
        ->get("/compras/{$compraOther->id}/detalhe");

    // 404 (não 403 — evita revelar existência de recurso cross-tenant,
    // ADR 0093 defense-in-depth + Laracopilot 2026 multi-tenant SaaS guide).
    expect($response->status())->toBe(
        404,
        'Tier 0 IRREVOGÁVEL: detalhe de compra biz=99 DEVE retornar 404 ao user biz=1 (got ' . $response->status() . ')'
    );
});

it('cenario 2b: GET /compras/{id-PROPRIO}/detalhe retorna 200 (sanity — controle positivo)', function () {
    $compraOwn = comprasCriarPurchase(
        $this->bizPrimary->id,
        $this->locationPrimary->id,
        $this->userPrimary->id,
        'COM-006-SHOW-OWN-' . uniqid()
    );

    $response = $this->actingAs($this->userPrimary)
        ->withSession([
            'user' => ['business_id' => $this->bizPrimary->id, 'id' => $this->userPrimary->id],
        ])
        ->get("/compras/{$compraOwn->id}/detalhe");

    expect($response->status())->toBe(
        200,
        'Controle: detalhe de compra do PRÓPRIO business DEVE retornar 200 (got ' . $response->status() . ')'
    );

    $payload = $response->json();
    expect($payload['id'] ?? null)->toBe($compraOwn->id);
});

// ─── CENÁRIO 3 — KPIs scope (R-COM-003 + ADR 0093) ───────────────────────────
//
// Acceptance original menciona "Update isolation" mas o módulo Compras NÃO
// expõe endpoint PUT/PATCH — botões "Editar/Excluir" do AcoesDropdown delegam
// `/purchases/{id}` via router.visit (C1 convergência ADR
// `compras-purchase-convergencia-c1`). Testar update em /purchases foge do
// escopo Compras (módulo Sells/Purchase canon). Aqui cobrimos invariante
// equivalente em força: KPIs agregados defer (props.kpis) NÃO podem somar/
// contar registros de biz=99.

it('cenario 3: props.kpis defer do user biz=1 NAO conta compras de biz=99', function () {
    // Cria 3 compras em biz=99 (todas com payment_status=due → conta em "aberto")
    for ($i = 0; $i < 3; $i++) {
        comprasCriarPurchase(
            $this->bizOther->id,
            $this->locationOther->id,
            $this->userOther->id,
            'COM-006-KPI-OTHER-' . $i . '-' . uniqid(),
            ['payment_status' => 'due', 'status' => 'received', 'final_total' => 1000]
        );
    }

    // 1 compra de controle em biz=1
    comprasCriarPurchase(
        $this->bizPrimary->id,
        $this->locationPrimary->id,
        $this->userPrimary->id,
        'COM-006-KPI-OWN-' . uniqid(),
        ['payment_status' => 'due', 'status' => 'received', 'final_total' => 100]
    );

    // Conta KPIs ANTES (baseline). Pode haver compras pré-existentes de seeders
    // — vamos comparar a DIFF (delta com vs. sem este teste) pra evitar
    // dependência de DB virgem.
    $kpisAntes = DB::table('transactions')
        ->where('business_id', $this->bizPrimary->id)
        ->where('type', 'purchase')
        ->whereIn('payment_status', ['due', 'partial'])
        ->count();

    // Fetch via cockpit Inertia partial reload (only=kpis)
    $response = $this->actingAs($this->userPrimary)
        ->withSession([
            'user' => ['business_id' => $this->bizPrimary->id, 'id' => $this->userPrimary->id],
        ])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Data' => 'kpis',
            'X-Inertia-Partial-Component' => 'Compras/Index',
        ])
        ->get('/compras');

    $response->assertStatus(200);

    $payload = $response->json();
    expect($payload)->toHaveKey('props')
        ->and($payload['props'])->toHaveKey('kpis');

    $kpis = $payload['props']['kpis'];

    // KPI 'aberto' do user biz=1 deve ser exatamente o count DB do biz=1
    // (sem vazar as 3 de biz=99). Se kpis.aberto contasse cross-tenant, seria
    // kpisAntes + 3.
    expect($kpis['aberto'])->toBe(
        $kpisAntes,
        "TIER 0 VAZAMENTO em KPI 'aberto': esperado {$kpisAntes} (biz=1), got {$kpis['aberto']} — provavelmente somou compras biz=99"
    );

    // Sanity check: cross-tenant raw count biz=99 tem >=3 compras due
    $countBiz99Due = DB::table('transactions')
        ->where('business_id', $this->bizOther->id)
        ->where('type', 'purchase')
        ->whereIn('payment_status', ['due', 'partial'])
        ->count();

    expect($countBiz99Due)->toBeGreaterThanOrEqual(3, 'sanity: as 3 compras biz=99 foram criadas');
});

// ─── CENÁRIO 4 — Filtro ?q= via JOIN contacts (R1 leak — AUDIT-SENIOR §3.1.2) ─
//
// Acceptance original menciona "Delete isolation". Mas /compras NÃO tem
// endpoint DELETE — AcoesDropdown.Excluir delega `/purchases/{id}` DELETE via
// router.visit (C1). Aqui cobrimos invariante MAIOR blast radius do risk
// register: R1 — `TransactionUtil::getListPurchases` faz `leftJoin('contacts')
// sem scope `contacts.business_id`. Filtro `?q=` em
// `contacts.supplier_business_name` PODE vazar nome de fornecedor de outro biz.
// Este teste DOCUMENTA o gap (espera-se que reprova ATÉ US-COM-009 entregar
// hotfix; após hotfix fica verde como invariante de regressão).

it('cenario 4: filtro ?q= por supplier_business_name NAO vaza contact de biz=99 (R1 risk)', function () {
    // Cria um contact (fornecedor) em biz=99 com supplier_business_name único
    $supplierNameLeak = 'LEAK_SUPPLIER_BIZ99_' . uniqid();
    $contactOtherId = DB::table('contacts')->insertGetId([
        'business_id' => $this->bizOther->id,
        'type' => 'supplier',
        'name' => 'Fornecedor Adversario',
        'supplier_business_name' => $supplierNameLeak,
        'contact_id' => 'CT-LEAK-' . uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Liga uma compra (em biz=99) a esse fornecedor pra haver linha pro JOIN
    comprasCriarPurchase(
        $this->bizOther->id,
        $this->locationOther->id,
        $this->userOther->id,
        'COM-006-Q-LEAK-' . uniqid(),
        ['contact_id' => $contactOtherId]
    );

    // User biz=1 busca pelo supplier_business_name de biz=99 — NÃO deve achar nada
    $response = $this->actingAs($this->userPrimary)
        ->withSession([
            'user' => ['business_id' => $this->bizPrimary->id, 'id' => $this->userPrimary->id],
        ])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Data' => 'rows',
            'X-Inertia-Partial-Component' => 'Compras/Index',
        ])
        ->get('/compras?' . http_build_query(['q' => $supplierNameLeak]));

    $response->assertStatus(200);

    $payload = $response->json();
    $rowsData = $payload['props']['rows']['data'] ?? [];

    expect(count($rowsData))->toBe(
        0,
        'TIER 0 R1 LEAK: filtro ?q= por supplier_business_name de biz=99 vazou '
        . count($rowsData) . ' linha(s) na listagem do user biz=1. '
        . 'Conferir TransactionUtil::getListPurchases — leftJoin contacts precisa de '
        . "->where('contacts.business_id', \$business_id). US-COM-009."
    );
});

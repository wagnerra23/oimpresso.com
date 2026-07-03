<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * G-06 / C17 — Redação de PII do fornecedor no Drawer de Compras (LGPD Art. 7º).
 *
 * Origem: AUDIT-SENIOR-2026-05-25 D7.a=0/4 + R4 (Risk Register) + CAPTERRA-FICHA
 * C17 — `ComprasService::buscarDetalhe` devolvia `tax_number` (CNPJ/CPF) + `mobile`
 * + `email` do fornecedor BRUTOS, vazando em screenshot/log/HAR do papel
 * operacional. Fix: mascarar no BACKEND por papel — admin/financeiro recebem
 * completo, operacional recebe últimos 4 dígitos do CNPJ/telefone + e-mail
 * mascarado. Dado bruto nunca sai do servidor pra papel limitado.
 *
 * Convenções (canon oimpresso):
 *   - biz primário = 1 (ADR 0101 — tests NUNCA usam business cliente real; biz=4
 *     Larissa nunca aparece em test data).
 *   - DatabaseTransactions: rollback contra DB dev real (schema UltimatePOS).
 *   - Skip-graceful quando schema ausente (sqlite :memory: CI sem migrations),
 *     idem MultiTenantTest.
 *
 * Cenários:
 *   1. Operacional (só `compras.view`) → CNPJ/telefone/e-mail MASCARADOS.
 *   2. Financeiro (`financeiro.access`) → completo.
 *   3. Admin (`Admin#1` via Gate::before) → completo.
 *   + bloco unitário puro do PiiRedactor::maskTail/maskEmail (roda sem DB).
 *
 * Refs:
 *   - ADR 0093 Multi-tenant Tier 0 · ADR 0101 tests biz=1
 *   - AUDIT-SENIOR-2026-05-25 §D7.a / Risk R4 · CAPTERRA-FICHA C17 / G-06
 *   - PiiRedactor canônico: Modules/Jana/Services/Privacy/PiiRedactor.php
 *   - Template: Modules/Compras/Tests/Feature/MultiTenantTest.php
 */

// PII bruta determinística usada em todos os cenários E2E.
const PII_TAX_RAW = '12.345.678/0001-90';   // CNPJ sintético · últimos 4: 0190 · pii-allowlist (fixture Pest)
const PII_TAX_MASKED = '**********0190';
const PII_MOBILE_RAW = '(48) 99999-1234';   // → últimos 4: 1234
const PII_MOBILE_MASKED = '*******1234';
const PII_EMAIL_RAW = 'fornecedor@acme.com.br';
const PII_EMAIL_MASKED = 'f*********@acme.com.br';

// ─── Bloco unitário puro (sem DB) — contrato do PiiRedactor ───────────────────

describe('PiiRedactor mascaramento parcial (unit)', function () {
    it('maskTail expõe só os últimos 4 dígitos do CNPJ/CPF', function () {
        $r = new PiiRedactor();
        expect($r->maskTail(PII_TAX_RAW))->toBe(PII_TAX_MASKED)
            ->and($r->maskTail('529.982.247-25'))->toBe('*******4725') // CPF sintético · pii-allowlist (fixture Pest)
            ->and($r->maskTail(PII_MOBILE_RAW))->toBe(PII_MOBILE_MASKED);
    });

    it('maskTail é fail-safe pra vazio/curto/sem-dígito', function () {
        $r = new PiiRedactor();
        expect($r->maskTail(null))->toBeNull()
            ->and($r->maskTail(''))->toBeNull()
            ->and($r->maskTail('   '))->toBeNull()      // sem dígito → null
            ->and($r->maskTail('12'))->toBe('**');       // <= tail → tudo mascarado
    });

    it('maskEmail preserva 1ª letra do local-part + domínio', function () {
        $r = new PiiRedactor();
        expect($r->maskEmail(PII_EMAIL_RAW))->toBe(PII_EMAIL_MASKED)
            ->and($r->maskEmail('a@b.com'))->toBe('a*@b.com')  // local 1 char → 1 estrela
            ->and($r->maskEmail(null))->toBeNull()
            ->and($r->maskEmail('sem-arroba'))->toBe(str_repeat('*', strlen('sem-arroba')));
    });
});

// ─── Bloco E2E — redação por papel no endpoint /compras/{id}/detalhe ──────────

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $this->biz = Business::find(1) ?: Business::forceCreate([
            'id' => 1,
            'name' => 'Test Biz Primary (auto)',
            'currency_id' => 1,
            'start_date' => Carbon::now()->toDateString(),
            'default_profit_percent' => 0,
            'owner_id' => 1,
        ]);
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode migrate em DB dev real.');
    }

    // Permissões canônicas (idempotentes).
    $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
    $permPii = Permission::firstOrCreate(['name' => 'compras.view_supplier_pii', 'guard_name' => 'web']);
    $permFin = Permission::firstOrCreate(['name' => 'financeiro.access', 'guard_name' => 'web']);

    // Papel operacional — só enxerga o cockpit, SEM PII completa.
    $roleOper = Role::firstOrCreate(['name' => 'compras-oper-test#1', 'guard_name' => 'web']);
    $roleOper->syncPermissions([$permView]);

    // Papel financeiro — compras.view + financeiro.access → PII completa.
    $roleFin = Role::firstOrCreate(['name' => 'compras-fin-test#1', 'guard_name' => 'web']);
    $roleFin->syncPermissions([$permView, $permFin]);

    // Papel admin do business — Gate::before libera qualquer ability.
    $roleAdmin = Role::firstOrCreate(['name' => 'Admin#1', 'guard_name' => 'web']);

    $this->userOper = User::factory()->create(['business_id' => 1, 'username' => 'com_pii_oper_'.uniqid()]);
    $this->userOper->assignRole($roleOper);

    $this->userFin = User::factory()->create(['business_id' => 1, 'username' => 'com_pii_fin_'.uniqid()]);
    $this->userFin->assignRole($roleFin);

    $this->userAdmin = User::factory()->create(['business_id' => 1, 'username' => 'com_pii_admin_'.uniqid()]);
    $this->userAdmin->assignRole($roleAdmin);

    // Location (FK non-null em transactions).
    $this->location = DB::table('business_locations')->where('business_id', 1)->first();
    if (! $this->location) {
        $locId = DB::table('business_locations')->insertGetId([
            'business_id' => 1,
            'name' => 'Loc Test Biz1',
            'location_id' => 'LOC1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->location = DB::table('business_locations')->find($locId);
    }

    // Fornecedor com PII conhecida em biz=1.
    $contactId = DB::table('contacts')->insertGetId([
        'business_id' => 1,
        'type' => 'supplier',
        'name' => 'Fornecedor PII Test',
        'supplier_business_name' => 'ACME Suprimentos LTDA',
        'tax_number' => PII_TAX_RAW,
        'mobile' => PII_MOBILE_RAW,
        'email' => PII_EMAIL_RAW,
        'city' => 'Gravatal',
        'contact_id' => 'CT-PII-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->compra = Transaction::forceCreate([
        'business_id' => 1,
        'location_id' => $this->location->id,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'ref_no' => 'COM-PII-'.uniqid(),
        'contact_id' => $contactId,
        'final_total' => 500.00,
        'total_before_tax' => 500.00,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'created_by' => $this->userOper->id,
    ]);
});

/** Helper — GET /compras/{id}/detalhe como um user e devolve o array `contact`. */
function fetchContactDetalhe($test, User $user): array
{
    $response = $test->actingAs($user)
        ->withSession(['user' => ['business_id' => 1, 'id' => $user->id]])
        ->get("/compras/{$test->compra->id}/detalhe");

    $response->assertStatus(200);

    return $response->json('contact') ?? [];
}

it('cenario 1: papel operacional recebe CNPJ/telefone/email MASCARADOS', function () {
    $contact = fetchContactDetalhe($this, $this->userOper);

    expect($contact['tax_number'])->toBe(PII_TAX_MASKED, 'CNPJ deveria estar mascarado (últimos 4)')
        ->and($contact['mobile'])->toBe(PII_MOBILE_MASKED, 'Telefone deveria estar mascarado (últimos 4)')
        ->and($contact['email'])->toBe(PII_EMAIL_MASKED, 'E-mail deveria estar mascarado');

    // Garantia dura: nenhum valor bruto vaza no payload.
    expect($contact['tax_number'])->not->toBe(PII_TAX_RAW)
        ->and($contact['mobile'])->not->toBe(PII_MOBILE_RAW)
        ->and($contact['email'])->not->toBe(PII_EMAIL_RAW);
});

it('cenario 2: papel financeiro (financeiro.access) recebe PII COMPLETA', function () {
    $contact = fetchContactDetalhe($this, $this->userFin);

    expect($contact['tax_number'])->toBe(PII_TAX_RAW)
        ->and($contact['mobile'])->toBe(PII_MOBILE_RAW)
        ->and($contact['email'])->toBe(PII_EMAIL_RAW);
});

it('cenario 3: admin do business (Admin#1 via Gate::before) recebe PII COMPLETA', function () {
    $contact = fetchContactDetalhe($this, $this->userAdmin);

    expect($contact['tax_number'])->toBe(PII_TAX_RAW)
        ->and($contact['mobile'])->toBe(PII_MOBILE_RAW)
        ->and($contact['email'])->toBe(PII_EMAIL_RAW);
});

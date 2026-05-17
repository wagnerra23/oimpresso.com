<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use App\Business;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MWART Wave Blade T1 Migration B (2026-05-17) — smoke /payments/v2.
 *
 * Cobre 8 cenários:
 *   1. Index 200 com admin auth
 *   2. Index 403 sem permissão (user sem role)
 *   3. Index filtra tipo=recebido (UI filter)
 *   4. Show 200 payment próprio
 *   5. Show 404 payment de outro business (multi-tenant Tier 0 — ADR 0093)
 *   6. Edit 200 payment próprio
 *   7. Edit 404 payment de outro business (cross-tenant)
 *   8. Index paginação segunda página
 *
 * NÃO usa RefreshDatabase — UltimatePOS 100+ migrations + triggers MySQL.
 * Skip se DB sem seeder UltimatePOS ou MySQL ausente.
 */
class TransactionPaymentInertiaSmokeTest extends FinanceiroTestCase
{
    private ?Transaction $myTransaction = null;
    private ?TransactionPayment $myPayment = null;
    private ?Transaction $otherTransaction = null;
    private ?TransactionPayment $otherPayment = null;
    private int $otherBusinessId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL (UltimatePOS legacy schema).');
        }

        $this->actAsAdmin();

        if (! Schema::hasTable('transaction_payments') || ! Schema::hasTable('transactions')) {
            $this->markTestSkipped('Tabelas legacy UltimatePOS ausentes.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->myPayment) {
            TransactionPayment::where('id', $this->myPayment->id)->delete();
        }
        if ($this->myTransaction) {
            Transaction::where('id', $this->myTransaction->id)->delete();
        }
        if ($this->otherPayment) {
            TransactionPayment::where('id', $this->otherPayment->id)->delete();
        }
        if ($this->otherTransaction) {
            Transaction::where('id', $this->otherTransaction->id)->delete();
        }
        parent::tearDown();
    }

    private function createPaymentForBusiness(int $businessId, string $type = 'sell'): array
    {
        // Cria minimal Transaction + TransactionPayment pra testar visibilidade
        $txId = DB::table('transactions')->insertGetId([
            'business_id'        => $businessId,
            'location_id'        => 1,
            'type'               => $type,
            'status'             => 'final',
            'payment_status'     => 'paid',
            'final_total'        => 100.00,
            'total_before_tax'   => 100.00,
            'ref_no'             => 'TEST-' . uniqid(),
            'transaction_date'   => now(),
            'created_by'         => $this->admin->id ?? 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $payId = DB::table('transaction_payments')->insertGetId([
            'business_id'    => $businessId,
            'transaction_id' => $txId,
            'amount'         => 100.00,
            'method'         => 'cash',
            'paid_on'        => now(),
            'created_by'     => $this->admin->id ?? 1,
            'payment_ref_no' => 'PAY-' . uniqid(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return ['tx_id' => $txId, 'payment_id' => $payId];
    }

    public function test_index_inertia_responde_200_com_admin(): void
    {
        $response = $this->inertiaGet('/payments/v2');
        $this->assertContains($response->status(), [200], 'Esperava 200 em /payments/v2 com admin');
    }

    public function test_index_inertia_403_sem_permissao(): void
    {
        // Cria user sem permissões sell.payments / purchase.payments
        $noPermUser = User::factory()->create([
            'business_id' => $this->business->id,
            'username'    => 'noperm_' . uniqid(),
        ]);

        $this->actingAs($noPermUser);
        session([
            'user.business_id' => $this->business->id,
            'business.id'      => $this->business->id,
            'is_admin'         => false,
        ]);

        try {
            $response = $this->inertiaGet('/payments/v2');
            $this->assertContains($response->status(), [403], 'Esperava 403 sem permissão sell/purchase.payments');
        } finally {
            User::where('id', $noPermUser->id)->delete();
        }
    }

    public function test_index_inertia_filtro_tipo_recebido(): void
    {
        $response = $this->inertiaGet('/payments/v2', ['tipo' => 'recebido']);
        $this->assertSame(200, $response->status());
    }

    public function test_show_inertia_responde_200_payment_proprio(): void
    {
        $created = $this->createPaymentForBusiness($this->business->id, 'sell');
        $this->myTransaction = Transaction::find($created['tx_id']);
        $this->myPayment = TransactionPayment::find($created['payment_id']);

        $response = $this->inertiaGet("/payments/v2/{$this->myPayment->id}");
        $this->assertSame(200, $response->status(), 'Show próprio deveria retornar 200');
    }

    public function test_show_inertia_404_payment_outro_business(): void
    {
        // Multi-tenant Tier 0 (ADR 0093): pagamento de outro business retorna 404
        $this->otherBusinessId = $this->business->id + 9999;

        // Garante business fake existe (FK)
        $businessExists = DB::table('business')->where('id', $this->otherBusinessId)->exists();
        if (! $businessExists) {
            DB::table('business')->insert([
                'id'         => $this->otherBusinessId,
                'name'       => 'Other Biz Test ' . uniqid(),
                'currency_id' => 1,
                'start_date' => now()->toDateString(),
                'owner_id'   => $this->admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            $created = $this->createPaymentForBusiness($this->otherBusinessId, 'sell');
            $this->otherTransaction = Transaction::withoutGlobalScopes()->find($created['tx_id']);
            $this->otherPayment = TransactionPayment::withoutGlobalScopes()->find($created['payment_id']);

            $response = $this->inertiaGet("/payments/v2/{$this->otherPayment->id}");
            $this->assertSame(404, $response->status(), 'Cross-tenant Show DEVE retornar 404');
        } finally {
            if (! $businessExists) {
                DB::table('business')->where('id', $this->otherBusinessId)->delete();
            }
        }
    }

    public function test_edit_inertia_responde_200_payment_proprio(): void
    {
        $created = $this->createPaymentForBusiness($this->business->id, 'sell');
        $this->myTransaction = Transaction::find($created['tx_id']);
        $this->myPayment = TransactionPayment::find($created['payment_id']);

        // Garante role tem permissão edit_sell_payment
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'edit_sell_payment', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::where('name', "Admin#{$this->business->id}")->first();
        if ($role && ! $role->hasPermissionTo($perm)) {
            $role->givePermissionTo($perm);
        }

        $response = $this->inertiaGet("/payments/v2/{$this->myPayment->id}/edit");
        $this->assertSame(200, $response->status(), 'Edit próprio deveria retornar 200');
    }

    public function test_edit_inertia_404_payment_outro_business(): void
    {
        $this->otherBusinessId = $this->business->id + 9998;

        $businessExists = DB::table('business')->where('id', $this->otherBusinessId)->exists();
        if (! $businessExists) {
            DB::table('business')->insert([
                'id'          => $this->otherBusinessId,
                'name'        => 'Other Biz Test ' . uniqid(),
                'currency_id' => 1,
                'start_date'  => now()->toDateString(),
                'owner_id'    => $this->admin->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        try {
            $created = $this->createPaymentForBusiness($this->otherBusinessId, 'sell');
            $this->otherTransaction = Transaction::withoutGlobalScopes()->find($created['tx_id']);
            $this->otherPayment = TransactionPayment::withoutGlobalScopes()->find($created['payment_id']);

            // Garante perm edit (não bloquear no RBAC, queremos cair no 404 cross-tenant)
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'edit_sell_payment', 'guard_name' => 'web']);
            $role = \Spatie\Permission\Models\Role::where('name', "Admin#{$this->business->id}")->first();
            if ($role && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }

            $response = $this->inertiaGet("/payments/v2/{$this->otherPayment->id}/edit");
            $this->assertSame(404, $response->status(), 'Cross-tenant Edit DEVE retornar 404');
        } finally {
            if (! $businessExists) {
                DB::table('business')->where('id', $this->otherBusinessId)->delete();
            }
        }
    }

    public function test_index_inertia_pagina_segunda_pagina(): void
    {
        $response = $this->inertiaGet('/payments/v2', ['page' => 2]);
        $this->assertSame(200, $response->status());
    }
}

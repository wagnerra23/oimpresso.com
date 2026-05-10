<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Sprint 2 / MWART-0001 — cobertura mínima do branch Inertia em
 * `RepairController@index`.
 *
 * Padrão Jana/Copiloto: roda contra DB dev real (UltimatePOS tem 100+
 * migrations + triggers que não rodam bem em sqlite). Auto-skip se o
 * banco não estiver semeado. Cria transactions de teste marcadas em
 * `invoice_no` com prefixo `TEST-MWART-` e limpa no afterEach.
 */

function repairBootstrapUser(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: '.$e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    foreach (['repair.view', 'repair.view_own', 'repair.create', 'repair.update', 'repair.delete', 'repair_status.update'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return [$business, $user];
}

function repairGivePerm(User $user, string $perm): void
{
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    if (! $user->hasPermissionTo($perm)) {
        $user->givePermissionTo($perm);
    }
}

afterEach(function () {
    try {
        Transaction::where('invoice_no', 'like', 'TEST-MWART-%')->delete();
    } catch (\Throwable $e) {
        // ignore — env de teste pode não ter a coluna ou a tabela
    }
    config([
        'mwart.repair_index.enabled'      => false,
        'mwart.repair_index.business_ids' => [],
    ]);
});

it('respeita flag MWART desligada — retorna Blade', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config(['mwart.repair_index.enabled' => false]);

    $response = $this->actingAs($user)->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('respeita flag MWART ligada (todos businesses) — retorna Inertia Repair/Index', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Repair/Index')
        ->has('repairs.data')
        ->has('filters')
        ->has('meta.repair_statuses')
        ->has('meta.business_locations')
        ->has('meta.totals')
        ->has('permissions.view_all')
    );
});

it('respeita business_ids whitelist — biz fora da lista usa Blade', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    // Lista whitelist com id que NÃO é o business atual
    $foreignId = $business->id + 999;
    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [$foreignId],
    ]);

    $response = $this->actingAs($user)->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('respeita business_ids whitelist — biz dentro da lista usa Inertia', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [(int) $business->id],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Repair/Index'));
});

it('força business_id scope — não vaza dados de outro tenant', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    // Cria 1 transaction em business diferente — não deve aparecer
    $otherBiz = Business::where('id', '!=', $business->id)->first();
    if (! $otherBiz) {
        test()->markTestSkipped('Precisa de >=2 businesses no env de teste.');
    }

    try {
        Transaction::create([
            'business_id'      => $otherBiz->id,
            'location_id'      => $otherBiz->locations()->first()?->id ?? 1,
            'type'             => 'sell',
            'sub_type'         => 'repair',
            'status'           => 'final',
            'payment_status'   => 'due',
            'invoice_no'       => 'TEST-MWART-CROSS-1',
            'transaction_date' => now(),
            'final_total'      => 1.00,
            'created_by'       => $user->id,
        ]);
    } catch (\Throwable $e) {
        test()->markTestSkipped('Schema de transactions não permite create simples: '.$e->getMessage());
    }

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/repair');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('repairs.data', function ($rows) {
            collect($rows)->each(function ($row) {
                expect($row['invoice_no'])->not->toBe('TEST-MWART-CROSS-1');
            });
            return true;
        })
    );
});

it('valida sort fora da whitelist é rejeitado', function () {
    [$business, $user] = repairBootstrapUser();
    repairGivePerm($user, 'repair.view');

    config([
        'mwart.repair_index.enabled'      => true,
        'mwart.repair_index.business_ids' => [],
    ]);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->get('/repair/repair?sort=hax;DROP--&dir=asc');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/module gate bloqueia repair_module neste env.');
    }

    expect($response->status())->toBe(302); // redirect with errors
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato de VALOR e Tier 0 do "Registrar pagamento" da compra
 * (`PurchaseController@registrarPagamento`, POST /purchases/{id}/pagamento).
 *
 * UC-PURSHW-12 `[V0]` — o pagamento grava o valor digitado, move o status e o saldo da compra.
 * UC-PURSHW-13 `[T0]` — não paga compra de outra empresa, não passa do saldo, exige permissão.
 *
 * POR QUE EXISTE: até o cutover de 2026-10-27, registrar pagamento de compra só era possível
 * no menu da lista Blade `purchase.index`. A Blade sai; esta é a tela React que a substitui.
 *
 * DUPLA PROVA (REGRA MESTRE de valor): cada pagamento é conferido por dois caminhos que não
 * dependem um do outro — (a) as linhas em `transaction_payments` + `payment_status` gravado,
 * lidos direto do banco; (b) o payload que a tela `Purchase/Show` recebe depois (`amount_paid`
 * e `payment_due`), calculado pelo `showInertia` a partir das relações do model.
 *
 * O caso 1234.5 é o incidente de 2026-06-05 virado teste: o valor com ponto decimal precisa
 * gravar 1234.50, não 12345 (o `num_uf` pt-BR lê "." como milhar; o endpoint não o usa).
 *
 * TENANTS (ADR 0358): tudo no 98. O adversário é um negócio diferente descoberto em runtime.
 * Nunca biz=1, nunca biz=4.
 *
 * @covers-uc UC-PURSHW-12
 * @covers-uc UC-PURSHW-13
 *
 * @see app/Http/Controllers/PurchaseController.php::registrarPagamento
 * @see resources/js/Pages/Purchase/Show.casos.md (UC-PURSHW-12 · UC-PURSHW-13)
 * @see memory/proibicoes.md (REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE)
 */
uses(DatabaseTransactions::class);

const PURPAG_TENANT = 98;

function purpagLocal(int $businessId): int
{
    $schemeId = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $businessId, 'name' => 'CT Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layoutId = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $businessId, 'name' => 'CT Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => 'CT Filial Pagamento',
        'location_id' => strtoupper(substr(md5('pag' . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $schemeId, 'invoice_layout_id' => $layoutId, 'is_active' => 1,
        // Sem isto `payment_types()` devolve lista vazia (é por filial, igual à Blade).
        'default_payment_accounts' => json_encode([
            'cash' => ['is_enabled' => 1, 'account' => null],
            'bank_transfer' => ['is_enabled' => 1, 'account' => null],
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function purpagCompra(int $businessId, int $locationId, int $userId, float $total): int
{
    $agora = now();

    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'ref_no' => 'CT-PURPAG-' . uniqid(),
        'transaction_date' => $agora,
        'total_before_tax' => $total,
        'final_total' => $total,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'essentials_duration' => 0,
        'created_at' => $agora,
        'updated_at' => $agora,
    ]);
}

/** Caminho (a): o que está gravado. */
function purpagGravado(int $compraId): array
{
    return [
        'soma' => round((float) DB::table('transaction_payments')->where('transaction_id', $compraId)->sum('amount'), 2),
        'linhas' => DB::table('transaction_payments')->where('transaction_id', $compraId)->count(),
        'status' => (string) DB::table('transactions')->where('id', $compraId)->value('payment_status'),
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (ADR 0062).');
    }
    foreach (['transactions', 'transaction_payments', 'business', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }
    if (! \App\Business::find(PURPAG_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    $this->local = purpagLocal(PURPAG_TENANT);
    $this->user = \App\User::factory()->create([
        'business_id' => PURPAG_TENANT,
        'username' => 'ct_purpag_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    foreach (['purchase.view', 'purchase.payments'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $this->user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $sessao = ['user' => ['business_id' => PURPAG_TENANT, 'id' => $this->user->id], 'business' => ['id' => PURPAG_TENANT]];
    $this->pagar = fn (int $id, array $dados) => $this->actingAs($this->user)
        ->withSession($sessao)
        ->from("/purchases/{$id}")
        ->post("/purchases/{$id}/pagamento", $dados + ['method' => 'cash', 'paid_on' => '2026-10-27']);

    // Caminho (b): o payload que a tela recebe.
    $this->tela = function (int $id) use ($sessao) {
        $r = $this->actingAs($this->user)->withSession($sessao)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists(public_path('build-inertia/manifest.json'))
                ? md5_file(public_path('build-inertia/manifest.json'))
                : '1',
        ])->get("/purchases/{$id}");
        $r->assertOk();

        return $r->json('props.purchase');
    };
});

it('UC-PURSHW-12 [V0] pagar 50,00 de uma compra de 135,00 deixa 85,00 a pagar, pelos dois caminhos', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);

    ($this->pagar)($id, ['amount' => 50])->assertRedirect("/purchases/{$id}")->assertSessionHasNoErrors();

    // (a) banco
    expect(purpagGravado($id))->toBe(['soma' => 50.0, 'linhas' => 1, 'status' => 'partial']);
    $linha = DB::table('transaction_payments')->where('transaction_id', $id)->first();
    expect((int) $linha->business_id)->toBe(PURPAG_TENANT);
    expect($linha->method)->toBe('cash');
    expect(substr((string) $linha->paid_on, 0, 10))->toBe('2026-10-27');

    // (b) tela
    $p = ($this->tela)($id);
    expect((float) $p['amount_paid'])->toBe(50.0);
    expect(round((float) $p['payment_due'], 2))->toBe(85.0);
    expect($p['payment_status'])->toBe('partial');
});

it('UC-PURSHW-12 [V0] o restante (85,00) quita a compra: status pago e saldo zero', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);

    ($this->pagar)($id, ['amount' => 50])->assertSessionHasNoErrors();
    ($this->pagar)($id, ['amount' => 85])->assertSessionHasNoErrors();

    expect(purpagGravado($id))->toBe(['soma' => 135.0, 'linhas' => 2, 'status' => 'paid']);
    $p = ($this->tela)($id);
    expect(round((float) $p['payment_due'], 2))->toBe(0.0);
    expect($p['payment_status'])->toBe('paid');
});

it('UC-PURSHW-12 [V0] 1234.5 grava 1234,50, não 12345 (o ponto é decimal, sem num_uf)', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 2000.00);

    ($this->pagar)($id, ['amount' => '1234.5'])->assertSessionHasNoErrors();

    expect(purpagGravado($id)['soma'])->toBe(1234.5);
    expect(round((float) ($this->tela)($id)['payment_due'], 2))->toBe(765.5);
});

it('UC-PURSHW-13 [V0] valor acima do saldo é recusado e nada é gravado', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);
    ($this->pagar)($id, ['amount' => 50])->assertSessionHasNoErrors();

    // Saldo 85,00: 85,01 passa por um centavo.
    ($this->pagar)($id, ['amount' => 85.01])->assertSessionHasErrors('amount');

    expect(purpagGravado($id))->toBe(['soma' => 50.0, 'linhas' => 1, 'status' => 'partial']);
});

it('UC-PURSHW-13 [V0] compra já paga não aceita outro pagamento', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 40.00);
    ($this->pagar)($id, ['amount' => 40])->assertSessionHasNoErrors();

    ($this->pagar)($id, ['amount' => 1])->assertSessionHasErrors('amount');

    expect(purpagGravado($id)['linhas'])->toBe(1);
});

it('UC-PURSHW-13 [T0] compra de outra empresa responde 404 e não ganha pagamento', function () {
    $outro = (int) DB::table('business')->where('id', '!=', PURPAG_TENANT)->value('id');
    if ($outro === 0) {
        $this->markTestSkipped('Lane sem um segundo negócio para servir de adversário.');
    }
    $localOutro = purpagLocal($outro);
    $id = purpagCompra($outro, $localOutro, $this->user->id, 135.00);

    ($this->pagar)($id, ['amount' => 10])->assertNotFound();

    expect(purpagGravado($id)['linhas'])->toBe(0);
});

it('UC-PURSHW-13 [T0] sem purchase.payments responde 403 e não grava', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);
    $this->user->revokePermissionTo('purchase.payments');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    ($this->pagar)($id, ['amount' => 10])->assertForbidden();

    expect(purpagGravado($id)['linhas'])->toBe(0);
});

it('UC-PURSHW-13 forma de pagamento que a filial não habilita é recusada', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);

    // A filial da fixture só habilita dinheiro e transferência.
    ($this->pagar)($id, ['amount' => 10, 'method' => 'cheque'])->assertSessionHasErrors('method');

    expect(purpagGravado($id)['linhas'])->toBe(0);
});

it('UC-PURSHW-12 a tela recebe as formas da filial e a permissão de registrar', function () {
    $id = purpagCompra(PURPAG_TENANT, $this->local, $this->user->id, 135.00);

    $r = $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => PURPAG_TENANT, 'id' => $this->user->id]])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists(public_path('build-inertia/manifest.json'))
                ? md5_file(public_path('build-inertia/manifest.json'))
                : '1',
        ])
        ->get("/purchases/{$id}");

    $r->assertOk();
    expect($r->json('props.permissions.registrar_pagamento'))->toBeTrue();
    expect(collect($r->json('props.formas_pagamento'))->pluck('value')->sort()->values()->all())
        ->toBe(['bank_transfer', 'cash']);
});

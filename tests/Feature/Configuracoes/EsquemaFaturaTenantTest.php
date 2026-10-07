<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 0 (ADR 0093) de /invoice-schemes — achado da thread sistema/playbook/05 ao ler o controller.
 *
 * O esquema de fatura numera as vendas de cada local. update() fazia `InvoiceScheme::where('id', $id)->update()`
 * e destroy()/setDefault() faziam `InvoiceScheme::find($id)`, os três sem negócio: pelo id, um negócio renomeava,
 * mudava prefixo/número inicial, apagava ou virava padrão o esquema de outro. Mesmo desenho do BarcodeController (#8924).
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Todo "não alcançou" vem com a contraprova positiva.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('invoice_schemes') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['invoice_settings.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function esqTenant(int $businessId, array $campos = []): int
{
    return DB::table('invoice_schemes')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank', 'number_type' => 'sequential',
        'prefix' => 'A', 'start_number' => 1, 'total_digits' => 4, 'is_default' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function esqCorpo(string $nome): array
{
    return ['name' => $nome, 'scheme_type' => 'blank', 'prefix' => 'X', 'start_number' => '500', 'total_digits' => '6', 'number_type' => 'sequential'];
}

test('editar altera o esquema do negócio e não alcança o de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = esqTenant($this->business->id);
    $alheio = esqTenant($outro->id, ['name' => 'Alheio intocado', 'prefix' => 'B', 'start_number' => 1]);

    $r = $this->withHeaders($this->ajax)->put("/invoice-schemes/{$meu}", esqCorpo('Renomeado'));
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('invoice_schemes')->where('id', $meu)->value('name'))->toBe('Renomeado');

    $this->withHeaders($this->ajax)->put("/invoice-schemes/{$alheio}", esqCorpo('Invadido'));
    $depois = DB::table('invoice_schemes')->where('id', $alheio)->first();
    expect($depois->name)->toBe('Alheio intocado');
    expect($depois->prefix)->toBe('B');
    expect((int) $depois->start_number)->toBe(1);
});

test('excluir remove o esquema do negócio e não alcança o de outro', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = esqTenant($this->business->id);
    $alheio = esqTenant($outro->id);

    $r = $this->withHeaders($this->ajax)->delete("/invoice-schemes/{$alheio}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('invoice_schemes')->where('id', $alheio)->exists())->toBeTrue();

    $r = $this->withHeaders($this->ajax)->delete("/invoice-schemes/{$meu}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('invoice_schemes')->where('id', $meu)->exists())->toBeFalse();
});

test('excluir recusa o esquema padrão do próprio negócio', function () {
    $padrao = esqTenant($this->business->id, ['is_default' => 1]);

    $r = $this->withHeaders($this->ajax)->delete("/invoice-schemes/{$padrao}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('invoice_schemes')->where('id', $padrao)->exists())->toBeTrue();
});

test('tornar padrão vale só para o esquema do negócio e não mexe no padrão de ninguém por fora', function () {
    $outro = $this->seededSupportClientTenant();
    $padraoMeu = esqTenant($this->business->id, ['is_default' => 1]);
    $outroMeu = esqTenant($this->business->id);
    $alheio = esqTenant($outro->id);

    $r = $this->withHeaders($this->ajax)->get("/invoice-schemes/set_default/{$alheio}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect((int) DB::table('invoice_schemes')->where('id', $alheio)->value('is_default'))->toBe(0);
    expect((int) DB::table('invoice_schemes')->where('id', $padraoMeu)->value('is_default'))->toBe(1);

    $r = $this->withHeaders($this->ajax)->get("/invoice-schemes/set_default/{$outroMeu}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect((int) DB::table('invoice_schemes')->where('id', $outroMeu)->value('is_default'))->toBe(1);
    expect((int) DB::table('invoice_schemes')->where('id', $padraoMeu)->value('is_default'))->toBe(0);
});

test('tornar padrão o esquema que já é o padrão mantém ele padrão', function () {
    $padrao = esqTenant($this->business->id, ['is_default' => 1]);

    $r = $this->withHeaders($this->ajax)->get("/invoice-schemes/set_default/{$padrao}");
    expect($r->json('success'))->toBeTrue();
    expect((int) DB::table('invoice_schemes')->where('id', $padrao)->value('is_default'))->toBe(1);
});

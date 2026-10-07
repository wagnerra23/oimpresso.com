<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 0 (ADR 0093) de /barcodes — achado da thread sistema/playbook/04 ao ler o controller.
 *
 * update() fazia `Barcode::where('id', $id)->update()` e destroy()/setDefault() faziam
 * `Barcode::find($id)`, os três sem negócio: pelo id, um negócio alterava, apagava ou virava
 * padrão a configuração de etiqueta de outro — e também os modelos globais (business_id NULL),
 * que o LabelsController oferece a todos os negócios.
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Todo "não alcançou" vem com a
 * contraprova positiva no próprio negócio.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('barcodes') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['barcode_settings.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function etqTenant(?int $businessId, array $campos = []): int
{
    return DB::table('barcodes')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Etq '.uniqid(), 'description' => 'teste',
        'width' => 1.5, 'height' => 1, 'paper_width' => 8.5, 'paper_height' => 11, 'top_margin' => 0.1,
        'left_margin' => 0.1, 'row_distance' => 0.1, 'col_distance' => 0.1, 'stickers_in_one_row' => 3,
        'stickers_in_one_sheet' => 24, 'is_default' => 0, 'is_continuous' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function etqCorpo(string $nome): array
{
    return ['name' => $nome, 'description' => 'x', 'width' => '2', 'height' => '1', 'paper_width' => '8.5',
        'paper_height' => '11', 'top_margin' => '0', 'left_margin' => '0', 'row_distance' => '0',
        'col_distance' => '0', 'stickers_in_one_row' => '3', 'stickers_in_one_sheet' => '24'];
}

test('editar altera a etiqueta do negócio e não alcança a de outro nem o modelo global', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = etqTenant($this->business->id);
    $alheia = etqTenant($outro->id, ['name' => 'Alheia intocada']);
    $global = etqTenant(null, ['name' => 'Global intocado']);

    $this->put("/barcodes/{$minha}", etqCorpo('Renomeada'))->assertRedirect();
    expect(DB::table('barcodes')->where('id', $minha)->value('name'))->toBe('Renomeada');

    $this->put("/barcodes/{$alheia}", etqCorpo('Invadida'))->assertRedirect();
    $this->put("/barcodes/{$global}", etqCorpo('Invadido'))->assertRedirect();
    expect(DB::table('barcodes')->where('id', $alheia)->value('name'))->toBe('Alheia intocada');
    expect(DB::table('barcodes')->where('id', $global)->value('name'))->toBe('Global intocado');
});

test('excluir remove a etiqueta do negócio e não alcança a de outro nem o modelo global', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = etqTenant($this->business->id);
    $alheia = etqTenant($outro->id);
    $global = etqTenant(null);

    foreach ([$alheia, $global] as $id) {
        $r = $this->withHeaders($this->ajax)->delete("/barcodes/{$id}");
        $r->assertOk();
        expect($r->json('success'))->toBeFalse();
        expect(DB::table('barcodes')->where('id', $id)->exists())->toBeTrue();
    }

    $r = $this->withHeaders($this->ajax)->delete("/barcodes/{$minha}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('barcodes')->where('id', $minha)->exists())->toBeFalse();
});

test('excluir recusa a etiqueta padrão do próprio negócio', function () {
    $padrao = etqTenant($this->business->id, ['is_default' => 1]);

    $r = $this->withHeaders($this->ajax)->delete("/barcodes/{$padrao}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('barcodes')->where('id', $padrao)->exists())->toBeTrue();
});

test('tornar padrão vale só para a etiqueta do negócio e não mexe no padrão de ninguém por fora', function () {
    $outro = $this->seededSupportClientTenant();
    $padraoMeu = etqTenant($this->business->id, ['is_default' => 1]);
    $outraMinha = etqTenant($this->business->id);
    $alheia = etqTenant($outro->id);

    $r = $this->withHeaders($this->ajax)->get("/barcodes/set_default/{$alheia}");
    $r->assertOk();
    expect($r->json('success'))->toBeFalse();
    expect((int) DB::table('barcodes')->where('id', $alheia)->value('is_default'))->toBe(0);
    expect((int) DB::table('barcodes')->where('id', $padraoMeu)->value('is_default'))->toBe(1);

    $r = $this->withHeaders($this->ajax)->get("/barcodes/set_default/{$outraMinha}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect((int) DB::table('barcodes')->where('id', $outraMinha)->value('is_default'))->toBe(1);
    expect((int) DB::table('barcodes')->where('id', $padraoMeu)->value('is_default'))->toBe(0);
});

test('tornar padrão a etiqueta que já é a padrão mantém ela padrão', function () {
    $padrao = etqTenant($this->business->id, ['is_default' => 1]);

    $r = $this->withHeaders($this->ajax)->get("/barcodes/set_default/{$padrao}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect((int) DB::table('barcodes')->where('id', $padrao)->value('is_default'))->toBe(1);
});

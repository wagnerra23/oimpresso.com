<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 0 (ADR 0093) — thread sistema/playbook/12 (origem: _saida-04 §3).
 *
 * PrinterController::edit, BarcodeController::edit e BusinessLocationController::edit usavam
 * `where('business_id')->find($id)`: com o id de outro negócio o model virava null e a tela quebrava
 * (500). BusinessLocationController::update com id alheio não alterava nada, mas respondia
 * `success: true`. Os quatro passam a responder 404. Mesmo desenho do #8924 e do #8979.
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Todo 404 vem com a contraprova do próprio id,
 * senão um 403 ou um erro qualquer passaria por isolamento.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || ! Schema::hasColumn('users', 'business_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->outro = $this->seededSupportClientTenant();
    $this->user = $this->usuarioComPermissoes(
        ['access_printers', 'barcode_settings.access', 'business_settings.access', 'access_all_locations'],
        $this->business
    );
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id, 'business.enabled_modules' => []]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function teuImpressora(int $businessId, int $criadoPor): int
{
    return DB::table('printers')->insertGetId([
        'business_id' => $businessId, 'name' => 'Imp '.uniqid(), 'connection_type' => 'network',
        'capability_profile' => 'default', 'char_per_line' => '42', 'ip_address' => '192.168.0.31',
        'port' => '9100', 'path' => '', 'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function teuEtiqueta(int $businessId): int
{
    return DB::table('barcodes')->insertGetId([
        'business_id' => $businessId, 'name' => 'Etq '.uniqid(), 'description' => 'teste',
        'width' => 1.5, 'height' => 1, 'paper_width' => 8.5, 'paper_height' => 11, 'top_margin' => 0.1,
        'left_margin' => 0.1, 'row_distance' => 0.1, 'col_distance' => 0.1, 'stickers_in_one_row' => 3,
        'stickers_in_one_sheet' => 24, 'is_default' => 0, 'is_continuous' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function teuLocal(int $businessId, string $nome): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá',
        'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

test('editar impressora: o id de outro negócio dá 404 e o próprio abre o formulário', function () {
    $alheia = teuImpressora($this->outro->id, $this->user->id);
    $minha = teuImpressora($this->business->id, $this->user->id);

    $this->get("/printers/{$alheia}/edit")->assertNotFound();

    $this->get("/printers/{$minha}/edit")->assertOk()->assertViewIs('printer.edit');
});

test('editar código de barras: o id de outro negócio dá 404 e o próprio abre o formulário', function () {
    $alheia = teuEtiqueta($this->outro->id);
    $minha = teuEtiqueta($this->business->id);

    $this->get("/barcodes/{$alheia}/edit")->assertNotFound();

    $this->get("/barcodes/{$minha}/edit")->assertOk()->assertViewIs('barcode.edit');
});

test('editar local: o id de outro negócio dá 404 e o próprio abre o formulário', function () {
    $alheio = teuLocal($this->outro->id, 'Alheio '.uniqid());
    $meu = teuLocal($this->business->id, 'Meu '.uniqid());

    $this->get("/business-location/{$alheio}/edit")->assertNotFound();

    $this->get("/business-location/{$meu}/edit")->assertOk()->assertViewIs('business_location.edit');
});

test('atualizar local: o id de outro negócio dá 404 sem alterar e o próprio é atualizado', function () {
    $alheio = teuLocal($this->outro->id, 'Alheio intocado');
    $meu = teuLocal($this->business->id, 'Meu '.uniqid());
    // Esquema e layout do próprio negócio: o validateInvoiceRefs do update() recusa os de fora.
    $corpo = fn (string $nome) => [
        'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá', 'zip_code' => '7806500',
        'invoice_scheme_id' => (string) DB::table('business_locations')->where('id', $meu)->value('invoice_scheme_id'),
        'invoice_layout_id' => (string) DB::table('business_locations')->where('id', $meu)->value('invoice_layout_id'),
    ];

    $this->withHeaders($this->ajax)->put("/business-location/{$alheio}", $corpo('Invadido'))->assertNotFound();
    expect(DB::table('business_locations')->where('id', $alheio)->value('name'))->toBe('Alheio intocado');

    $r = $this->withHeaders($this->ajax)->put("/business-location/{$meu}", $corpo('Renomeado'));
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(DB::table('business_locations')->where('id', $meu)->value('name'))->toBe('Renomeado');
});

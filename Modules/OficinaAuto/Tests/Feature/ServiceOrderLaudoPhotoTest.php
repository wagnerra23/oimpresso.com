<?php

declare(strict_types=1);

use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * F3 OS-V2-1 — Fotos & Laudo OS-level (ServiceOrderPhotoController).
 *
 * Cobre o porte do protótipo Cowork aprovado [W] 2026-06-09: upload de foto
 * anexada à própria ServiceOrder (HasArquivos morphTo), legenda editável e
 * presença no laudo A4 impresso.
 *
 * Defesa Tier 0 [ADR 0093]:
 *   - store/destroy escopados por business (sameTenant policy + global scope Arquivo)
 *   - cross-owner guard: arquivo de outra OS na rota desta OS → 404
 *   - reject non-image (FormRequest mimes/mimetypes)
 *
 * MySQL-only (ADR 0101): schema UltimatePOS + tabelas arquivos*. Skip em sqlite —
 * roda no CI (mesmo padrão de ServiceOrderRichSheetPayloadTest).
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderPhotoController.php
 */

const BIZ_LAUDO = 1;
const BIZ_LAUDO_OUTRO = 2;
const PLATE_LAUDO_PREFIX = 'LAUDO';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('Rode as migrations do Modules/Arquivos primeiro (ADR 0123)');
    }
    Storage::fake(config('arquivos.disk_default', 'local'));
});

function laudo_criaOs(string $suffix, int $biz = BIZ_LAUDO): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'plate'        => PLATE_LAUDO_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
        'model_year'   => 2021,
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'contact_id'  => 1,
    ]);
}

function laudo_user(int $biz = BIZ_LAUDO): User
{
    $user = User::factory()->create(['business_id' => $biz]);
    $user->givePermissionTo('superadmin');

    return $user;
}

function laudo_cleanup(string $suffix): void
{
    $vehicles = Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', PLATE_LAUDO_PREFIX . $suffix . '%')
        ->pluck('id')
        ->toArray();

    if (empty($vehicles)) {
        return;
    }

    $osIds = ServiceOrder::withoutGlobalScopes()
        ->whereIn('vehicle_id', $vehicles)
        ->pluck('id')
        ->toArray();

    if (! empty($osIds)) {
        Arquivo::withoutGlobalScopes()
            ->where('arquivable_type', ServiceOrder::class)
            ->whereIn('arquivable_id', $osIds)
            ->forceDelete();
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $osIds)->forceDelete();
    }

    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicles)->forceDelete();
}

// ---------------------------------------------------------------------------
// store
// ---------------------------------------------------------------------------

it('store anexa foto à OS escopada por business → 201 + arquivo morphTo ServiceOrder', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $os = laudo_criaOs('A');
    $user = laudo_user();

    $response = $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$os->id}/fotos",
        ['photo' => UploadedFile::fake()->image('correia.jpg', 800, 600)],
    );

    $response->assertCreated();
    expect($response->json('foto.label'))->toBe('correia.jpg');
    expect($response->json('foto'))->toHaveKeys(['id', 'label', 'mime_type', 'size_bytes', 'display_url', 'created_at']);

    // Arquivo anexado à OS (morphTo) + escopado no business da sessão.
    $arquivo = Arquivo::withoutGlobalScopes()
        ->where('arquivable_type', ServiceOrder::class)
        ->where('arquivable_id', $os->id)
        ->first();
    expect($arquivo)->not->toBeNull();
    expect((int) $arquivo->business_id)->toBe(BIZ_LAUDO);
})->afterEach(fn () => laudo_cleanup('A'));

it('store rejeita arquivo que não é imagem → 422', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $os = laudo_criaOs('B');
    $user = laudo_user();

    $response = $this->actingAs($user)->postJson(
        "/oficina-auto/ordens-servico/{$os->id}/fotos",
        ['photo' => UploadedFile::fake()->create('orcamento.pdf', 120, 'application/pdf')],
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('photo');

    expect(
        Arquivo::withoutGlobalScopes()
            ->where('arquivable_type', ServiceOrder::class)
            ->where('arquivable_id', $os->id)
            ->count()
    )->toBe(0);
})->afterEach(fn () => laudo_cleanup('B'));

// ---------------------------------------------------------------------------
// multi-tenant + cross-owner guard
// ---------------------------------------------------------------------------

it('store em OS de outro business → 404 (global scope route binding)', function () {
    // OS pertence ao business 2; sessão é business 1.
    session(['user.business_id' => BIZ_LAUDO]);
    $osOutro = laudo_criaOs('C', BIZ_LAUDO_OUTRO);
    $user = laudo_user(BIZ_LAUDO);

    $response = $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$osOutro->id}/fotos",
        ['photo' => UploadedFile::fake()->image('x.jpg')],
    );

    $response->assertNotFound();
})->afterEach(fn () => laudo_cleanup('C'));

it('cross-owner guard: arquivo de uma OS não pode ser apagado pela rota de outra OS → 404', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $osA = laudo_criaOs('D');
    $osB = laudo_criaOs('D2');
    $user = laudo_user();

    // Sobe foto na OS B.
    $up = $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$osB->id}/fotos",
        ['photo' => UploadedFile::fake()->image('b.jpg')],
    );
    $arquivoId = $up->json('foto.id');

    // Tenta apagar pela rota da OS A → cross-owner guard → 404.
    $del = $this->actingAs($user)->deleteJson(
        "/oficina-auto/ordens-servico/{$osA->id}/fotos/{$arquivoId}",
    );
    $del->assertNotFound();

    // Foto continua viva na OS B.
    expect(Arquivo::withoutGlobalScopes()->whereKey($arquivoId)->whereNull('deleted_at')->exists())->toBeTrue();
})->afterEach(function () {
    laudo_cleanup('D');
});

// ---------------------------------------------------------------------------
// updateLabel + destroy + index
// ---------------------------------------------------------------------------

it('updateLabel persiste a legenda (original_name) + destroy soft-deleta', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $os = laudo_criaOs('E');
    $user = laudo_user();

    $up = $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$os->id}/fotos",
        ['photo' => UploadedFile::fake()->image('foto.jpg')],
    );
    $arquivoId = $up->json('foto.id');

    // PATCH legenda
    $patch = $this->actingAs($user)->patchJson(
        "/oficina-auto/ordens-servico/{$os->id}/fotos/{$arquivoId}",
        ['label' => 'Correia dentada · antes'],
    );
    $patch->assertOk();
    expect($patch->json('foto.label'))->toBe('Correia dentada · antes');

    // index reflete a legenda
    $index = $this->actingAs($user)->getJson("/oficina-auto/ordens-servico/{$os->id}/fotos");
    $index->assertOk();
    expect($index->json('fotos'))->toHaveCount(1);
    expect($index->json('fotos.0.label'))->toBe('Correia dentada · antes');

    // DELETE → 204 + soft-delete
    $del = $this->actingAs($user)->deleteJson("/oficina-auto/ordens-servico/{$os->id}/fotos/{$arquivoId}");
    $del->assertNoContent();
    expect(Arquivo::withoutGlobalScopes()->whereKey($arquivoId)->whereNull('deleted_at')->exists())->toBeFalse();
})->afterEach(fn () => laudo_cleanup('E'));

// ---------------------------------------------------------------------------
// payload do drawer + laudo no print A4
// ---------------------------------------------------------------------------

it('GET /service-orders/{id} JSON inclui laudo_photos após upload', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $os = laudo_criaOs('F');
    $user = laudo_user();

    $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$os->id}/fotos",
        ['photo' => UploadedFile::fake()->image('laudo.jpg')],
    )->assertCreated();

    $response = $this->actingAs($user)->getJson("/oficina-auto/service-orders/{$os->id}");
    $response->assertOk();
    expect($response->json('laudo_photos'))->toBeArray()->toHaveCount(1);
    expect($response->json('laudo_photos.0.label'))->toBe('laudo.jpg');
})->afterEach(fn () => laudo_cleanup('F'));

it('printInvoice A4 inclui a seção "Fotos da vistoria" com a legenda quando há fotos', function () {
    session(['user.business_id' => BIZ_LAUDO]);
    $os = laudo_criaOs('G');
    $user = laudo_user();

    $arquivoId = $this->actingAs($user)->post(
        "/oficina-auto/ordens-servico/{$os->id}/fotos",
        ['photo' => UploadedFile::fake()->image('chassi.jpg')],
    )->json('foto.id');

    $this->actingAs($user)->patchJson(
        "/oficina-auto/ordens-servico/{$os->id}/fotos/{$arquivoId}",
        ['label' => 'Chassi lateral'],
    )->assertOk();

    $print = $this->actingAs($user)->getJson("/oficina-auto/ordens-servico/{$os->id}/print");
    $print->assertOk();
    $html = $print->json('receipt.html_content');
    expect($html)->toContain('Fotos da vistoria');
    expect($html)->toContain('Chassi lateral');
})->afterEach(fn () => laudo_cleanup('G'));

<?php

declare(strict_types=1);
// Cobre UC-P03, UC-P04, UC-P05 e UC-P06 (resources/js/Pages/User/Perfil.casos.md) - G-2.

use App\Media;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato da tela "Meu perfil" (/perfil): salvar, trocar senha, remover foto e idioma
 * em português. Roda na lane Acessos com o tenant de teste 98 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($this->user);
    session(['user.id' => $this->user->id, 'user.business_id' => $this->business->id, 'business.id' => $this->business->id]);
});

function perfilInertia($test)
{
    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])->get('/perfil');
    $response->assertStatus(200);

    return json_decode($response->getContent(), true);
}

it('UC-P03 · salvar o perfil grava no proprio usuario e nao no de outro', function () {
    $outro = $this->usuarioComPermissoes([], $this->business);
    $nomeDoOutro = $outro->first_name;

    $this->post('/perfil/update', [
        'first_name' => 'NomeSalvoPerfil',
        'email' => $this->user->email,
        'bank_details' => ['bank_name' => 'Banco Teste'],
    ])->assertRedirect();

    $salvo = User::find($this->user->id);
    expect($salvo->first_name)->toBe('NomeSalvoPerfil');
    expect(json_decode((string) $salvo->bank_details, true)['bank_name'] ?? null)->toBe('Banco Teste');
    expect(User::find($outro->id)->first_name)->toBe($nomeDoOutro);
});

it('UC-P04 · trocar senha exige a senha atual correta', function () {
    $this->user->password = Hash::make('senha-atual-123');
    $this->user->save();

    $this->post('/perfil/password', [
        'current_password' => 'senha-errada',
        'new_password' => 'nova-senha-456',
        'new_password_confirmation' => 'nova-senha-456',
    ])->assertSessionHasErrors('current_password');
    expect(Hash::check('senha-atual-123', User::find($this->user->id)->password))->toBeTrue();

    $this->post('/perfil/password', [
        'current_password' => 'senha-atual-123',
        'new_password' => 'nova-senha-456',
        'new_password_confirmation' => 'nova-senha-456',
    ])->assertSessionHasNoErrors();
    expect(Hash::check('nova-senha-456', User::find($this->user->id)->password))->toBeTrue();
});

it('UC-P05 · remover a foto apaga a midia do proprio usuario', function () {
    $media = Media::create([
        'business_id' => $this->business->id,
        'file_name' => 'perfil-teste-inexistente.png',
        'uploaded_by' => $this->user->id,
        'model_type' => User::class,
        'model_id' => $this->user->id,
    ]);
    // Anti-vácuo: a foto aparece na tela antes de remover.
    expect(perfilInertia($this)['props']['usuario']['photo_url'])->not->toBeNull();

    $this->post('/perfil/update', [
        'first_name' => $this->user->first_name,
        'email' => $this->user->email,
        'remove_photo' => '1',
    ])->assertRedirect();

    expect(Media::find($media->id))->toBeNull();
    expect(perfilInertia($this)['props']['usuario']['photo_url'])->toBeNull();
});

it('UC-P06 · a lista de idiomas mostra o nome em portugues', function () {
    $languages = perfilInertia($this)['props']['languages'];

    expect($languages['pt'] ?? null)->toBe('Português');
    expect($languages['en'] ?? null)->toBe('Inglês');
});

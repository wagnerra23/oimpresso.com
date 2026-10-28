<?php

declare(strict_types=1);
// Cobre UC-P02 (resources/js/Pages/User/Perfil.casos.md) - G-2 rastreabilidade caso-teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover F5 do perfil (2026-10-27): /user/profile deixou de servir o Blade e leva para a
 * tela React /perfil. O PerfilSmokeTest cobria o comportamento antigo, mas depende de
 * DEV_LOGIN_* e pula no CI; este roda na lane Acessos com o tenant de teste 98 (ADR 0358).
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

it('UC-P02 · o endereco antigo /user/profile leva para /perfil', function () {
    $this->get('/user/profile')->assertRedirect(route('perfil'));
});

it('UC-P02 · a tela de destino /perfil abre o perfil React do usuario logado', function () {
    // Anti-vácuo: o redirect só vale se o destino abre. Sem isto, um /perfil quebrado
    // passaria pelo caso acima.
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
    ])->get('/perfil');

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'] ?? null)->toBe('User/Perfil');
    expect($page['props']['usuario']['email'] ?? null)->toBe($this->user->email);
});

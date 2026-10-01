<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;

/**
 * Contrato da BASE do app das lojas (/m) — decisão [W] 2026-10-01.
 * Casos: resources/js/Pages/Mobile/{Inicio,Mais,EmConstrucao}.casos.md (UC-MOB-01..04).
 * Contrato do shell: memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md.
 *
 * Tier 0 (ADR 0093): o nome da empresa vem do business do usuário autenticado; o caso
 * cross-tenant usa o tenant 98 (ADR 0358) contra o 99 — nunca o 4.
 */
function mobileLogar(Business $business): User
{
    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->orderBy('id')
        ->first();

    expect($user)->not->toBeNull("tenant {$business->id} sem usuário não-customer no seed");

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'user.first_name' => $user->first_name ?? 'Usuário',
        'business.id' => $business->id,
        'business.currency_id' => $business->currency_id ?? 1,
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $user;
}

it('UC-MOB-01 · sem sessão, /m leva ao login e guarda /m como destino', function () {
    $response = $this->get('/m');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toEndWith('/login');
    expect((string) session('url.intended'))->toEndWith('/m');
});

it('UC-MOB-02 · logado, /m é o Início com o meu nome e a empresa do MEU business', function () {
    $tenant = $this->seededTenant();
    $user = mobileLogar($tenant);

    $this->actingAs($user)->get('/m')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mobile/Inicio')
            ->where('empresa.nome', (string) $tenant->name)
            ->has('usuario.nome')
            ->has('hoje'));
});

it('UC-MOB-02 · Tier 0 — usuário de outro business vê a empresa DELE, não a do tenant 98', function () {
    $tenant98 = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();
    expect($outro->name)->not->toBe($tenant98->name, 'fixture: os dois tenants precisam de nomes distintos');

    $user = mobileLogar($outro);

    $this->actingAs($user)->get('/m')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mobile/Inicio')
            ->where('empresa.nome', (string) $outro->name));
});

it('UC-MOB-03 · /m/mais mostra quem está logado e em qual empresa', function () {
    $tenant = $this->seededTenant();
    $user = mobileLogar($tenant);

    $this->actingAs($user)->get('/m/mais')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Mobile/Mais')
            ->where('empresa.nome', (string) $tenant->name)
            ->has('usuario.nome'));
});

it('UC-MOB-04 · abas sem tela respondem 200 com o marcador, e slug desconhecido é 404', function () {
    $user = mobileLogar($this->seededTenant());

    foreach (['tarefas', 'pedidos', 'producao'] as $aba) {
        $this->actingAs($user)->get("/m/{$aba}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Mobile/EmConstrucao')
                ->where('aba', $aba));
    }

    $this->actingAs($user)->get('/m/nao-existe')->assertNotFound();
});

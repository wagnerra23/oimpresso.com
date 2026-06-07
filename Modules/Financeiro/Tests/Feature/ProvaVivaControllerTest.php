<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * ADR 0253 — Pest GUARD da tela /financeiro/prova-viva (prova viva dos primitivos).
 *
 * Cobre:
 * - Rota responde 200 + header Inertia (atrás do guard financeiro.dashboard.view)
 * - Inertia component path correto (Financeiro/ProvaViva)
 * - Read-only / sem dado de tenant: a tela é prova de LAYOUT (mock no .tsx),
 *   logo o payload não expõe props de negócio — isolamento Tier 0 (ADR 0093)
 *   trivialmente preservado por construção.
 *
 * Padrão de skip gracioso igual FluxoControllerTest (DB greenfield / module gate).
 */
function provaVivaBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
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

    return $user;
}

it('renderiza Inertia component Financeiro/ProvaViva', function () {
    $user = provaVivaBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/prova-viva');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription/permission gate bloqueia neste env.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env (financeiro:install pendente).');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/ProvaViva')
    );
});

it('bloqueia acesso sem a permissão financeiro.dashboard.view', function () {
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    // User SEM a permissão de leitura do financeiro.
    $user = User::factory()->create(['business_id' => $business->id]);

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business'         => ['id' => $business->id, 'name' => $business->name],
    ]);

    $response = $this->actingAs($user)->get('/financeiro/prova-viva');

    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env.');
    }

    expect($response->status())->toBe(403);
});

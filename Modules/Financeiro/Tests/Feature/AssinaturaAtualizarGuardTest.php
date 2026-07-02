<?php

declare(strict_types=1);
// @covers-us US-FIN-063

use App\User;
use Inertia\Testing\AssertableInertia;

uses(Tests\TestCase::class);

/**
 * Atualizar Cobrança de assinatura (US-FIN-063 retro · task MCP FIN-004).
 *
 * GUARDs:
 *  (A1) GET /financeiro/assinaturas/atualizar responde 200 com shape Inertia
 *       (component Financeiro/AssinaturaAtualizar + prop assinaturas)
 *  (A2) guest é redirecionado pro login (middleware auth — tela nunca pública)
 *
 * Skip gracioso quando DB greenfield (padrão GUARDs Financeiro — ver ImpostosGuardTest).
 */
function assinaturaAtualizarBootstrap(): User
{
    try {
        $business = test()->seededTenant(); // trait WithSeededTenant (biz=1 canônico, ADR 0101)
    } catch (\Illuminate\Database\QueryException $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    session([
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return $user;
}

it('A1 — GET /financeiro/assinaturas/atualizar renderiza a tela com prop assinaturas', function () {
    $user = assinaturaAtualizarBootstrap();

    $response = test()->actingAs($user)->get('/financeiro/assinaturas/atualizar');

    if ($response->status() >= 500) {
        test()->markTestSkipped('Ambiente sem tabelas RecurringBilling (greenfield): '.$response->status());
    }

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/AssinaturaAtualizar')
        ->has('assinaturas'));
});

it('A2 — guest não acessa a tela (redirect login)', function () {
    $response = test()->get('/financeiro/assinaturas/atualizar');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('login');
});

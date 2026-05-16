<?php

declare(strict_types=1);

use App\Business;
use App\User;

uses(Tests\TestCase::class);

/**
 * US-JANA-PAINEL-001 · Onda A1 smoke do PainelController.
 *
 * Valida que:
 *  - rota GET /jana/painel retorna 200 quando autenticado
 *  - Inertia component 'Jana/Painel' é resolvido
 *  - payload contém business + person + brief + 4 kpis + 6 analises + 4 acoes
 *  - business_id da sessão é honrado (multi-tenant Tier 0 — ADR 0093)
 *
 * Padrão dos outros JanaControllerTest: roda contra DB dev real (UPos não migra SQLite).
 * markTestSkipped se ambiente vazio.
 */

function janaPainelBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UPos antes.');
    }

    try {
        $user = User::where('business_id', $business->id)->first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela users indisponível: '.$e->getMessage());
    }

    if (! $user) {
        test()->markTestSkipped("Sem user em business_id={$business->id}.");
    }

    return [$business, $user];
}

beforeEach(function () {
    [$this->business, $this->user] = janaPainelBootstrap();
    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'business'         => ['id' => $this->business->id, 'name' => $this->business->name],
    ]);
});

it('GET /jana/painel retorna 200 com Inertia component Jana/Painel', function () {
    $response = $this->get('/jana/painel');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('Jana/Painel'));
});

it('payload contém estrutura canon: business + person + brief + 4 kpis + 6 analises + 4 acoes', function () {
    $response = $this->get('/jana/painel');

    $response->assertInertia(fn ($page) => $page
        ->component('Jana/Painel')
        ->has('business', fn ($b) => $b
            ->where('id', $this->business->id)
            ->has('name')
            ->has('version')
        )
        ->has('painel.person.name')
        ->has('painel.brief.greeting')
        ->has('painel.brief.paragraphs')
        ->has('painel.brief.chips', 4)
        ->has('painel.kpis', 4)
        ->has('painel.analises', 6)
        ->has('painel.acoes', 4)
    );
});

it('respeita business_id da sessão (Tier 0 multi-tenant)', function () {
    $response = $this->get('/jana/painel');

    $response->assertInertia(fn ($page) => $page
        ->where('business.id', $this->business->id)
    );
});

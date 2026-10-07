<?php

declare(strict_types=1);
// Cobre UC-COM-04 (resources/js/Pages/Report/SalesRepresentative/Index.casos.md) — eixo endpoint.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

// Tier 0 (ADR 0093): o percentual de comissão vem de `users`, que NÃO tem global scope de
// business. O endpoint lia `User::find($commission_agent)` — id de vendedor de outra empresa
// devolvia o cmmsn_percent dele. Tenant 98 × tenant de suporte fictício (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'cmmsn_percent')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['sales_representative.view', 'access_all_locations'], $this->business);

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);

    $this->filtro = ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()];
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

test('UC-COM-04 Tier 0 — vendedor de OUTRA empresa não tem o percentual exposto (404)', function () {
    $alheio = \App\User::factory()->create([
        'business_id' => $this->seededSupportClientTenant()->id,
        'is_cmmsn_agnt' => 1,
        'cmmsn_percent' => 37.25, // valor discriminante: não coincide com nenhum default
    ]);
    expect($alheio->business_id)->not->toBe($this->business->id);

    $r = $this->withHeaders($this->ajax)
        ->get('/reports/sales-representative-total-commission?'.http_build_query($this->filtro + ['commission_agent' => $alheio->id]));

    $r->assertNotFound();
    expect($r->getContent())->not->toContain('37.25');
});

test('UC-COM-04 controle — vendedor do PRÓPRIO negócio segue com o mesmo percentual', function () {
    $agente = \App\User::factory()->create([
        'business_id' => $this->business->id,
        'is_cmmsn_agnt' => 1,
        'cmmsn_percent' => 12.5,
    ]);

    $r = $this->withHeaders($this->ajax)
        ->get('/reports/sales-representative-total-commission?'.http_build_query($this->filtro + ['commission_agent' => $agente->id]));

    $r->assertOk();
    expect((float) $r->json('commission_percentage'))->toBe(12.5);
});

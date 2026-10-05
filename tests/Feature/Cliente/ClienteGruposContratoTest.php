<?php

declare(strict_types=1);
// Cobre UC-CGRP-01, UC-CGRP-02, UC-CGRP-03, UC-CGRP-04, UC-CGRP-05 (Cliente/Grupos/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread cliente/playbook/03 — /customer-group em Inertia (D2 = tela própria).
 *
 * Backend: CustomerGroupController (routes/web.php `Route::resource('customer-group')`).
 * O store/update NÃO mudou de forma: recebe o percentual como texto pt-BR (o que o input_number
 * da Blade mandava) e o `num_uf` converte. Os casos de valor abaixo provam o mesmo número por dois
 * caminhos: o endpoint gravando e o `num_uf` aplicado ao texto que a tela nova monta (regra mestre
 * de valor). Tenant de teste 98 × cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('customer_groups')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(
        ['customer.view', 'customer.create', 'customer.update', 'customer.delete'],
        $this->business
    );
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function grupoCriar(int $businessId, int $userId, string $nome, float $amount = 0, ?int $spg = null): int
{
    return (int) DB::table('customer_groups')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'amount' => $amount,
        'price_calculation_type' => $spg ? 'selling_price_group' : 'percentage',
        'selling_price_group_id' => $spg, 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function gruposDeferidos($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Cliente/Grupos/Index',
        'X-Inertia-Partial-Data' => 'grupos',
    ])->get('/customer-group');
    $r->assertOk();

    return $r->json('props.grupos') ?? [];
}

test('UC-CGRP-01 GET /customer-group renderiza Inertia Cliente/Grupos/Index (não a Blade)', function () {
    // X-Requested-With junto do X-Inertia, como o browser manda: o ramo ajax() antigo engolia isso.
    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/customer-group');

    $r->assertOk();
    expect($r->json('component'))->toBe('Cliente/Grupos/Index');
    expect($r->json('props'))->toHaveKeys(['tabelas', 'pode']);
    expect($r->json('props.pode.criar'))->toBeTrue();
});

test('UC-CGRP-02 Tier 0 — a lista só traz grupo do negócio da sessão, com a contagem de cadastros', function () {
    $outro = $this->seededSupportClientTenant();
    $outroUser = \App\User::factory()->create(['business_id' => $outro->id]);

    $meu = grupoCriar($this->business->id, $this->user->id, 'Grupo Proprio '.uniqid(), 5);
    $alheio = grupoCriar($outro->id, $outroUser->id, 'Grupo Alheio '.uniqid(), 5);
    foreach ([1, 2] as $_) {
        DB::table('contacts')->insert([
            'business_id' => $this->business->id, 'created_by' => $this->user->id, 'type' => 'customer',
            'name' => 'Cliente do grupo '.uniqid(), 'contact_status' => 'active', 'customer_group_id' => $meu,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $ids = collect(gruposDeferidos($this))->keyBy('id');

    expect($ids->has($meu))->toBeTrue('o grupo do próprio negócio tem de aparecer (anti-vácuo)');
    expect($ids->has($alheio))->toBeFalse();
    expect($ids[$meu]['cadastros'])->toBe(2);
});

test('UC-CGRP-03 valor — o percentual com sinal e decimal chega igual por dois caminhos', function () {
    $util = new \App\Utils\Util;
    // Texto que a tela nova monta (paraTexto = toLocaleString pt-BR, 2 casas) e o que a Blade mandava.
    $casos = ['10,50' => 10.5, '-5,25' => -5.25, '0,00' => 0.0, '12' => 12.0, '-10' => -10.0];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Grupo Valor '.uniqid();
        $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post('/customer-group', ['name' => $nome, 'price_calculation_type' => 'percentage', 'amount' => $texto]);
        $r->assertOk();
        expect($r->json('success'))->toBeTrue();

        $gravado = (float) DB::table('customer_groups')->where('business_id', $this->business->id)->where('name', $nome)->value('amount');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect($gravado)->toBe($esperado);
        expect((float) $util->num_uf($texto))->toBe($esperado);
    }
});

test('UC-CGRP-04 editar troca o percentual sem tocar no grupo de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $outroUser = \App\User::factory()->create(['business_id' => $outro->id]);
    $meu = grupoCriar($this->business->id, $this->user->id, 'Grupo Edit '.uniqid(), 3);
    $alheio = grupoCriar($outro->id, $outroUser->id, 'Grupo Alheio Edit '.uniqid(), 3);
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $this->withHeaders($h)->post("/customer-group/{$meu}", [
        '_method' => 'PUT', 'name' => 'Grupo Editado', 'price_calculation_type' => 'percentage', 'amount' => '-7,50',
    ])->assertOk();
    expect((float) DB::table('customer_groups')->where('id', $meu)->value('amount'))->toBe(-7.5);

    $r = $this->withHeaders($h)->post("/customer-group/{$alheio}", [
        '_method' => 'PUT', 'name' => 'Invadido', 'price_calculation_type' => 'percentage', 'amount' => '99',
    ]);
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('customer_groups')->where('id', $alheio)->value('name'))->not->toBe('Invadido');
});

test('UC-CGRP-05 Tier 0 — grupo não aceita tabela de preço de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $spgAlheia = (int) DB::table('selling_price_groups')->insertGetId([
        'name' => 'Tabela Alheia '.uniqid(), 'business_id' => $outro->id, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $spgMinha = (int) DB::table('selling_price_groups')->insertGetId([
        'name' => 'Tabela Minha '.uniqid(), 'business_id' => $this->business->id, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    $nomeRecusado = 'Grupo Tabela Alheia '.uniqid();
    $r = $this->withHeaders($h)->post('/customer-group', [
        'name' => $nomeRecusado, 'price_calculation_type' => 'selling_price_group', 'selling_price_group_id' => $spgAlheia,
    ]);
    expect($r->json('success'))->toBeFalse();
    expect(DB::table('customer_groups')->where('name', $nomeRecusado)->exists())->toBeFalse();

    // Contraprova: a tabela do próprio negócio grava (sem isto, o recusado acima podia ser outra falha).
    $nomeAceito = 'Grupo Tabela Minha '.uniqid();
    $this->withHeaders($h)->post('/customer-group', [
        'name' => $nomeAceito, 'price_calculation_type' => 'selling_price_group', 'selling_price_group_id' => $spgMinha,
    ])->assertOk();
    expect((int) DB::table('customer_groups')->where('name', $nomeAceito)->value('selling_price_group_id'))->toBe($spgMinha);
});

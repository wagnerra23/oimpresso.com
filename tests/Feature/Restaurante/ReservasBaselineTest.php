<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/03, F2 — o que a Blade de Reservas (/bookings) mostra HOJE, antes da tela React. Trava
 * o JSON do calendário (BookingController::index → RestaurantUtil::getBookingsForCalendar): só reservas do negócio,
 * 403 sem crud_all_bookings e sem crud_own_bookings, e quem só tem crud_own_bookings vê só as reservas que criou —
 * mesmo sendo o atendente de outra. Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Restaurante/RUNBOOK-reservas.md
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('bookings') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
});

function reservasBaselineEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

function reservasBaselineCliente(int $businessId, int $criador, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => $nome, 'mobile' => '',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function reservasBaselineReserva(int $businessId, int $local, int $cliente, int $criador, ?int $atendente = null): int
{
    return (int) DB::table('bookings')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'contact_id' => $cliente, 'waiter_id' => $atendente,
        'booking_start' => now()->addDay()->setTime(10, 0), 'booking_end' => now()->addDay()->setTime(11, 0),
        'created_by' => $criador, 'booking_status' => 'booked', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Os títulos que o calendário da Blade pede (sem X-Inertia): "cliente - mesa". */
function reservasBaselineCalendario($teste): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/bookings?'.http_build_query(['start' => now()->toDateString(), 'end' => now()->addDays(7)->toDateString()]));
    $r->assertOk();

    return collect($r->json())->pluck('customer_name');
}

test('o calendário da Blade traz só as reservas do negócio da sessão', function () {
    $user = $this->usuarioComPermissoes(['crud_all_bookings'], $this->business);
    reservasBaselineEntrar($this, $user);
    $outro = $this->seededSupportClientTenant();
    $minha = 'Minha '.uniqid();
    $alheia = 'Alheia '.uniqid();
    reservasBaselineReserva($this->business->id, $this->local, reservasBaselineCliente($this->business->id, $user->id, $minha), $user->id);
    reservasBaselineReserva($outro->id, EstoqueFixture::locationId($outro->id, '-RES-'.uniqid()),
        reservasBaselineCliente($outro->id, $user->id, $alheia), $user->id);

    $clientes = reservasBaselineCalendario($this);

    expect($clientes)->toContain($minha);
    expect($clientes)->not->toContain($alheia);
});

test('sem crud_all_bookings e sem crud_own_bookings a rota devolve 403', function () {
    reservasBaselineEntrar($this, $this->usuarioComPermissoes([], $this->business));

    $this->get('/bookings')->assertForbidden();
});

test('com só crud_own_bookings o calendário traz só as reservas que o usuário criou', function () {
    $proprio = $this->usuarioComPermissoes(['crud_own_bookings'], $this->business);
    $colega = $this->usuarioComPermissoes(['crud_all_bookings'], $this->business);
    $criou = 'Criou '.uniqid();
    $atende = 'Atende '.uniqid();
    reservasBaselineReserva($this->business->id, $this->local, reservasBaselineCliente($this->business->id, $proprio->id, $criou), $proprio->id);
    reservasBaselineReserva($this->business->id, $this->local, reservasBaselineCliente($this->business->id, $colega->id, $atende), $colega->id, $proprio->id);

    reservasBaselineEntrar($this, $proprio);
    $clientes = reservasBaselineCalendario($this);

    expect($clientes)->toContain($criou);
    // Atendente da reserva do colega, e mesmo assim fora: o filtro do calendário fica preso em created_by (RUNBOOK §10).
    expect($clientes)->not->toContain($atende);
});

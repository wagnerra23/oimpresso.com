<?php

declare(strict_types=1);
// Cobre UC-RESV-01, UC-RESV-02, UC-RESV-03, UC-RESV-04 (Restaurante/Reservas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Thread restaurante/playbook/03, F3 — /bookings em Inertia atrás da flag useV2RestauranteReservas.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * A Blade está travada em ReservasBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('bookings') || ! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['crud_all_bookings', 'access_all_locations'], $this->business);
    $this->local = EstoqueFixture::locationId($this->business->id, '-RES-'.uniqid());
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    reservasContratoEntrar($this, $this->user);
});

function reservasContratoEntrar($teste, \App\User $user): void
{
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
}

function reservasContratoReserva(int $businessId, int $local, int $criador, string $cliente, array $extra = []): int
{
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => $cliente, 'mobile' => '',
        'created_by' => $criador, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('bookings')->insertGetId($extra + [
        'business_id' => $businessId, 'location_id' => $local, 'contact_id' => $contato,
        'booking_start' => now()->addDay()->setTime(10, 0), 'booking_end' => now()->addDay()->setTime(11, 0),
        'created_by' => $criador, 'booking_status' => 'booked', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Prop deferida `reservas`, pedida como o browser pede (X-Inertia + X-Requested-With). */
function reservasContratoLista($teste): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Restaurante/Reservas/Index', 'X-Inertia-Partial-Data' => 'reservas',
    ])->get('/bookings');
    $r->assertOk();

    return collect($r->json('props.reservas') ?? [])->keyBy('id');
}

function reservasFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2RestauranteReservas' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-RESV-01 com a flag ligada, GET /bookings renderiza Inertia (não o JSON do calendário)', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteReservas']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/bookings');

    $r->assertOk();
    expect($r->json('component'))->toBe('Restaurante/Reservas/Index');
});

test('UC-RESV-02 com a flag desligada, ou com ?classico=1, GET /bookings segue na Blade', function () {
    reservasFlagDesligada();
    $this->get('/bookings')->assertOk()->assertViewIs('restaurant.booking.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2RestauranteReservas']);
    $this->get('/bookings?classico=1')->assertOk()->assertViewIs('restaurant.booking.index');
});

test('UC-RESV-03 Tier 0 — só as reservas do meu negócio, deste mês em diante, com a situação', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteReservas']);
    $outro = $this->seededSupportClientTenant();
    $atendente = \App\User::factory()->create(['business_id' => $this->business->id, 'first_name' => 'Larissa', 'surname' => null, 'last_name' => 'Prado']);
    $mesa = DB::table('res_tables')->insertGetId([
        'business_id' => $this->business->id, 'location_id' => $this->local, 'name' => 'Mesa '.uniqid(),
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $minha = reservasContratoReserva($this->business->id, $this->local, $this->user->id, 'Minha '.uniqid(),
        ['table_id' => $mesa, 'waiter_id' => $atendente->id, 'booking_status' => 'waiting']);
    $antiga = reservasContratoReserva($this->business->id, $this->local, $this->user->id, 'Antiga '.uniqid(), [
        'booking_start' => now()->startOfMonth()->subDays(3), 'booking_end' => now()->startOfMonth()->subDays(3)->addHour(),
    ]);
    $alheia = reservasContratoReserva($outro->id, EstoqueFixture::locationId($outro->id, '-RES-'.uniqid()), $this->user->id, 'Alheia '.uniqid());

    $lista = reservasContratoLista($this);

    expect($lista->has($alheia))->toBeFalse();
    expect($lista->has($antiga))->toBeFalse();
    expect($lista[$minha]['situacao'])->toBe('waiting');
    expect($lista[$minha]['atendente'])->toBe('Larissa Prado');
    expect($lista[$minha]['mesa'])->toBe(DB::table('res_tables')->where('id', $mesa)->value('name'));
    expect($lista[$minha]['local_id'])->toBe($this->local);
});

test('UC-RESV-04 Tier 0 — com só crud_own_bookings, só as reservas que o usuário criou', function () {
    config(['feature-flags.forced_on' => 'useV2RestauranteReservas']);
    $proprio = $this->usuarioComPermissoes(['crud_own_bookings', 'access_all_locations'], $this->business);
    $criou = reservasContratoReserva($this->business->id, $this->local, $proprio->id, 'Criou '.uniqid());
    $doColega = reservasContratoReserva($this->business->id, $this->local, $this->user->id, 'Colega '.uniqid(),
        ['waiter_id' => $proprio->id]);

    reservasContratoEntrar($this, $proprio);
    $lista = reservasContratoLista($this);

    expect($lista->has($criou))->toBeTrue();
    expect($lista->has($doColega))->toBeFalse();
});

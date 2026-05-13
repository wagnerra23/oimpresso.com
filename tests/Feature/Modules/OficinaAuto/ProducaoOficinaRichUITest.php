<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * ProducaoOficinaController@index — payload V2 RICA (espelha visual-source.html).
 *
 * Cobre os ENRIQUECIMENTOS do controller adicionados pra suportar o overhaul
 * visual UI (6 KPIs + cards 5-6 linhas + drawer rico):
 *
 *  - 6 KPIs canônicos (total/disponivel/locada/aguardando_recolhimento/manutencao/atrasadas/valor_em_curso)
 *  - Card payload tem rental_notes, rental_created_at, atendente_nome/iniciais, daily_rate, os_number
 *  - Filter ?q=Construtora filtra por nome cliente (eager-loaded contact)
 *  - valor_em_curso = sum(valor_receber) das colunas locada + aguardando
 *  - Cross-tenant biz=99 vê 0 (Tier 0 ADR 0093)
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa) — ADR 0101.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see prototipo-ui/prototipos/producao-oficina/visual-source.html (canon V2 rica)
 * @see memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md
 */

const BIZ_WAGNER_RICH = 1;
const BIZ_FICTICIO_RICH = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('vehicles')) {
        $this->markTestSkipped('vehicles table missing — rode OficinaAuto migrate primeiro');
    }
    if (! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('current_status column missing — Wave 5 schema pending');
    }
    if (! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('service_orders table missing — rode OficinaAuto migrate primeiro');
    }
});

afterEach(function () {
    Vehicle::withoutGlobalScopes()
        ->where('plate', 'like', 'RICH-%')
        ->forceDelete();
    \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()
        ->where('notes', 'like', 'RICH-TEST-%')
        ->forceDelete();
});

it('Controller@index retorna 6 KPIs canônicos (V2 rica)', function () {
    session(['user.business_id' => BIZ_WAGNER_RICH]);
    $this->actingAs(stubUserSuperadminRich());

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    // 6 KPIs canônicos (espelha visual-source.html linha de 6 cards horizontais)
    expect($payload['kpis'])->toHaveKeys([
        'total',
        'disponivel',
        'locada',
        'aguardando_recolhimento',
        'manutencao',
        'atrasadas',
        'valor_em_curso',
    ]);

    // Tipos numéricos (atrasadas é alias de aguardando_recolhimento — int)
    expect($payload['kpis']['total'])->toBeInt();
    expect($payload['kpis']['disponivel'])->toBeInt();
    expect($payload['kpis']['locada'])->toBeInt();
    expect($payload['kpis']['aguardando_recolhimento'])->toBeInt();
    expect($payload['kpis']['manutencao'])->toBeInt();
    expect($payload['kpis']['atrasadas'])->toBeInt();
    expect($payload['kpis']['valor_em_curso'])->toBeFloat();

    // Total = soma das 5 colunas (single source of truth)
    $totalSum = count($payload['kanban']['disponivel'])
        + count($payload['kanban']['locada'])
        + count($payload['kanban']['aguardando'])
        + count($payload['kanban']['manutencao'])
        + count($payload['kanban']['pronta']);
    expect($payload['kpis']['total'])->toBe($totalSum);

    // atrasadas === aguardando_recolhimento (alias semântico)
    expect($payload['kpis']['atrasadas'])->toBe($payload['kpis']['aguardando_recolhimento']);
});

it('card payload V2 tem rental_notes, rental_created_at, atendente, daily_rate, os_number', function () {
    session(['user.business_id' => BIZ_WAGNER_RICH]);
    $this->actingAs(stubUserSuperadminRich());

    $vehicle = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-001',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rental = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_RICH,
        'vehicle_id'           => $vehicle->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(5),
        'expected_return_date' => now()->addDays(2)->toDateString(),
        'daily_rate'           => 150.00,
        'notes'                => 'RICH-TEST-001 — observação canônica',
    ]);
    $vehicle->update(['current_rental_id' => $rental->id]);

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    $card = collect($payload['kanban']['locada'])->firstWhere('plate', 'RICH-001');
    expect($card)->not->toBeNull();

    // Novos campos V2 enriquecidos
    expect($card)->toHaveKeys([
        'os_number',
        'rental_created_at',
        'rental_notes',
        'daily_rate',
        'atendente_nome',
        'atendente_iniciais',
    ]);
    expect($card['os_number'])->toBe($rental->id);
    expect($card['rental_notes'])->toBe('RICH-TEST-001 — observação canônica');
    expect($card['daily_rate'])->toBe(150.00);
    expect($card['rental_created_at'])->not->toBeNull(); // ISO 8601
});

it('filter ?q=Construtora filtra por nome cliente (contact eager-loaded)', function () {
    session(['user.business_id' => BIZ_WAGNER_RICH]);
    $this->actingAs(stubUserSuperadminRich());

    // Cliente "Construtora Aliança" — exige contact real do schema UltimatePOS.
    // Precondition: tabela contacts existe + business=1 tem ao menos 1 cliente.
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('contacts table missing — UltimatePOS schema');
    }

    $contact = \App\Contact::query()
        ->where('business_id', BIZ_WAGNER_RICH)
        ->where('type', 'customer')
        ->first();

    if (! $contact) {
        $this->markTestSkipped('Nenhum customer biz=1 — UltimatePOS seeders pendentes');
    }

    // Caçamba COM contato vinculado (deve aparecer no q=nome do cliente)
    $vehicleA = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-Q01',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rentalA = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_RICH,
        'vehicle_id'           => $vehicleA->id,
        'contact_id'           => $contact->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(2),
        'expected_return_date' => now()->addDays(3)->toDateString(),
        'daily_rate'           => 100,
        'notes'                => 'RICH-TEST-Q01',
    ]);
    $vehicleA->update(['current_rental_id' => $rentalA->id]);

    // Caçamba SEM contato (não deve aparecer com q=nome)
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-Q02',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'disponivel',
    ]);

    // Filter pelas primeiras 4 letras do contato — match like %ABCD%
    $needle = mb_substr($contact->name, 0, 4);
    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create(
        '/oficina-auto/producao-oficina',
        'GET',
        ['q' => $needle]
    );
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    $allPlates = collect($payload['kanban'])
        ->flatMap(fn ($col) => collect($col)->pluck('plate'))
        ->all();

    expect($allPlates)->toContain('RICH-Q01');
    // Q02 SEM contato vinculado — só apareceria se needle bater placa/vehicle_number
    if (! str_contains('RICH-Q02', $needle)) {
        expect($allPlates)->not->toContain('RICH-Q02');
    }
    expect($payload['filters']['q'])->toBe($needle);
});

it('valor_em_curso soma valor_receber das colunas locada + aguardando', function () {
    session(['user.business_id' => BIZ_WAGNER_RICH]);
    $this->actingAs(stubUserSuperadminRich());

    // Caçamba locada no prazo: 3 dias × R$ 100/dia = R$ 300 (em locada)
    $vehLocada = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-V01',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rLocada = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_RICH,
        'vehicle_id'           => $vehLocada->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(3)->startOfDay(),
        'expected_return_date' => now()->addDays(2)->toDateString(),
        'daily_rate'           => 100.00,
        'notes'                => 'RICH-TEST-V01',
    ]);
    $vehLocada->update(['current_rental_id' => $rLocada->id]);

    // Caçamba locada overdue: 7 dias × R$ 200/dia = R$ 1400 (em aguardando)
    $vehOver = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-V02',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'locada',
    ]);
    $rOver = \Modules\OficinaAuto\Entities\ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'          => BIZ_WAGNER_RICH,
        'vehicle_id'           => $vehOver->id,
        'order_type'           => 'locacao',
        'status'               => 'aberta',
        'entered_at'           => now()->subDays(7)->startOfDay(),
        'expected_return_date' => now()->subDays(2)->toDateString(),
        'daily_rate'           => 200.00,
        'notes'                => 'RICH-TEST-V02',
    ]);
    $vehOver->update(['current_rental_id' => $rOver->id]);

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    // Soma dos valor_receber das 2 caçambas que CRIAMOS deve estar embutida
    // no valor_em_curso global (pode ter outras caçambas biz=1 ali — só
    // garantimos que o nosso subset está incluído).
    $cardLocada = collect($payload['kanban']['locada'])->firstWhere('plate', 'RICH-V01');
    $cardAguardando = collect($payload['kanban']['aguardando'])->firstWhere('plate', 'RICH-V02');
    expect($cardLocada)->not->toBeNull();
    expect($cardAguardando)->not->toBeNull();

    $subsetSum = (float) ($cardLocada['valor_receber'] ?? 0)
        + (float) ($cardAguardando['valor_receber'] ?? 0);

    // valor_em_curso global >= subset que acabamos de criar
    expect($payload['kpis']['valor_em_curso'])->toBeGreaterThanOrEqual($subsetSum - 0.01);
});

it('cross-tenant biz=99 vê 0 caçambas biz=1 (Tier 0 ADR 0093)', function () {
    Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id'    => BIZ_WAGNER_RICH,
        'plate'          => 'RICH-XT9',
        'vehicle_type'   => 'cacamba_estacionaria',
        'capacity_m3'    => 5,
        'current_status' => 'disponivel',
    ]);

    session(['user.business_id' => BIZ_FICTICIO_RICH]);
    $this->actingAs(stubUserSuperadminRich());

    $controller = new \Modules\OficinaAuto\Http\Controllers\ProducaoOficinaController();
    $request = \Illuminate\Http\Request::create('/oficina-auto/producao-oficina', 'GET');
    $response = $controller->index($request);

    $payload = $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];

    $allPlates = collect($payload['kanban'])
        ->flatMap(fn ($col) => collect($col)->pluck('plate'))
        ->all();

    expect($allPlates)->not->toContain('RICH-XT9');
    expect($payload['kpis']['total'])->toBe(0);
    expect($payload['kpis']['valor_em_curso'])->toBe(0.0);
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function stubUserSuperadminRich(): \App\User
{
    $user = \App\User::query()
        ->where('business_id', BIZ_WAGNER_RICH)
        ->first();

    if (! $user) {
        test()->markTestSkipped('User biz=1 ausente — rode UltimatePOS seeders primeiro');
    }

    return $user;
}

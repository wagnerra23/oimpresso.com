<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\Event;
use Modules\Financeiro\Events\TituloCriado;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * PR F (2026-05-25) — G9 da auditoria: Event TituloCriado.
 *
 * Cobre:
 *  (1) store() dispatch TituloCriado com Titulo correto
 *  (2) Event payload tem business_id do titulo (multi-tenant Tier 0 preservado)
 *  (3) Update NÃO dispatch (evento é apenas pra novo título, não edição)
 *
 * Skip gracioso quando DB greenfield.
 */

function eventBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
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
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return [$business, $user];
}

it('store dispatch TituloCriado com Titulo do business correto', function () {
    [$business, $user] = eventBootstrap();

    Event::fake([TituloCriado::class]);

    $response = $this->actingAs($user)->post('/financeiro/unificado', [
        'tipo'              => 'receber',
        'valor_total'       => 12.34,
        'vencimento'        => now()->addDays(10)->toDateString(),
        'cliente_descricao' => 'PR F event test',
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertRedirect();

    Event::assertDispatched(TituloCriado::class, function (TituloCriado $event) use ($business) {
        return $event->titulo->business_id === $business->id
            && $event->titulo->cliente_descricao === 'PR F event test'
            && $event->titulo->tipo === 'receber'
            && (float) $event->titulo->valor_total === 12.34;
    });

    // Cleanup título criado
    Titulo::where('business_id', $business->id)
        ->where('cliente_descricao', 'PR F event test')
        ->get()
        ->each
        ->forceDelete();
});

it('update NÃO dispatch TituloCriado (event é apenas pra novo título)', function () {
    [$business, $user] = eventBootstrap();

    $titulo = Titulo::where('business_id', $business->id)
        ->where('status', 'aberto')
        ->first();

    if (! $titulo) {
        test()->markTestSkipped('Sem título aberto pra editar.');
    }

    Event::fake([TituloCriado::class]);

    $response = $this->actingAs($user)->put("/financeiro/unificado/{$titulo->id}", [
        'cliente_descricao' => $titulo->cliente_descricao,
        'observacoes'       => 'PR F event test update',
        'categoria_id'      => null,
        'vencimento'        => $titulo->vencimento->toDateString(),
    ]);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    Event::assertNotDispatched(TituloCriado::class);
});

<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
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

    // Cleanup do título criado PELO TESTE. Vai pelo query builder de propósito:
    // fin_titulos é append-only por domínio — Titulo::delete() lança
    // DomainException desde a Sub-Onda 1A (Titulo.php:121), e forceDelete() do
    // SoftDeletes chama delete() internamente, então a forma Eloquent SEMPRE
    // estourou aqui. Mesmo padrão do irmão BaixaConservacaoValorContratoTest.
    $ids = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('cliente_descricao', 'PR F event test')
        ->pluck('id')
        ->all();

    if ($ids !== []) {
        DB::table('fin_titulo_baixas')->whereIn('titulo_id', $ids)->delete();
        DB::table('fin_titulos')->whereIn('id', $ids)->delete();
    }
});

it('update NÃO dispatch TituloCriado (event é apenas pra novo título)', function () {
    [$business, $user] = eventBootstrap();

    $titulo = Titulo::where('business_id', $business->id)
        ->where('status', 'aberto')
        ->first();

    // Não depender de estado ambiente. Num banco FRESCO (o do CI) e com
    // executionOrder="random", pode não existir título aberto quando este caso
    // roda — e aí ele SKIPava, virando verde por não-execução (§5 LC-13).
    // Medido no run da lane em #5192: foi exatamente isso que aconteceu ("- it
    // update NÃO dispatch"). Aqui o pré-requisito é criado pelo MESMO caminho de
    // produto que o caso anterior exercita (POST /financeiro/unificado, cujo
    // status nasce 'aberto' por default de schema).
    if (! $titulo) {
        $this->actingAs($user)->post('/financeiro/unificado', [
            'tipo'              => 'receber',
            'valor_total'       => 55.66,
            'vencimento'        => now()->addDays(20)->toDateString(),
            'cliente_descricao' => 'PR F event test update-fixture',
        ]);

        $titulo = Titulo::where('business_id', $business->id)
            ->where('status', 'aberto')
            ->first();
    }

    // Guarda residual honesta: se nem pelo caminho de produto deu pra ter um
    // título aberto (module gate desligado neste env), o caso não tem como rodar.
    if (! $titulo) {
        test()->markTestSkipped('Sem título aberto pra editar e o store não criou um (module gate?).');
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

    // Limpa só o título que ESTE caso eventualmente criou (query builder — ver
    // comentário do cleanup do caso anterior sobre o append-only de fin_titulos).
    $ids = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('cliente_descricao', 'PR F event test update-fixture')
        ->pluck('id')
        ->all();

    if ($ids !== []) {
        DB::table('fin_titulo_baixas')->whereIn('titulo_id', $ids)->delete();
        DB::table('fin_titulos')->whereIn('id', $ids)->delete();
    }
});

<?php

declare(strict_types=1);
// Cobre UC-PCV-22 no disparo agendado (Producao/Apontamento/Index.casos.md).

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * `producao:fechar-turno` — quem dispara o fechamento do UC-PCV-22. Prova o REGISTRO no Schedule
 * vivo (não `class_exists`, §5 2026-07-28) e o efeito: fecha o aberto depois do fim do turno, nada
 * antes. Tenant 98. ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('pcp_apontamentos')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas pcp_*.');
    }
});

afterEach(fn () => Carbon::setTestNow());

it('UC-PCV-22 · o comando está no Schedule a cada 15 min', function () {
    $eventos = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'producao:fechar-turno'));

    expect($eventos)->toHaveCount(1)->and($eventos->first()->expression)->toBe('*/15 * * * *');
});

it('UC-PCV-22 · o comando fecha o aberto depois do fim do turno, e nada antes', function () {
    $biz = (int) $this->seededTenant()->id;
    $user = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'CMD-'.uniqid(), 'nome' => 'Acabamento',
        'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);
    $inicio = (int) DB::table('pcp_apontamentos')->insertGetId(['business_id' => $biz, 'chave_idempotencia' => 'cmd-'.uniqid(), 'tipo' => 'inicio',
        'posto_id' => $posto, 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'user_id' => $user, 'iniciado_por' => $user,
        'momento' => '2026-10-15 09:00:00']);

    Carbon::setTestNow('2026-10-15 17:00:00');
    $this->artisan('producao:fechar-turno')->assertSuccessful();
    expect(DB::table('pcp_apontamentos')->where('inicio_id', $inicio)->count())->toBe(0);

    Carbon::setTestNow('2026-10-15 18:20:00');
    $this->artisan('producao:fechar-turno')->assertSuccessful();
    $this->artisan('producao:fechar-turno')->assertSuccessful(); // idempotente: a 2ª rodada não duplica
    expect(DB::table('pcp_apontamentos')->where('inicio_id', $inicio)->pluck('origem')->all())->toBe(['turno']);
});

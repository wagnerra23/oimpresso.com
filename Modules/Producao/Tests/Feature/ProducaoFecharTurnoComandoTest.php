<?php

declare(strict_types=1);
// Cobre UC-PCV-22 no disparo agendado e UC-PCROB-01..02 (Producao/Apontamento/Index.casos.md).

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Modules\Producao\Jobs\CriarOsDaVendaJob;
use Modules\Producao\Services\ApontamentoOperadorService;

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

// ── Auditoria lote 2.3 · robustez do comando e do job ──

it('UC-PCROB-01 · a trava da sobreposição vence em 30 min, não em 24 h', function () {
    $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'producao:fechar-turno'));

    expect($evento->withoutOverlapping)->toBeTrue()->and($evento->expiresAt)->toBe(30);
});

it('UC-PCROB-01 · falha de uma empresa não para as outras, e o comando termina com falha', function () {
    $b98 = (int) $this->seededTenant()->id;
    $b99 = (int) $this->seededSupportClientTenant()->id;
    $abrir = function (int $biz): int {
        $user = (int) DB::table('users')->where('business_id', $biz)->value('id');
        $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'ROB-'.uniqid(), 'nome' => 'Acabamento',
            'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('pcp_apontamentos')->insertGetId(['business_id' => $biz, 'chave_idempotencia' => 'rob-'.uniqid(), 'tipo' => 'inicio',
            'posto_id' => $posto, 'tipo_custo' => 'pessoa', 'custo_hora' => '30.00', 'user_id' => $user, 'iniciado_por' => $user,
            'momento' => '2026-10-15 09:00:00']);
    };
    $aberto98 = $abrir($b98);
    $aberto99 = $abrir($b99);
    // A 98 falha de propósito; as outras empresas seguem o serviço real (só este método é usado pelo comando).
    $real = app(ApontamentoOperadorService::class);
    $this->app->instance(ApontamentoOperadorService::class, new class($real, $b98) extends ApontamentoOperadorService {
        public function __construct(private readonly ApontamentoOperadorService $realSvc, private readonly int $falha) {}

        public function fecharAbertosNoFimDoTurno(int $businessId, \Carbon\CarbonInterface $agora): int
        {
            return $businessId === $this->falha ? throw new RuntimeException('falha de teste') : $this->realSvc->fecharAbertosNoFimDoTurno($businessId, $agora);
        }
    });
    Log::spy();

    Carbon::setTestNow('2026-10-15 18:20:00');
    $this->artisan('producao:fechar-turno')->assertFailed();

    expect(DB::table('pcp_apontamentos')->where('inicio_id', $aberto99)->pluck('origem')->all())->toBe(['turno']);
    expect(DB::table('pcp_apontamentos')->where('inicio_id', $aberto98)->count())->toBe(0);
    Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'fechar-turno') && $ctx['business_id'] === $b98)->once();
});

it('UC-PCROB-02 · o job da OS espera entre as tentativas e a falha final fica no log de erro', function () {
    $job = new CriarOsDaVendaJob(98, 990077);
    expect($job->tries)->toBe(3)->and($job->backoff)->toBe([30, 120]);

    Log::spy();
    $job->failed(new RuntimeException('falha de teste'));
    Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx = []) => str_contains($msg, 'sem OS')
        && $ctx['business_id'] === 98 && $ctx['transaction_id'] === 990077)->once();
});

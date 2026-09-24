<?php

declare(strict_types=1);

use App\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Providers\EssentialsServiceProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM · a presença web CEDE ao Ponto — thread 09 do playbook SINCRONIZAR Hrm.
 *
 * Contrato: ADR 0014, emenda 2026-09-05 / 2026-09-24 (D1 [W]: o Ponto é dono único da jornada)
 * + a aposentadoria das 5 chaves de presença das Configurações ([W] 2026-09-24).
 *
 * Cada caso cita o que a emenda promete, e nenhum deriva do código (§5 2026-06-05):
 *   - as rotas de attendance viram 301 para o destino no Ponto (tabela da emenda);
 *   - a API do Connector responde 410 nos 3 endpoints de presença;
 *   - o cron `pos:autoClockOutUser` não é mais agendado, NEM em `live` (o caso força o
 *     env, porque em `testing` o agendamento antigo já não rodava e o teste passaria à toa);
 *   - as Configurações não entregam nem gravam mais as 5 chaves.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant de teste 98, nunca biz=4. DatabaseTransactions.
 *
 * @group hrm
 */
const HPC_BIZ = 98;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (lane essentials-pest).');
    }
    foreach (['business', 'users'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente.");
        }
    }
    $user = User::where('business_id', HPC_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 — o seed canônico (pest-mysql-setup) não rodou.');
    }
    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.HPC_BIZ, 'guard_name' => 'web'],
        ['business_id' => HPC_BIZ]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush();
    $this->actingAs($user);
    $this->hpcUser = $user;
});

it('UC-HRM-PRES-01: as rotas de presença do HRM viram 301 para o destino no Ponto', function () {
    $casos = [
        ['get', '/hrm/attendance', '/ponto/espelho'],
        ['get', '/hrm/attendance/123/edit', '/ponto/espelho'],
        ['get', '/hrm/get-attendance-by-date', '/ponto/espelho'],
        ['get', '/hrm/user-attendance-summary', '/ponto/espelho'],
        ['post', '/hrm/clock-in-clock-out', '/ponto'],
        ['post', '/hrm/import-attendance', '/ponto/importacoes'],
    ];
    foreach ($casos as [$verbo, $de, $para]) {
        $this->{$verbo}($de)->assertStatus(301)->assertRedirect($para);
    }
});

it('UC-HRM-PRES-02: nada entra em essentials_attendances pelo caminho HTTP antigo', function () {
    if (! Schema::hasTable('essentials_attendances')) {
        $this->markTestSkipped('Tabela essentials_attendances ausente.');
    }
    $antes = DB::table('essentials_attendances')->where('business_id', HPC_BIZ)->count();

    $this->post('/hrm/clock-in-clock-out', ['type' => 'clock_in', 'clock_in_note' => 'HPC'])
        ->assertStatus(301);

    expect(DB::table('essentials_attendances')->where('business_id', HPC_BIZ)->count())->toBe($antes);
});

it('UC-HRM-PRES-03: a API do Connector responde 410 nos 3 endpoints de presença', function () {
    foreach ([['GET', '/connector/api/get-attendance/1'], ['POST', '/connector/api/clock-in'], ['POST', '/connector/api/clock-out']] as [$verbo, $uri]) {
        $rota = app('router')->getRoutes()->match(Request::create($uri, $verbo));
        expect($rota->getActionMethod())->toBe('cedidoAoPonto');
    }
    // Controle negativo: o endpoint de feriados, que não é presença, continua no método dele.
    $feriados = app('router')->getRoutes()->match(Request::create('/connector/api/holidays', 'GET'));
    expect($feriados->getActionMethod())->toBe('getHolidays');

    $resposta = app(\Modules\Connector\Http\Controllers\Api\AttendanceController::class)->cedidoAoPonto();
    expect($resposta->getStatusCode())->toBe(410)
        ->and($resposta->getData(true)['destino'])->toBe('/ponto');
});

it('UC-HRM-PRES-04: pos:autoClockOutUser não é agendado nem em app.env=live', function () {
    config(['app.env' => 'live']);
    // Com o app já em pé, `booted()` executa o callback na hora — é o caminho que agendava.
    (new EssentialsServiceProvider(app()))->registerScheduleCommands();

    $agendados = collect(app(Schedule::class)->events())
        ->map(fn ($e) => (string) $e->command)
        ->filter(fn ($c) => str_contains($c, 'pos:autoClockOutUser'));

    expect($agendados)->toBeEmpty();
});

it('UC-HRM-PRES-05: as Configurações não entregam nem gravam as 5 chaves de presença', function () {
    $aposentadas = ['grace_before_checkin', 'grace_after_checkin', 'grace_before_checkout', 'grace_after_checkout', 'is_location_required'];

    $resposta = $this->post('/hrm/settings', [
        'leave_ref_no_prefix' => 'HPC',
        'grace_before_checkin' => '15',
        'is_location_required' => true,
        'calculate_sales_target_commission_without_tax' => false,
    ]);
    if ($resposta->getStatusCode() === 403) {
        $this->markTestSkipped('biz=98 sem assinatura essentials_module no seed — o gate barrou antes do contrato.');
    }
    $resposta->assertRedirect();

    $gravado = json_decode((string) DB::table('business')->where('id', HPC_BIZ)->value('essentials_settings'), true);
    // Controle positivo: a chave que continua viva foi gravada — prova que o save aconteceu.
    expect($gravado['leave_ref_no_prefix'] ?? null)->toBe('HPC');
    foreach ($aposentadas as $chave) {
        expect(array_key_exists($chave, $gravado))->toBeFalse();
    }

    $props = $this->get('/hrm/settings')->assertOk()->viewData('page')['props']['settings'];
    foreach ($aposentadas as $chave) {
        expect(array_key_exists($chave, $props))->toBeFalse();
    }
});

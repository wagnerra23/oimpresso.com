<?php

declare(strict_types=1);

use App\Jobs\RunBackupJob;
use App\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * ONDA 2 da migracao do Backup — geracao fora da requisicao.
 *
 * Roda NO CT 100 contra o MySQL real, NUNCA local nem sqlite (proibicoes.md secao Ambiente).
 *
 * O caso que da nome a este arquivo NAO e "despachou o job" — e "o job despachado tem
 * QUEM O EXECUTE". Medido em app/Console/Kernel.php (2026-08-19): quem drena a fila
 * `default` esta atras de `config('queue.backlog_worker_enabled')`, default FALSE. Um
 * backup despachado pra `default` ficaria parado na tabela `jobs` sem ninguem reclamar —
 * a tela diria "backup na fila" e o backup nunca aconteceria.
 *
 * Por isso o par de casos aqui e:
 *   1. o job vai pra fila `backups` (nao `default`);
 *   2. existe comando agendado drenando `backups` — perguntado ao REGISTRY do scheduler,
 *      nao por grep no arquivo (o disco diz que o texto existe; o registry diz que o
 *      comando esta registrado).
 *
 * @see app/Jobs/RunBackupJob.php
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasTable('users')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode no CT 100 (MySQL real). NAO sqlite.');
    }

    Permission::firstOrCreate(['name' => 'backup', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->biz = $this->seededTenant();                 // biz=98 ficticio (ADR 0358)

    $this->admin = User::factory()->create(['business_id' => $this->biz->id]);
    $this->admin->givePermissionTo('backup');

    $this->actingAs($this->admin);
    session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id]);
});

test('gerar backup despacha o job em vez de rodar na requisicao', function () {
    Queue::fake();

    $this->from('/backup')->get('/backup/create')->assertRedirect('/backup');

    Queue::assertPushed(RunBackupJob::class);
});

test('POST /backup tambem despacha o job', function () {
    Queue::fake();

    $this->from('/backup')->post('/backup')->assertRedirect('/backup');

    Queue::assertPushed(RunBackupJob::class);
});

test('o job vai para a fila backups, nunca para a default', function () {
    Queue::fake();

    $this->from('/backup')->get('/backup/create');

    Queue::assertPushed(RunBackupJob::class, function (RunBackupJob $job) {
        return $job->queue === 'backups';
    });
});

test('existe worker agendado drenando a fila backups', function () {
    $comandos = collect(app(Schedule::class)->events())
        ->map(fn ($evento) => (string) $evento->command);

    $drenam = $comandos->filter(
        fn (string $c) => str_contains($c, 'queue:work') && str_contains($c, '--queue=backups')
    );

    expect($drenam)->not->toBeEmpty(
        'Sem worker agendado pra fila `backups`, o job fica parado na tabela jobs e o '
        .'backup nunca roda — a tela mentiria dizendo "backup na fila".'
    );
});

test('o job nao repete: um backup pela metade e pior que nenhum', function () {
    $job = new RunBackupJob(1);

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBeGreaterThanOrEqual(1800);
});

test('em demo nao despacha job nenhum', function () {
    config(['app.env' => 'demo']);
    Queue::fake();

    $this->from('/backup')->get('/backup/create');
    $this->from('/backup')->post('/backup');

    Queue::assertNothingPushed();
});

test('sem a permissao backup nao despacha job', function () {
    $semPermissao = User::factory()->create(['business_id' => $this->biz->id]);
    $this->actingAs($semPermissao);
    Queue::fake();

    $this->get('/backup/create')->assertForbidden();

    Queue::assertNothingPushed();
});

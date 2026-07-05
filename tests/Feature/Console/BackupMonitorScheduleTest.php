<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/**
 * Onda 3 Ops/DR (AUDITORIA-OPS-DR-2026-07) — catraca de frescor do backup.
 *
 * `backup:run` cria o backup diário (01:30), mas nada VERIFICAVA se ele existe
 * e está fresco. spatie tem `monitor_backups` em config/backup.php + a notificação
 * UnhealthyBackupWasFoundNotification via mail — só que `backup:monitor` nunca era
 * agendado, então a morte silenciosa do backup passava batido (o mesmo modo de
 * falha observado no baileys-auth backup, quebrado 6+ dias sem alarme).
 *
 * Esta é a catraca (morde no CI): se alguém remover o schedule OU afrouxar o
 * limiar de idade (MaximumAgeInDays > 1), o teste quebra.
 *
 * Nota: NÃO declarar `uses(Tests\TestCase::class)` aqui — `tests/Pest.php` já
 * registra TestCase pra toda a pasta `tests/Feature/` (ver
 * ArquivosHealthCheckScheduleTest para o mesmo cuidado).
 */

it('agenda backup:monitor daily 09:00 BRT', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'backup:monitor'));

    expect($events)->not->toBeEmpty('schedule deveria ter backup:monitor registrado — sem ele o backup pode morrer em silêncio');
    expect($events->first()->expression)->toBe('0 9 * * *', 'deveria rodar às 09:00 todo dia (cron 0 9 * * *)');
    expect($events->first()->timezone)->toBe('America/Sao_Paulo', 'timezone deve ser BRT');
});

it('backup:monitor tem withoutOverlapping e só roda em live', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'backup:monitor'));

    expect($event)->not->toBeNull();
    expect($event->withoutOverlapping)->toBeTrue('withoutOverlapping evita concorrência com backup:run/cleanup');

    $ref = new \ReflectionClass($event);
    if ($ref->hasProperty('environments')) {
        $prop = $ref->getProperty('environments');
        $prop->setAccessible(true);
        $envs = $prop->getValue($event);

        expect($envs)->toContain('live', 'monitor só faz sentido em produção (onde backup:run roda)');
        expect($envs)->not->toContain('testing', 'não deve rodar em testing (evita ruído no CI)');
    }
});

it('config de monitor exige backup com no máximo 1 dia de idade', function () {
    // O limiar de frescor é o coração da catraca: RPO = 24h. Afrouxar isto
    // (ex: MaximumAgeInDays = 7) reabriria a janela de morte silenciosa.
    $monitor = config('backup.monitor_backups');

    expect($monitor)->toBeArray()->not->toBeEmpty('config backup.monitor_backups deve existir');

    $healthChecks = $monitor[0]['health_checks'] ?? [];
    $maxAge = $healthChecks[\Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class] ?? null;

    expect($maxAge)->not->toBeNull('deve haver health_check MaximumAgeInDays configurado');
    expect($maxAge)->toBeLessThanOrEqual(1, 'backup não pode passar de 1 dia sem alarme (RPO 24h)');
});

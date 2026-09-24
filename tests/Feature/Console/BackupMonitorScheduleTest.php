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

        // FALHA AQUI SIGNIFICA: monitor só faz sentido em produção (onde backup:run roda)
        expect($envs)->toContain('live');
        // ⚠️ ERRATA — a versão anterior deste comentário dizia que na forma NEGADA o
        // defeito era "benigno". É FALSO, e ficou 40 minutos no main: eu apliquei De
        // Morgan a uma API que não o implementa. Era
        // `->not->toContain('testing', '<mensagem>')`, e o mecanismo é:
        //   Mixins/Expectation.php:184        `toContain` ITERA os needles e asserta cada
        //                                      um -> `toContain($a,$msg)` SEMPRE lança,
        //                                      porque a msg nunca está no haystack;
        //   OppositeExpectation.php:770-784   `not->` roda o positivo num try e PASSA
        //                                      exatamente quando ele lança.
        // Composto: o assert passava SEMPRE, com ou sem 'testing'. Não era fraco — estava
        // MORTO. `toBeFalse(string $message)` não é variádico, então a mensagem tem lugar.
        expect(in_array('testing', $envs, true))->toBeFalse('não deve rodar em testing (evita ruído no CI)');
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

/**
 * Incidente 2026-09-22 — o veredito do backup não pode depender do SMTP.
 *
 * Mecanismo (lido no vendor, não inferido): Tasks/Backup/BackupJob.php:357 dispara
 * `event(new BackupWasSuccessful(...))` DENTRO do `try` de Commands/BackupCommand.php
 * (try na 56, `return SUCCESS` na 103). Se o canal levantar exceção, o `catch` da 104
 * devolve `FAILURE` na 125 — um backup BEM-SUCEDIDO vira exit 1.
 *
 * Foi o que aconteceu por 3 meses: 694 falhas `535` (auth SMTP) desde 2026-06-21,
 * todas às 01h e 09h, enquanto o zip do dia estava em disco com o MySQL dentro. O
 * alarme noturno era sobre e-mail e passava por "backup falhou".
 */
it('notificação de SUCESSO não tem canal — SMTP fora do ar não pode reprovar backup bom', function () {
    $canais = config('backup.notifications.notifications');

    $sucesso = [
        \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class,
        \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class,
        \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class,
    ];

    foreach ($sucesso as $notificacao) {
        expect($canais)->toHaveKey($notificacao);
        expect($canais[$notificacao])->toBe(
            [],
            "{$notificacao} não pode ter canal: ela roda dentro do try do BackupCommand, "
            .'então qualquer canal que falhe (SMTP fora do ar) converte backup bem-sucedido em exit 1'
        );
    }
});

it('notificação de FALHA continua indo por mail — o alarme volta sozinho quando a credencial for corrigida', function () {
    // O contraponto do teste acima. Zerar TODOS os canais calaria o alarme real:
    // aqui o backup falhar de verdade tem que continuar tentando avisar.
    $canais = config('backup.notifications.notifications');

    $falha = [
        \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class,
        \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class,
        \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class,
    ];

    foreach ($falha as $notificacao) {
        expect($canais)->toHaveKey($notificacao);

        // ⚠️ `toContain` do Pest é VARIÁDICO: o 2º argumento vira outro needle, não
        // mensagem. Escrever `toContain('mail', "explicação")` faz o assert exigir que
        // o array contenha a própria frase — e ele reprova sempre. É a lápide §5
        // 2026-07-28 + emenda 2026-09-05; caí nela neste mesmo arquivo e o CI pegou.
        // `toBeTrue(string $message)` NÃO é variádico, então a mensagem tem lugar próprio.
        expect(in_array('mail', $canais[$notificacao], true))->toBeTrue(
            "{$notificacao} precisa manter um canal de alarme — sem ele a falha REAL de backup fica muda"
        );
    }
});

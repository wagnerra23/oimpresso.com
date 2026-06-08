<?php

declare(strict_types=1);

/**
 * Pest — Slack notify em `sells:smoke-daily` failure (gap Onda 6).
 *
 * Valida estruturalmente:
 *  - Notification class SellsSmokeFailedNotification existe + assina contrato
 *  - SmokeDailyCommand importa Notification + dispatcher condicional
 *  - config/services.php declara slack.smoke_webhook_url
 *  - Try/catch graceful (sem webhook = no-op; Slack down = warning, NÃO fail)
 *  - Payload Slack Block Kit canônico (header + section + context)
 *  - HTTP timeout 5s (cron 06:30 BRT não pode segurar)
 *
 * Cobertura estrutural pura (mesmo pattern SellsOnda6SmokeTest):
 * Pest SQLite in-memory não cobre HTTP real. Smoke prod valida via
 * Wagner forçando falha + observando Slack — fora do escopo Pest.
 *
 * Refs:
 *  - app/Notifications/SellsSmokeFailedNotification.php
 *  - app/Console/Commands/Sells/SmokeDailyCommand.php (dispatchSlackNotify)
 *  - config/services.php (bloco slack)
 *  - Gap catalogado em "NÃO INCLUI" do PR #1044 (Onda 6 R6 Smoke)
 */

const SLACK_NOTIFICATION_PATH = 'app/Notifications/SellsSmokeFailedNotification.php';
const SLACK_CMD_PATH = 'app/Console/Commands/Sells/SmokeDailyCommand.php';
const SLACK_SERVICES_PATH = 'config/services.php';

function slackRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Notification class ─────────────────────────────────────────────

it('Notification SellsSmokeFailedNotification.php existe', function () {
    expect(file_exists(base_path(SLACK_NOTIFICATION_PATH)))->toBeTrue();
});

it('Notification estende Illuminate\Notifications\Notification', function () {
    $source = slackRead(SLACK_NOTIFICATION_PATH);
    expect($source)
        ->toContain('namespace App\\Notifications;')
        ->toContain('use Illuminate\\Notifications\\Notification;')
        ->toContain('class SellsSmokeFailedNotification extends Notification');
});

it('Notification recebe array $failures no construtor', function () {
    $source = slackRead(SLACK_NOTIFICATION_PATH);
    expect($source)
        ->toContain('public function __construct(public array $failures)');
});

it('Notification expõe método toSlackPayload(): array', function () {
    $source = slackRead(SLACK_NOTIFICATION_PATH);
    expect($source)
        ->toContain('public function toSlackPayload(): array');
});

it('Notification gera payload Block Kit (header + section + context)', function () {
    $source = slackRead(SLACK_NOTIFICATION_PATH);
    expect($source)
        ->toContain("'type' => 'header'")
        ->toContain("'type' => 'section'")
        ->toContain("'type' => 'context'")
        ->toContain("'type' => 'mrkdwn'")
        ->toContain('sells:smoke-daily FALHOU');
});

it('Notification payload é instanciável e retorna array com keys canônicas', function () {
    $notif = new \App\Notifications\SellsSmokeFailedNotification([
        'tenancy: biz=4 ZERO vendas 30d',
        'manifest: chunks Cowork ausentes — SaleSheet',
    ]);

    $payload = $notif->toSlackPayload();

    expect($payload)
        ->toBeArray()
        ->toHaveKeys(['text', 'blocks']);

    expect($payload['text'])->toContain('sells:smoke-daily FALHOU');
    expect($payload['text'])->toContain('2 check');

    expect($payload['blocks'])->toBeArray()->toHaveCount(4);
    expect($payload['blocks'][0]['type'])->toBe('header');
    expect($payload['blocks'][1]['type'])->toBe('section');
    expect($payload['blocks'][1]['text']['text'])->toContain('tenancy: biz=4 ZERO vendas 30d');
    expect($payload['blocks'][1]['text']['text'])->toContain('manifest: chunks Cowork ausentes');
});

// ─── Comando dispatcher ─────────────────────────────────────────────

it('SmokeDailyCommand importa Notification + Http facade', function () {
    $source = slackRead(SLACK_CMD_PATH);
    expect($source)
        ->toContain('use App\\Notifications\\SellsSmokeFailedNotification;')
        ->toContain('use Illuminate\\Support\\Facades\\Http;');
});

it('SmokeDailyCommand chama dispatchSlackNotify() dentro do bloco --notify', function () {
    $source = slackRead(SLACK_CMD_PATH);
    expect($source)
        ->toContain("if (\$this->option('notify'))")
        ->toContain('$this->dispatchSlackNotify();');
});

it('dispatchSlackNotify() lê config services.slack.smoke_webhook_url', function () {
    $source = slackRead(SLACK_CMD_PATH);
    expect($source)
        ->toContain('protected function dispatchSlackNotify(): void')
        ->toContain("config('services.slack.smoke_webhook_url')")
        ->toContain('if (empty($slackUrl))');
});

it('dispatchSlackNotify() usa try/catch graceful (Slack down NÃO derruba)', function () {
    $source = slackRead(SLACK_CMD_PATH);
    expect($source)
        ->toContain('try {')
        ->toContain('} catch (\\Throwable $e) {')
        ->toContain("Log::channel('single')->warning(");
});

it('dispatchSlackNotify() usa timeout 5s (cron 06:30 BRT)', function () {
    $source = slackRead(SLACK_CMD_PATH);
    expect($source)
        ->toContain('Http::timeout(5)->post($slackUrl, $payload)');
});

// ─── Config services.php ────────────────────────────────────────────

it('config/services.php declara bloco slack.smoke_webhook_url', function () {
    $source = slackRead(SLACK_SERVICES_PATH);
    expect($source)
        ->toContain("'slack' => [")
        ->toContain("'smoke_webhook_url' => env('SLACK_SMOKE_WEBHOOK_URL')");
});

it('config services.slack.smoke_webhook_url default null (gracefulNo-op)', function () {
    // Limpa env temporariamente pra simular ausência da var.
    $original = $_ENV['SLACK_SMOKE_WEBHOOK_URL'] ?? null;
    unset($_ENV['SLACK_SMOKE_WEBHOOK_URL']);

    // Força reload do config sem env setado.
    config(['services.slack.smoke_webhook_url' => env('SLACK_SMOKE_WEBHOOK_URL')]);

    expect(config('services.slack.smoke_webhook_url'))->toBeNull();

    // Restaura
    if ($original !== null) {
        $_ENV['SLACK_SMOKE_WEBHOOK_URL'] = $original;
    }
});

// ─── Integration leve (Http fake) ───────────────────────────────────
//
// NOTA: NÃO rodamos $this->artisan('sells:smoke-daily') aqui porque
// SQLite in-memory (default Pest config) não tem schema MySQL real —
// o check checkMultiTenantScope() crasha em SELECT count(*) FROM transactions.
// Mesma constraint canônica do SellsOnda6SmokeTest.php (todo o teste é estrutural).
// Aqui invocamos dispatchSlackNotify() diretamente via Reflection pra
// validar comportamento isolado (sem schema dependency).

it('dispatchSlackNotify() faz POST ao webhook quando URL setada (Http::fake)', function () {
    \Illuminate\Support\Facades\Http::fake([
        'hooks.slack.com/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
    ]);

    config(['services.slack.smoke_webhook_url' => 'https://hooks.slack.com/services/T1/B2/secret']);

    $cmd = new \App\Console\Commands\Sells\SmokeDailyCommand;
    $cmd->setLaravel(app());

    // Popula failures via Reflection (property protected).
    $ref = new \ReflectionClass($cmd);
    $prop = $ref->getProperty('failures');
    $prop->setAccessible(true);
    $prop->setValue($cmd, ['tenancy: biz=4 ZERO vendas 30d (test)']);

    // Invoca dispatchSlackNotify() via Reflection (método protected).
    $method = $ref->getMethod('dispatchSlackNotify');
    $method->setAccessible(true);
    $method->invoke($cmd);

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return str_contains($request->url(), 'hooks.slack.com')
            && isset($request->data()['text'])
            && str_contains($request->data()['text'], 'sells:smoke-daily FALHOU');
    });
});

it('dispatchSlackNotify() é no-op silencioso quando URL vazia', function () {
    config(['services.slack.smoke_webhook_url' => null]);
    \Illuminate\Support\Facades\Http::fake();

    $cmd = new \App\Console\Commands\Sells\SmokeDailyCommand;
    $cmd->setLaravel(app());

    $ref = new \ReflectionClass($cmd);
    $prop = $ref->getProperty('failures');
    $prop->setAccessible(true);
    $prop->setValue($cmd, ['drift teste']);

    $method = $ref->getMethod('dispatchSlackNotify');
    $method->setAccessible(true);
    $method->invoke($cmd);

    // No-op = não dispara HTTP.
    \Illuminate\Support\Facades\Http::assertNothingSent();
});

it('dispatchSlackNotify() não derruba quando Slack responde HTTP 500', function () {
    \Illuminate\Support\Facades\Http::fake([
        'hooks.slack.com/*' => \Illuminate\Support\Facades\Http::response(['error' => 'server'], 500),
    ]);

    config(['services.slack.smoke_webhook_url' => 'https://hooks.slack.com/services/X/Y/Z']);

    $cmd = new \App\Console\Commands\Sells\SmokeDailyCommand;
    $cmd->setLaravel(app());

    $ref = new \ReflectionClass($cmd);
    $prop = $ref->getProperty('failures');
    $prop->setAccessible(true);
    $prop->setValue($cmd, ['teste 500']);

    $method = $ref->getMethod('dispatchSlackNotify');
    $method->setAccessible(true);

    // NÃO deve lançar exception — só logar warning.
    expect(fn () => $method->invoke($cmd))->not->toThrow(\Throwable::class);
});

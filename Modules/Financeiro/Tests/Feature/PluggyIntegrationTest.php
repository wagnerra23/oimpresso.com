<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Financeiro\Services\Integrations\PluggyBankSyncService;
use Modules\Financeiro\Services\Integrations\PluggyClient;

uses(Tests\TestCase::class);

/**
 * W27 — Pluggy integration smoke (Open Banking BR esqueleto).
 *
 * Cobre:
 *   - PluggyClient classe + métodos esperados
 *   - PluggyBankSyncService classe + idempotency key determinística
 *   - Webhook HMAC SHA-256 valida assinatura correta + rejeita errada
 *   - Mock mode (PLUGGY_FORCE_MOCK=true) NÃO chama HTTP real
 *   - HTTP fake: auth → listAccounts → listTransactions encadeados sem DB
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO toca DB real. Só smoke de unidade +
 * Http::fake encadeado. Sync com DB real fica pra teste integração futura
 * (PLUGGY_ENABLED=true + business real).
 *
 * @see Modules\Financeiro\Services\Integrations\PluggyClient
 * @see Modules\Financeiro\Services\Integrations\PluggyBankSyncService
 * @see config/financeiro.php
 */
describe('W27 Pluggy Integration smoke', function () {

    beforeEach(function () {
        // Garante baseline pluggy config independentemente do .env de dev
        config([
            'financeiro.pluggy.enabled'              => true,
            'financeiro.pluggy.client_id'            => 'test-client-id',
            'financeiro.pluggy.client_secret'        => 'test-client-secret',
            'financeiro.pluggy.base_url'             => 'https://api.pluggy.ai',
            'financeiro.pluggy.webhook_secret'       => 'whsec_test_abc123',
            'financeiro.pluggy.timeout'              => 5,
            'financeiro.pluggy.retry_times'          => 0,
            'financeiro.pluggy.api_key_cache_ttl_sec'=> 60,
            'financeiro.pluggy.force_mock'           => false,
        ]);
        Cache::flush();
    });

    it('PluggyClient existe com métodos esperados', function () {
        expect(class_exists(PluggyClient::class))->toBeTrue();

        $methods = collect((new ReflectionClass(PluggyClient::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')->toArray();

        expect($methods)->toContain('listAccounts');
        expect($methods)->toContain('listTransactions');
        expect($methods)->toContain('createConnectToken');
        expect($methods)->toContain('verifyWebhookSignature');
    });

    it('PluggyBankSyncService existe com sync + upsert + idempotency key', function () {
        expect(class_exists(PluggyBankSyncService::class))->toBeTrue();

        $methods = collect((new ReflectionClass(PluggyBankSyncService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')->toArray();

        expect($methods)->toContain('syncAccount');
        expect($methods)->toContain('upsertTransaction');
        expect($methods)->toContain('buildIdempotencyKey');
    });

    it('config/financeiro.php existe com pluggy.* chaves canônicas', function () {
        expect(file_exists(config_path('financeiro.php')))->toBeTrue();

        $cfg = require config_path('financeiro.php');
        expect($cfg)->toBeArray();
        expect($cfg)->toHaveKey('pluggy');
        expect($cfg['pluggy'])->toHaveKeys([
            'enabled', 'client_id', 'client_secret', 'base_url',
            'webhook_secret', 'timeout', 'force_mock',
        ]);
    });

    it('pluggy default enabled=false (Wagner habilita por business)', function () {
        // Lê o arquivo cru pra confirmar default — independente do override
        // do beforeEach (que liga pra outros testes do file).
        $cfg = require config_path('financeiro.php');
        $envBackup = getenv('PLUGGY_ENABLED');
        putenv('PLUGGY_ENABLED');  // limpa
        $reload = require config_path('financeiro.php');
        if ($envBackup !== false) {
            putenv("PLUGGY_ENABLED={$envBackup}");
        }
        expect($reload['pluggy']['enabled'])->toBeFalse();
    });

    it('idempotency_key determinístico mesmo tx_id → mesmo hash', function () {
        $service = new PluggyBankSyncService(new PluggyClient());

        $k1 = $service->buildIdempotencyKey('tx-abc-123');
        $k2 = $service->buildIdempotencyKey('tx-abc-123');
        $k3 = $service->buildIdempotencyKey('tx-xyz-999');

        expect($k1)->toBe($k2);                  // determinístico
        expect($k1)->not->toBe($k3);             // distintos pra ids diferentes
        expect($k1)->toStartWith('pluggy:');     // namespace claro
        expect(strlen($k1))->toBeLessThanOrEqual(191);  // cabe em INDEX MySQL
    });

    it('verifyWebhookSignature aceita HMAC SHA-256 válido + rejeita inválido', function () {
        $client = new PluggyClient();
        $body = '{"event":"item/updated","itemId":"abc"}';
        $secret = 'whsec_test_abc123';

        $validSig = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $invalidSig = 'sha256=' . hash_hmac('sha256', $body, 'wrong_secret');

        expect($client->verifyWebhookSignature($body, $validSig))->toBeTrue();
        expect($client->verifyWebhookSignature($body, $invalidSig))->toBeFalse();
        expect($client->verifyWebhookSignature($body, 'malformed'))->toBeFalse();
    });

    it('force_mock=true NÃO chama HTTP real (Pest seguro sem credenciais)', function () {
        config(['financeiro.pluggy.force_mock' => true]);
        Http::preventStrayRequests();  // qualquer HTTP real explode o teste

        $client = new PluggyClient();
        $accounts = $client->listAccounts('item-fake-123');

        expect($accounts)->toBeArray()->not->toBeEmpty();
        expect($accounts[0])->toHaveKey('id');
        expect($accounts[0]['itemId'])->toBe('item-fake-123');

        $token = $client->createConnectToken(99);
        expect($token)->toBeString()->toContain('mock')->toContain('biz99');

        $txs = $client->listTransactions(
            'acc-fake',
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-17'),
        );
        expect($txs)->toBeArray()->not->toBeEmpty();
        expect($txs[0])->toHaveKey('amount');
    });

    it('Http::fake encadeado: /auth → /accounts → /transactions', function () {
        Http::fake([
            'api.pluggy.ai/auth' => Http::response(['apiKey' => 'fake-api-key-2h'], 200),
            'api.pluggy.ai/accounts*' => Http::response([
                'results' => [[
                    'id'           => 'acc-real-1',
                    'type'         => 'BANK',
                    'name'         => 'Itaú PJ',
                    'balance'      => 5000.00,
                    'currencyCode' => 'BRL',
                ]],
            ], 200),
            'api.pluggy.ai/transactions*' => Http::response([
                'results' => [
                    [
                        'id' => 'tx-001', 'accountId' => 'acc-real-1',
                        'date' => '2026-05-10T12:00:00Z', 'description' => 'PIX recebido',
                        'amount' => 250.00, 'type' => 'CREDIT',
                    ],
                ],
            ], 200),
        ]);

        $client = new PluggyClient();

        $accounts = $client->listAccounts('item-real');
        expect($accounts)->toHaveCount(1);
        expect($accounts[0]['id'])->toBe('acc-real-1');

        $txs = $client->listTransactions('acc-real-1', Carbon::parse('2026-05-01'), Carbon::parse('2026-05-17'));
        expect($txs)->toHaveCount(1);
        expect((float) $txs[0]['amount'])->toBe(250.00);

        Http::assertSentCount(3);  // 1 auth + 1 accounts + 1 transactions
    });

    it('webhook_secret vazio → verifyWebhookSignature sempre retorna false (fail-secure)', function () {
        config(['financeiro.pluggy.webhook_secret' => '']);

        $client = new PluggyClient();
        $body = 'whatever';
        $sig = 'sha256=' . hash_hmac('sha256', $body, '');

        expect($client->verifyWebhookSignature($body, $sig))->toBeFalse();
    });

    it('PluggyClient.apiKey cache evita chamada /auth repetida', function () {
        Http::fake([
            'api.pluggy.ai/auth' => Http::response(['apiKey' => 'cached-key'], 200),
            'api.pluggy.ai/accounts*' => Http::response(['results' => []], 200),
        ]);

        $client = new PluggyClient();
        $client->listAccounts('item-a');
        $client->listAccounts('item-b');
        $client->listAccounts('item-c');

        // 1 auth + 3 accounts = 4 total (NÃO 6 — cache funcionando)
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/auth'));
    });

});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Webhook Pagar.me v5 pela rota HTTP: a assinatura decide se o evento entra.
 *
 * Antes deste arquivo só o parser do Pagar.me tinha teste (PagarmeDriverTest); o
 * controller que barra a requisição forjada (PagarmeWebhookController::validateSignature)
 * nunca tinha sido exercitado pela rota.
 *
 * Contrato (docblock do controller, "Fail-secure"): sem webhook_secret na credencial
 * ou com X-Hub-Signature-256 inválida → 401 e o evento NÃO é gravado.
 *
 * Controle positivo: o mesmo corpo com a assinatura certa devolve 200 e grava uma linha
 * em gateway_webhook_events com signature_valid = 1. Sem ele, um 401 em todos os casos
 * passaria pelos negativos (rota quebrada, credencial não achada etc.).
 *
 * Tenant fictício 98 (ADR 0358). O segredo é um marcador inerte, não credencial real.
 *
 * @see Modules/PaymentGateway/Http/Controllers/Webhooks/PagarmeWebhookController.php
 */

const PGWH_TENANT = 98;
const PGWH_SEGREDO = 'segredo-de-teste-inerte';

beforeEach(function () {
    if (! \App\Business::find(PGWH_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    $ambiente = null;
    foreach (['sandbox', 'production'] as $amb) {
        $existe = DB::table('payment_gateway_credentials')
            ->where('business_id', PGWH_TENANT)
            ->where('gateway_key', 'pagarme')
            ->where('ambiente', $amb)
            ->exists();
        if (! $existe) {
            $ambiente = $amb;
            break;
        }
    }
    if ($ambiente === null) {
        $this->markTestSkipped('Tenant 98 já tem credencial Pagar.me nos dois ambientes.');
    }

    $this->criarCredencial = fn (array $config) => PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => PGWH_TENANT,
        'gateway_key' => 'pagarme',
        'ambiente' => $ambiente,
        'ativo' => true,
        'nome_display' => 'CT Pagarme ' . uniqid(),
        'config_json' => $config,
        'health_status' => 'ok',
    ]);

    $this->corpo = json_encode([
        'id' => 'hook_teste_' . uniqid(),
        'type' => 'charge.paid',
        'data' => ['id' => 'ch_teste_' . uniqid(), 'status' => 'paid'],
    ]);

    $this->enviar = fn (?string $assinatura) => $this->call(
        'POST',
        '/paymentgateway/webhooks/pagarme/' . PGWH_TENANT,
        [],
        [],
        [],
        array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $assinatura,
        ], fn ($v) => $v !== null),
        $this->corpo,
    );

    $this->eventosPagarme = fn () => DB::table('gateway_webhook_events')
        ->where('business_id', PGWH_TENANT)
        ->where('gateway_key', 'pagarme')
        ->count();
});

it('controle positivo: assinatura correta devolve 200 e grava o evento com signature_valid', function () {
    ($this->criarCredencial)(['webhook_secret' => PGWH_SEGREDO]);
    $antes = ($this->eventosPagarme)();

    $resp = ($this->enviar)('sha256=' . hash_hmac('sha256', $this->corpo, PGWH_SEGREDO));

    $resp->assertOk()->assertJsonPath('ok', true);
    expect(($this->eventosPagarme)())->toBe($antes + 1);
    expect((int) DB::table('gateway_webhook_events')->where('id', $resp->json('webhook_row_id'))->value('signature_valid'))
        ->toBe(1);
});

it('assinatura inválida devolve 401 e não grava evento', function () {
    ($this->criarCredencial)(['webhook_secret' => PGWH_SEGREDO]);
    $antes = ($this->eventosPagarme)();

    ($this->enviar)('sha256=' . hash_hmac('sha256', $this->corpo, 'outro-segredo'))
        ->assertStatus(401)
        ->assertJsonPath('error', 'signature_invalid');

    expect(($this->eventosPagarme)())->toBe($antes);
});

it('sem cabeçalho de assinatura devolve 401 e não grava evento', function () {
    ($this->criarCredencial)(['webhook_secret' => PGWH_SEGREDO]);
    $antes = ($this->eventosPagarme)();

    ($this->enviar)(null)
        ->assertStatus(401)
        ->assertJsonPath('error', 'signature_invalid');

    expect(($this->eventosPagarme)())->toBe($antes);
});

it('credencial sem webhook_secret devolve 401 mesmo com assinatura calculada', function () {
    ($this->criarCredencial)([]);
    $antes = ($this->eventosPagarme)();

    ($this->enviar)('sha256=' . hash_hmac('sha256', $this->corpo, ''))
        ->assertStatus(401)
        ->assertJsonPath('error', 'signature_invalid');

    expect(($this->eventosPagarme)())->toBe($antes);
});

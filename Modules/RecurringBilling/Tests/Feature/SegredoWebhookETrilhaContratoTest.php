<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Subscription;

uses(Tests\TestCase::class);

/**
 * Auditoria RecurringBilling d4 (2026-11-19):
 *  - `config_json.webhook_secret` ficava em claro em rb_boleto_credentials;
 *  - mudança de valor/ciclo/forma (que vivem em `metadata`) não entrava no activity log da
 *    Subscription — só restava um Log::info em arquivo.
 *
 * SQLite in-memory (lane modules-pest). Tenant 98 (ADR 0358); nunca o 4.
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Rodado em SQLite in-memory (schema montado aqui).');
    }

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('banco', 20);
        $t->string('ambiente', 20)->default('production');
        $t->boolean('ativo')->default(true);
        $t->json('config_json');
        $t->string('nome_display')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->timestamps();
    });

    Schema::dropIfExists('rb_subscriptions');
    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('plan_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('status', 20)->default('active');
        $t->date('start_date')->nullable();
        $t->date('next_due_date')->nullable();
        $t->date('billing_anchor_date')->nullable();
        $t->dateTime('canceled_at')->nullable();
        $t->dateTime('paused_at')->nullable();
        $t->json('metadata')->nullable();
        $t->string('payment_method', 10)->nullable();
        $t->string('churn_reason', 64)->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    $this->migration = require base_path('Modules/RecurringBilling/Database/Migrations/2026_11_19_100000_cifra_webhook_secret_rb_boleto_credentials.php');
    $this->credencial = fn (array $config) => DB::table('rb_boleto_credentials')->insertGetId(['business_id' => 98,
        'banco' => 'asaas', 'ativo' => true, 'config_json' => json_encode($config), 'created_at' => now(), 'updated_at' => now()]);
    $this->config = fn (int $id) => json_decode((string) DB::table('rb_boleto_credentials')->where('id', $id)->value('config_json'), true);
});

it('d4 · a migration cifra o webhook_secret em claro, não cifra duas vezes e o down devolve o original', function () {
    $emClaro = ($this->credencial)(['webhook_secret' => 'segredo-legado-98', 'client_id' => 'cid']);
    $jaCifrado = ($this->credencial)(['webhook_secret' => Crypt::encryptString('segredo-novo-98')]);
    $semSegredo = ($this->credencial)(['client_id' => 'cid']);

    $this->migration->up();
    $this->migration->up(); // idempotente

    $c1 = ($this->config)($emClaro);
    expect($c1['webhook_secret'])->not->toBe('segredo-legado-98')
        ->and(Crypt::decryptString($c1['webhook_secret']))->toBe('segredo-legado-98')
        ->and($c1['client_id'])->toBe('cid')
        ->and(Crypt::decryptString(($this->config)($jaCifrado)['webhook_secret']))->toBe('segredo-novo-98')
        ->and(($this->config)($semSegredo))->toBe(['client_id' => 'cid']);

    $this->migration->down();
    expect(($this->config)($emClaro)['webhook_secret'])->toBe('segredo-legado-98');
});

// O caso 'o model e o resolver leem o segredo decifrado' saiu em 2026-11-24 com o BoletoCredential e o
// BoletoCredentialResolver (onda 5b). O webhook confere o segredo do PaymentGateway (AsaasWebhookAuthTest).

it('d4 · mudar valor ou ciclo da assinatura entra no activity log, sem o resto da metadata', function () {
    $sub = Subscription::create(['business_id' => 98, 'contact_id' => 501, 'status' => 'active',
        'start_date' => '2031-01-01', 'next_due_date' => '2031-04-05', 'billing_anchor_date' => '2031-04-05',
        'metadata' => ['valor' => 90.0, 'ciclo' => 'mensal', 'forma_pagamento' => 'boleto',
            'pix_automatico' => ['id_rec' => 'RN-TESTE-98']]]);

    $sub->update(['metadata' => array_merge($sub->metadata, ['valor' => 120.0, 'ciclo' => 'trimestral'])]);

    $log = DB::table('activity_log')->where('subject_id', $sub->id)->where('event', 'updated')->latest('id')->first();
    expect($log)->not->toBeNull();
    $props = json_decode((string) $log->properties, true);
    expect($props['attributes']['metadata']['valor'])->toEqual(120.0)
        ->and($props['old']['metadata']['valor'])->toEqual(90.0)
        ->and($props['attributes']['metadata']['ciclo'])->toBe('trimestral')
        ->and((string) $log->properties)->not->toContain('RN-TESTE-98');

    // Mexer só fora das chaves de cobrança não gera entrada.
    $antes = DB::table('activity_log')->where('subject_id', $sub->id)->count();
    $sub->update(['metadata' => array_merge($sub->fresh()->metadata, ['pix_automatico' => ['id_rec' => 'RN-OUTRO-98']])]);
    expect(DB::table('activity_log')->where('subject_id', $sub->id)->count())->toBe($antes);
});

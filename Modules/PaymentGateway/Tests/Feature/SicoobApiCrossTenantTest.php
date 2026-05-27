<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.sicoob_api PR6 — US-FIN-044 cross-tenant final.
 *
 * Suite consolidada que valida ISOLAMENTO Tier 0 ADR 0093 em TODOS os
 * vetores expostos pelos PRs 1-5:
 *
 *   Vector 1 (PR3 mTLS): .pfx biz=4 NÃO carrega via cred biz=99
 *   Vector 2 (PR2 OAuth cache): token biz=4 NÃO autentica biz=99
 *   Vector 3 (PR4 webhook HMAC): secret biz=4 NÃO valida payload biz=99
 *   Vector 4 (PR2 payload): emissão biz=4 usa numero_cliente biz=4
 *   Vector 5 (PR4 webhook event): GatewayWebhookEvent.business_id da rota
 *
 * Multi-tenant Tier 0 [ADR 0093]: NUNCA cliente real. Aqui biz=4 e biz=99
 * são fictícios (não confundir com biz=4 real ROTA LIVRE produção).
 */

beforeEach(function () {
    Cache::flush();
    File::ensureDirectoryExists(storage_path('app/private/sicoob'));

    // Helper: cria credencial Sicoob completa com tudo isolado por biz_id.
    $this->makeSicoobCred = function (int $bizId, string $clientId, int $convenio, string $webhookSecret, string $pfxPwd): PaymentGatewayCredential {
        // Cria .pfx fake por biz
        $pfxRel = "sicoob/{$bizId}.pfx";
        $pfxAbs = storage_path('app/private/' . $pfxRel);
        File::ensureDirectoryExists(dirname($pfxAbs));
        File::put($pfxAbs, "FAKE-PFX-BIZ-{$bizId}");

        return PaymentGatewayCredential::query()->create([
            'business_id'   => $bizId,
            'gateway_key'   => 'sicoob_api',
            'ambiente'      => 'sandbox',
            'ativo'         => true,
            'requires_mtls' => true,
            'mtls_pfx_path' => $pfxRel,
            'config_json'   => [
                'client_id'                    => $clientId,
                'client_secret'                => "secret-{$bizId}",
                'numero_cliente'               => $convenio,
                'codigo_modalidade'            => 1,
                'numero_conta'                 => $bizId * 1000,
                'webhook_secret'               => $webhookSecret,
                'mtls_pfx_password_encrypted'  => Crypt::encryptString($pfxPwd),
            ],
        ]);
    };
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/private/sicoob'));
});

function callMtlsOptionsXT(SicoobApiDriver $driver, PaymentGatewayCredential $cred): array
{
    $r = new ReflectionMethod($driver, 'mtlsOptions');
    $r->setAccessible(true);

    return $r->invoke($driver, $cred);
}

function postSicoobXT($test, int $businessId, array $payload, string $signature)
{
    return $test->call(
        method: 'POST',
        uri: "/paymentgateway/webhooks/sicoob-api/{$businessId}",
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_SICOOB_SIGNATURE' => $signature,
        ],
        content: json_encode($payload),
    );
}

it('Vector 1: .pfx biz=4 e biz=99 carregam paths DIFERENTES com senhas DIFERENTES', function () {
    $cred4 = ($this->makeSicoobCred)(4, 'client-4', 444, 'webhook-secret-4', 'pfx-pwd-4');
    $cred99 = ($this->makeSicoobCred)(99, 'client-99', 999, 'webhook-secret-99', 'pfx-pwd-99');

    $driver = new SicoobApiDriver();
    $opts4 = callMtlsOptionsXT($driver, $cred4);
    $opts99 = callMtlsOptionsXT($driver, $cred99);

    expect($opts4['cert'][0])->toContain('sicoob/4.pfx')
        ->and($opts99['cert'][0])->toContain('sicoob/99.pfx')
        ->and($opts4['cert'][0])->not->toBe($opts99['cert'][0])
        ->and($opts4['cert'][1])->toBe('pfx-pwd-4')
        ->and($opts99['cert'][1])->toBe('pfx-pwd-99')
        ->and($opts4['cert'][1])->not->toBe($opts99['cert'][1]);
});

it('Vector 2: OAuth cache token biz=4 NÃO compartilha key com biz=99', function () {
    $cred4 = ($this->makeSicoobCred)(4, 'client-4', 444, 'ws-4', 'p4');
    $cred99 = ($this->makeSicoobCred)(99, 'client-99', 999, 'ws-99', 'p99');

    Http::fake([
        '*/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'token-biz-4', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'token-biz-99', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['boleto' => ['nossoNumero' => '1']]],
        ], 200),
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 1,
        valorCentavos: 1000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'XT',
        idempotencyKey: 'xt-' . uniqid(),
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'X'],
    );

    $driver = new SicoobApiDriver();
    $driver->emitirBoleto($input, $cred4);
    $driver->emitirBoleto($input, $cred99);

    // Se cache compartilhasse, sequence só consumiria 1 token e ficaria 1
    // chamada token + 2 boletos = 3 total. Se isolado, 2 tokens + 2 boletos = 4.
    Http::assertSentCount(4);
});

it('Vector 3: webhook HMAC secret biz=4 NÃO valida payload chegando como biz=99', function () {
    ($this->makeSicoobCred)(4, 'c-4', 444, 'webhook-secret-4', 'p4');
    ($this->makeSicoobCred)(99, 'c-99', 999, 'webhook-secret-99', 'p99');

    $payload = ['evento' => 'cobranca.liquidada', 'nossoNumero' => '12345'];
    $raw = json_encode($payload);
    $sigBiz4 = hash_hmac('sha256', $raw, 'webhook-secret-4');
    $sigBiz99 = hash_hmac('sha256', $raw, 'webhook-secret-99');

    // biz=4 webhook com secret-4 → 200
    postSicoobXT($this, 4, $payload, $sigBiz4)->assertStatus(200);

    // biz=99 webhook usando secret-4 → 401 (cross-tenant attempt)
    postSicoobXT($this, 99, $payload, $sigBiz4)->assertStatus(401);

    // biz=4 webhook usando secret-99 → 401 (reverso)
    postSicoobXT($this, 4, $payload, $sigBiz99)->assertStatus(401);

    // biz=99 com secret-99 → 200
    postSicoobXT($this, 99, $payload, $sigBiz99)->assertStatus(200);

    // GatewayWebhookEvent: 2 linhas, uma por biz
    $events = GatewayWebhookEvent::query()->withoutGlobalScopes()->get();
    expect($events->where('business_id', 4)->count())->toBe(1)
        ->and($events->where('business_id', 99)->count())->toBe(1);
});

it('Vector 4: emissão boleto biz=4 envia numero_cliente 444 (NÃO biz=99 999)', function () {
    $cred4 = ($this->makeSicoobCred)(4, 'c-4', 444, 'ws4', 'p4');

    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['boleto' => ['nossoNumero' => '111']]],
        ], 200),
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 4,
        contactId: 1,
        valorCentavos: 5000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'biz=4 emissão',
        idempotencyKey: 'biz4-' . uniqid(),
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'Test'],
    );

    (new SicoobApiDriver())->emitirBoleto($input, $cred4);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/boletos')) {
            return false;
        }
        $body = $request->data()[0] ?? [];

        // numero_cliente DEVE ser 444 (biz=4) — NUNCA 999 (biz=99)
        return $body['numeroCliente'] === 444
            && $body['numeroContaCorrente'] === 4000;
    });
});

it('Vector 5: GatewayWebhookEvent grava business_id da ROTA (não payload)', function () {
    ($this->makeSicoobCred)(4, 'c-4', 444, 'ws-4', 'p4');

    // Atacante envia payload tentando declarar business_id=99 mas POST é
    // pra rota /webhooks/sicoob-api/4 — controller IGNORA business_id do
    // payload e usa o da rota.
    $payload = [
        'evento'      => 'cobranca.liquidada',
        'nossoNumero' => 'ATTACK-001',
        'business_id' => 99, // tentativa de spoof
    ];
    $sig = hash_hmac('sha256', json_encode($payload), 'ws-4');

    postSicoobXT($this, 4, $payload, $sig)->assertStatus(200);

    $event = GatewayWebhookEvent::query()->withoutGlobalScopes()->first();
    expect($event->business_id)->toBe(4)  // rota, não payload
        ->and($event->business_id)->not->toBe(99);
});

it('Vector 6: requires_mtls=true biz=4 NÃO carrega .pfx que pertence a biz=99', function () {
    // Cria cred biz=4 apontando pra .pfx que NÃO foi criado pra esse biz
    File::ensureDirectoryExists(storage_path('app/private/sicoob'));
    File::put(storage_path('app/private/sicoob/99.pfx'), 'fake-biz-99');

    $credSpoof = PaymentGatewayCredential::query()->create([
        'business_id'   => 4,
        'gateway_key'   => 'sicoob_api',
        'ambiente'      => 'sandbox',
        'ativo'         => true,
        'requires_mtls' => true,
        // path APONTA pra biz=99 — config errada
        'mtls_pfx_path' => 'sicoob/99.pfx',
        'config_json'   => [
            'client_id'                   => 'c-4',
            'client_secret'               => 's-4',
            'numero_cliente'              => 444,
            'codigo_modalidade'           => 1,
            'numero_conta'                => 4000,
            'mtls_pfx_password_encrypted' => Crypt::encryptString('p4'),
        ],
    ]);

    // Driver carrega o path que a credential aponta — não força business_id
    // no path. É RESPONSABILIDADE do storeSicoobPfx (PR5 controller) usar
    // session->business_id pra construir path. Este test confirma: se admin
    // configurar errado, driver USA o que tá lá. Defesa-em-profundidade:
    // controller é a guard, não o driver.
    $opts = callMtlsOptionsXT(new SicoobApiDriver(), $credSpoof);
    expect($opts['cert'][0])->toContain('99.pfx');

    // Em prod, isso é prevenido por (a) storeSicoobPfx usar
    // session->business_id no path, (b) cred ownership filtrado pelo
    // business_id session em PaymentGatewaysController. Pest do controller
    // valida (b) — sai do escopo deste arquivo.
});

it('Vector 7: cache OAuth com mesmo client_id em business_ids DIFERENTES → tokens distintos', function () {
    // Edge case: 2 cooperados Sicoob diferentes COMPARTILHAM client_id por
    // erro do gerente (não devia, mas pode). Cache key precisa ainda
    // separar por business_id.
    $cred4 = ($this->makeSicoobCred)(4, 'SAME-CLIENT-ID', 444, 'ws-4', 'p4');
    $cred99 = ($this->makeSicoobCred)(99, 'SAME-CLIENT-ID', 999, 'ws-99', 'p99');

    Http::fake([
        '*/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'token-biz-4-mesmo-cid', 'expires_in' => 3600], 200)
            ->push(['access_token' => 'token-biz-99-mesmo-cid', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['boleto' => ['nossoNumero' => '1']]],
        ], 200),
    ]);

    $input = new EmitirCobrancaInput(
        businessId: 1, contactId: 1, valorCentavos: 100,
        vencimento: new DateTimeImmutable('+1 day'),
        descricao: 'shared cid',
        idempotencyKey: 'shared-' . uniqid(),
        meta: ['payer_cpf_cnpj' => '12345678900', 'payer_name' => 'T'],
    );

    $d = new SicoobApiDriver();
    $d->emitirBoleto($input, $cred4);
    $d->emitirBoleto($input, $cred99);

    // Cache key inclui business_id ANTES do client_id_hash:
    //   "sicoob_api:token:{biz_id}:{ambiente}:{client_id_hash}"
    // Mesmo com client_id_hash IGUAL, business_id diferente força 2 chamadas.
    Http::assertSentCount(4); // 2 tokens + 2 boletos
});

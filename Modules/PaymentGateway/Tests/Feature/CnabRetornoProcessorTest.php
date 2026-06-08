<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\PaymentGateway\Events\CobrancaCancelada;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\PaymentGateway\Jobs\CnabRetornoProcessor;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\CnabRetornoUpload;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class);

/**
 * Schema in-memory per test (pattern canon WebhookEndpointsTest — não usa
 * RefreshDatabase pois migrations canônicas têm ALTER TABLE MODIFY ENUM
 * MySQL-only).
 */
function setupCnabRetornoSchema(): void
{
    if (! Schema::hasTable('payment_gateway_credentials')) {
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('gateway_key', 30)->index();
            $table->string('ambiente', 20)->default('production');
            $table->boolean('ativo')->default(true);
            $table->string('nome_display')->nullable();
            $table->json('config_json');
            $table->unsignedInteger('conta_bancaria_id')->nullable();
            $table->string('health_status', 20)->default('unknown');
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('cobrancas')) {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable();
            $table->string('gateway_external_id')->nullable()->index();
            $table->string('tipo', 20)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('valor_centavos');
            $table->unsignedInteger('valor_pago_centavos')->nullable();
            $table->date('vencimento');
            $table->timestamp('paga_em')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('payer_cpf_cnpj', 14)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            $table->text('descricao');
            $table->string('idempotency_key', 191);
            $table->string('origem_type', 30)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('linha_digitavel', 60)->nullable();
            $table->string('codigo_barras', 60)->nullable();
            $table->text('pix_emv')->nullable();
            $table->string('pix_qr_code_path')->nullable();
            $table->string('boleto_pdf_url')->nullable();
            $table->string('nosso_numero', 30)->nullable();
            $table->string('forma_pagamento', 20)->nullable();
            $table->json('payload_gateway')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'cob_biz_idem_uq');
        });
    }
    if (! Schema::hasTable('gateway_webhook_events')) {
        Schema::create('gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->nullable();
            $table->string('gateway_key', 30)->index();
            $table->string('evento', 60)->index();
            $table->string('gateway_event_id', 191);
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'gateway_key', 'gateway_event_id'], 'gw_wh_uq_cnab_test');
        });
    }
    if (! Schema::hasTable('cnab_retorno_uploads')) {
        Schema::create('cnab_retorno_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('payment_gateway_credential_id')->index();
            $table->string('arquivo_path');
            $table->string('arquivo_nome_original');
            $table->unsignedInteger('arquivo_tamanho_bytes')->default(0);
            $table->timestamp('processado_em')->nullable();
            $table->unsignedInteger('qtd_paga')->default(0);
            $table->unsignedInteger('qtd_cancelada')->default(0);
            $table->unsignedInteger('qtd_vencida')->default(0);
            $table->unsignedInteger('qtd_registrada')->default(0);
            $table->text('erros_json')->nullable();
            $table->unsignedBigInteger('processado_por_user_id')->nullable();
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
        });
    }
}

/**
 * Onda 4f.0 — ADR 0170 (fundação CNAB compartilhada).
 *
 * Job CnabRetornoProcessor: parse arquivo retorno → match Cobranca →
 * dispatch CobrancaPaga/Cancelada/Vencida + grava audit log + upload row.
 *
 * Strategy de fixtures:
 *   - Usa fixtures reais da lib eduardokum em lib-custom/laravel-boleto/exemplos/arquivos/
 *   - bradesco.ret tem 1 detalhe NN=000000000011 ocorrencia=02 (ENTRADA)
 *   - Gera fixture sintética em runtime modificando bytes 109-110 pra '06' (LIQUIDAÇÃO)
 *     ou '09' (BAIXA sem pagamento) — pattern documentado em lib readme.
 *
 * Multi-tenant Tier 0 (ADR 0093): testa isolamento por business_id.
 */

/** Caminho absoluto do fixture original Bradesco CNAB400. */
function bradescoRetFixturePath(): string
{
    return realpath(__DIR__ . '/../../../../lib-custom/laravel-boleto/exemplos/arquivos/bradesco.ret');
}

/**
 * Lê fixture e substitui o código de ocorrência (bytes 109-110 do detalhe, 1-indexed)
 * pra simular liquidação/baixa.
 */
function fakeCnabRetorno(string $codigoOcorrencia): string
{
    $fixture = file_get_contents(bradescoRetFixturePath());
    $linhas = preg_split('/\r\n|\r|\n/', $fixture);

    // linha 0 = header, linha 1 = detalhe, linha 2 = trailer
    if (! isset($linhas[1])) {
        throw new RuntimeException('fixture inesperado');
    }

    $detalhe = $linhas[1];
    // Substitui posições 109-110 (1-indexed) = índice 108-109
    $detalhe = substr_replace($detalhe, $codigoOcorrencia, 108, 2);
    $linhas[1] = $detalhe;

    return implode("\n", array_filter($linhas, fn ($l) => $l !== ''));
}

beforeEach(function () {
    setupCnabRetornoSchema();
    session(['business.id' => 1]);
    Storage::fake('local');
    Event::fake([CobrancaPaga::class, CobrancaCancelada::class, CobrancaVencida::class]);

    $this->cred = PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'bradesco_cnab',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Bradesco CNAB Test',
        'config_json'  => [
            'agencia'           => '1234',
            'conta'             => '567890',
            'carteira'          => '09',
            'cedente_nome'      => 'Empresa Teste',
            'cedente_documento' => '12345678000199',
        ],
    ]);

    // Pré-cria cobranca matching nosso_numero do fixture (=000000000011)
    $this->cobranca = Cobranca::query()->create([
        'business_id'                    => 1,
        'payment_gateway_credential_id'  => $this->cred->id,
        'gateway_external_id'            => '000000000011',
        'nosso_numero'                   => '000000000011',
        'tipo'                           => 'boleto',
        'status'                         => 'emitida',
        'valor_centavos'                 => 10000, // R$ [redacted Tier 0]
        'vencimento'                     => now()->subDays(5)->toDateString(), // vencida há 5d
        'descricao'                      => 'Cobrança teste CNAB',
        'idempotency_key'                => 'cnab-retorno-test-001',
        'origem_type'                    => 'sale',
        'origem_id'                      => 99001,
        'payer_cpf_cnpj'                 => '12345678900',
        'payer_name'                     => 'João Pagador',
    ]);
});

// ─── liquidação ──────────────────────────────────────────────────────────

it('dispatcha CobrancaPaga para títulos liquidados (ocorrência 06)', function () {
    $arquivo = fakeCnabRetorno('06'); // LIQUIDAÇÃO
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/file.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/file.ret',
        'arquivo_nome_original'         => 'bradesco_retorno.ret',
        'arquivo_tamanho_bytes'         => strlen($arquivo),
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
        disk: 'local',
    ))->handle();

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) {
        return $e->cobrancaId === $this->cobranca->id
            && $e->businessId === 1
            && $e->valorPagoCentavos === 10000
            && $e->formaPagamento === 'boleto';
    });

    $this->cobranca->refresh();
    expect($this->cobranca->status)->toBe('paga');
    expect($this->cobranca->paga_em)->not->toBeNull();
    expect($this->cobranca->valor_pago_centavos)->toBe(10000);
});

// ─── baixa sem pagamento → cancelamento ──────────────────────────────────

it('dispatcha CobrancaCancelada para baixa sem pagamento (ocorrência 09)', function () {
    $arquivo = fakeCnabRetorno('09'); // BAIXA AUTOM
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/baixa.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/baixa.ret',
        'arquivo_nome_original'         => 'baixa.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    Event::assertDispatched(CobrancaCancelada::class, function (CobrancaCancelada $e) {
        return $e->cobrancaId === $this->cobranca->id
            && $e->motivo === 'cnab_retorno_baixa';
    });

    $this->cobranca->refresh();
    expect($this->cobranca->status)->toBe('cancelada');
});

// ─── entrada confirmada vencida → CobrancaVencida ────────────────────────

it('dispatcha CobrancaVencida quando entrada (02) tem vencimento passado', function () {
    $arquivo = fakeCnabRetorno('02'); // ENTRADA — vencimento da cobranca foi há 5d
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/entrada.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/entrada.ret',
        'arquivo_nome_original'         => 'entrada.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    Event::assertDispatched(CobrancaVencida::class, function (CobrancaVencida $e) {
        return $e->cobrancaId === $this->cobranca->id
            && $e->diasVencido >= 4;
    });
});

// ─── idempotência ────────────────────────────────────────────────────────

it('é idempotente: quando paga_em já setado, não dispatcha CobrancaPaga novamente', function () {
    // Pré-marca cobranca como já paga
    $this->cobranca->update([
        'status'              => 'paga',
        'paga_em'             => now()->subDay(),
        'valor_pago_centavos' => 10000,
    ]);

    $arquivo = fakeCnabRetorno('06');
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/rerun.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/rerun.ret',
        'arquivo_nome_original'         => 'rerun.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    // Job ainda incrementa contador 'paga' (achou um detalhe LIQUIDADA),
    // mas NÃO dispatcha o evento (aplicarLiquidacao early-return).
    Event::assertNotDispatched(CobrancaPaga::class);
});

// ─── métricas no CnabRetornoUpload ───────────────────────────────────────

it('grava CnabRetornoUpload com métricas após processar', function () {
    $arquivo = fakeCnabRetorno('06');
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/metrics.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/metrics.ret',
        'arquivo_nome_original'         => 'metrics.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    $upload->refresh();
    expect($upload->processado_em)->not->toBeNull();
    expect($upload->qtd_paga)->toBe(1);
    expect($upload->qtd_cancelada)->toBe(0);
    expect($upload->qtd_registrada)->toBe(0);
    expect($upload->errosArray())->toBeArray();

    // GatewayWebhookEvent audit row criada
    $audit = GatewayWebhookEvent::query()
        ->where('business_id', 1)
        ->where('payment_gateway_credential_id', $this->cred->id)
        ->where('evento', 'cnab_retorno_upload')
        ->first();
    expect($audit)->not->toBeNull();
    expect($audit->signature_valid)->toBeTrue();
});

// ─── multi-tenant Tier 0 ─────────────────────────────────────────────────

it('respeita business_id global scope — não atualiza cobranca de outro business', function () {
    // Outra business com mesma nossoNumero — NUNCA pode ser tocada
    $cobrancaBiz2 = Cobranca::query()->create([
        'business_id'                    => 2,
        'payment_gateway_credential_id'  => null,
        'gateway_external_id'            => '000000000011',
        'nosso_numero'                   => '000000000011',
        'tipo'                           => 'boleto',
        'status'                         => 'emitida',
        'valor_centavos'                 => 99999,
        'vencimento'                     => now()->addDay()->toDateString(),
        'descricao'                      => 'Outra biz — não tocar',
        'idempotency_key'                => 'biz2-cnab-001',
    ]);

    $arquivo = fakeCnabRetorno('06');
    Storage::disk('local')->put('cnab-retornos/biz-1/cred-' . $this->cred->id . '/tier0.ret', $arquivo);

    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/tier0.ret',
        'arquivo_nome_original'         => 'tier0.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id, // credential da biz 1
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    // Cobranca biz 1 atualizada
    $this->cobranca->refresh();
    expect($this->cobranca->status)->toBe('paga');

    // Cobranca biz 2 INTOCADA — Tier 0 honrado
    $cobrancaBiz2->refresh();
    expect($cobrancaBiz2->status)->toBe('emitida');
    expect($cobrancaBiz2->paga_em)->toBeNull();
});

// ─── arquivo inexistente ─────────────────────────────────────────────────

it('não quebra quando arquivo de retorno não existe', function () {
    $upload = CnabRetornoUpload::query()->create([
        'business_id'                   => 1,
        'payment_gateway_credential_id' => $this->cred->id,
        'arquivo_path'                  => 'cnab-retornos/biz-1/cred-' . $this->cred->id . '/nao-existe.ret',
        'arquivo_nome_original'         => 'nao-existe.ret',
    ]);

    (new CnabRetornoProcessor(
        credentialId: $this->cred->id,
        arquivoRetornoPath: $upload->arquivo_path,
        uploadId: $upload->id,
    ))->handle();

    $upload->refresh();
    expect($upload->processado_em)->not->toBeNull();
    expect($upload->errosArray())->toContain('arquivo_inexistente:' . $upload->arquivo_path);
});

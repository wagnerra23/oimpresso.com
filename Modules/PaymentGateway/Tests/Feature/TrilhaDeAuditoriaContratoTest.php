<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\PaymentGateway\Models\CnabRetornoUpload;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;

/**
 * Trilha de auditoria do PaymentGateway (auditoria 2026-11-19, lote 3 item 3.4).
 *
 * 1. inter_webhook_log, gateway_webhook_events e cnab_retorno_uploads: o worker
 *    completa as colunas de processamento; o que chegou do banco/gateway não muda
 *    e nada é apagado pelo Eloquent.
 * 2. Excluir credencial com histórico desativa em vez de apagar (antes: 500 da FK
 *    iwl_cred_fk depois de já ter apagado os certificados).
 * 3. Driver Sicoob apaga o .pfx temporário ao fim de cada chamada.
 *
 * Tenant 98 (ADR 0358). Fica em Modules/PaymentGateway/Tests: roda na lane do módulo.
 */
uses(\Tests\TestCase::class, DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

const TRILHA_BIZ = 98;

function trilhaCredencial(string $gateway = 'inter', array $config = []): PaymentGatewayCredential
{
    foreach (['sandbox', 'production'] as $amb) {
        $existe = DB::table('payment_gateway_credentials')
            ->where('business_id', TRILHA_BIZ)->where('gateway_key', $gateway)->where('ambiente', $amb)->exists();
        if (! $existe) {
            return PaymentGatewayCredential::withoutGlobalScopes()->create([
                'business_id'  => TRILHA_BIZ,
                'gateway_key'  => $gateway,
                'ambiente'     => $amb,
                'ativo'        => true,
                'nome_display' => 'CT TRILHA ' . uniqid(),
                'config_json'  => array_merge(['client_id' => 'marcador-inerte', 'client_secret' => 'marcador-inerte'], $config),
            ]);
        }
    }

    throw new RuntimeException("Sem ambiente livre para {$gateway} no business " . TRILHA_BIZ);
}

function trilhaInterLog(PaymentGatewayCredential $cred): InterWebhookLog
{
    return InterWebhookLog::withoutGlobalScopes()->create([
        'business_id'                   => TRILHA_BIZ,
        'payment_gateway_credential_id' => $cred->id,
        'txid'                          => 'trilha' . uniqid(),
        'valor_centavos'                => 1000,
        'signature_valid'               => true,
        'status'                        => 'received',
        'payload'                       => ['txid' => 'original'],
    ]);
}

beforeEach(function () {
    if (! Schema::hasTable('inter_webhook_log') || ! \App\Business::find(TRILHA_BIZ)) {
        $this->markTestSkipped('Schema/tenant 98 ausentes — roda na lane MySQL do PaymentGateway.');
    }
});

it('inter_webhook_log: o worker atualiza status e processamento, mas o payload não muda e a linha não é apagada', function () {
    $log = trilhaInterLog(trilhaCredencial());

    $log->update(['status' => 'processed', 'processed_at' => now(), 'error_message' => null]);
    expect(DB::table('inter_webhook_log')->where('id', $log->id)->value('status'))->toBe('processed');

    expect(fn () => $log->fresh()->update(['payload' => ['txid' => 'adulterado']]))
        ->toThrow(DomainException::class, 'trilha de auditoria');
    expect(fn () => InterWebhookLog::withoutGlobalScopes()->whereKey($log->id)->update(['valor_centavos' => 1]))
        ->toThrow(DomainException::class);
    expect(fn () => $log->fresh()->delete())->toThrow(DomainException::class);
    expect(fn () => InterWebhookLog::withoutGlobalScopes()->whereKey($log->id)->delete())->toThrow(DomainException::class);

    $linha = DB::table('inter_webhook_log')->where('id', $log->id)->first();
    expect($linha)->not->toBeNull()
        ->and(json_decode($linha->payload, true))->toBe(['txid' => 'original'])
        ->and((int) $linha->valor_centavos)->toBe(1000);
});

it('gateway_webhook_events: processamento atualiza, payload e exclusão são recusados', function () {
    $cred = trilhaCredencial();
    $evento = GatewayWebhookEvent::withoutGlobalScopes()->create([
        'business_id'                   => TRILHA_BIZ,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_key'                   => 'inter',
        'evento'                        => 'teste.trilha',
        'gateway_event_id'              => 'trilha-' . uniqid(),
        'payload'                       => ['a' => 1],
        'signature_valid'               => true,
    ]);

    $evento->update(['processed_at' => now(), 'error_message' => 'ok', 'cobranca_id' => null]);
    expect(DB::table('gateway_webhook_events')->where('id', $evento->id)->value('error_message'))->toBe('ok');

    expect(fn () => $evento->fresh()->update(['evento' => 'outro']))->toThrow(DomainException::class);
    expect(fn () => $evento->fresh()->delete())->toThrow(DomainException::class);
    expect(DB::table('gateway_webhook_events')->where('id', $evento->id)->value('evento'))->toBe('teste.trilha');
});

it('cnab_retorno_uploads: contadores atualizam, arquivo e exclusão são recusados', function () {
    $cred = trilhaCredencial('bradesco_cnab');
    $upload = CnabRetornoUpload::withoutGlobalScopes()->create([
        'business_id'                   => TRILHA_BIZ,
        'payment_gateway_credential_id' => $cred->id,
        'arquivo_path'                  => 'cnab/trilha.ret',
        'arquivo_nome_original'         => 'trilha.ret',
        'arquivo_tamanho_bytes'         => 10,
    ]);

    $upload->update(['processado_em' => now(), 'qtd_paga' => 2]);
    expect((int) DB::table('cnab_retorno_uploads')->where('id', $upload->id)->value('qtd_paga'))->toBe(2);

    expect(fn () => $upload->fresh()->update(['arquivo_path' => 'cnab/outro.ret']))->toThrow(DomainException::class);
    expect(fn () => $upload->fresh()->delete())->toThrow(DomainException::class);
    expect(DB::table('cnab_retorno_uploads')->where('id', $upload->id)->value('arquivo_path'))->toBe('cnab/trilha.ret');
});

it('excluir credencial com log do Inter desativa em vez de apagar (sem 500)', function () {
    $cred = trilhaCredencial();
    trilhaInterLog($cred);
    $user = $this->usuarioComPermissoes(['business_settings.access'], \App\Business::findOrFail(TRILHA_BIZ));

    $this->actingAs($user)
        ->withSession(['user.business_id' => TRILHA_BIZ, 'business.id' => TRILHA_BIZ])
        ->deleteJson('/settings/payment-gateways/' . $cred->id)
        ->assertOk()
        ->assertJsonPath('desativada', true);

    $linha = DB::table('payment_gateway_credentials')->where('id', $cred->id)->first();
    expect($linha)->not->toBeNull()->and((bool) $linha->ativo)->toBeFalse();
    expect(DB::table('inter_webhook_log')->where('payment_gateway_credential_id', $cred->id)->count())->toBe(1);
});

it('excluir credencial SEM histórico continua apagando', function () {
    $cred = trilhaCredencial('c6');
    $user = $this->usuarioComPermissoes(['business_settings.access'], \App\Business::findOrFail(TRILHA_BIZ));

    $this->actingAs($user)
        ->withSession(['user.business_id' => TRILHA_BIZ, 'business.id' => TRILHA_BIZ])
        ->deleteJson('/settings/payment-gateways/' . $cred->id)
        ->assertOk()
        ->assertJsonMissingPath('desativada');

    expect(DB::table('payment_gateway_credentials')->where('id', $cred->id)->exists())->toBeFalse();
});

it('Sicoob: o .pfx temporário existe durante a chamada e é apagado ao fim dela', function () {
    Cache::flush();
    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'PFX-FALSO-DO-TESTE',
        'senha'      => 'senha-falsa',
        'valido_ate' => new DateTimeImmutable('+30 days'),
        'source'     => 'nfe_brasil',
    ]);
    app()->instance(CertificadoService::class, $stub);

    $cred = trilhaCredencial('sicoob_api', ['numero_cliente' => 123, 'codigo_modalidade' => 1]);
    $antes = glob(sys_get_temp_dir() . '/sicoob-pfx-*') ?: [];
    $durante = [];

    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/boletos*' => function () use (&$durante, $antes) {
            $durante = array_values(array_diff(glob(sys_get_temp_dir() . '/sicoob-pfx-*') ?: [], $antes));

            return Http::response(['resultado' => [['boleto' => ['situacaoBoleto' => 'EM_ABERTO']]]], 200);
        },
    ]);

    app(SicoobApiDriver::class)->consultar((object) ['gateway_external_id' => '123'], $cred);

    // Controle positivo: o arquivo existia enquanto a requisição rodava.
    expect($durante)->not->toBeEmpty();
    $depois = array_values(array_diff(glob(sys_get_temp_dir() . '/sicoob-pfx-*') ?: [], $antes));
    expect($depois)->toBe([]);
});

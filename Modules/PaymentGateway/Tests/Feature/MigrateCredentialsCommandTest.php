<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\CopiaCredencialLegadaRb;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Cópia `rb_boleto_credentials` → `payment_gateway_credentials` (ADR 0170 Onda 2.5 · decisão b1 de
 * 2026-11-23, auditoria RecurringBilling 2026-11-19). A regra é a `CopiaCredencialLegadaRb`, usada
 * pelo comando `paymentgateway:migrate-credentials` e pela migration de dados de 2026-11-23.
 *
 *   (1) dry-run não persiste; (2) apply cria com o mapeamento banco→gateway_key; (3) 2× apply não
 *   duplica; (4) `--business` restringe; (5) o segredo que o RB cifra campo a campo chega ao PG em
 *   claro dentro do `encrypted:array` (nunca o ciphertext do RB, nunca o valor em claro na coluna);
 *   (6) linha do PG que ficou com ciphertext do RB tem o campo decifrado; (7) a cópia NÃO liga a
 *   credencial a conta — contrato INVERTIDO em 2026-11-23: antes o comando gravava o
 *   `fin_contas_bancarias.id` do RB no campo do PG que é `accounts.id`, e credencial ligada à conta
 *   muda quem emite.
 *
 * Tenant 98, adversário 99 (ADR 0358). Nunca biz=4 nem biz=1.
 */

beforeEach(function () {
    if (! Schema::hasTable('rb_boleto_credentials')) {
        $this->markTestSkipped('rb_boleto_credentials ausente — RB não instalado neste env.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    foreach ([$this->b98, $this->b99] as $biz) {
        // SUPERADMIN: fixture — limpa as credenciais das duas empresas fictícias antes do caso.
        PaymentGatewayCredential::withoutGlobalScopes()->where('business_id', $biz)->delete();
        DB::table('rb_boleto_credentials')->where('business_id', $biz)->delete();
    }

    DB::table('rb_boleto_credentials')->insert([
        [
            'business_id' => $this->b98, 'banco' => 'inter', 'ambiente' => 'production', 'ativo' => true,
            'config_json' => json_encode(['client_id' => 'cid-98', 'client_secret' => Crypt::encryptString('segredo-inter-98')]),
            'nome_display' => 'Inter Prod', 'conta_bancaria_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'business_id' => $this->b98, 'banco' => 'asaas', 'ambiente' => 'sandbox', 'ativo' => false,
            'config_json' => json_encode(['api_key' => Crypt::encryptString('chave-asaas-98'), 'webhook_secret' => Crypt::encryptString('wh-98')]),
            'nome_display' => 'Asaas SB', 'conta_bancaria_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);
});

function pgCreds(int $biz): \Illuminate\Support\Collection
{
    // SUPERADMIN: leitura da fixture, filtrada pela empresa do caso.
    return PaymentGatewayCredential::withoutGlobalScopes()->where('business_id', $biz)->get();
}

it('DRY-RUN não persiste em payment_gateway_credentials', function () {
    $this->artisan('paymentgateway:migrate-credentials')->assertSuccessful();

    expect(pgCreds($this->b98))->toHaveCount(0);
});

it('APPLY cria com o mapeamento banco→gateway_key, ambiente e ativo do RB', function () {
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])->assertSuccessful();

    $rows = pgCreds($this->b98);
    expect($rows)->toHaveCount(2);
    $inter = $rows->firstWhere('gateway_key', 'inter');
    $asaas = $rows->firstWhere('gateway_key', 'asaas');
    expect($inter->ambiente)->toBe('production')
        ->and($inter->ativo)->toBeTrue()
        ->and($inter->config_json['client_id'])->toBe('cid-98')
        ->and($asaas->ambiente)->toBe('sandbox')
        ->and($asaas->ativo)->toBeFalse();
});

it('2x APPLY é idempotente — não duplica credenciais', function () {
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])->assertSuccessful();
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])->assertSuccessful();

    expect(pgCreds($this->b98))->toHaveCount(2);
});

it('--business restringe o escopo: a 99 não é tocada', function () {
    DB::table('rb_boleto_credentials')->insert([
        'business_id' => $this->b99, 'banco' => 'c6', 'ambiente' => 'production', 'ativo' => true,
        'config_json' => json_encode(['client_id' => 'c6-99']), 'nome_display' => 'C6 99',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('paymentgateway:migrate-credentials', ['--business' => $this->b98, '--apply' => true])->assertSuccessful();

    expect(pgCreds($this->b99))->toHaveCount(0)
        ->and(pgCreds($this->b98))->toHaveCount(2);
});

it('segredo cifrado campo a campo no RB chega ao PG em claro, e a coluna não guarda o valor em claro', function () {
    (new CopiaCredencialLegadaRb())->executar($this->b98, true);

    $inter = pgCreds($this->b98)->firstWhere('gateway_key', 'inter');
    $asaas = pgCreds($this->b98)->firstWhere('gateway_key', 'asaas');
    expect($inter->config_json['client_secret'])->toBe('segredo-inter-98')   // o driver do PG lê em claro
        ->and($asaas->config_json['api_key'])->toBe('chave-asaas-98')
        ->and($asaas->config_json['webhook_secret'])->toBe('wh-98');

    $bruto = (string) DB::table('payment_gateway_credentials')->where('id', $asaas->id)->value('config_json');
    expect($bruto)->not->toContain('chave-asaas-98')                          // cifrado em repouso
        ->and($bruto)->not->toContain('wh-98');
});

it('linha do PG com ciphertext do RB (execução antiga do comando) tem o campo decifrado; rodar de novo não muda', function () {
    $cifrado = Crypt::encryptString('chave-asaas-98');
    PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'gateway_key' => 'asaas', 'ambiente' => 'sandbox', 'ativo' => false,
        'nome_display' => 'Asaas SB', 'config_json' => ['api_key' => $cifrado, 'extra' => 'mantem'],
    ]);

    $r1 = collect((new CopiaCredencialLegadaRb())->executar($this->b98, true))->keyBy('gateway_key');
    $r2 = collect((new CopiaCredencialLegadaRb())->executar($this->b98, true))->keyBy('gateway_key');

    $asaas = pgCreds($this->b98)->firstWhere('gateway_key', 'asaas');
    expect($r1['asaas']['acao'])->toBe('decifrar_campos')
        ->and($r2['asaas']['acao'])->toBe('existe')
        ->and($asaas->config_json['api_key'])->toBe('chave-asaas-98')
        ->and($asaas->config_json['extra'])->toBe('mantem')        // não sobrescreve o resto
        ->and(pgCreds($this->b98)->where('gateway_key', 'asaas'))->toHaveCount(1);

    // o relatório nunca carrega segredo
    expect(json_encode($r1->all()))->not->toContain('chave-asaas-98')
        ->and(json_encode($r1->all()))->not->toContain($cifrado);
});

it('a cópia não liga a credencial a conta nem preenche fin_contas_bancarias (não muda quem emite)', function () {
    if (! Schema::hasTable('fin_contas_bancarias')) {
        $this->markTestSkipped('fin_contas_bancarias ausente — Financeiro não instalado neste env.');
    }

    $owner = (int) DB::table('business')->where('id', $this->b98)->value('owner_id');
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $this->b98, 'name' => 'Conta Teste', 'account_number' => '777',
        'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $contaId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $this->b98, 'account_id' => $accountId, 'banco_codigo' => '077',
        'agencia' => '0001', 'carteira' => '112',
        'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture de teste
        'beneficiario_razao_social' => 'Conta Teste',
        'created_at' => now(), 'updated_at' => now(), 'payment_gateway_credential_id' => null,
    ]);
    DB::table('rb_boleto_credentials')->where('business_id', $this->b98)->where('banco', 'inter')
        ->update(['conta_bancaria_id' => $contaId]);

    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])->assertSuccessful();

    $inter = pgCreds($this->b98)->firstWhere('gateway_key', 'inter');
    expect($inter->conta_bancaria_id)->toBeNull()
        ->and(DB::table('fin_contas_bancarias')->where('id', $contaId)->value('payment_gateway_credential_id'))->toBeNull();
});

it('modo da migration (sem reparar) cria o que falta e não grava na linha do PG que já existe', function () {
    $cifrado = Crypt::encryptString('chave-asaas-98');
    $existente = PaymentGatewayCredential::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'gateway_key' => 'asaas', 'ambiente' => 'sandbox', 'ativo' => false,
        'nome_display' => 'Asaas SB', 'config_json' => ['api_key' => $cifrado],
    ]);
    $antes = (string) DB::table('payment_gateway_credentials')->where('id', $existente->id)->value('config_json');

    $r = collect((new CopiaCredencialLegadaRb())->executar($this->b98, true, repararExistentes: false))->keyBy('gateway_key');

    expect($r['inter']['acao'])->toBe('criar')
        ->and($r['asaas']['acao'])->toBe('existe_com_campo_cifrado')
        ->and((string) DB::table('payment_gateway_credentials')->where('id', $existente->id)->value('config_json'))->toBe($antes)
        ->and(pgCreds($this->b98))->toHaveCount(2);
});

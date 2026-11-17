<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * nfse:health — sinais de certificado e de erro lendo as tabelas e status REAIS.
 *
 * Até 2026-11-16 o comando lia `nfse_certificados` (tabela inexistente: o cert mora em
 * `nfe_certificados`, com FK a partir de nfse_provider_configs.cert_id) e contava
 * status 'rejeitada' (fora do enum de nfse_emissoes). Os dois sinais nunca mudavam.
 *
 * Tenant 98 (ADR 0358) com adversário 99. Tudo dentro de transação revertida.
 * MySQL: o schema UltimatePOS (business, FKs) não existe no SQLite.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('nfse:health lê schema MySQL UltimatePOS (nfse_provider_configs → business FK)');
    }
    foreach (['nfse_provider_configs', 'nfe_certificados', 'nfse_emissoes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("{$t} ausente — rode as migrations de NFSe/NfeBrasil");
        }
    }

    $this->biz = $this->seededTenant()->id;
    $this->outra = $this->seededSupportClientTenant()->id;
    expect($this->biz)->not->toBe($this->outra);
});

function nfseHealthCert(int $businessId, string $validoAte): int
{
    return (int) DB::table('nfe_certificados')->insertGetId([
        'business_id'        => $businessId,
        'uuid'               => (string) Str::uuid(),
        'cnpj_titular'       => '00000000000098',
        'valido_ate'         => $validoAte,
        'encrypted_password' => 'teste',
        'ativo'              => true,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
}

function nfseHealthConfig(int $businessId, ?int $certId, string $municipio = '9999998'): void
{
    DB::table('nfse_provider_configs')->where('business_id', $businessId)->delete();
    DB::table('nfse_provider_configs')->insert([
        'business_id'           => $businessId,
        'municipio_codigo_ibge' => $municipio,
        'cert_id'               => $certId,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
}

/** @return array<string, array{name:string,status:string,details:string}> */
function nfseHealthRodar(int $businessId, ?int &$exit = null): array
{
    $exit = Artisan::call('nfse:health', ['--business' => $businessId, '--json' => true, '--alert' => true]);
    $json = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    return collect($json['checks'])->keyBy('name')->all();
}

it('lê a tabela de certificado que existe (nfe_certificados), não a inexistente nfse_certificados', function () {
    $checks = nfseHealthRodar($this->biz);

    expect($checks['certificado_table']['status'])->toBe('OK')
        ->and($checks['certificado_table']['details'])->toContain('nfe_certificados');
});

it('certificado vigente da própria empresa → OK', function () {
    nfseHealthConfig($this->biz, nfseHealthCert($this->biz, now()->addDays(200)->toDateString()));

    $checks = nfseHealthRodar($this->biz);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('OK');
});

it('certificado vencendo em menos de 30 dias → WARN e exit 1 com --alert', function () {
    nfseHealthConfig($this->biz, nfseHealthCert($this->biz, now()->addDays(10)->toDateString()));

    $checks = nfseHealthRodar($this->biz, $exit);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('WARN')
        ->and($checks['cert_vencimento_alarme']['details'])->toContain('vencendo')
        ->and($exit)->toBe(1);
});

it('certificado que vence hoje já conta como vencido (a emissão bloqueia no dia) → FAIL e exit 2', function () {
    nfseHealthConfig($this->biz, nfseHealthCert($this->biz, now()->toDateString()));

    $checks = nfseHealthRodar($this->biz, $exit);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('FAIL')
        ->and($checks['cert_vencimento_alarme']['details'])->toContain('vencido')
        ->and($exit)->toBe(2);
});

it('config apontando para certificado de OUTRA empresa → FAIL (Tier 0)', function () {
    nfseHealthConfig($this->biz, nfseHealthCert($this->outra, now()->addDays(200)->toDateString()));

    $checks = nfseHealthRodar($this->biz, $exit);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('FAIL')
        ->and($checks['cert_vencimento_alarme']['details'])->toContain('outra empresa')
        ->and($exit)->toBe(2);
});

it('config sem certificado → WARN', function () {
    nfseHealthConfig($this->biz, null);

    $checks = nfseHealthRodar($this->biz);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('WARN')
        ->and($checks['cert_vencimento_alarme']['details'])->toContain('sem certificado');
});

it('olha o cert_id da config NFS-e, não o certificado ativo da empresa', function () {
    // A empresa tem um certificado vigente e ativo, mas a config NFS-e aponta outro, vencido.
    nfseHealthCert($this->biz, now()->addDays(300)->toDateString());
    nfseHealthConfig($this->biz, nfseHealthCert($this->biz, now()->subDays(3)->toDateString()));

    $checks = nfseHealthRodar($this->biz);

    expect($checks['cert_vencimento_alarme']['status'])->toBe('FAIL');
});

it('conta emissões em status erro (o que o módulo grava) nas últimas 24h', function () {
    $antes = nfseHealthRodar($this->biz);
    expect($antes['erros_recentes']['status'])->toBe('OK');

    for ($i = 0; $i < 5; $i++) {
        DB::table('nfse_emissoes')->insert([
            'business_id'     => $this->biz,
            'competencia'     => now()->startOfMonth()->toDateString(),
            'tomador_nome'    => 'Tomador Teste',
            'descricao'       => 'Servico teste health',
            'valor_servicos'  => 10,
            'status'          => 'erro',
            'idempotency_key' => 'health-' . Str::random(40),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    $depois = nfseHealthRodar($this->biz);

    expect($depois['erros_recentes']['status'])->toBe('WARN')
        ->and($depois['erros_recentes']['details'])->toContain('5 emissão');
});

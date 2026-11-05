<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * @see memory/requisitos/Fiscal/_telas/cert-health-check.casos.md — UC-FCERT-07
 *
 * @covers-us US-FISCAL-022
 *
 * O limiar do alerta vem de `fiscal.cert_health_check_alert_days` (env
 * FISCAL_CERT_HEALTH_ALERT_DAYS). Até 2026-11-05 o comando usava 30 fixo e ignorava a
 * config — achado D13 da auditoria do Fiscal.
 *
 * Roda nas duas lanes: em SQLite (:memory:) o teste monta a tabela mínima que o comando lê;
 * no MySQL usa a tabela real. Sempre `--dry-run` — nada é gravado em `mcp_alertas_eventos`.
 * O cert é do tenant fictício 98 (ADR 0358) e é apagado no fim.
 */
const CERT_LIMIAR_BIZ = 98;

beforeEach(function () {
    $this->criouTabelaCert = false;

    if (DB::connection()->getDriverName() === 'sqlite' && ! Schema::hasTable('nfe_certificados')) {
        Schema::create('nfe_certificados', function ($t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('business_id');
            $t->string('uuid')->nullable();
            $t->string('cnpj_titular')->nullable();
            $t->text('encrypted_password')->nullable();
            $t->date('valido_ate')->nullable();
            $t->boolean('ativo')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });
        $this->criouTabelaCert = true;
    }

    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('nfe_certificados ausente');
    }
});

afterEach(function () {
    if ($this->criouTabelaCert ?? false) {
        Schema::dropIfExists('nfe_certificados');
    }
});

/** Insere cert A1 ativo no tenant fictício vencendo em $dias. Só usa colunas que existem. */
function certLimiarInserir(int $dias): array
{
    $uuid = (string) Str::uuid();
    $row = [
        'business_id' => CERT_LIMIAR_BIZ,
        'valido_ate'  => now()->startOfDay()->addDays($dias)->toDateString(),
        'ativo'       => true,
        'created_at'  => now(),
        'updated_at'  => now(),
    ];
    $opcionais = [
        'uuid'               => $uuid,
        'cnpj_titular'       => '00000000000191',
        'encrypted_password' => 'CERT_LIMIAR_TEST_PLACEHOLDER',
        'cert_pfx_encrypted' => 'CERT_LIMIAR_TEST_PLACEHOLDER',
        'senha_encrypted'    => 'CERT_LIMIAR_TEST_PLACEHOLDER',
        'titular_cnpj'       => '00000000000191',
    ];
    foreach ($opcionais as $col => $valor) {
        if (Schema::hasColumn('nfe_certificados', $col)) {
            $row[$col] = $valor;
        }
    }

    return ['id' => (int) DB::table('nfe_certificados')->insertGetId($row), 'uuid' => $uuid];
}

/** Roda o comando em ensaio e devolve [stats, linha do cert pedido]. */
function certLimiarRodar(string $uuid): array
{
    Artisan::call('fiscal:cert-health-check', ['--dry-run' => true, '--json' => true, '--detail' => true]);
    $saida = json_decode(Artisan::output(), true);
    expect($saida)->toBeArray('a saída --json do comando não é JSON');

    $linha = collect($saida['detalhes'] ?? [])->firstWhere('uuid', $uuid);
    expect($linha)->not->toBeNull('o cert de teste não apareceu no relatório do comando');

    return [$saida['stats'], $linha];
}

it('UC-FCERT-07 · o limiar de dias vem da config e muda o resultado', function () {
    $cert = certLimiarInserir(20);

    try {
        // Limiar 30: um cert que vence em 20 dias está dentro da janela.
        config(['fiscal.cert_health_check_alert_days' => 30]);
        [$stats30, $linha30] = certLimiarRodar($cert['uuid']);
        expect($stats30['limiar_dias'])->toBe(30);
        expect($linha30['dias'])->toBe(20);
        expect($linha30['alertavel'])->toBeTrue();

        // Limiar 10: o MESMO cert, no mesmo dia, sai da janela.
        config(['fiscal.cert_health_check_alert_days' => 10]);
        [$stats10, $linha10] = certLimiarRodar($cert['uuid']);
        expect($stats10['limiar_dias'])->toBe(10);
        expect($linha10['dias'])->toBe(20);
        expect($linha10['alertavel'])->toBeFalse();
    } finally {
        DB::table('nfe_certificados')->where('id', $cert['id'])->delete();
    }
});

it('UC-FCERT-07 · config vazia ou não numérica cai no limiar padrão de 30', function () {
    $cert = certLimiarInserir(25);

    try {
        foreach ([null, '', 'abc'] as $valor) {
            config(['fiscal.cert_health_check_alert_days' => $valor]);
            [$stats, $linha] = certLimiarRodar($cert['uuid']);
            expect($stats['limiar_dias'])->toBe(30);
            expect($linha['alertavel'])->toBeTrue();
        }

        // CONTROLE: valor numérico em string (como vem do .env) é respeitado.
        config(['fiscal.cert_health_check_alert_days' => '10']);
        [$stats10, $linha10] = certLimiarRodar($cert['uuid']);
        expect($stats10['limiar_dias'])->toBe(10);
        expect($linha10['alertavel'])->toBeFalse();
    } finally {
        DB::table('nfe_certificados')->where('id', $cert['id'])->delete();
    }
});

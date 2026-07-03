<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * US-FISCAL-022 — Comando `fiscal:cert-health-check` (cap #13 CAPTERRA Fiscal).
 *
 * Aceite (ADR 0101 — biz=1 dogfood, NUNCA biz=4 cliente):
 *  - cert A1 vencendo em 15d → cria alerta em mcp_alertas_eventos
 *  - cert A1 válido 200d → NÃO cria alerta
 *  - idempotência: rodar 2× não duplica (dedup por business+cert)
 *  - alerta escopado ao business do cert (multi-tenant Tier 0 ADR 0093)
 *
 * SQLite skip: nfe_certificados + mcp_alertas_eventos têm schema MySQL canon.
 */

const CERT_HC_BIZ = 1; // ADR 0101 — biz=1, jamais cliente

/**
 * Insere um cert A1 de teste pra biz=1 com a validade dada, adaptando-se ao
 * schema real (duas migrations concorrem por `nfe_certificados`: NfeBrasil
 * uuid/cnpj_titular/encrypted_password vs NFSe cert_pfx/senha/titular_*).
 * Só preenche colunas que existem. Retorna o uuid usado na chave de idempotência.
 */
function inserirCertTeste(string $validoAte): string
{
    $uuid = (string) Str::uuid();
    $row = [
        'business_id' => CERT_HC_BIZ,
        'valido_ate'  => $validoAte,
        'ativo'       => true,
        'created_at'  => now(),
        'updated_at'  => now(),
    ];

    // Colunas NOT NULL do schema NfeBrasil (produção/staging).
    if (Schema::hasColumn('nfe_certificados', 'uuid')) {
        $row['uuid'] = $uuid;
    }
    if (Schema::hasColumn('nfe_certificados', 'cnpj_titular')) {
        $row['cnpj_titular'] = '00000000000191';
    }
    if (Schema::hasColumn('nfe_certificados', 'encrypted_password')) {
        $row['encrypted_password'] = 'CERT_HC_TEST_PLACEHOLDER';
    }
    // Colunas NOT NULL do schema NFSe (defesa se essa migration venceu a corrida).
    if (Schema::hasColumn('nfe_certificados', 'cert_pfx_encrypted')) {
        $row['cert_pfx_encrypted'] = 'CERT_HC_TEST_PLACEHOLDER';
    }
    if (Schema::hasColumn('nfe_certificados', 'senha_encrypted')) {
        $row['senha_encrypted'] = 'CERT_HC_TEST_PLACEHOLDER';
    }
    if (Schema::hasColumn('nfe_certificados', 'titular_cnpj')) {
        $row['titular_cnpj'] = '00000000000191';
    }

    DB::table('nfe_certificados')->insert($row);

    return $uuid;
}

/** Limpa alerta + cert do uuid (idempotência do próprio teste). */
function limparCertTeste(string $uuid): void
{
    DB::table('mcp_alertas_eventos')
        ->where('chave_idempotencia', 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}")
        ->delete();

    if (Schema::hasColumn('nfe_certificados', 'uuid')) {
        DB::table('nfe_certificados')->where('uuid', $uuid)->forceDelete();
    }
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: nfe_certificados + mcp_alertas_eventos exigem MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_certificados') || ! Schema::hasTable('mcp_alertas_eventos')) {
        $this->markTestSkipped('Tabelas ausentes — rodar migrate primeiro');
    }
    // Sem uuid não dá pra ancorar chave determinística no teste.
    if (! Schema::hasColumn('nfe_certificados', 'uuid')) {
        $this->markTestSkipped('Schema NFSe sem coluna uuid — teste ancora no schema NfeBrasil (produção)');
    }
});

it('comando registrado em php artisan list', function () {
    Artisan::call('list');
    expect(Artisan::output())->toContain('fiscal:cert-health-check');
});

it('cert vencendo em 15d gera alerta escopado ao business (ADR 0093)', function () {
    $uuid = inserirCertTeste(now()->addDays(15)->toDateString());
    $chave = 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}";

    try {
        $exit = Artisan::call('fiscal:cert-health-check');
        expect($exit)->toBe(0);

        $alerta = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->first();

        expect($alerta)->not->toBeNull('Cert vencendo em 15d DEVE gerar alerta');
        expect($alerta->tipo)->toBe('cert_a1_vencimento');
        expect((int) $alerta->business_id)->toBe(CERT_HC_BIZ, 'Alerta escopado ao business do cert');
        expect($alerta->status)->toBe('aberto');
        expect($alerta->severidade)->toBe('medium'); // 8..30d
    } finally {
        limparCertTeste($uuid);
    }
});

it('cert válido 200d NÃO gera alerta', function () {
    $uuid = inserirCertTeste(now()->addDays(200)->toDateString());
    $chave = 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}";

    try {
        Artisan::call('fiscal:cert-health-check');

        $existe = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->exists();
        expect($existe)->toBeFalse('Cert válido 200d NÃO deve gerar alerta (>30d)');
    } finally {
        limparCertTeste($uuid);
    }
});

it('cert vencido gera alerta severidade critical', function () {
    $uuid = inserirCertTeste(now()->subDays(5)->toDateString());
    $chave = 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}";

    try {
        Artisan::call('fiscal:cert-health-check');
        $alerta = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->first();

        expect($alerta)->not->toBeNull();
        expect($alerta->severidade)->toBe('critical');
    } finally {
        limparCertTeste($uuid);
    }
});

it('idempotente — rodar 2× não duplica o alerta (dedup por business+cert)', function () {
    $uuid = inserirCertTeste(now()->addDays(10)->toDateString());
    $chave = 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}";

    try {
        Artisan::call('fiscal:cert-health-check');
        Artisan::call('fiscal:cert-health-check');

        $count = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->count();
        expect($count)->toBe(1, 'Dedup: 1 evento por business+cert, não spammar todo dia');
    } finally {
        limparCertTeste($uuid);
    }
});

it('dry-run não persiste alerta', function () {
    $uuid = inserirCertTeste(now()->addDays(15)->toDateString());
    $chave = 'cert_a1_vencimento:' . CERT_HC_BIZ . ":{$uuid}";

    try {
        Artisan::call('fiscal:cert-health-check', ['--dry-run' => true]);

        $existe = DB::table('mcp_alertas_eventos')->where('chave_idempotencia', $chave)->exists();
        expect($existe)->toBeFalse('--dry-run NÃO persiste');
    } finally {
        limparCertTeste($uuid);
    }
});

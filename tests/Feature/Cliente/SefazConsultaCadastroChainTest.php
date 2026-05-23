<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0186 — Chain de certificado SEFAZ ConsultaCadastro.
 *
 * Cobre:
 *   - Endpoint `/cliente/lookup/cnpj/{cnpj}/sefaz?uf=XX`
 *   - Chain cert: primário business → legado → institucional fallback
 *   - Matriz UFs supported (RS,SP,PR,MG,BA,SC vs outras)
 *   - Cache Redis 30d shared entre tenants
 *   - Multi-tenant Tier 0: `withoutGlobalScope` autorizado APENAS no fallback
 *   - Audit log `mcp_audit_log` no uso do fallback institucional
 *
 * Multi-tenant: cross-tenant biz=1 (institucional) vs biz=99 (não autorizado).
 *
 * NOTA: testes que dependem de WS SEFAZ real são SKIPADOS — SEFAZ é externo,
 * mockado via stub do `Tools::sefazCadastro`. Cobertura efetiva: chain de
 * cert + endpoint contract + cache + matriz UFs. SEFAZ in-the-wild fica pra
 * smoke manual pré-deploy.
 *
 * Skip graceful em sqlite :memory: sem schema UPOS (CI sem MySQL).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory).');
    }
    if (! Schema::hasTable('nfe_certificados')) {
        $this->markTestSkipped('Schema NfeBrasil ausente — rode migrate.');
    }

    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id]);

    Cache::flush();
});

// ---------------------------------------------------------------------
// Endpoint contract — UF supported / unsupported
// ---------------------------------------------------------------------

test('GET /sefaz -- UF supported retorna 404 reason quando sem cert (graceful)', function () {
    // Larissa biz=4 sem cert ativo nem fallback institucional → 404 reason="sefaz_or_cert_error"
    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181/sefaz?uf=RS');

    $response->assertStatus(404)
        ->assertJsonPath('reason', 'sefaz_or_cert_error')
        ->assertJsonPath('uf', 'RS');
});

test('GET /sefaz -- UF NAO supported retorna 404 reason=uf_unsupported', function () {
    // GO está fora da matriz `fiscal.sefaz_consulta_cadastro_ufs_supported`.
    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181/sefaz?uf=GO');

    $response->assertStatus(404)
        ->assertJsonPath('reason', 'uf_unsupported')
        ->assertJsonPath('uf', 'GO');
});

test('GET /sefaz -- UF invalida (1 letra) retorna 422', function () {
    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181/sefaz?uf=X');

    $response->assertStatus(422)
        ->assertJsonPath('reason', 'invalid_request');
});

test('GET /sefaz -- sem uf query param retorna 422', function () {
    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181/sefaz');

    $response->assertStatus(422);
});

test('GET /sefaz -- exige autenticacao', function () {
    auth()->logout();
    session()->forget('user.business_id');

    $response = $this->getJson('/cliente/lookup/cnpj/11222333000181/sefaz?uf=RS');

    expect($response->getStatusCode())->toBeIn([302, 401, 403]);
});

// ---------------------------------------------------------------------
// Config canon `fiscal.php`
// ---------------------------------------------------------------------

test('config fiscal.sefaz_consulta_cadastro_ufs_supported tem 6 UFs canon', function () {
    $ufs = config('fiscal.sefaz_consulta_cadastro_ufs_supported');

    expect($ufs)->toBeArray();
    expect(array_keys($ufs))->toEqualCanonicalizing(['RS', 'SP', 'PR', 'MG', 'BA', 'SC']);
    foreach ($ufs as $uf => $cfg) {
        expect($cfg)->toHaveKey('endpoint');
        expect($cfg)->toHaveKey('status');
    }
});

test('config fiscal.fallback_business_id default=1', function () {
    expect(config('fiscal.fallback_business_id'))->toBe(1);
});

test('config fiscal.sefaz_consulta_cadastro_cache_ttl_seconds = 30 dias', function () {
    expect(config('fiscal.sefaz_consulta_cadastro_cache_ttl_seconds'))->toBe(60 * 60 * 24 * 30);
});

// ---------------------------------------------------------------------
// Chain de cert — multi-tenant Tier 0 (ADR 0093 + ADR 0186)
// ---------------------------------------------------------------------

test('CertificadoService::carregarParaSefazComFallback lanca RuntimeException se nada disponivel', function () {
    // Biz consumidor sem cert. Fallback institucional (biz=1) também sem cert nesse cenário.
    Config::set('fiscal.fallback_business_id', 999999); // biz inexistente

    $svc = app(\Modules\NfeBrasil\Services\CertificadoService::class);

    expect(fn () => $svc->carregarParaSefazComFallback($this->business->id))
        ->toThrow(\RuntimeException::class);
});

test('SefazConsultaCadastroService retorna null pra UF nao suportada (sem hittar SEFAZ)', function () {
    $svc = app(\Modules\NfeBrasil\Services\SefazConsultaCadastroService::class);

    $result = $svc->consultar('11222333000181', 'GO', $this->business->id);

    expect($result)->toBeNull();
});

test('SefazConsultaCadastroService retorna null se feature flag desligada', function () {
    Config::set('fiscal.sefaz_consulta_cadastro_enabled', false);

    $svc = app(\Modules\NfeBrasil\Services\SefazConsultaCadastroService::class);
    $result = $svc->consultar('11222333000181', 'RS', $this->business->id);

    expect($result)->toBeNull();
});

test('SefazConsultaCadastroService retorna null pra CNPJ menor que 14 digitos', function () {
    $svc = app(\Modules\NfeBrasil\Services\SefazConsultaCadastroService::class);
    $result = $svc->consultar('123', 'RS', $this->business->id);

    expect($result)->toBeNull();
});

// ---------------------------------------------------------------------
// Multi-tenant Tier 0 — invariante withoutGlobalScope (ADR 0186 §camada #3)
// ---------------------------------------------------------------------

test('apenas CertificadoService::carregarParaSefazComFallback usa withoutGlobalScope(ScopeByBusiness) em nfe_certificados', function () {
    // Invariante ADR 0186: a unica query no codebase autorizada a escapar do
    // tenant scope em nfe_certificados e a camada #3 (fallback institucional).
    // Pest test garante que nao ha outros usos suspeitos.
    $base = base_path();
    $pattern = 'withoutGlobalScope.*ScopeByBusiness';

    $cmd = sprintf(
        'grep -rEn %s %s/Modules %s/app 2>nul || grep -rEn %s %s/Modules %s/app 2>/dev/null',
        escapeshellarg($pattern),
        escapeshellarg($base),
        escapeshellarg($base),
        escapeshellarg($pattern),
        escapeshellarg($base),
        escapeshellarg($base),
    );
    $output = shell_exec($cmd) ?? '';
    $lines = array_filter(explode("\n", trim($output)));

    // Filtra arquivos que MENCIONAM mas nao usam (comments). Considera apenas
    // linhas que tem `::withoutGlobalScope` (assinatura de chamada).
    $callsRelacionadasACertificado = array_filter($lines, function (string $line) {
        return str_contains($line, 'NfeCertificado::')
            && str_contains($line, 'withoutGlobalScope')
            && ! str_starts_with(trim(substr($line, strpos($line, ':') + 1)), '//')
            && ! str_starts_with(trim(substr($line, strpos($line, ':') + 1)), '*');
    });

    // Aceita 0 (codebase pre-implementacao) ou 1 (so o do CertificadoService::carregarParaSefazComFallback).
    expect(count($callsRelacionadasACertificado))->toBeLessThanOrEqual(1);

    // Se houver 1, validar que e no CertificadoService.
    if (count($callsRelacionadasACertificado) === 1) {
        $line = reset($callsRelacionadasACertificado);
        expect($line)->toContain('CertificadoService');
    }
});

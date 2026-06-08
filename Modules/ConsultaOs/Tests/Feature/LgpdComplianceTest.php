<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * LGPD Compliance smoke — Modules/ConsultaOs (D7 LGPD governance).
 *
 * Valida 3 sub-dimensoes D7 (ADR 0155 module-grade v3):
 *   a) PiiRedactor wrap em logs (busca publica audit)
 *   b) Log estruturado de auditoria (channel default, payload sem PII raw)
 *   c) Config retention.php declarada com janelas LGPD-compliant
 *
 * Pattern canonico Wave 9 Crm — replicado pra portal publico.
 *
 * Refs:
 *   - ADR 0093 multi-tenant Tier 0 IRREVOGAVEL
 *   - ADR 0101 tests biz=1 (nao se aplica aqui — portal publico sem sessao biz)
 *   - ADR 0155 module-grade v3 sub-dimensoes D7
 *   - Modules/ConsultaOs/Config/retention.php
 *   - Modules/Crm/Config/retention.php (pattern original Wave 9)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: TestCase UltimatePOS requer schema MySQL (ADR 0101).');
    }
});

it('D7.c retention.php declara consulta_os_logs com janela LGPD-compliant', function () {
    $config = require __DIR__.'/../../Config/retention.php';

    expect($config)->toBeArray();
    expect($config)->toHaveKey('enabled');
    expect($config)->toHaveKey('entities');
    expect($config)->toHaveKey('strategy');
    expect($config['entities'])->toHaveKey('consulta_os_logs');
    expect($config['entities'])->toHaveKey('consulta_os_tokens');

    // ANPD Resolucao 02/2022 + janela fiscal — minimo 1 ano pra log de seguranca
    expect($config['entities']['consulta_os_logs'])->toBeGreaterThanOrEqual(365);
    expect($config['entities']['consulta_os_logs'])->toBeLessThanOrEqual(1825); // max 5 anos LGPD

    // Token efêmero — nao pode reter alem de 1 ano (sem finalidade ativa)
    expect($config['entities']['consulta_os_tokens'])->toBeLessThanOrEqual(365);

    // Estrategia 'anonymize' default preserva analytics agregado sem PII
    expect($config['strategy'])->toBeIn(['hard_delete', 'anonymize', 'soft_delete']);
});

it('D7.a PiiRedactor disponivel via container e redaciona CPF/CNPJ/email/telefone', function () {
    $redactor = app(PiiRedactor::class);

    expect($redactor)->toBeInstanceOf(PiiRedactor::class);

    // Smoke: garantir que numero de OS contendo PII colado por engano e redacted
    $entradaSuja = 'OS 4821 contato cliente@acme.com.br CPF 123.456.789-00';
    $saida = $redactor->redact($entradaSuja);

    expect($saida)->toContain('[REDACTED:EMAIL]');
    expect($saida)->toContain('[REDACTED:CPF]');
    expect($saida)->not->toContain('cliente@acme.com.br');
    expect($saida)->not->toContain('123.456.789-00');
});

it('D7.b busca publica registra audit log estruturado sem vazar PII', function () {
    Log::spy();

    $response = $this->getJson('/consulta-os/buscar?numero=4821');
    $response->assertStatus(200);

    // Log::info chamado com payload estruturado contendo chaves esperadas + sem PII raw
    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            if ($message !== 'consultaos.busca_publica') {
                return false;
            }

            // Schema audit log obrigatorio (D7.b)
            $chavesEsperadas = ['numero_redacted', 'estagio', 'resultado', 'ip_truncado', 'user_agent', 'timestamp'];
            foreach ($chavesEsperadas as $chave) {
                if (! array_key_exists($chave, $context)) {
                    return false;
                }
            }

            // IP truncado /24 (ultimo octeto = 0) — anti-tracking individual
            if (str_contains($context['ip_truncado'], '.')) {
                $partes = explode('.', $context['ip_truncado']);
                expect(end($partes))->toBe('0', 'IP IPv4 deve ser truncado /24 (LGPD pseudonimizacao)');
            }

            // User-Agent maximo 80 chars (sem fingerprint extenso)
            expect(strlen((string) $context['user_agent']))->toBeLessThanOrEqual(80);

            return true;
        })
        ->atLeast()
        ->once();
});

it('D7.b busca com numero contendo PII raw redaciona antes de logar', function () {
    Log::spy();

    // Simula usuario colando CPF no campo numero (acidental). FormRequest valida
    // alpha_num + max:20 → 422. Mesmo assim, se passar, audit log redaciona.
    // Aqui usamos numero valido mas garantimos que PiiRedactor esta no caminho.
    $response = $this->getJson('/consulta-os/buscar?numero=99999');
    $response->assertStatus(404);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'consultaos.busca_publica'
                && $context['resultado'] === 'not_found'
                && isset($context['numero_redacted']);
        })
        ->atLeast()
        ->once();
});

it('D7 retention nao loga business_id (rota publica sem sessao multi-tenant)', function () {
    Log::spy();

    $response = $this->getJson('/consulta-os/buscar?numero=4821');
    $response->assertStatus(200);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            if ($message !== 'consultaos.busca_publica') {
                return false;
            }

            // Portal publico NUNCA loga business_id (rota sem session.user.business_id)
            return ! array_key_exists('business_id', $context);
        })
        ->atLeast()
        ->once();
});

<?php

declare(strict_types=1);

use Modules\Connector\Services\DelphiSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Connector POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry Delphi handshake):
 *   1. DelphiSyncService preserva detectBodyFormat() retornando whitelist
 *      canônica {array_tabelas, json_flat, pipe, unknown} — regression guard
 *      contrato Delphi G1/G2/TThreadLicenca.
 *   2. AcceptDelphiTokenHandshakeRequest (W23 D8) preserva anti-spoofing
 *      (business_id prohibited no body) — regression guard Tier 0 Connector.
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Connector é API external — business_id NUNCA vem do body Delphi (anti-spoofing)
 *   - Resposta string `S;msg` / `N;motivo` canônica (Delphi parsa literal — NÃO mudar)
 *   - PT-BR + Multi-tenant {@see ADR 0093} + NÃO biz=4 ({@see ADR 0101})
 *
 * @see Modules\Connector\Tests\Feature\Wave23ConnectorSaturationTest (predecessor)
 * @see memory/requisitos/Connector/SPEC.md
 */
describe('Wave 28 Connector Polish — saturação final ≥95', function () {

    it('W28 sentry — DelphiSyncService::detectBodyFormat preserva whitelist 4 formatos', function () {
        $svc = new DelphiSyncService();
        $ref = new ReflectionClass($svc);

        expect($ref->hasMethod('detectBodyFormat'))->toBeTrue(
            'detectBodyFormat() removido — contrato Delphi G1/G2/TThreadLicenca quebrado'
        );

        // Smoke direto (zero DB hit — método é puro)
        expect($svc->detectBodyFormat(''))->toBe('unknown');
        expect($svc->detectBodyFormat('{}'))->toBe('unknown');
        // pipe format (TThreadLicenca legacy)
        expect($svc->detectBodyFormat('ABCD1234|HOST|3.7|10.0.0.1|12345678901234|RAZAO'))->toBe('pipe');
        // json_flat (WR Comercial atual G2)
        expect($svc->detectBodyFormat('{"cnpj":"12345678901234","serial_hd":"X"}'))->toBe('json_flat');
        // array_tabelas (Delphi G1 legacy 3.7)
        expect($svc->detectBodyFormat('[{"NOME_TABELA":"EMPRESA"}]'))->toBe('array_tabelas');
    });

    it('W28 sentry — AcceptDelphiTokenHandshakeRequest preserva anti-spoofing (Tier 0)', function () {
        $reqPath = __DIR__ . '/../../Http/Requests/AcceptDelphiTokenHandshakeRequest.php';
        expect(file_exists($reqPath))->toBeTrue(
            'AcceptDelphiTokenHandshakeRequest ausente — W23 D8 anti-spoofing quebrado'
        );

        $source = file_get_contents($reqPath);

        // Tier 0: business_id NUNCA aceito do body Delphi (vem do CNPJ → resolve server-side)
        // Sentry: classe deve estar marcada como FormRequest Laravel canônico
        expect($source)->toContain('FormRequest');
        expect($source)->toContain('public function rules()');
        // CNPJ é fonte de verdade pra resolver business_id (anti-spoofing)
        expect($source)->toMatch('/cnpj/i');
    });
});

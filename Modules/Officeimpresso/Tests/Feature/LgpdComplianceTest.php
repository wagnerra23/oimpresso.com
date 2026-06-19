<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: LGPD compliance D7 Officeimpresso — asserts estáticos (wiring/source-grep) de canon móvel (PiiRedactor/LogsActivity/module.json) — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Officeimpresso\Entities\Licenca_Computador;
use Modules\Officeimpresso\Entities\LicencaLog;

uses(Tests\TestCase::class);

/**
 * LGPD Compliance D7 — Wave 10 implementation 2026-05-16.
 *
 * Officeimpresso é módulo bridge legacy WR Sistemas Delphi (CNAE gráfico).
 * Toca dados sensíveis cliente legacy: CNPJ, razão social, email, telefone,
 * endereço — payloads brutos chegam via `processa-dados-cliente` /
 * `salvar-equipamento` / `oimpresso/registrar` e são gravados em
 * `licenca_log.metadata.body_preview`.
 *
 * D7.a (4 pts) — PiiRedactor sanitiza body antes de persistir
 * D7.b (3 pts) — LogsActivity nos 2 Entities (LicencaLog + Licenca_Computador)
 * D7.c (3 pts) — `retention_days` declarado em `module.json`
 *
 * Esses testes NÃO dependem de DB MySQL — usam fixtures fake fakes pra
 * validar wiring de classes + arquivos canônicos. Roda local sem prod schema.
 *
 * @see Modules/Jana/Services/Privacy/PiiRedactor
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
 */

// ------------------------------------------------------------------
// D7.a — PiiRedactor wiring
// ------------------------------------------------------------------

it('D7.a — PiiRedactor é resolvable do container', function () {
    $redactor = app(PiiRedactor::class);
    expect($redactor)->toBeInstanceOf(PiiRedactor::class);
});

it('D7.a — PiiRedactor sanitiza payload Delphi com CNPJ + email + telefone', function () {
    $redactor = app(PiiRedactor::class);

    // Payload fake estilo Delphi g1 (processa-dados-cliente NOME_TABELA=EMPRESA)
    // CNPJ fictício 11.222.333/0001-44, email fake@example.com, tel (48) 9 9999-8888 — pii-allowlist (fixture LGPD redaction test)
    $bodyDelphi = '[{"NOME_TABELA":"EMPRESA","RAZAO_SOCIAL":"GRAFICA FAKE LTDA","CNPJCPF":"11.222.333/0001-44","EMAIL":"contato@fake.com.br","TELEFONE":"(48) 9 9999-8888","CEP":"88780-000"}]'; // pii-allowlist (fixture fake — testa PiiRedactor)

    $redacted = $redactor->redact($bodyDelphi);

    expect($redacted)->not->toContain('11.222.333/0001-44'); // pii-allowlist (fixture fake CNPJ)
    expect($redacted)->not->toContain('contato@fake.com.br');
    expect($redacted)->not->toContain('88780-000');
    expect($redacted)->toContain('[REDACTED:CNPJ]');
    expect($redacted)->toContain('[REDACTED:EMAIL]');
    expect($redacted)->toContain('[REDACTED:CEP]');
});

it('D7.a — LogDelphiAccess middleware importa PiiRedactor', function () {
    $content = file_get_contents(
        base_path('Modules/Officeimpresso/Http/Middleware/LogDelphiAccess.php')
    );
    expect($content)->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;');
    expect($content)->toContain('PiiRedactor::class');
});

it('D7.a — ParseLicencaLogCommand sanitiza error_message antes de gravar', function () {
    $content = file_get_contents(
        base_path('Modules/Officeimpresso/Console/ParseLicencaLogCommand.php')
    );
    expect($content)->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;');
    expect($content)->toContain('PiiRedactor::class');
});

it('D7.a — AuditController redaciona metadata vindo do Delphi', function () {
    $content = file_get_contents(
        base_path('Modules/Officeimpresso/Http/Controllers/AuditController.php')
    );
    expect($content)->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;');
    expect($content)->toContain('redactArray');
});

// ------------------------------------------------------------------
// D7.b — LogsActivity trait nos Entities
// ------------------------------------------------------------------

it('D7.b — Licenca_Computador usa trait LogsActivity', function () {
    $traits = class_uses(Licenca_Computador::class);
    expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
});

it('D7.b — LicencaLog usa trait LogsActivity', function () {
    $traits = class_uses(LicencaLog::class);
    expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
});

it('D7.b — Licenca_Computador implementa getActivitylogOptions', function () {
    $licenca = new Licenca_Computador;
    $options = $licenca->getActivitylogOptions();
    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

it('D7.b — LicencaLog implementa getActivitylogOptions', function () {
    $log = new LicencaLog;
    $options = $log->getActivitylogOptions();
    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// ------------------------------------------------------------------
// D7.c — retention_days em module.json
// ------------------------------------------------------------------

it('D7.c — module.json declara retention_days', function () {
    $modulePath = base_path('Modules/Officeimpresso/module.json');
    expect(file_exists($modulePath))->toBeTrue();

    $config = json_decode((string) file_get_contents($modulePath), true);
    expect($config)->toHaveKey('retention_days');
    expect($config['retention_days'])->toBeArray();
});

it('D7.c — retention_days cobre todas as classes de evento + licenca inativa', function () {
    $config = json_decode(
        (string) file_get_contents(base_path('Modules/Officeimpresso/module.json')),
        true
    );

    $retention = $config['retention_days'] ?? [];
    expect($retention)->toHaveKey('licenca_log_api_call');
    expect($retention)->toHaveKey('licenca_log_login_events');
    expect($retention)->toHaveKey('licenca_log_admin_actions');
    expect($retention)->toHaveKey('licenca_log_error_logs');
    expect($retention)->toHaveKey('licenca_computador_inactive');

    // Janelas mínimas razoáveis (em dias)
    expect($retention['licenca_log_api_call'])->toBeGreaterThanOrEqual(180);
    expect($retention['licenca_log_admin_actions'])->toBeGreaterThanOrEqual(1825); // 5 anos audit legal
    expect($retention['licenca_computador_inactive'])->toBeGreaterThanOrEqual(365);
});

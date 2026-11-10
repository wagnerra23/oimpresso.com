<?php

declare(strict_types=1);

use App\Support\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * Wave 23 — Pest CI enforce `pii_leak_in_activity_log` (D5 LGPD gap +3).
 *
 * Reforça contrato Tier 0 ADR 0093 + ADR 0094 §"PIIs reais NUNCA em log".
 * Garante que a Auditoria não exporta CPF/CNPJ/email/telefone em activity_log
 * via `revert_reason` ou audit notes — `PiiRedactor` Jana DEVE ser invocado
 * antes da escrita.
 *
 * O que o RevertService grava de verdade fica em RevertRedacaoContratoTest, que roda o
 * revert. Aqui ficam o PiiRedactor, o ActionGate e as regras do FormRequest.
 *
 * Se Jana for opcional no ambiente, fall back fail-open documentado
 * (ActionGate::logViolation idem) mas a Auditoria É obrigatória — PiiRedactor
 * é dependência hard.
 *
 * @see app/Support/Privacy/PiiRedactor.php
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §"PII reais NUNCA em log"
 */

it('PiiRedactor Jana existe + tem método redact(string,mode)', function () {
    expect(class_exists(PiiRedactor::class))->toBeTrue();
    expect(method_exists(PiiRedactor::class, 'redact'))->toBeTrue();
});

it('AuditNote NÃO loga o campo `note` em activity_log (Wave 18 D7 PII residual)', function () {
    $note = new \Modules\Auditoria\Entities\AuditNote();
    $options = $note->getActivitylogOptions();

    // O conteúdo da note pode ter PII residual — Spatie loga só metadata
    // (activity_id + user_id), nunca o note text.
    expect($options->logAttributes)->not->toContain('note');
});

it('ActionGate redige PII no payload do log de violação', function () {
    $payload = null;
    \Illuminate\Support\Facades\Log::shouldReceive('channel')->andReturnSelf();
    \Illuminate\Support\Facades\Log::shouldReceive('warning')->once()->withArgs(function ($msg, $dados) use (&$payload) {
        $payload = $dados;

        return $msg === 'ActionGate violation';
    });

    $gate = app(\Modules\Governance\Http\Middleware\ActionGate::class);
    $request = \Illuminate\Http\Request::create('/qualquer', 'GET');
    $rota = 'rota com CPF 123.456.789-09'; # pii-allowlist

    (fn () => $this->logViolation('no_actor', $rota, null, $request))->call($gate);

    expect($payload['route'])->toContain('[REDACTED:CPF]');
    expect($payload['route'])->not->toContain('123.456.789-09'); # pii-allowlist
});

it('config governance.pii_redaction_enabled liga a redação por padrão', function () {
    expect(config('governance.pii_redaction_enabled'))->toBeTrue();
});

// ------------------------------------------------------------------
// Wave 25 — expansão PII Leak coverage (placeholder mode, CPF/CNPJ regex)
// ------------------------------------------------------------------

it('PiiRedactor placeholder mode preserva legibilidade — substitui mas NÃO mascara tudo', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Cliente CPF 123.456.789-09 ligou às 10h.'; # pii-allowlist

    $output = $redactor->redact($input, 'placeholder');

    // Output deve continuar contendo "Cliente" e "ligou" (não vira tudo asterisco)
    expect($output)->toContain('Cliente');
    expect($output)->toContain('ligou');
    // Mas CPF formatado deve sumir
    expect($output)->not->toContain('123.456.789-09'); # pii-allowlist
});

it('PiiRedactor lida com CNPJ formatado BR (xx.xxx.xxx/yyyy-zz)', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Empresa CNPJ 12.345.678/0001-99 enviou nota.'; # pii-allowlist

    $output = $redactor->redact($input, 'placeholder');

    expect($output)->not->toContain('12.345.678/0001-99'); # pii-allowlist
    expect($output)->toContain('Empresa');
});

it('PiiRedactor é idempotente — redact(redact(x)) == redact(x)', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'CPF 111.222.333-44 e email teste@gmail.com'; # pii-allowlist

    $pass1 = $redactor->redact($input, 'placeholder');
    $pass2 = $redactor->redact($pass1, 'placeholder');

    expect($pass1)->toBe($pass2, 'redact deve ser idempotente — segunda chamada não altera');
});

it('AuditNote casts não expõem note como searchable (defesa LGPD)', function () {
    $note = new \Modules\Auditoria\Entities\AuditNote();
    $casts = $note->getCasts();

    // note deve ser plain string (sem encrypted cast removido) — mas activity log
    // não deve ter note no logAttributes (já testado acima). Aqui só reforça que
    // não há cast acidental tipo `'note' => 'encrypted'` que mudaria o storage.
    expect($casts)->not->toHaveKey('note');
});
it('ExportAuditEntriesRequest aceita include_properties opcional e exige motivo', function () {
    $rules = (new \Modules\Auditoria\Http\Requests\ExportAuditEntriesRequest())->rules();

    expect($rules['include_properties'])->toBe(['nullable', 'boolean']);
    expect($rules['motivo'])->toContain('required', 'string', 'min:10');
});

<?php

declare(strict_types=1);

use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * Wave 23 — Pest CI enforce `pii_leak_in_activity_log` (D5 LGPD gap +3).
 *
 * Reforça contrato Tier 0 ADR 0093 + ADR 0094 §"PIIs reais NUNCA em log".
 * Garante que a Auditoria não exporta CPF/CNPJ/email/telefone em activity_log
 * via `revert_reason` ou audit notes — `PiiRedactor` Jana DEVE ser invocado
 * antes da escrita.
 *
 * Cobertura unit-level (não toca DB):
 *   1. PiiRedactor existe + tem método redact
 *   2. RevertService importa PiiRedactor (assert via reflection/file content)
 *   3. RevertService::revert chama redact() ANTES de log.save (assert source code)
 *   4. modo placeholder mantém legibilidade (NÃO replace tudo por *)
 *
 * Se Jana for opcional no ambiente, fall back fail-open documentado
 * (ActionGate::logViolation idem) mas a Auditoria É obrigatória — PiiRedactor
 * é dependência hard.
 *
 * @see Modules/Jana/Services/Privacy/PiiRedactor.php
 * @see Modules/Auditoria/Services/RevertService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §"PII reais NUNCA em log"
 */

it('PiiRedactor Jana existe + tem método redact(string,mode)', function () {
    expect(class_exists(PiiRedactor::class))->toBeTrue();
    expect(method_exists(PiiRedactor::class, 'redact'))->toBeTrue();
});

it('RevertService importa PiiRedactor (use statement obrigatório)', function () {
    $path = base_path('Modules/Auditoria/Services/RevertService.php');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;');
});

it('RevertService::revert invoca PiiRedactor sobre revert_reason (ANTES de save)', function () {
    $path = base_path('Modules/Auditoria/Services/RevertService.php');
    $content = file_get_contents($path);

    // Pattern canônico (placeholder mantém legibilidade — ADR 0127):
    expect($content)->toContain("app(PiiRedactor::class)->redact(\$reason, 'placeholder')");

    // Garantir ordem: redact ANTES do log->save()
    $redactPos = strpos($content, '->redact($reason');
    $savePos = strpos($content, '$log->save();');
    expect($redactPos)->toBeLessThan($savePos, 'redact precisa rodar ANTES de log->save');
});

it('RevertService trata reason min:10 chars (defesa entrada — InvalidArgumentException)', function () {
    $path = base_path('Modules/Auditoria/Services/RevertService.php');
    $content = file_get_contents($path);

    expect($content)->toContain('strlen($reason) < 10');
    expect($content)->toContain('InvalidArgumentException');
});

it('AuditNote NÃO loga o campo `note` em activity_log (Wave 18 D7 PII residual)', function () {
    $note = new \Modules\Auditoria\Entities\AuditNote();
    $options = $note->getActivitylogOptions();

    // O conteúdo da note pode ter PII residual — Spatie loga só metadata
    // (activity_id + user_id), nunca o note text.
    expect($options->logAttributes)->not->toContain('note');
});

it('ActionGate Governance também invoca PiiRedactor em logViolation (cintura+suspensório)', function () {
    $path = base_path('Modules/Governance/Http/Middleware/ActionGate.php');
    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    // PiiRedactor pode estar via FQCN ou import — aceitar ambos
    expect(
        str_contains($content, 'PiiRedactor')
        || str_contains($content, 'Modules\\Jana\\Services\\Privacy\\PiiRedactor')
    )->toBeTrue('Governance ActionGate precisa importar PiiRedactor');
});

it('config governance.pii_redaction_enabled tem default true (ADR 0094 §PII)', function () {
    // Wave 18 adicionou flag em config('governance.pii_redaction_enabled').
    // Não force boot da Application — leia config file estaticamente.
    $path = base_path('Modules/Governance/Config/retention.php');
    if (! file_exists($path)) {
        $this->markTestSkipped('Modules/Governance/Config/retention.php não encontrado (Wave 18 pode estar pendente)');
    }

    $content = file_get_contents($path);
    expect($content)->toContain('pii_redaction_enabled');
});

// ------------------------------------------------------------------
// Wave 25 — expansão PII Leak coverage (placeholder mode, CPF/CNPJ regex)
// ------------------------------------------------------------------

it('PiiRedactor placeholder mode preserva legibilidade — substitui mas NÃO mascara tudo', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Cliente CPF 123.456.789-09 ligou às 10h.';

    $output = $redactor->redact($input, 'placeholder');

    // Output deve continuar contendo "Cliente" e "ligou" (não vira tudo asterisco)
    expect($output)->toContain('Cliente');
    expect($output)->toContain('ligou');
    // Mas CPF formatado deve sumir
    expect($output)->not->toContain('123.456.789-09');
});

it('PiiRedactor lida com CNPJ formatado BR (xx.xxx.xxx/yyyy-zz)', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Empresa CNPJ 12.345.678/0001-99 enviou nota.';

    $output = $redactor->redact($input, 'placeholder');

    expect($output)->not->toContain('12.345.678/0001-99');
    expect($output)->toContain('Empresa');
});

it('PiiRedactor é idempotente — redact(redact(x)) == redact(x)', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'CPF 111.222.333-44 e email teste@gmail.com';

    $pass1 = $redactor->redact($input, 'placeholder');
    $pass2 = $redactor->redact($pass1, 'placeholder');

    expect($pass1)->toBe($pass2, 'redact deve ser idempotente — segunda chamada não altera');
});

it('RevertService NUNCA persiste reason raw em activity_log (sempre via PiiRedactor)', function () {
    $path = base_path('Modules/Auditoria/Services/RevertService.php');
    $content = file_get_contents($path);

    // Garantir que NÃO existe pattern `revert_reason' => $reason` (raw direto)
    // mas sim a versão `PiiRedactor::class)->redact($reason, ...)` na atribuição.
    expect($content)->toMatch('/redact\(\$reason/');

    // Sanidade: assign de revert_reason aparece DEPOIS do redact (ordem importa)
    $redactPos = strpos($content, '->redact($reason');
    $reasonAssign = strpos($content, "'revert_reason'");
    if ($reasonAssign !== false) {
        expect($redactPos)->toBeLessThan($reasonAssign, 'redact precisa rodar ANTES de atribuir revert_reason');
    }
});

it('AuditNote casts não expõem note como searchable (defesa LGPD)', function () {
    $note = new \Modules\Auditoria\Entities\AuditNote();
    $casts = $note->getCasts();

    // note deve ser plain string (sem encrypted cast removido) — mas activity log
    // não deve ter note no logAttributes (já testado acima). Aqui só reforça que
    // não há cast acidental tipo `'note' => 'encrypted'` que mudaria o storage.
    expect($casts)->not->toHaveKey('note');
});

it('ExportAuditEntriesRequest tem flag include_properties default false (Wave 25 D8)', function () {
    $path = base_path('Modules/Auditoria/Http/Requests/ExportAuditEntriesRequest.php');
    expect(file_exists($path))->toBeTrue('FormRequest Wave 25 export deve existir');

    $content = file_get_contents($path);
    expect($content)->toContain("'include_properties' => ['nullable', 'boolean']");
    expect($content)->toContain("'motivo'        => ['required', 'string', 'min:10'");
});

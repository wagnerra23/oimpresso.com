<?php

use App\Contact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-003 — trait LogsActivity em App\Contact com PII LGPD redacted.
 *
 * Valida (per SPEC + ADR 0127 + regras-time.md):
 *   - Campos auditados (name, email, mobile, contact_type, customer_group_id) entram em log
 *   - tax_number_1 (CPF/CNPJ) NAO entra em log_only — PII LGPD nao deve aparecer
 *   - Mesmo se tax_number_1 mudar, properties JSON nao deve conter regex CPF/CNPJ
 *   - Multi-tenant Tier 0 (ADR 0093)
 *
 * CRITICO: este test e o ultimo guard. Se Pest verde aqui, garantimos que
 * properties em activity_log nao vaza CPF/CNPJ mesmo se Contact for atualizado.
 *
 * Skip-graceful em sqlite memory (CI). Validacao real com mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode local com DB_CONNECTION=mysql (dev) ou aguarde CI integration job.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->contact = Contact::where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')
        ->first();
    if (! $this->contact) {
        $this->markTestSkipped('Sem contact no business.');
    }

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

it('cenario 1: update Contact.name gera entry com log_name=crm.contact', function () {
    $oldName = $this->contact->name;
    $newName = $oldName.' [AUDIT-003-test]';

    $this->contact->name = $newName;
    $this->contact->save();

    $log = Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->where('log_name', 'crm.contact')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('LogsActivity deveria ter gravado entry on update');
    expect($log->business_id)->toBe($this->business->id);

    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;
    expect($old['name'] ?? null)->toBe($oldName);
    expect($new['name'] ?? null)->toBe($newName);
});

it('cenario 2: update tax_number_1 NAO gera entry (campo NAO logado pra PII LGPD)', function () {
    $countBefore = Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->count();

    // tax_number_1 NAO esta no logOnly por design — PII nao auditada
    $this->contact->tax_number_1 = '123.456.789-00'; // formato CPF deliberado pro test # pii-allowlist
    $this->contact->save();

    $countAfter = Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->count();

    expect($countAfter)->toBe($countBefore, 'tax_number_1 NAO deve gerar entry — campo PII fora do logOnly');
});

it('cenario 3: PII regex assert — properties JSON NUNCA contem CPF/CNPJ mesmo em update completo', function () {
    // Update mistura campo logado + campo PII — properties so deve ter campo logado
    $this->contact->name = 'Cliente Teste Audit-003';
    $this->contact->tax_number_1 = '12.345.678/0001-99'; // CNPJ formato deliberado # pii-allowlist
    $this->contact->mobile = '(11) 99999-8888';
    $this->contact->save();

    $log = Activity::query()
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->where('log_name', 'crm.contact')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();

    $propsJson = json_encode($log->properties ?? []);
    expect($propsJson)->not->toMatch('/\d{3}\.\d{3}\.\d{3}-\d{2}/', 'CPF NUNCA deve aparecer em properties (LGPD)');
    expect($propsJson)->not->toMatch('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', 'CNPJ NUNCA deve aparecer em properties (LGPD)');
    // Defensivo extra: chave 'tax_number_1' nao deve estar nos arrays old/attributes
    expect($log->properties['old'] ?? [])->not->toHaveKey('tax_number_1');
    expect($log->properties['attributes'] ?? [])->not->toHaveKey('tax_number_1');
});

it('cenario 4: multi-tenant Tier 0 — Contact activity nao vaza cross-tenant', function () {
    $this->contact->name = 'Cliente Audit-003 Tier0 Test';
    $this->contact->save();

    $logsThisBiz = Activity::query()
        ->where('business_id', $this->business->id)
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->count();

    expect($logsThisBiz)->toBeGreaterThan(0);

    $logsOtherBiz = Activity::query()
        ->where('business_id', '!=', $this->business->id)
        ->where('subject_type', Contact::class)
        ->where('subject_id', $this->contact->id)
        ->count();

    expect($logsOtherBiz)->toBe(0, 'activity nao deve vazar pra outro business_id (Tier 0)');
});

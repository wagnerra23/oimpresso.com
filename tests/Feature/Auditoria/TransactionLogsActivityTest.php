<?php

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * US-AUDIT-001 — trait LogsActivity em App\Transaction.
 *
 * Valida (per SPEC memory/requisitos/Auditoria/SPEC.md + ADR 0127):
 *   1. created → activity_log entry com event='created' e properties.attributes
 *   2. updated em campo logado (status/payment_status) → entry com properties.old + .attributes
 *   3. updated em campo NAO logado (invoice_no) → NAO gera entry (logOnlyDirty + logOnly enxuto)
 *   4. multi-tenant Tier 0: activity scoped por business_id (ADR 0093)
 *   5. PII redact: properties NAO contem CPF/CNPJ regex (Transaction nao tem campo
 *      direto de PII no logOnly, mas paranoia LGPD justifica assert defensivo)
 *
 * Padrao = Modules/Financeiro/Models/Titulo + TransactionObserverIntegrationTest
 * (DB real, DatabaseTransactions rollback, business/location/contact existentes
 * via seeder UltimatePOS).
 *
 * Refs: ADR 0127 §F1 padronizacao, ADR 0093 multi-tenant Tier 0,
 *       ADR 0101 smoke biz=1 (testes nunca usam biz cliente real)
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Guard: este teste depende de schema + dados UltimatePOS reais (mysql dev).
    // Em CI / sqlite :memory: as tabelas business/users/contacts nem existem —
    // skip-graceful pra nao confundir com falha real. Padrao do projeto:
    // veja Modules/Financeiro/Tests/Feature/TransactionObserverIntegrationTest.
    try {
        $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode local com DB_CONNECTION=mysql (dev) ou aguarde CI integration job.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->location = DB::table('business_locations')
        ->where('business_id', $this->business->id)
        ->first();
    if (! $this->location) {
        $this->markTestSkipped('Sem business_location pro business primary.');
    }

    $this->contact = DB::table('contacts')
        ->where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')
        ->first();
    if (! $this->contact) {
        $this->markTestSkipped('Sem contact no business.');
    }

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

function audit_makeSell(int $businessId, int $locationId, int $contactId, int $userId, array $overrides = []): Transaction
{
    $defaults = [
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total' => 100.0000,
        'total_before_tax' => 100.0000,
        'created_by' => $userId,
        'invoice_no' => 'AUDIT-001-'.uniqid(),
    ];

    return Transaction::create(array_merge($defaults, $overrides));
}

it('cenario 1: created sell gera activity_log entry com event=created e properties.attributes', function () {
    $tx = audit_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 250.00, 'total_before_tax' => 250.00]
    );

    $log = Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->where('log_name', 'sales.transaction')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('LogsActivity deveria ter gravado activity_log entry on created');
    expect($log->event)->toBe('created');
    expect($log->business_id)->toBe($this->business->id);

    $attrs = $log->properties['attributes'] ?? null;
    expect($attrs)->not->toBeNull('properties.attributes deve estar populado on created');
    expect($attrs['final_total'] ?? null)->not->toBeNull();
    expect((float) $attrs['final_total'])->toBe(250.00);
    expect($attrs['payment_status'] ?? null)->toBe('due');
    expect($attrs['status'] ?? null)->toBe('final');
});

it('cenario 2: updated payment_status due->paid gera entry com properties.old + .attributes', function () {
    $tx = audit_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 99.50, 'total_before_tax' => 99.50]
    );

    $tx->payment_status = 'paid';
    $tx->save();

    $log = Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->where('log_name', 'sales.transaction')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull('updated em campo logado deveria gerar entry');

    $old = $log->properties['old'] ?? null;
    $new = $log->properties['attributes'] ?? null;

    expect($old)->not->toBeNull('properties.old deve existir on updated (logOnlyDirty)');
    expect($old['payment_status'] ?? null)->toBe('due');
    expect($new['payment_status'] ?? null)->toBe('paid');
});

it('cenario 3: updated em campo NAO logado (invoice_no) NAO gera entry duplicado', function () {
    $tx = audit_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id
    );

    $countBefore = Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->count();

    // invoice_no NAO esta no logOnly — alteracao nao deve criar entry
    $tx->invoice_no = 'AUDIT-001-CHANGED-'.uniqid();
    $tx->save();

    $countAfter = Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->count();

    expect($countAfter)->toBe($countBefore, 'campo fora do logOnly nao deve gerar nova entry (dontSubmitEmptyLogs)');
});

it('cenario 4: multi-tenant Tier 0 — query Activity scoped por business_id NAO mistura businesses', function () {
    // Cria tx no business primario
    $tx = audit_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id
    );

    $logsThisBiz = Activity::query()
        ->where('business_id', $this->business->id)
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->count();

    expect($logsThisBiz)->toBeGreaterThan(0, 'log da venda deve aparecer no proprio business');

    // Mesmo subject_id em business diferente NAO deve aparecer (paranoia leak cross-tenant)
    $logsOtherBiz = Activity::query()
        ->where('business_id', '!=', $this->business->id)
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->count();

    expect($logsOtherBiz)->toBe(0, 'activity nao deve vazar pra outro business_id (Tier 0 IRREVOGAVEL)');
});

it('cenario 5: PII redact — properties JSON nao contem regex CPF/CNPJ', function () {
    $tx = audit_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id
    );

    $log = Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $tx->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();

    $propsJson = json_encode($log->properties ?? []);
    expect($propsJson)->not->toMatch('/\d{3}\.\d{3}\.\d{3}-\d{2}/', 'CPF nao deve aparecer em properties');
    expect($propsJson)->not->toMatch('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', 'CNPJ nao deve aparecer em properties');
});

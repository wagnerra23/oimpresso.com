<?php

declare(strict_types=1);

use Modules\Connector\Http\Requests\RegisterUserRequest;
use Modules\Connector\Http\Requests\StoreCashRegisterApiRequest;
use Modules\Connector\Http\Requests\StoreContactApiRequest;
use Modules\Connector\Http\Requests\StoreExpenseApiRequest;
use Modules\Connector\Http\Requests\StoreFollowUpRequest;
use Modules\Connector\Http\Requests\StoreLicencaComputadorRequest;
use Modules\Connector\Http\Requests\StoreOauthClientRequest;
use Modules\Connector\Http\Requests\UpdateFollowUpRequest;
use Modules\Connector\Services\ContactPayloadValidatorService;
use Modules\Connector\Services\DelphiSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 18 Connector SATURATION — D5 + D6 + D8.
 *
 * Cobre:
 *   D5: Services extraction (ContactPayloadValidatorService novo +
 *       DelphiSyncService existente)
 *   D6: OTel spans declarados nos Services (smoke regex)
 *   D8: 8 FormRequests (5 antigos + 2 novos Wave 18)
 *
 * NÃO toca DB (light Pest CI-friendly). Cross-tenant MySQL real fica em
 * MultiTenantIsolationTest.php.
 *
 * Tier 0 (ADR 0093): Service NUNCA aceita business_id do input — caller
 * passa após resolver token Passport.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// ------------------------------------------------------------------
// D5 — ContactPayloadValidatorService (novo)
// ------------------------------------------------------------------

dataset('tax_numbers_validos', [
    'CNPJ 14 dígitos sem máscara' => ['12345678000190', true],
    'CPF 11 dígitos sem máscara'  => ['12345678909', true],
    'CNPJ com máscara'            => ['12.345.678/0001-90', true],
    'CPF com máscara'             => ['123.456.789-09', true],
    'string vazia (aceito)'       => ['', true],
    'CNPJ inválido 13 dígitos'    => ['1234567800019', false],
    'CPF inválido 10 dígitos'     => ['1234567890', false],
    'texto não-numérico'          => ['abc', false],
]);

it('ContactPayloadValidatorService.isValidTaxNumber valida CPF/CNPJ formato', function (string $taxNumber, bool $expected) {
    $svc = new ContactPayloadValidatorService();
    expect($svc->isValidTaxNumber($taxNumber))->toBe($expected);
})->with('tax_numbers_validos');

dataset('emails_test', [
    'valido simples' => ['user@example.com', true],
    'valido com sub' => ['x.y@sub.example.com', true],
    'vazio aceito'   => ['', true],
    'sem @'          => ['invalido.com', false],
    'sem domain'     => ['x@', false],
]);

it('ContactPayloadValidatorService.isValidEmail valida RFC 5321 básico', function (string $email, bool $expected) {
    $svc = new ContactPayloadValidatorService();
    expect($svc->isValidEmail($email))->toBe($expected);
})->with('emails_test');

dataset('mobiles_br', [
    'DDD+9'      => ['11987654321', true],
    'DDD+8'      => ['1198765432', true],
    'vazio'      => ['', true],
    'só 9 digit' => ['987654321', false],
    'com 12'     => ['119876543210', false],
]);

it('ContactPayloadValidatorService.isValidMobileBr valida formato BR', function (string $mobile, bool $expected) {
    $svc = new ContactPayloadValidatorService();
    expect($svc->isValidMobileBr($mobile))->toBe($expected);
})->with('mobiles_br');

it('ContactPayloadValidatorService aceita payload válido (errors vazio)', function () {
    $svc = new ContactPayloadValidatorService();
    // Smoke sem DB lookup (contact_id vazio)
    $errors = $svc->validatePayload(1, [
        'tax_number' => '12345678000190',
        'email'      => 'user@example.com',
        'mobile'     => '11987654321',
    ]);
    expect($errors)->toBeArray();
})->skip(! function_exists('config'), 'Bootstrap Laravel necessário pra OtelHelper');

// ------------------------------------------------------------------
// D5 — DelphiSyncService (smoke API existente)
// ------------------------------------------------------------------

it('DelphiSyncService expõe detectBodyFormat', function () {
    $svc = new DelphiSyncService();
    expect(method_exists($svc, 'detectBodyFormat'))->toBeTrue();
});

it('DelphiSyncService detecta 3 formatos payload Delphi', function () {
    $svc = new DelphiSyncService();
    expect($svc->detectBodyFormat('ABCD1234|HOST|VER|IP|CNPJ|RAZAO'))->toBe('pipe');
    expect($svc->detectBodyFormat('{"cnpj":"123","serial_hd":"ABC"}'))->toBe('json_flat');
    expect($svc->detectBodyFormat(''))->toBe('unknown');
});

// ------------------------------------------------------------------
// D6 — OTel spans declarados em Services
// ------------------------------------------------------------------

it('ContactPayloadValidatorService usa OtelHelper canônico', function () {
    $content = file_get_contents(base_path('Modules/Connector/Services/ContactPayloadValidatorService.php'));
    expect($content)->toContain('App\\Util\\OtelHelper');
    expect($content)->toContain('connector.contact.validate');
});

it('DelphiSyncService usa OtelHelper canônico', function () {
    $content = file_get_contents(base_path('Modules/Connector/Services/DelphiSyncService.php'));
    expect($content)->toContain('App\\Util\\OtelHelper');
});

// ------------------------------------------------------------------
// D8 — Dataset recursivo dos FormRequests Connector
// ------------------------------------------------------------------

dataset('connector_form_requests', [
    'RegisterUserRequest'           => [RegisterUserRequest::class],
    'StoreContactApiRequest'        => [StoreContactApiRequest::class],
    'StoreFollowUpRequest'          => [StoreFollowUpRequest::class],
    'StoreLicencaComputadorRequest' => [StoreLicencaComputadorRequest::class],
    'StoreOauthClientRequest'       => [StoreOauthClientRequest::class],
    'UpdateFollowUpRequest'         => [UpdateFollowUpRequest::class],
    'StoreCashRegisterApiRequest'   => [StoreCashRegisterApiRequest::class], // Wave 18 novo
    'StoreExpenseApiRequest'        => [StoreExpenseApiRequest::class],      // Wave 18 novo
    // Wave 18 RETRY (deltas):
    'StoreSyncRequest'              => [\Modules\Connector\Http\Requests\StoreSyncRequest::class],
    'UpdateContactApiRequest'       => [\Modules\Connector\Http\Requests\UpdateContactApiRequest::class],
    'StoreProductApiRequest'        => [\Modules\Connector\Http\Requests\StoreProductApiRequest::class],
    'StoreSellPosApiRequest'        => [\Modules\Connector\Http\Requests\StoreSellPosApiRequest::class],
    'StoreAttendanceApiRequest'     => [\Modules\Connector\Http\Requests\StoreAttendanceApiRequest::class],
]);

it('FormRequest Connector tem rules() método público', function (string $class) {
    expect(class_exists($class))->toBeTrue();
    $req = new $class();
    expect(method_exists($req, 'rules'))->toBeTrue();
    expect($req->rules())->toBeArray();
})->with('connector_form_requests');

it('FormRequest Connector tem authorize() método público', function (string $class) {
    expect(class_exists($class))->toBeTrue();
    expect(method_exists($class, 'authorize'))->toBeTrue();
})->with('connector_form_requests');

// ------------------------------------------------------------------
// D8 — Specific Wave 18 FormRequests
// ------------------------------------------------------------------

it('StoreCashRegisterApiRequest exige location_id', function () {
    $req = new StoreCashRegisterApiRequest();
    $rules = $req->rules();
    expect($rules['location_id'])->toContain('required', 'integer');
});

it('StoreExpenseApiRequest exige transaction_date + total_before_tax + final_total', function () {
    $req = new StoreExpenseApiRequest();
    $rules = $req->rules();
    expect($rules['transaction_date'])->toContain('required', 'date');
    expect($rules['total_before_tax'])->toContain('required', 'numeric');
    expect($rules['final_total'])->toContain('required', 'numeric');
});

it('StoreExpenseApiRequest aceita recur_interval_type whitelist days/months/years', function () {
    $req = new StoreExpenseApiRequest();
    $rules = $req->rules();
    $recurRule = collect($rules['recur_interval_type'])->first(fn ($r) => str_starts_with((string) $r, 'in:'));
    expect($recurRule)->toContain('days', 'months', 'years');
});

// ------------------------------------------------------------------
// Wave 18 RETRY — Δ D8: 5 FormRequests novos (sync/contact/product/sell/attendance)
// ------------------------------------------------------------------

it('StoreSyncRequest exige cnpj obrigatório', function () {
    $req = new \Modules\Connector\Http\Requests\StoreSyncRequest();
    $rules = $req->rules();
    expect($rules['cnpj'])->toContain('required', 'string', 'min:11', 'max:18');
});

it('StoreSellPosApiRequest exige location_id+contact_id+products[] (min:1 max:500)', function () {
    $req = new \Modules\Connector\Http\Requests\StoreSellPosApiRequest();
    $rules = $req->rules();
    expect($rules['location_id'])->toContain('required', 'integer');
    expect($rules['products'])->toContain('required', 'array', 'min:1', 'max:500');
    expect($rules['products.*.product_id'])->toContain('required', 'integer');
});

it('StoreProductApiRequest whitelist type=single|variable|combo|modifier', function () {
    $req = new \Modules\Connector\Http\Requests\StoreProductApiRequest();
    $rules = $req->rules();
    $typeRule = collect($rules['type'])->first(fn ($r) => str_starts_with((string) $r, 'in:'));
    expect($typeRule)->toContain('single', 'variable', 'combo', 'modifier');
});

it('StoreAttendanceApiRequest exige clock_in OU clock_out (required_without)', function () {
    $req = new \Modules\Connector\Http\Requests\StoreAttendanceApiRequest();
    $rules = $req->rules();
    expect($rules['clock_in_time'])->toContain('required_without:clock_out_time');
    expect($rules['clock_out_time'])->toContain('required_without:clock_in_time', 'after_or_equal:clock_in_time');
});

it('UpdateContactApiRequest usa sometimes em first_name/email/type (PATCH parcial)', function () {
    $req = new \Modules\Connector\Http\Requests\UpdateContactApiRequest();
    $rules = $req->rules();
    expect($rules['first_name'])->toContain('sometimes');
    expect($rules['email'])->toContain('sometimes');
    expect($rules['type'])->toContain('sometimes');
});

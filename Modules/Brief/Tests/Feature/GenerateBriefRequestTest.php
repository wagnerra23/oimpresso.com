<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Modules\Brief\Http\Requests\GenerateBriefRequest;

uses(Tests\TestCase::class);

/**
 * GenerateBriefRequestTest — Wave 18 D8.c Security SATURATION.
 *
 * Valida as 3 rules do FormRequest dedicado pra futuro endpoint
 * POST /brief/admin/generate. Testes puros de schema (sem hit DB).
 *
 * @see Modules\Brief\Http\Requests\GenerateBriefRequest
 */

it('aceita payload vazio (todos campos nullable)', function () {
    $rules = (new GenerateBriefRequest())->rules();
    $v = Validator::make([], $rules);
    expect($v->fails())->toBeFalse();
});

it('aceita payload com dry_run + motivo + bypass_cap', function () {
    $rules = (new GenerateBriefRequest())->rules();
    $v = Validator::make([
        'dry_run'    => true,
        'motivo'     => 'teste manual Wagner',
        'bypass_cap' => false,
    ], $rules);
    expect($v->fails())->toBeFalse();
});

it('rejeita motivo > 255 chars', function () {
    $rules = (new GenerateBriefRequest())->rules();
    $v = Validator::make([
        'motivo' => str_repeat('a', 256),
    ], $rules);
    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('motivo'))->toContain('255');
});

it('rejeita dry_run não-booleano string arbitrária', function () {
    $rules = (new GenerateBriefRequest())->rules();
    $v = Validator::make(['dry_run' => 'not-a-bool-string'], $rules);
    expect($v->fails())->toBeTrue();
});

it('coerce dry_run "true" string pra bool real via prepareForValidation', function () {
    $req = GenerateBriefRequest::create('/brief/admin/generate', 'POST', [
        'dry_run' => 'true',
        'bypass_cap' => '1',
    ]);

    // prepareForValidation só roda no validate() do pipeline FormRequest; chamamos
    // o método protegido via reflection só pra exercer o coerce isolado.
    $reflection = new ReflectionClass($req);
    $method = $reflection->getMethod('prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($req);

    expect($req->input('dry_run'))->toBeTrue();
    expect($req->input('bypass_cap'))->toBeTrue();
});

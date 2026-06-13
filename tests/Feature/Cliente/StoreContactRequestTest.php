<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Slice 7 — Feature test pro StoreContactRequest wirado em ContactController@store.
 *
 * Antes deste slice, $request->only(['cpf_cnpj', 'indicador_ie', ...]) aceitava
 * qualquer string sem checar mod-11 SEFAZ. Agora o FormRequest valida.
 *
 * Cenários:
 *   - CPF inválido (mod-11 fail) → 422 + erro em cpf_cnpj.
 *   - CNPJ inválido → 422.
 *   - indicador_ie fora de 1/2/9 → 422.
 *   - regime fora do conjunto canônico → 422.
 *   - CPF válido + payload base → segue (não 422 em cpf_cnpj).
 *
 * Skip-graceful em sqlite memory / sem schema UltimatePOS (CI).
 * Validação real em mysql dev pre-merge.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    try {
        $this->business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql dev.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);
});

it('rejeita CPF inválido com 422 e erro em cpf_cnpj', function () {
    $payload = [
        'type' => 'customer',
        'first_name' => 'Cliente Slice7 CPF inválido',
        'cpf_cnpj' => '11144477700', // dígito verificador errado
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    $response->assertStatus(302); // Laravel redireciona em fail por padrão sem JSON
    $response->assertSessionHasErrors(['cpf_cnpj']);
});

it('rejeita CNPJ inválido com 422 / redirect + erro em cpf_cnpj', function () {
    $payload = [
        'type' => 'supplier',
        'supplier_business_name' => 'Fornecedor Slice7 CNPJ inválido',
        'cpf_cnpj' => '11444777000100', // dígito verificador errado
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    $response->assertSessionHasErrors(['cpf_cnpj']);
});

it('rejeita indicador_ie fora de 1/2/9', function () {
    $payload = [
        'type' => 'customer',
        'first_name' => 'Cliente Slice7 indicador',
        'indicador_ie' => 5, // valor fora do enum SEFAZ
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    $response->assertSessionHasErrors(['indicador_ie']);
});

it('rejeita regime fora do conjunto canônico', function () {
    $payload = [
        'type' => 'customer',
        'first_name' => 'Cliente Slice7 regime',
        'regime' => 'lucro_arbitrado', // valor fora do enum
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    $response->assertSessionHasErrors(['regime']);
});

it('aceita CPF válido sem erro no campo cpf_cnpj', function () {
    $payload = [
        'type' => 'customer',
        'first_name' => 'Cliente Slice7 CPF válido',
        'cpf_cnpj' => '11144477735', // mod-11 ok
        'indicador_ie' => 9,
        'regime' => 'simples',
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    // Não validamos status final (pode haver outras regras UPOS desconhecidas),
    // só garantimos que cpf_cnpj/indicador_ie/regime NÃO falharam validação.
    $errors = session('errors');
    if ($errors) {
        $bag = $errors->getBag('default');
        expect($bag->has('cpf_cnpj'))->toBeFalse('CPF válido não deve falhar mod-11');
        expect($bag->has('indicador_ie'))->toBeFalse('indicador_ie=9 deve passar');
        expect($bag->has('regime'))->toBeFalse('regime=simples deve passar');
    }
});

it('aceita CNPJ válido sem erro no campo cpf_cnpj', function () {
    $payload = [
        'type' => 'supplier',
        'supplier_business_name' => 'Fornecedor Slice7 CNPJ válido',
        'cpf_cnpj' => '11444777000161', // mod-11 ok
    ];

    $response = $this->post('/contacts', $payload, ['X-Inertia' => 'true']);

    $errors = session('errors');
    if ($errors) {
        expect($errors->getBag('default')->has('cpf_cnpj'))->toBeFalse('CNPJ válido não deve falhar mod-11');
    }
});

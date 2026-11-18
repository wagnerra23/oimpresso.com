<?php

declare(strict_types=1);
// Cobre UC-CIDX-21 e UC-CIDX-22 (Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duas rotas antigas da ficha do contato que não conferiam permissão:
 *
 * - GET /contacts/stock-report/{fornecedor} (UC-CIDX-21) também não filtrava empresa: um
 *   usuário de qualquer empresa lia produtos, quantidades e valor em estoque comprados de um
 *   fornecedor de outra empresa.
 * - GET /contacts/payments/{contato} (UC-CIDX-22) mostrava os pagamentos de qualquer contato,
 *   com o número de conta bancária em claro.
 *
 * Cada recusa tem o controle do lado aceito (quem tem permissão, na própria empresa, recebe 200).
 * Tenant de teste (ADR 0358) + empresa-cliente fictícia (biz 99). Transação revertida por caso.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('transaction_payments')) {
        $this->markTestSkipped('Schema UltimatePOS exige MySQL (ADR 0358).');
    }

    $this->business = $this->seededTenant();
    $this->location = DB::table('business_locations')->where('business_id', $this->business->id)->first();
    if (! $this->location) {
        $this->markTestSkipped('Sem business_location no seed.');
    }
});

function craEntrar($test, $user, int $businessId): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => $businessId,
        'user.id' => $user->id,
        'business.id' => $businessId,
    ]);
}

function craContato(int $businessId, int $criadoPor, string $type): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => $type, 'name' => 'CRA-' . uniqid(),
        'mobile' => '00000000000', 'contact_status' => 'active',
        'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function craPagamento(int $businessId, int $locationId, int $contactId, int $userId, string $conta): void
{
    $tx = \App\Transaction::withoutEvents(fn () => \App\Transaction::create([
        'business_id' => $businessId, 'location_id' => $locationId, 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'contact_id' => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(), 'final_total' => 50,
        'total_remaining_amount' => 0, 'created_by' => $userId, 'invoice_no' => 'CRA-' . uniqid(),
    ]));

    DB::table('transaction_payments')->insert([
        'transaction_id' => $tx->id, 'payment_for' => $contactId, 'business_id' => $businessId,
        'amount' => 50, 'method' => 'bank_transfer', 'is_return' => 0,
        'bank_account_number' => $conta, 'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

const CRA_XHR = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

test('UC-CIDX-21 estoque por fornecedor de OUTRA empresa responde 404', function () {
    $user = $this->usuarioComPermissoes(['supplier.view', 'customer.view'], $this->business);
    $outra = $this->seededSupportClientTenant();
    $fornecedorDeFora = craContato((int) $outra->id, (int) $outra->owner_id, 'supplier');
    craEntrar($this, $user, (int) $this->business->id);

    expect($this->withHeaders(CRA_XHR)->get("/contacts/stock-report/{$fornecedorDeFora}")->status())->toBe(404);
});

test('UC-CIDX-21 estoque por fornecedor sem permissão de ver contatos responde 403', function () {
    $user = $this->usuarioComPermissoes(['product.view'], $this->business);
    $fornecedor = craContato((int) $this->business->id, (int) $user->id, 'supplier');
    craEntrar($this, $user, (int) $this->business->id);

    expect($this->withHeaders(CRA_XHR)->get("/contacts/stock-report/{$fornecedor}")->status())->toBe(403);
});

test('UC-CIDX-21 controle: fornecedor da própria empresa, com permissão, responde 200', function () {
    $user = $this->usuarioComPermissoes(['supplier.view'], $this->business);
    $fornecedor = craContato((int) $this->business->id, (int) $user->id, 'supplier');
    craEntrar($this, $user, (int) $this->business->id);

    $r = $this->withHeaders(CRA_XHR)->get("/contacts/stock-report/{$fornecedor}");

    expect($r->status())->toBe(200)
        ->and($r->json())->toHaveKey('data');
});

test('UC-CIDX-22 pagamentos antigos sem permissão de ver contatos respondem 403', function () {
    $user = $this->usuarioComPermissoes(['product.view'], $this->business);
    $cliente = craContato((int) $this->business->id, (int) $user->id, 'customer');
    craPagamento((int) $this->business->id, (int) $this->location->id, $cliente, (int) $user->id, '00012345678');
    craEntrar($this, $user, (int) $this->business->id);

    expect($this->withHeaders(CRA_XHR)->get("/contacts/payments/{$cliente}")->status())->toBe(403);
});

test('UC-CIDX-22 pagamentos antigos mostram a conta mascarada, nunca o número inteiro', function () {
    $user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $cliente = craContato((int) $this->business->id, (int) $user->id, 'customer');
    craPagamento((int) $this->business->id, (int) $this->location->id, $cliente, (int) $user->id, '00012345678');
    craEntrar($this, $user, (int) $this->business->id);

    $r = $this->withHeaders(CRA_XHR)->get("/contacts/payments/{$cliente}");

    // Pré-condição anti-vácuo: o pagamento veio na resposta (senão "não contém" passaria vazio).
    expect($r->status())->toBe(200)
        ->and($r->getContent())->toContain('****5678')
        ->and($r->getContent())->not->toContain('00012345678');
});

test('UC-CIDX-22 pagamentos de contato de OUTRA empresa respondem 404', function () {
    $user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $outra = $this->seededSupportClientTenant();
    $clienteDeFora = craContato((int) $outra->id, (int) $outra->owner_id, 'customer');
    craEntrar($this, $user, (int) $this->business->id);

    expect($this->withHeaders(CRA_XHR)->get("/contacts/payments/{$clienteDeFora}")->status())->toBe(404);
});

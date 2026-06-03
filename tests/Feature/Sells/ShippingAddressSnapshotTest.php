<?php

declare(strict_types=1);

use App\Contact;
use App\ContactAddress;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-081 -- snapshot imutavel do endereco de entrega na venda (ADR 0101 biz=1).
 *
 * Garante:
 *   - transactions.shipping_address (string) e CONGELADA no fechamento
 *   - shipping_address_id (FK) rastreia o endereco, mas editar/deletar o
 *     endereco NAO altera a string snapshot da venda passada (Pegadinha 3)
 *
 * Skip-graceful em SQLite memory.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    foreach (['contacts', 'contact_addresses', 'transactions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ($t) -- rode com mysql ou CI integration.");
        }
    }
    if (! Schema::hasColumn('transactions', 'shipping_address_id')) {
        $this->markTestSkipped('Migration 2026_06_02_110000 ainda nao rodou.');
    }

    $this->business = \App\Business::first();
    $this->user = $this->business ? \App\User::where('business_id', $this->business->id)->first() : null;
    if (! $this->business || ! $this->user) {
        $this->markTestSkipped('Sem business/user em DB.');
    }
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
    $this->actingAs($this->user);

    $this->contact = Contact::create([
        'business_id' => $this->business->id,
        'created_by' => $this->user->id,
        'type' => 'customer',
        'name' => 'Cliente Snapshot',
        'contact_status' => 'active',
    ]);
});

it('congela a string de entrega no snapshot da venda mesmo apos editar o endereco', function () {
    $addr = ContactAddress::create([
        'contact_id' => $this->contact->id,
        'label' => 'Original',
        'address_line_1' => 'Rua Antiga',
        'numero' => '100',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'is_default' => true,
    ]);

    // Simula o fechamento da venda: materializa string + guarda FK rastreio.
    $snapshot = $addr->toFlatString();
    $tx = Transaction::create([
        'business_id' => $this->business->id,
        'type' => 'sell',
        'status' => 'final',
        'contact_id' => $this->contact->id,
        'created_by' => $this->user->id,
        'transaction_date' => now(),
        'shipping_address' => $snapshot,
        'shipping_address_id' => $addr->id,
        'final_total' => 0,
        'total_before_tax' => 0,
        'payment_status' => 'paid',
    ]);

    // Edita o endereco do contato DEPOIS da venda.
    $addr->update([
        'address_line_1' => 'Rua Nova Totalmente Diferente',
        'numero' => '999',
    ]);

    $txFresh = $tx->fresh();

    // String snapshot permanece a ORIGINAL (congelada).
    expect($txFresh->shipping_address)->toContain('Rua Antiga');
    expect($txFresh->shipping_address)->not->toContain('Rua Nova Totalmente Diferente');
    // FK ainda rastreia.
    expect($txFresh->shipping_address_id)->toBe($addr->id);
});

it('preserva a venda quando o endereco e deletado (FK set null, string intacta)', function () {
    $addr = ContactAddress::create([
        'contact_id' => $this->contact->id,
        'address_line_1' => 'Rua Para Deletar',
        'city' => 'Campinas',
        'state' => 'SP',
        'is_default' => true,
    ]);

    $tx = Transaction::create([
        'business_id' => $this->business->id,
        'type' => 'sell',
        'status' => 'final',
        'contact_id' => $this->contact->id,
        'created_by' => $this->user->id,
        'transaction_date' => now(),
        'shipping_address' => $addr->toFlatString(),
        'shipping_address_id' => $addr->id,
        'final_total' => 0,
        'total_before_tax' => 0,
        'payment_status' => 'paid',
    ]);

    // SoftDeletes no model -> forceDelete pra exercitar a FK onDelete('set null').
    $addr->forceDelete();

    $txFresh = $tx->fresh();

    expect($txFresh->shipping_address)->toContain('Rua Para Deletar');
    // FK virou null (set null), mas a venda sobreviveu.
    expect($txFresh->shipping_address_id)->toBeNull();
});
